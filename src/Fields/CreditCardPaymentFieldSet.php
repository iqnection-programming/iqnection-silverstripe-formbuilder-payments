<?php

namespace IQnection\FormBuilderPayments\Fields;

use IQnection\FormBuilder\Model\Field;
use SilverStripe\Forms;
use SilverStripe\View\Requirements;

class CreditCardPaymentField extends Field
{
	private static $table_name = 'FormBuilderCreditCardPaymentField';
	private static $singular_name = 'Credit Card Payment Field Set';
	
	private static $extensions = [
		\IQnection\FormBuilderPayments\Extensions\PaymentField::class
	];
	
	private static $db = [
		'AllowedCardTypes' => 'Text',
		'AuthOnly' => 'Boolean'
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
	
	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->replaceField('AllowedCardTypes', Forms\CheckboxsetField::create('AllowedCardTypes','Allowed Card Types')
			->setSource(array_combine($this->Config()->get('allowed_card_types'),$this->Config()->get('allowed_card_types')))
		);
		$fields->replaceField('AuthOnly',Forms\OptionsetField::create('AuthOnly','Transaction Type')
			->setSource([0 => 'Authorize and Capture', 1 => 'Authorize Only'])
		);
		return $fields;
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
		
	public function getBaseField(&$validator = null)
	{
		Requirements::javascript('iqnection-modules/formbuilder-payments:javascript/formbuilderpayments.js');
		
		$fieldGroup = Forms\FieldGroup::create($this->Name.'_group');
		$fieldGroup->setTitle('');
		$fieldGroup->setFieldHolderTemplate('SilverStripe\Forms\FieldGroup_DefaultFieldHolder');
		$fieldGroup->addExtraClass('full-width-field');
		$allowedCardTypes = json_decode($this->AllowedCardTypes);
		$fieldGroup->push( Forms\DropdownField::create($this->getFrontendFieldName().'[CardType]','Credit Card')
			->setSource(array_combine($allowedCardTypes,$allowedCardTypes))
			->setEmptyString('-- Select --'));
		$fieldGroup->push( Forms\FieldGroup::create('Cardholder\'s Name', [
			Forms\TextField::create($this->getFrontendFieldName().'[BillingFirstName]','')->setRightTitle('First Name')->addExtraClass('required'),
			Forms\TextField::create($this->getFrontendFieldName().'[BillingLastName]','')->setRightTitle('Last Name')->addExtraClass('required')
		])->addExtraClass('stacked'));
		
		$fieldGroup->push( Forms\TextField::create($this->getFrontendFieldName().'[BillingAddress1]','Cardholder\'s Street Address')->addExtraClass('required') );
		$fieldGroup->push( Forms\TextField::create($this->getFrontendFieldName().'[BillingZip]','Zip/Postal Code')->addExtraClass('required') );
		$fieldGroup->push( Forms\TextField::create($this->getFrontendFieldName().'[CardNumber]','Card Number')->addExtraClass('required') );
		
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
		
		$fieldGroup->push( Forms\TextField::create($this->getFrontendFieldName().'[CCV]','CCV Code')->addExtraClass('required') );
		
		$fieldGroup->push( Forms\TextField::create($this->getFrontendFieldName().'[Amount]','Total Cost')
			->setReadonly(true)
			->setValue('$'.number_format($this->Amount,2))
			->addExtraClass('readonly'));
		
		$validator->addRequiredField($this->getFrontendFieldName().'[CardType]');
		$validator->addRequiredField($this->getFrontendFieldName().'[BillingFirstName]');
		$validator->addRequiredField($this->getFrontendFieldName().'[BillingLastName]');
		$validator->addRequiredField($this->getFrontendFieldName().'[BillingAddress1]');
		$validator->addRequiredField($this->getFrontendFieldName().'[BillingZip]');
		$validator->addRequiredField($this->getFrontendFieldName().'[CardNumber]');
		$validator->addRequiredField($this->getFrontendFieldName().'[ExpirationDate][Month]');
		$validator->addRequiredField($this->getFrontendFieldName().'[ExpirationDate][Year]');
		$validator->addRequiredField($this->getFrontendFieldName().'[CCV]');
		
		return $fieldGroup;
	}
	
	public function prepareSubmittedValue($value)
	{
		$value = $this->preparePaymentData([$this->getFrontendFieldName() => $value], null);
		$value['CCV'] = str_repeat('*', strlen($value['CCV']));
		$value['CardNumber'] = '****'.substr($value['CardNumber'], -4, 4);
		return implode("\n", [
			'First Name: '.$value['BillingFirstName'], 
			'Last Name: '.$value['BillingLastName'], 
			'Address: '.$value['BillingAddress1'], 
			'Zip: '.$value['BillingZip'], 
			'Card Type: '.$value['CardType'], 
			'Card Last 4: '.$value['CardNumber'], 
			'Expiration: '.$value['ExpirationDate'],
			'CCV: '.$value['CCV'],
			'Type: '.($paymentData['AuthOnly'] ? 'Authorized Only' : 'Authorized & Captured'),
			'Amount: '.$value['Amount'],
		]);
	}
	
	public function preparePaymentData($data, $form)
	{
		$paymentData = $data[$this->getFrontendFieldName()];
		$paymentData['ExpirationDate'] = $paymentData['ExpirationDate']['Year'].'-'.$paymentData['ExpirationDate']['Month'];
		$paymentData['AuthOnly'] = array_key_exists('AuthOnly',$paymentData) ? $paymentData['AuthOnly'] : $this->AuthOnly;
		return $paymentData;
	}
}







