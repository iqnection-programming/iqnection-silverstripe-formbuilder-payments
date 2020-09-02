<?php

namespace IQnection\FormBuilderPayments\Extensions;

use SilverStripe\ORM\DataExtension;
use IQnection\Payment\Payment;
use SilverStripe\Forms;
use IQnection\FormBuilderPayments\Model\SubmissionPaymentFieldValue;

class SubmissionExtension extends DataExtension
{
	public function Payments()
	{
		// do we have any payment fields values?
		$paymentIds = [0];
		foreach($this->owner->SubmissionFieldValues() as $SubmissionFieldValue)
		{
			if ($SubmissionFieldValue instanceof SubmissionPaymentFieldValue)
			{
				$paymentIds[] = $SubmissionFieldValue->PaymentID;
			}
		}
		return Payment::get()->byIds($paymentIds);
	}
	
	public function updateCMSFields($fields)
	{
		if ( ($Payments = $this->owner->Payments()) && ($Payments->Count()) )
		{
			$fields->addFieldToTab('Root.Payments', Forms\GridField\GridField::create(
				'Payments',
				'Payments',
				$Payments,
				Forms\GridField\GridFieldConfig_RecordViewer::create()
			));
		}
	}
}