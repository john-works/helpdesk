<?php

SetupWebPage::AddModule(
	__FILE__, // Path to the current file, all other file names are relative to the directory containing this file
	'registry-service-menu/1.0.1',
	array(
		// Identification
		//
		'label' => 'Registry Service requests under Incident Management',
		'category' => 'feature',

		// Setup
		//
		'dependencies' => array(
			'itop-incident-mgmt-itil/2.0.0',
			'itop-service-mgmt/2.0.0',
			'itop-request-mgmt-itil/2.0.0',
		),
		'mandatory' => false,
		'visible' => true,

		// Components
		//
		'datamodel' => array(
		),
		'webservice' => array(
		),
		'data.struct' => array(
		),
		'data.sample' => array(
		),

		// Documentation
		//
		'doc.manual_setup' => '',
		'doc.more_information' => '',

		// Default settings
		//
		'settings' => array(
		),
	)
);
