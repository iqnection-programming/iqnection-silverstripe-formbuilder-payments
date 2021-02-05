<?php

namespace IQnection\FormBuilderPayments\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\View\Requirements;

class FormBuilderPayments extends DataExtension
{
	public function updateForm($formBuilder)
	{
		Requirements::javascript('iqnection-modules/formbuilder-payments:client/javascript/formbuilder-payments.js');
		Requirements::css('iqnection-modules/formbuilder-payments:client/css/formbuilder-payments.css');
	}
}
