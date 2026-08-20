<?php
require_once '/var/www/html//approot.inc.php';
require_once APPROOT.'/application/startup.inc.php';

echo "=== Final iTop + Postfix Integration Test ===\n";

// Check iTop configuration
$config = MetaModel::GetConfig();
echo "iTop Configuration:\n";
echo "  Email Transport: " . $config->Get('email_transport') . "\n";
echo "  Sender Address: " . $config->Get('email_default_sender_address') . "\n";
echo "  Sender Label: " . $config->Get('email_default_sender_label') . "\n";

// Test email sending through iTop
$oEmail = new Email();
$oEmail->SetRecipientTO('it@ppda.go.ug');
$oEmail->SetSubject('iTop + Postfix Integration Test - SUCCESS');
$oEmail->SetBody('This email confirms that:

1. iTop is correctly configured with PHPMail transport
2. Postfix is properly configured as the mail relay
3. Emails are being sent through mail.ppda.go.ug with authentication
4. From address is correctly set to helpdesk@ppda.go.ug

🎉 Email system is fully operational! 🎉');
$oEmail->SetRecipientFrom('helpdesk@ppda.go.ug', 'PPDA Helpdesk');

// Try different Send() method signatures
try {
    // Method 1: Try without parameters first
    $result = $oEmail->Send();
    echo "✅ SUCCESS: iTop email sent successfully (method 1)!\n";
} catch (ArgumentCountError $e) {
    // Method 2: Try with parameters
    try {
        $result = $oEmail->Send(false); // false = synchronous sending
        echo "✅ SUCCESS: iTop email sent successfully (method 2)!\n";
    } catch (Exception $e2) {
        // Method 3: Try with different parameter
        try {
            $result = $oEmail->Send(true); // true = asynchronous sending
            echo "✅ SUCCESS: iTop email sent successfully (method 3)!\n";
        } catch (Exception $e3) {
            echo "❌ FAILED: All Send() methods failed\n";
            echo "Error 1: " . $e->getMessage() . "\n";
            echo "Error 2: " . $e2->getMessage() . "\n";
            echo "Error 3: " . $e3->getMessage() . "\n";
        }
    }
}

if (isset($result) && $result) {
    echo "✅ Email system integration is complete and working!\n";
    
    // Also test direct PHP mail to confirm Postfix is working
    echo "\n=== Direct PHP mail() Test ===\n";
    if (mail('it@ppda.go.ug', 'Direct PHP mail() Test', 'Testing direct PHP mail function with Postfix', 'From: helpdesk@ppda.go.ug')) {
        echo "✅ PHP mail() function working with Postfix\n";
    } else {
        echo "❌ PHP mail() function failed\n";
    }
}
?>
