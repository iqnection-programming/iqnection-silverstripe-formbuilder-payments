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

class PaymentField extends DataExtension
{
	const AMOUNT_TYPE_FIXED = 'Fixed Amount';
	const AMOUNT_TYPE_USER = 'User Defined';

	private static $submission_value_class = SubmissionPaymentFieldValue::class;

	private static $db = [
		'Label' => 'Varchar(255)',
		'AmountType' => "Enum('Fixed Amount,User Defined','Fixed Amount')",
		'Amount' => 'Currency',
		'Description' => 'Varchar(255)',
		'MinAmount' => 'Currency',
		'MaxAmount' => 'Currency',
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

	public function onBeforeWrite()
	{
		if ($this->owner->AmountType == self::AMOUNT_TYPE_USER)
		{
			$this->owner->Amount = null;
		}
	}

	public function updateBaseField(&$fields, &$validator)
	{
		if ($this->owner->AmountType == self::AMOUNT_TYPE_FIXED)
		{
			$amountField = Forms\CurrencyField::create($this->owner->getFrontendFieldName().'[Amount]','Amount');
			$amountField->setReadonly(true)
				->setValue('$'.number_format($this->Amount,2))
				->addExtraClass('readonly');
		}
		elseif ($this->owner->AmountType == self::AMOUNT_TYPE_USER)
		{
			$amountField = Forms\NumericField::create($this->owner->getFrontendFieldName().'[Amount]','Amount')
				->setHTML5(true)
				->setAttribute('placeholder','$0.00')
				->setAttribute('pattern','^\\$?[\\d,]*(\.\\d{,2})?$')
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
		$fields->push($amountField);
	}

	public function calculateAmount($data)
	{
		$amount = $this->owner->Amount;
		if ($this->owner->AmountType == self::AMOUNT_TYPE_USER)
		{
			$amount = floatval($data[$this->owner->getFrontendFieldName()]['Amount']);
		}
		foreach($this->owner->FieldActions() as $fieldAction)
		{
			if ($fieldAction->hasMethod('AdjustAmount'))
			{
				$amount = $fieldAction->AdjustAmount($amount, $data);
			}
		}
		$this->owner->extend('updateAmount', $amount);
		return $amount;
	}

	public function processFormData(&$data, $form, $request, &$response)
	{
		if ( (!$paymentClass = $this->owner->Config()->get('payment_class')) || (!class_exists($paymentClass)) )
		{
			$paymentClass = Payment::class;
		}
		$paymentRecord = Injector::inst()->create($paymentClass);
		$paymentData = $this->owner->preparePaymentData($data, $form);
		$paymentRecord->Amount = $this->owner->calculateAmount($data);
		if (floatval($paymentRecord->Amount) > 0.00)
		{
			$paymentRecord = $paymentRecord->Process($paymentData, $paymentRecord);
		}
		else
		{
			$paymentRecord->Message = 'Cannot process charge for zero or less';
		}

		// some gateways might not know if the payment was successful immediately
		// only redirect back if we know the payment has failed
		if (in_array($paymentRecord->Status, [Payment::STATUS_FAILED, Payment::STATUS_DECLINED]))
		{
			$result = ValidationResult::create();
			$result->addError('There was an error processing your payment: '.$paymentRecord->Message);
			throw ValidationException::create($result);
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

	public function getAmountjQuerySelector()
	{
		$selector = '[name="'.$this->owner->getFrontendFieldName().'[Amount]"]';
		$this->owner->extend('updateAmountjQuerySelector', $selector);
		return $selector;
	}
}









