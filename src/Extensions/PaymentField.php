<?php

namespace IQnection\FormBuilderPayments\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Core\Injector\Injector;
use IQnection\Payment\Payment;
use SilverStripe\ORM\ValidationException;
use SilverStripe\ORM\ValidationResult;
use IQnection\FormBuilderPayments\Model\SubmissionPaymentFieldValue;

class PaymentField extends DataExtension
{
	private static $submission_value_class = SubmissionPaymentFieldValue::class;
	
	private static $db = [
		'Label' => 'Varchar(255)',
		'Amount' => 'Currency',
		'Description' => 'Varchar(255)',
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
		return $data;
	}
	
	public function processFormData(&$data, $form, $request, &$response)
	{
		if ( (!$paymentClass = $this->owner->Config()->get('payment_class')) || (!class_exists($paymentClass)) )
		{
			$paymentClass = Payment::class;
		}
		$paymentRecord = Injector::inst()->create($paymentClass);
		$paymentData = $this->owner->preparePaymentData($data, $form);
		$paymentRecord->Amount = $paymentData['Amount'];
		$paymentRecord = $paymentRecord->Process($paymentData, $paymentRecord);
		
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
}









