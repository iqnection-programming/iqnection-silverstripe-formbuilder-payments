<?php

namespace IQnection\FormBuilderPayments\Actions;

use IQnection\FormBuilder\Model\FieldAction;
use IQnection\FormBuilder\Fields\MoneyField;
use SilverStripe\Forms;
use IQnection\FormBuilderPayments\Extensions\PaymentField;
use IQnection\FormBuilder\FormBuilder;

class AdjustAmountFieldAction extends FieldAction
{
	const ADJUSTMENT_TYPE_FIXED = 'Fixed Amount';
	const ADJUSTMENT_TYPE_USER = 'User Defined';

	private static $table_name = 'FormBuilderAdjustAmountFieldAction';
	private static $singular_name = 'Adjust Charge Amount';

	private static $db = [
		'Amount' => 'Currency',
		'AdjustmentType' => "Enum('Fixed Amount,User Defined','Fixed Amount')"
	];

	private static $has_one = [
		'UserAmountField' => MoneyField::class
	];

	private static $defaults = [
		'AdjustmentType' => 'Fixed Amount'
	];

	private static $allowed_field_types = [
		PaymentField::class
	];

	private static $form_builder_has_one_duplicates = [
		'UserAmountField'
	];

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$amountField = $fields->dataFieldByName('Amount');
		$fields->removeByName([
			'Amount',
			'AdjustmentType',
			'UserAmountFieldID'
		]);
		$fields->addFieldToTab('Root.Main', Forms\SelectionGroup::create('AdjustmentType',[
			self::ADJUSTMENT_TYPE_FIXED => Forms\SelectionGroup_Item::create(
				self::ADJUSTMENT_TYPE_FIXED, [
					$amountField
				]
			),
			self::ADJUSTMENT_TYPE_USER => Forms\SelectionGroup_Item::create(
				self::ADJUSTMENT_TYPE_USER, [
					Forms\DropdownField::create('UserAmountFieldID','Select User Input Field')
						->setSource($this->getAvailableUserAmountFields()->map('ID','Name'))
						->setEmptyString('-- Select --')
				]
			)
		]));
		return $fields;
	}

	public function validate()
	{
		$result = parent::validate();

		if ( ($this->AdjustmentType == self::ADJUSTMENT_TYPE_USER) && (!$this->UserAmountField()->Exists()) )
		{
			$result->addError('You must select a money field');
		}
		return $result;
	}

	public function getAvailableUserAmountFields()
	{
		$fieldIDs = [0];
		foreach($this->FormBuilder()->DataFields() as $dataField)
		{
			if ($dataField instanceof MoneyField)
			{
				$fieldIDs[] = $dataField->ID;
			}
		}
		return MoneyField::get()->Filter('ID',$fieldIDs);
	}

	public function getActionData()
	{
		$actionData = parent::getActionData();
		$actionData['action']['callback'] = 'actionAdjustPaymentAmount';
		$actionData['action']['selector'] = $this->Parent()->getAmountField_jQuerySelector();
		$actionData['action']['id'] = $this->ID;
		$actionData['action']['amount'] = [
			'_default' => $this->Parent()->Amount,
			'adjust' => $this->Amount,
			'adjust_type' => $this->AdjustmentType,
			'adjust_field' => $this->UserAmountField()->getjQuerySelector()
		];
		return $actionData;
	}

	public function singular_name()
	{
		$singular_name = parent::singular_name();
		if ($this->Exists())
		{
			$singular_name .= ' '.$this->dbObject('Amount')->Nice();
		}
		return $singular_name;
	}

	public function AdjustAmount($amount, $formData)
	{
		if ($this->testConditions($formData))
		{
			if ($this->AdjustmentType == self::ADJUSTMENT_TYPE_FIXED)
			{
				$amount += $this->Amount;
			}
			elseif ($this->AdjustmentType == self::ADJUSTMENT_TYPE_USER)
			{
				$linkedFieldName = $this->UserAmountField();
				if ($linkedFieldName->Exists())
				{
					$amount += floatval($formData[$linkedFieldName->getFrontendFieldName()]);
				}
			}
		}
		return $amount;
	}
}







