<?php
require_once '/var/www/html/itop/approot.inc.php';
require_once APPROOT.'/application/startup.inc.php';

// List all configuration settings that might be related to email
$config = MetaModel::GetConfig();
$allSettings = $config->GetAll();

echo "=== All Configuration Keys (email/smtp related) ===\n";
foreach ($allSettings as $key => $value) {
    if (strpos($key, 'email') !== false || strpos($key, 'smtp') !== false || 
        strpos($key, 'mail') !== false || strpos($key, 'transport') !== false) {
        echo "$key\n";
    }
}

echo "\n=== Total configuration keys: " . count($allSettings) . " ===\n";
?>
