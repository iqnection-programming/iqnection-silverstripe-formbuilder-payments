<?php

namespace IQnection\FormBuilderPayments\Model;

use IQnection\FormBuilder\Model\SubmissionFieldValue;
use IQnection\Payment\Payment;
use SilverStripe\Forms;

class SubmissionPaymentFieldValue extends SubmissionFieldValue
{
	private static $table_name = 'FormBuilderSubmissionPaymentFieldValue';
	
	private static $has_one = [
		'Payment' => Payment::class
	];
	
	public function getCMSFields()
	{
		$fields = parent::getCMSFields();

		if ($this->Payment()->Exists())
		{
			$fields->addFieldToTab('Root.PaymentDetails', Forms\LiteralField::create('<div style="width:100%;overflow:scroll;"><pre><xmp>'.print_r(json_encode($this->Payment()->Response, JSON_PRETTY_PRINT),1).'</xmp></pre></div>'));
		}
		return $fields;
	}
	
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
}