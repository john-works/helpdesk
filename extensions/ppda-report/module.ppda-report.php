<?php
SetupWebPage::AddModule(
	__FILE__,
	'ppda-report/1.0.0',
	array(
		'label' => 'PPDA Help Desk Report menu',
		'category' => 'business',
		'dependencies' => array('itop-request-mgmt-itil'),
		'mandatory' => false,
		'visible' => true,

		'datamodel' => array(
			'datamodel.ppda-report.xml',
			'model.ppda-report.php',
		),
		'webservice' => array(),
		'data.struct' => array(),
		'data.sample' => array(),

		'doc.manual_setup' => '',
		'doc.more_information' => '',

		'settings' => array(),
	)
);