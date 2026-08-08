<?php
require_once '/var/www/html/itop/approot.inc.php';
require_once APPROOT.'/application/startup.inc.php';

echo "=== iTop Email Configuration Debug ===\n";
echo "Email Transport: " . (MetaModel::GetConfig()->Get('email_transport') ?: 'NOT SET') . "\n";
echo "SMTP Host: " . (MetaModel::GetConfig()->Get('email_smtp.host') ?: 'NOT SET') . "\n";
echo "SMTP Port: " . (MetaModel::GetConfig()->Get('email_smtp.port') ?: 'NOT SET') . "\n";
echo "SMTP Username: " . (MetaModel::GetConfig()->Get('email_smtp.username') ?: 'NOT SET') . "\n";
echo "SMTP Encryption: " . (MetaModel::GetConfig()->Get('email_smtp.encryption') ?: 'NOT SET') . "\n";
echo "SMTP Auth Mode: " . (MetaModel::GetConfig()->Get('email_smtp.auth_mode') ?: 'NOT SET') . "\n";

// Test if we can create an email
try {
    $oEmail = new Email();
    echo "Email object created successfully\n";
} catch (Exception $e) {
    echo "Error creating email: " . $e->getMessage() . "\n";
}
?>
