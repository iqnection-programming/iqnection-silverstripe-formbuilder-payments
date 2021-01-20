<?php

namespace IQnection\FormBuilderPayments\Fields;

use IQnection\FormBuilder\Model\Field;
use SilverStripe\Forms;
use SilverStripe\View\Requirements;
use IQnection\FormBuilderPayments\Extensions\PaymentField;
use IQnection\Payment\Payment;
use IQnection\FormBuilder\Fields\EmailField;
use IQnection\FormBuilder\Fields\TextField;

class CreditCardPaymentField extends Field
{
	private static $table_name = 'FormBuilderCreditCardPaymentField';
	private static $singular_name = 'Credit Card Payment Field Set';

	private static $extensions = [
		PaymentField::class
	];

	private static $db = [
		'AllowedCardTypes' => 'Text',
		'AuthOnly' => 'Boolean'
	];

	private static $has_one = [
		'EmailField' => EmailField::class,
		'PhoneField' => TextField::class
	];

	private static $defaults = [
		'AuthOnly' => false
	];

	private static $allowed_card_types = [
		'Visa',
		'MasterCard',
		'Discover',
		'American Express'
	];

	private static $form_builder_has_one_duplicates = [
		'EmailField',
		'PhoneField'
	];

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->removeByName([
			'Description',
			'EmailFieldID',
			'PhoneFieldID'
		]);
		$fields->replaceField('AllowedCardTypes', Forms\CheckboxsetField::create('AllowedCardTypes','Allowed Card Types')
			->setSource(array_combine($this->Config()->get('allowed_card_types'),$this->Config()->get('allowed_card_types')))
		);
		$fields->replaceField('AuthOnly',Forms\OptionsetField::create('AuthOnly','Transaction Type')
			->setSource([0 => 'Authorize and Capture', 1 => 'Authorize Only'])
		);
		$fields->insertAfter('Label', Forms\FieldGroup::create('Additional Billing Fields', [
			Forms\DropdownField::create('EmailFieldID','Billing Email Field')
				->setSource($this->getAvailableEmailFields()->map('ID','Name'))
				->setEmptyString('None'),
			Forms\DropdownField::create('PhoneFieldID','Billing Phone Number Field')
				->setSource($this->getAvailablePhoneFields()->map('ID','Name'))
				->setEmptyString('None')
		]));

