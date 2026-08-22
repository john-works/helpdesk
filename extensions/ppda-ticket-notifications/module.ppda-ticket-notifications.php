<?php

SetupWebPage::AddModule(
	__FILE__,
	'ppda-ticket-notifications/1.0.0',
	array(
		'label' => 'PPDA Ticket Notifications (styled assignment & resolution emails)',
		'category' => 'feature',

		'dependencies' => array(
			'itop-tickets/2.0.0',
			'itop-request-mgmt-itil/2.0.0',
		),
		'mandatory' => false,
		'visible' => true,

		'datamodel' => array(
			'main.ppda-ticket-notifications.php',
		),
		'webservice' => array(),
		'data.struct' => array(),
		'data.sample' => array(),

		'doc.manual_setup' => '',
		'doc.more_information' => '',

		'settings' => array(),
	)
);
