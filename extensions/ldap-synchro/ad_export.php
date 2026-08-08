<?php
/**
 * LDAP/AD User Export to CSV for iTop Synchro
 */

$ldap_host = '192.168.33.8';
$ldap_port = 389;
$ldap_bind_dn = 'CN=itop_user,OU=Service Accounts,DC=ppda,DC=go,dc=ug';
$ldap_bind_pwd = 'ppda2016*';
$ldap_base_dn = 'dc=ppda,dc=go,dc=ug';
$ldap_filter = '(&(objectClass=user)(objectCategory=person)(!(userAccountControl:1.2.840.113556.1.4.803:=2)))';
$ldap_attrs = array('sAMAccountName', 'sn', 'givenName', 'mail', 'employeeNumber', 'telephoneNumber', 'mobile', 'title', 'distinguishedName', 'userPrincipalName');

$default_org_id = 8;
$csv_file = isset($argv[1]) ? $argv[1] : '/tmp/ad_users.csv';
$domain = 'ppda.go.ug';

$skip_accounts = array('guest', 'krbtgt', 'administrator');

// Also skip accounts with ad_ prefix (duplicates of real accounts)
$skip_prefixes = array('ad_');

$ldap = ldap_connect($ldap_host, $ldap_port);
if (!$ldap) {
    fwrite(STDERR, "ERROR: Could not connect to LDAP server $ldap_host:$ldap_port\n");
    exit(1);
}

ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

if (!@ldap_bind($ldap, $ldap_bind_dn, $ldap_bind_pwd)) {
    fwrite(STDERR, "ERROR: LDAP bind failed: " . ldap_error($ldap) . "\n");
    exit(1);
}

$result = @ldap_search($ldap, $ldap_base_dn, $ldap_filter, $ldap_attrs);
if (!$result) {
    fwrite(STDERR, "ERROR: LDAP search failed: " . ldap_error($ldap) . "\n");
    exit(1);
}

$entries = @ldap_get_entries($ldap, $result);
if (!$entries) {
    fwrite(STDERR, "ERROR: Could not get LDAP entries\n");
    exit(1);
}

$fp = fopen($csv_file, 'w');
if (!$fp) {
    fwrite(STDERR, "ERROR: Could not open $csv_file for writing\n");
    exit(1);
}

fputcsv($fp, array('primary_key', 'name', 'first_name', 'email', 'phone', 'mobile_phone', 'function', 'employee_number', 'org_id'), ';');

$count = 0;
for ($i = 0; $i < $entries['count']; $i++) {
    $entry = $entries[$i];

    if (empty($entry['samaccountname'][0])) {
        continue;
    }

    $sam = strtolower($entry['samaccountname'][0]);
    if (in_array($sam, $skip_accounts)) {
        continue;
    }

    // Skip accounts with ad_ prefix (duplicates of real accounts)
    foreach ($skip_prefixes as $prefix) {
        if (strpos($sam, $prefix) === 0) {
            continue 2;
        }
    }

    $email = !empty($entry['mail'][0]) ? $entry['mail'][0] : $sam . '@' . $domain;
    $last_name = isset($entry['sn'][0]) ? $entry['sn'][0] : $sam;
    $first_name = isset($entry['givenname'][0]) ? $entry['givenname'][0] : $sam;
    $phone = isset($entry['telephonenumber'][0]) ? $entry['telephonenumber'][0] : '';
    $mobile = isset($entry['mobile'][0]) ? $entry['mobile'][0] : '';
    $title = isset($entry['title'][0]) ? $entry['title'][0] : '';
    $employee_number = isset($entry['employeenumber'][0]) ? $entry['employeenumber'][0] : '';
    $org_id = $default_org_id;

    fputcsv($fp, array(
        $email,
        $last_name,
        $first_name,
        $email,
        $phone,
        $mobile,
        $title,
        $employee_number,
        $org_id
    ), ';');

    $count++;
}

fclose($fp);
ldap_close($ldap);

echo "Exported $count users to $csv_file\n";
