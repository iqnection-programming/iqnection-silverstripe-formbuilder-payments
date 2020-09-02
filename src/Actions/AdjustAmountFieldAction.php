<?php

namespace IQnection\FormBuilderPayments\Actions;

use IQnection\FormBuilder\Model\FieldAction;

class AdjustAmountFieldAction extends FieldAction
{
	private static $table_name = 'FormBuilderAdjustAmountFieldAction';
	private static $singular_name = 'Adjust Charge Amount';
	
	private static $db = [
		'Amount' => 'Currency',
	];
	
	private static $allowed_field_types = [
		\IQnection\FormBuilderPayments\Extensions\PaymentField::class
	];
	
	public function getActionData()
	{
		$actionData = parent::getActionData();
		$actionData['action']['callback'] = 'actionAdjustPaymentAmount';
		$actionData['action']['amount'] = $this->Amount;
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
}