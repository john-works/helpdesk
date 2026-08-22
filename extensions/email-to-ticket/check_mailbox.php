<?php
require_once __DIR__ . '/class.imapclient.php';
$imap = new IMAPClient();
$imap->connect('mail.ppda.go.ug', 993, true);
$imap->login('helpdesk@ppda.go.ug', '23r0@2Q2$');
$imap->selectMailbox('INBOX');
$all = $imap->search('ALL');
$unseen = $imap->search('UNSEEN');
echo 'Total: ' . count($all) . PHP_EOL;
echo 'Unseen: ' . count($unseen) . PHP_EOL;
if (!empty($unseen)) {
    echo 'IDs: ' . implode(', ', $unseen) . PHP_EOL;
}
$imap->close();
