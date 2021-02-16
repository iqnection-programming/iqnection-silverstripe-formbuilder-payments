<?php

namespace IQnection\FormBuilderPayments\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Core\Injector\Injector;
use IQnection\Payment\Payment;
use SilverStripe\ORM\ValidationException;
use SilverStripe\ORM\ValidationResult;
use IQnection\FormBuilderPayments\Model\SubmissionPaymentFieldValue;
use IQnection\FormBuilder\Fields\MoneyField;
use SilverStripe\Forms;
use SilverStripe\View\Requirements;

class PaymentField extends DataExtension
{
	const AMOUNT_TYPE_FIXED = 'Fixed Amount';
	const AMOUNT_TYPE_USER = 'User Defined';

	private static $submission_value_class = SubmissionPaymentFieldValue::class;

	private static $db = [
		'Label' => 'Varchar(255)',
		'AmountType' => "Enum('Fixed Amount,User Defined','Fixed Amount')",
		'Amount' => 'Currency',
		'AllowZeroPayment' => 'Boolean',
		'Description' => 'Varchar(255)',
		'MinAmount' => 'Currency',
		'MaxAmount' => 'Currency',
		'AdjustmentsLog' => 'Text',
	];

	private static $defaults = [
		'AmountType' => 'Fixed Amount'
	];

	/**
	 * Set the class that will be called to process the payment
	 * the method "Process" must be declared in this class
	 */
	private static $payment_processor;

	/**
	 * set the data object class to save the payment transaction into
	 */
	private static $payment_class;

	/**
	 * makes any alterations to the data before passing it along to the payment processor class
	 *
	 * @param $data array submitted data
	 * @param $form SilverStripe\Forms\Form the form object
	 *
	 * @returns array
	 */
	public function preparePaymentData($data, $form)
	{
		$paymentData = $data[$this->owner->getFrontendFieldName()];
		return $paymentData;
	}

	public function updateCMSFields($fields)
	{
		$amountField = $fields->dataFieldByName('Amount');
		$fields->removeByName([
			'Amount',
			'AmountType',
			'MinAmount',
			'MaxAmount',
		]);
		$fields->addFieldToTab('Root.Main', Forms\CheckboxField::create('AllowZeroPayment','Allow Submission if Amount is Zero?'));
		$fields->addFieldToTab('Root.Main', Forms\SelectionGroup::create('AmountType',[
			self::AMOUNT_TYPE_FIXED => Forms\SelectionGroup_Item::create(
				self::AMOUNT_TYPE_FIXED, [
					$amountField
				]
			),
			self::AMOUNT_TYPE_USER => Forms\SelectionGroup_Item::create(
				self::AMOUNT_TYPE_USER, [
					Forms\LiteralField::create('_amountNote','A field will be provided for the user to enter the amount'),
					Forms\FieldGroup::create('Restrictions', [
						Forms\CurrencyField::create('MinAmount','Minimum'),
						Forms\CurrencyField::create('MaxAmount','Maximum')
					])->setDescription('Set to zero for no restriction')
				]
			)
		]));
	}

	public function updateConditionOptions(&$field, $fieldAction = null, $fieldName = null)
	{
		$field->push(Forms\SelectionGroup_Item::create('Is Empty', null, 'Is Zero'));
		$field->push(Forms\SelectionGroup_Item::create('Has Value', null, 'Is Greater Then Zero'));
	}

	public function onBeforeWrite()
	{
		if ($this->owner->AmountType == self::AMOUNT_TYPE_USER)
		{
			$this->owner->Amount = null;
		}
	}

	public function getPaymentFields($defaults = null) { }

	public function getPaymentFields_jQuerySelector()
	{
		if (!$this->owner->_paymentFieldsjQuerySelector)
		{
			$paymentFields = $this->owner->getPaymentFields();
			$this->owner->_paymentFieldsjQuerySelector = '#'.$this->owner->FormBuilder()->getFormHTMLID().'_'.$paymentFields->ID();
		}
		return $this->owner->_paymentFieldsjQuerySelector;
	}

	public function getAmountField_jQuerySelector()
	{
		return '[name="'.$this->owner->getFrontendFieldName().'[Amount]"]';
	}

