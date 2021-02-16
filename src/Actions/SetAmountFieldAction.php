<?php

namespace IQnection\FormBuilderPayments\Actions;

use IQnection\FormBuilder\Model\FieldAction;
use IQnection\FormBuilder\Fields\MoneyField;
use SilverStripe\Forms;
use IQnection\FormBuilderPayments\Extensions\PaymentField;

class SetAmountFieldAction extends FieldAction
{
	private static $table_name = 'FormBuilderSetAmountFieldAction';
	private static $singular_name = 'Set Charge Amount';

	private static $db = [
		'Amount' => 'Currency'
	];

	private static $has_one = [];

	private static $defaults = [];

	private static $allowed_field_types = [
		PaymentField::class
	];

	public function getActionData()
	{
		$actionData = parent::getActionData();
		$actionData['action']['callback'] = 'actionSetPaymentAmount';
		$actionData['action']['selector'] = $this->Parent()->getAmountField_jQuerySelector();
		$actionData['action']['id'] = $this->ID;
		$actionData['action']['amount'] = [
			'adjust' => $this->Amount,
			'_default' => $this->Parent()->Amount,
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

	public function AdjustAmount($amount, $formData, &$adjustments)
	{
		$previousAmount = $amount;
		$adjustment = 0;
		$hidden = $this->Parent()->isHidden($formData);
		if ( ($this->testConditions($formData)) && (!$hidden) )
		{
			if ($this->AdjustmentType == self::ADJUSTMENT_TYPE_FIXED)
			{
				$adjustment = $this->Amount;
			}
			elseif ($this->AdjustmentType == self::ADJUSTMENT_TYPE_USER)
			{
				$linkedFieldName = $this->UserAmountField();
				if ($linkedFieldName->Exists())
				{
					$adjustment = floatval($formData[$linkedFieldName->getFrontendFieldName()]);
				}
			}
		}
		$amount = $previousAmount + $adjustment;
		$adjustments[] = [
			'hidden' => $hidden,
			'actionID' => $this->ID,
			'type' => $this->AdjustmentType,
			'previousAmount' => $previousAmount,
			'adjustment' => $adjustment,
			'newAmount' => $amount,
			'explain' => (string) $this->Explain()
		];
		return $amount;
	}
}







