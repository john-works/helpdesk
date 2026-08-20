<?php
require_once '/var/www/html/itop/approot.inc.php';
require_once APPROOT.'/application/startup.inc.php';

echo "=== iTop Email Configuration Debug ===\n";
echo "Email Transport: " . (MetaModel::GetConfig()->Get('email_transport') ?: 'NOT SET') . "\n";
echo "SMTP Host: " . (MetaModel::GetConfig()->Get('email_smtp.host') ?: 'NOT SET') . "\n";
echo "SMTP Port: " . (MetaModel::GetConfig()->Get('email_smtp.port') ?: 'NOT SET') . "\n";
echo "SMTP Username: " . (MetaModel::GetConfig()->Get('email_smtp.username') ?: 'NOT SET') . "\n";
echo "SMTP Encryption: " . (MetaModel::GetConfig()->Get('email_smtp.encryption') ?: 'NOT SET') . "\n";

// Check if there are multiple values
$config = MetaModel::GetConfig();
foreach ($config->GetLoadedFiles() as $file) {
    echo "Config file: $file\n";
}
?>
