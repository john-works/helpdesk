<?php
require_once '/var/www/html/itop/approot.inc.php';
require_once APPROOT.'/application/startup.inc.php';

echo "=== iTop Email System Test ===\n";

// Check configuration
$config = MetaModel::GetConfig();
echo "Configuration:\n";
echo "  Email Transport: " . $config->Get('email_transport') . "\n";
echo "  Sender: " . $config->Get('email_default_sender_address') . "\n";

// Create and send email using the correct method
$oEmail = new Email();
$oEmail->SetRecipientTO('it@ppda.go.ug');
$oEmail->SetSubject('iTop Email Test - System Verified');
$oEmail->SetBody('This email confirms that the iTop email system is properly configured and working with Postfix.');
$oEmail->SetRecipientFrom('helpdesk@ppda.go.ug', 'PPDA Helpdesk');

// Use the correct Send method - pass an empty array by reference
$aIssues = array();
$result = $oEmail->Send($aIssues);

if ($result) {
    echo "✅ SUCCESS: Email sent successfully!\n";
    echo "✅ iTop email system is fully operational!\n";
    echo "✅ Postfix relay is working correctly!\n";
} else {
    echo "❌ FAILED: Email not sent\n";
    if (!empty($aIssues)) {
        echo "Issues: " . print_r($aIssues, true) . "\n";
    }
}

// Test direct PHP mail as well
echo "\n=== Direct PHP mail() Test ===\n";
if (mail('it@ppda.go.ug', 'PHP mail() Test', 'Direct PHP mail test with Postfix', 'From: helpdesk@ppda.go.ug')) {
    echo "✅ PHP mail() function working\n";
} else {
    echo "❌ PHP mail() function failed\n";
}
?>