	public function updateBaseField(&$fields, &$validator = null, $defaults = null)
	{
		$wrapperFieldGroup = $fields;
		if (!($wrapperFieldGroup instanceof Forms\CompositeField))
		{
			$wrapperFieldGroup = Forms\FieldGroup::create($this->Name.'_groupWrapper')
				->setTitle('')
				->setFieldHolderTemplate('SilverStripe\Forms\FieldGroup_DefaultFieldHolder')
				->addExtraClass('full-width-field');

			if ($fields instanceof Forms\FormField)
			{
				$wrapperFieldGroup->push($fields);
			}
			$fields = $wrapperFieldGroup;
		}
		$fields = Forms\FieldGroup::create($this->Name.'_groupWrapper')
			->setTitle('')
			->setFieldHolderTemplate('SilverStripe\Forms\FieldGroup_DefaultFieldHolder')
			->addExtraClass('full-width-field');

		if ($paymentField_group = $this->owner->getPaymentFields($defaults = null))
		{
			$paymentField_group->setAttribute('data-cc-fields', $this->ID);
			$fields->push($paymentField_group);
		}

		if ($amountField = $this->owner->getAmountBaseField())
		{
			$fields->push($amountField);
		}
	}

	public function getAmountBaseField()
	{
		if ($this->owner->AmountType == self::AMOUNT_TYPE_FIXED)
		{
			$amountField = Forms\TextField::create($this->owner->getFrontendFieldName().'[Amount]','Amount');
			$amountField->setReadonly(true)
				->setValue(number_format($this->owner->Amount,2))
				->addExtraClass('payment-amount-field payment-amount-field--fixed')
				->addExtraClass('readonly');
		}
		elseif ($this->owner->AmountType == self::AMOUNT_TYPE_USER)
		{
			$amountField = Forms\TextField::create($this->owner->getFrontendFieldName().'[Amount]','Amount')
				->setAttribute('placeholder','0.00')
				->setAttribute('pattern','^[\\d,]*(\.\\d{,2})?$')
				->addExtraClass('payment-amount-field payment-amount-field--open')
				->setValue('');
			if (ceil($this->owner->MinAmount))
			{
				$amountField->setAttribute('min', $this->owner->MinAmount);
			}
			if (ceil($this->owner->MaxAmount))
			{
				$amountField->setAttribute('max', $this->owner->MaxAmount);
			}
			$this->owner->Amount = null;
		}
		$this->owner->extend('updateAmountBaseField', $amountField);
		return $amountField;
	}

	public function calculateAmount($data)
	{
		$amount = $this->owner->Amount;
		if ($this->owner->AmountType == self::AMOUNT_TYPE_USER)
		{
			$amount = floatval($data[$this->owner->getFrontendFieldName()]['Amount']);
		}
		$adjustments = [
			[
				'baseAmount' => $amount
			]
		];
		foreach($this->owner->FieldActions() as $fieldAction)
		{
			if ($fieldAction->hasMethod('AdjustAmount'))
			{
				$amount = $fieldAction->AdjustAmount($amount, $data, $adjustments);
			}
		}
		$this->owner->extend('updateAmount', $amount, $adjustments);
		$this->owner->AdjustmentsLog = serialize($adjustments);
		return $amount;
	}

	public function processFormData(&$data, &$form, &$controller)
	{
		if ( (!$paymentClass = $this->owner->Config()->get('payment_class')) || (!class_exists($paymentClass)) )
		{
			$paymentClass = Payment::class;
		}
		$paymentRecord = Injector::inst()->create($paymentClass);
		$paymentData = $this->owner->preparePaymentData($data, $form);
		$paymentData['Amount'] = $this->owner->calculateAmount($data);
		$paymentRecord->Amount = $paymentData['Amount'];
		if ( (ceil($paymentRecord->Amount) == 0) && ($this->owner->AllowZeroPayment) )
		{
			$paymentRecord->Status == Payment::STATUS_SUCCESS;
		}
		else
		{
			$paymentRecord = $paymentRecord->Process($paymentData, $paymentRecord);

			// some gateways might not know if the payment was successful immediately
			// only redirect back if we know the payment has failed
			if (in_array($paymentRecord->Status, [Payment::STATUS_FAILED, Payment::STATUS_DECLINED]))
			{
				$result = ValidationResult::create();
				$result->addError('There was an error processing your payment: '.$paymentRecord->Message);
				throw ValidationException::create($result);
			}
		}
		$paymentRecord->write();
		$data[$this->owner->getFrontendFieldName()]['PaymentID'] = $paymentRecord->ID;
	}

	public function updateSubmissionFieldValue($submissionFieldValue, $rawValue)
	{
		// find the payment record and link it to the submission value
		if ( ($paymentID = $rawValue['PaymentID']) && ($Payment = Payment::get()->byId($paymentID)) )
		{
			$submissionFieldValue->PaymentID = $paymentID;
		}
	}

	public function updateFieldJsValidation(&$rules)
	{
		if ($this->owner->AmountType == self::AMOUNT_TYPE_USER)
		{
			$rules['number'] = true;
			if (ceil($this->owner->MinAmount) > 0)
			{
				$rules['min'] = $this->owner->MinAmount;
			}
			if (ceil($this->owner->MaxAmount) > 0)
			{
				$rules['max'] = $this->owner->MaxAmount;
			}
		}
		return $rules;
	}
}









