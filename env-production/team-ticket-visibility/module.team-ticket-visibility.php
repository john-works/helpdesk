<?php

SetupWebPage::AddModule(
	__FILE__, // Path to the current file, all other file names are relative to the directory containing this file
	'team-ticket-visibility/1.0.0',
	array(
		// Identification
		//
		'label' => 'Team Ticket Visibility (Registry / ICT separation)',
		'category' => 'feature',

		// Setup
		//
		'dependencies' => array(
			'itop-tickets/2.0.0',
			'itop-request-mgmt-itil/2.0.0',
			'itop-incident-mgmt-itil/2.0.0',
			'itop-service-mgmt/2.0.0',
		),
		'mandatory' => false,
		'visible' => true,

		// Components
		//
		'datamodel' => array(
			'main.team-ticket-visibility.php',
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
