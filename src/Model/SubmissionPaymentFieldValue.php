<?php

namespace IQnection\FormBuilderPayments\Model;

use IQnection\FormBuilder\Model\SubmissionFieldValue;
use IQnection\Payment\Payment;
use SilverStripe\Forms;

class SubmissionPaymentFieldValue extends SubmissionFieldValue
{
	private static $table_name = 'FormBuilderSubmissionPaymentFieldValue';

	private static $db = [
		'AdjustmentsLog' => 'Text',
	];

	private static $has_one = [
		'Payment' => Payment::class
	];

	public function onAfterWrite()
	{
		parent::onAfterWrite();
		if ($this->Payment()->Exists())
		{
			$this->Payment()->PaidObjectID = $this->ID;
			$this->Payment()->PaidObjectType = $this->getClassName();
			$this->Payment()->write();
		}
	}

	public function DebugInfo()
	{
		return unserialize($this->AdjustmentsLog);
	}
}