		return $fields;
	}

	public function getAvailableEmailFields()
	{
		$emailFieldIDs = [0];
		foreach($this->FormBuilder()->DataFields() as $dataField)
		{
			if ($dataField instanceof EmailField)
			{
				$emailFieldIDs[] = $dataField->ID;
			}
		}
		return EmailField::get()->Filter('ID',$emailFieldIDs);
	}

	public function getAvailablePhoneFields()
	{
		$fieldIDs = [0];
		foreach($this->FormBuilder()->DataFields() as $dataField)
		{
			if ( ($dataField instanceof TextField) && ($dataField->Format == 'validatePhone') )
			{
				$fieldIDs[] = $dataField->ID;
			}
		}
		$possibleFields = TextField::get()->Filter('ID',$fieldIDs);
		if (!$possibleFields->Count())
		{
			$possibleFields = $this->FormBuilder()->DataFields()->Filter('Name:PartialMatch', 'Phone');
		}
		return $possibleFields;
	}

	public function BillingEmailField()
	{
		if ($this->EmailField()->Exists())
		{
			return $this->EmailField();
		}
		return $this->getAvailableEmailFields()->First();
	}

	public function BillingPhoneField()
	{
		if ($this->PhoneField()->Exists())
		{
			return $this->PhoneField();
		}
		return $this->getAvailablePhoneFields()->First();
	}

	public function validate()
	{
		$result = parent::validate();
		if (!$this->AllowedCardTypes)
		{
			$result->addError('You must select at least one card type');
		}
		return $result;
	}

	public function getPaymentFields(&$validator, $defaults = null)
	{
		$fieldGroup = Forms\FieldGroup::create($this->Name.'_group')
			->setTitle('')
			->setFieldHolderTemplate('SilverStripe\Forms\FieldGroup_DefaultFieldHolder')
			->addExtraClass('full-width-field');

		$fieldGroup->setTitle('');

		$fieldGroup->setFieldHolderTemplate('SilverStripe\Forms\FieldGroup_DefaultFieldHolder');
		$fieldGroup->addExtraClass('full-width-field');

		$allowedCardTypes = json_decode($this->AllowedCardTypes);
		$fieldGroup->push( Forms\DropdownField::create($this->getFrontendFieldName().'[CardType]','Credit Card')
			->setSource(array_combine($allowedCardTypes,$allowedCardTypes))
			->setEmptyString('-- Select --'));
		$fieldGroup->push( Forms\FieldGroup::create('Name on Card', [
			Forms\TextField::create($this->getFrontendFieldName().'[BillingFirstName]','')->setRightTitle('First Name')->addExtraClass('required'),
			Forms\TextField::create($this->getFrontendFieldName().'[BillingLastName]','')->setRightTitle('Last Name')->addExtraClass('required')
		])->addExtraClass('stacked'));

		$fieldGroup->push( Forms\FieldGroup::create('Billing Address', [
			Forms\TextField::create($this->getFrontendFieldName().'[BillingAddress1]','')->setRightTitle('Street Address')->addExtraClass('required'),
			Forms\TextField::create($this->getFrontendFieldName().'[BillingZip]','')->setRightTitle('Zip/Postal Code')->addExtraClass('required')
		])->addExtraClass('stacked'));

		$fieldGroup->push( Forms\FieldGroup::create('Credit Card', [
			Forms\TextField::create($this->getFrontendFieldName().'[CardNumber]','')
				->setRightTitle('Card Number')
				->addExtraClass('required')
				->setAttribute('placeholder','0000-0000-0000-0000')
				->setAttribute('minlength',15)
				->setAttribute('maxlength',19)
				->setValue('')
				->setAttribute('pattern','^(\\d{4}[\\-\\s]?){4}|\\d{4}[\\-\\s]?\\d{6}[\\-\\s]?\\d{5}$'),
			Forms\TextField::create($this->getFrontendFieldName().'[CCV]','')
				->setRightTitle('CCV Code')
				->addExtraClass('required')
				->setAttribute('placeholder','000')
				->setValue('')
				->setAttribute('minlength',3)
				->setAttribute('maxlength',4)
		])->addExtraClass('stacked'));

		unset($defaults[$this->getFrontendFieldName().'[CardNumber]']);
		unset($defaults[$this->getFrontendFieldName().'[CCV]']);
		$months = [];
		for($m=1; $m<=12; $m++)
		{
			$monthNum = str_pad($m, 2, "0", STR_PAD_LEFT);
			$months[$monthNum] = $monthNum.' - '.date('F', strtotime('2000-'.$monthNum.'-01'));
		}
		$years = [];
		$currentYear = date('Y');
		for($y = $currentYear; $y <= ($currentYear + 10); $y++)
		{
			$years[$y] = $y;
		}
		$fieldGroup->push( Forms\FieldGroup::create('Expiration Date', [
			Forms\DropdownField::create($this->getFrontendFieldName().'[ExpirationDate][Month]','')
				->addExtraClass('required')
				->setSource($months)
				->setRightTitle('Month')
				->setEmptyString('-- Select --'),
			Forms\DropdownField::create($this->getFrontendFieldName().'[ExpirationDate][Year]','')
				->addExtraClass('required')
				->setRightTitle('Year')
				->setEmptyString('-- Select --')
				->setSource($years)
		])->addExtraClass('stacked') );

		return $fieldGroup;
	}

	public function prepareSubmittedValue($value, $formData = [])
	{
		$value = $this->preparePaymentData($formData, null);
		$value['CCV'] = str_repeat('*', strlen($value['CCV']));
		$value['CardNumber'] = '****'.substr($value['CardNumber'], -4, 4);
		$amount = $value['Amount'];
		$value['TransactionId'] = '(none)';
		$value['AuthorizationCode'] = '(none)';
		if ( ($paymentID = $value['PaymentID']) && ($Payment = Payment::get()->byId($paymentID)) )
		{
			$value['TransactionId'] = (string) $Payment->TransactionId;
			$value['AuthorizationCode'] = (string) $Payment->AuthorizationCode;
		}

		return implode("\n", [
			'First Name: '.$value['BillingFirstName'],
			'Last Name: '.$value['BillingLastName'],
			'Address: '.$value['BillingAddress1'],
			'Zip: '.$value['BillingZip'],
			'Phone: '.$value['BillingPhone'],
			'Email: '.$value['BillingEmail'],
			'Card Type: '.$value['CardType'],
			'Card Last 4: '.$value['CardNumber'],
			'Expiration: '.$value['ExpirationDate'],
			'CCV: '.$value['CCV'],
			'Type: '.($value['AuthOnly'] ? 'Authorized Only' : 'Authorized & Captured'),
			'Amount: $'.number_format($amount, 2),
			'Authorization Code: '.$value['AuthorizationCode'],
			'Transaction ID: '.$value['TransactionId']
		]);
	}

	public function preparePaymentData($data, $form)
	{
		$paymentData = $data[$this->getFrontendFieldName()];
		$paymentData['ExpirationDate'] = $paymentData['ExpirationDate']['Year'].'-'.$paymentData['ExpirationDate']['Month'];
		$paymentData['AuthOnly'] = array_key_exists('AuthOnly',$paymentData) ? $paymentData['AuthOnly'] : $this->AuthOnly;
		if ( ($billingEmailField = $this->BillingEmailField()) && ($billingEmailField->Exists()) )
		{
			$paymentData['BillingEmail'] = $data[$billingEmailField->getFrontendFieldName()];
		}
		if ( ($billingPhoneField = $this->BillingPhoneField()) && ($billingPhoneField->Exists()) )
		{
			$paymentData['BillingPhone'] = $data[$billingPhoneField->getFrontendFieldName()];
		}
		$this->extend('updatePaymentData',$paymentData,$form);
		return $paymentData;
	}

	public function updateFrontEndValidator(&$validator, $formData = [])
	{
		$isRequired = !$this->AllowZeroPayment;
		if (count($formData))
		{
			$hasSubmittedAmount = ceil(preg_replace('/[^0-9\.]/', '', $formData[$this->getFrontendFieldName().'[Amount]']));
			if (!$this->AllowZeroPayment)
			{
				$isRequired = true;
			}
		}
		if ($isRequired)
		{
			$validator->addRequiredField($this->getFrontendFieldName().'[CardType]');
			$validator->addRequiredField($this->getFrontendFieldName().'[BillingFirstName]');
			$validator->addRequiredField($this->getFrontendFieldName().'[BillingLastName]');
			$validator->addRequiredField($this->getFrontendFieldName().'[BillingAddress1]');
			$validator->addRequiredField($this->getFrontendFieldName().'[BillingZip]');
			$validator->addRequiredField($this->getFrontendFieldName().'[CardNumber]');
			$validator->addRequiredField($this->getFrontendFieldName().'[ExpirationDate][Month]');
			$validator->addRequiredField($this->getFrontendFieldName().'[ExpirationDate][Year]');
			$validator->addRequiredField($this->getFrontendFieldName().'[CCV]');
		}
	}

	public function getOnLoadFieldActions($onLoadCondition = null)
	{
		$actions = parent::getOnLoadFieldActions($onLoadCondition);

		if ($this->AllowZeroPayment)
		{
			// hide the payment fields if no payment is required
			$conditions[] = [
				'selector' => $this->getAmountField_jQuerySelector(),
				'state' => 'Is Zero',
				'stateCallback' => 'stateMatchAny',
				'config' => [
					'matchValue' => [
						'0',
						'0.00'
					]
				],
				'selections' => [],
			];
			$actions[] = [
				'id' => $this->ID.'.2',
				'name' => 'Field: '.$this->Name,
				'action' => [
					'type' => 'Hide CC Fields When No Amount',
					'selector' => $this->getPaymentFields_jQuerySelector(),
					'fieldType' => $this->singular_name(),
					'callback' => 'actionHideField',
				],
				'conditions' => $conditions,
				'conditionsHash' => 'paymentAmountZero'
			];
		}
		return $actions;
	}
}







