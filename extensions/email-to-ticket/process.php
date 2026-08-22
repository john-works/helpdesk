<?php

define("EMAIL_IMAP_HOST", "mail.ppda.go.ug");
define("EMAIL_IMAP_PORT", 993);
define("EMAIL_IMAP_SSL", true);
define("IMAP_MAILBOX", "INBOX");
define("PROCESSED_FOLDER", "Processed");
define("LOG_FILE", "/tmp/email-to-ticket.log");
define("DEDUP_FILE", __DIR__ . DIRECTORY_SEPARATOR . "processed-messageids.txt");
define("CRON_INTERVAL", 60);
define("EMAIL_SMTP_HOST", "mail.ppda.go.ug");
define("EMAIL_SMTP_PORT", 465);
define("EMAIL_SMTP_SSL", true);

$processLock = fopen(__DIR__ . DIRECTORY_SEPARATOR . 'email-to-ticket.lock', 'c');
if ($processLock === false || !flock($processLock, LOCK_EX | LOCK_NB)) {
    if (is_resource($processLock)) {
        fclose($processLock);
    }
    log_msg("Skipped overlapping email-to-ticket process");
    exit(0);
}
register_shutdown_function(function () use ($processLock) {
    flock($processLock, LOCK_UN);
    fclose($processLock);
});

// One entry per mailbox polled by the cron.
// Each mailbox maps to a Service (family) and to the Team that handles its tasks.
//  - service_id: leave null to let agents pick the service manually
//  - team_id:    team auto-assigned to new tickets from this mailbox
//  - notify:     when set, an acknowledgment email is sent to the sender each time a new ticket is created
//                (from = this mailbox, cc = the given address). Leave null to disable.
$MAILBOXES = [
    [
        'user'            => 'helpdesk@ppda.go.ug',
        'pass'            => '23r0@2Q2$',
        'class'           => 'UserRequest',
        'service_id'      => null,
        'team_id'         => null,
        'allowed_domains' => null,
        'notify'          => null,
    ],
];

require_once __DIR__ . "/class.imapclient.php";
require_once __DIR__ . "/class.smtpclient.php";

function log_msg($msg)
{
    $line = date("Y-m-d H:i:s") . " " . $msg . "\n";
    file_put_contents(LOG_FILE, $line, FILE_APPEND);
    echo $line;
}

function run_once($cfg)
{
    try {
        $imap = new IMAPClient();
        $imap->connect(EMAIL_IMAP_HOST, EMAIL_IMAP_PORT, EMAIL_IMAP_SSL);
        $imap->login($cfg['user'], $cfg['pass']);

        try {
            $imap->createMailbox(PROCESSED_FOLDER);
        } catch (\Exception $e) {}

        $imap->selectMailbox(IMAP_MAILBOX);
        $unseen = $imap->search("UNSEEN");

        if (empty($unseen)) {
            $imap->close();
            return 0;
        }

        $count = 0;
        $processedIds = [];
        foreach ($unseen as $msgId) {
            try {
                processEmail($imap, $msgId, $cfg);
                $processedIds[] = $msgId;
                $count++;
            } catch (\Exception $e) {
                log_msg("ERROR processing msg $msgId: " . $e->getMessage());
            }
        }

        if (!empty($processedIds)) {
            foreach ($processedIds as $msgId) {
                try {
                    $imap->copyToFolder($msgId, PROCESSED_FOLDER);
                    $imap->markDeleted($msgId);
                } catch (\Exception $e) {
                    log_msg("WARNING: Could not move msg $msgId to Processed: " . $e->getMessage());
                    try { $imap->markAsSeen($msgId); } catch (\Exception $e2) {}
                }
            }
            try {
                $imap->expunge();
            } catch (\Exception $e) {
                log_msg("WARNING: EXPUNGE failed: " . $e->getMessage());
            }
        }

        $imap->close();
        return $count;

    } catch (\Exception $e) {
        log_msg("FATAL ERROR: " . $e->getMessage());
        return -1;
    }
}

function processEmail($imap, $msgId, $cfg)
{
    $header = $imap->fetchHeader($msgId);
    $rawEmail = $imap->fetchBody($msgId);

    $from = $header["From"] ?? "unknown";
    $to = $header["To"] ?? "";
    $subject = $header["Subject"] ?? "(No subject)";
    $messageId = trim($header["Message-ID"] ?? "");

    // Block emails sent to everyone@ppda.go.ug - do not create tickets for them
    if (stripos($to, "everyone@ppda.go.ug") !== false) {
        log_msg("  BLOCKED: email sent to everyone@ppda.go.ug (To: $to)");
        $imap->markAsSeen($msgId);
        return;
    }

    log_msg("Processing msg $msgId: From=$from Subject=$subject");

    $fromEmail = "";
    if (preg_match("/<([^>]+@[^>]+)>/", $from, $m)) {
        $fromEmail = strtolower(trim($m[1]));
    } elseif (preg_match("/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/", $from, $m)) {
        $fromEmail = strtolower(trim($m[1]));
    }

    if (empty($fromEmail)) {
        log_msg("  WARNING: Could not extract email from: $from");
        $imap->markAsSeen($msgId);
        return;
    }

    // Skip emails sent by this mailbox itself (e.g. the ack notifications we send),
    // to avoid the cron reprocessing them and creating tickets.
    if (strtolower($fromEmail) === strtolower($cfg['user'])) {
        log_msg("  SKIPPED: self-sent email from {$cfg['user']}");
        $imap->markAsSeen($msgId);
        return;
    }

    if (!empty($messageId) && isMessageIdProcessed($messageId)) {
        log_msg("  SKIPPED: duplicate email (Message-ID already processed)");
        $imap->markAsSeen($msgId);
        return;
    }

    $allowedDomains = $cfg['allowed_domains'] ?? null;
    if (!empty($allowedDomains)) {
        $isAllowed = false;
        foreach ($allowedDomains as $suffix) {
            if (str_ends_with($fromEmail, $suffix)) {
                $isAllowed = true;
                break;
            }
        }
        if (!$isAllowed) {
            log_msg("  SKIPPING: $fromEmail not from an allowed domain (" . implode(", ", $allowedDomains) . ")");
            $imap->markAsSeen($msgId);
            return;
        }
    }

    require_once "/var/www/html/itop_new/approot.inc.php";
    require_once APPROOT . "/application/startup.inc.php";

    UserRights::Login("admin");
    CMDBObject::SetCurrentChangeFromParams("Email-to-Ticket: $fromEmail", "email-processing");

    // Check if email is a reply to an existing resolved/closed ticket
    $ticketRef = extractTicketRef($subject);
    if ($ticketRef) {
        log_msg("  Found ticket ref $ticketRef in subject, looking up...");
        $oTicket = MetaModel::GetObjectByColumn("Ticket", "ref", $ticketRef);

        if ($oTicket) {
            $status = $oTicket->Get("status");
            $operationalStatus = $oTicket->Get("operational_status");

            if ($operationalStatus === "resolved" || $operationalStatus === "closed" || $status === "resolved" || $status === "closed") {
                log_msg("  Ticket $ticketRef is $status/$operationalStatus, reopening...");
                handleTicketReply($oTicket, $rawEmail, $fromEmail);
                return;
            } else {
                log_msg("  Ticket $ticketRef is $status (not resolved), adding public log entry only...");
                handleTicketReply($oTicket, $rawEmail, $fromEmail);
                return;
            }
        } else {
            log_msg("  Ticket ref $ticketRef not found, creating new ticket");
        }
    }

    $oPerson = MetaModel::GetObjectByColumn("Person", "email", $fromEmail);

    if (!$oPerson) {
        log_msg("  SKIPPING: Unknown sender $fromEmail");
        $imap->markAsSeen($msgId);
        return;
    }

    $orgId = $oPerson->Get("org_id");
    if (!$orgId) {
        $oDefaultOrg = MetaModel::GetObjectByColumn("Organization", "name", "PPDA");
        if ($oDefaultOrg) {
            $orgId = $oDefaultOrg->GetKey();
        } else {
            log_msg("  ERROR: No organization found");
            $imap->markAsSeen($msgId);
            return;
        }
    }

    $emailData = parseEmail($rawEmail);
    $htmlBody = $emailData["html"];
    if (trim(strip_tags($htmlBody)) === "") {
        $htmlBody = "<p><i>(No content in email body)</i></p>";
        $emailData["html"] = $htmlBody;
    }

    $sClass = $cfg['class'];
    $oRequest = MetaModel::NewObject($sClass);
    $oRequest->Set("title", $subject);
    $oRequest->Set("description", $htmlBody);
    $oRequest->Set("org_id", $orgId);
    $oRequest->Set("caller_id", $oPerson->GetKey());
    $oRequest->Set("origin", "mail");
    if (!empty($cfg['service_id'])) {
        $oRequest->Set("service_id", $cfg['service_id']);
    }
    if (!empty($cfg['team_id'])) {
        $oRequest->Set("team_id", $cfg['team_id']);
    }
    $oRequest->Set("public_log", "Ticket created automatically from email");
    $key = $oRequest->DBInsert();
    $ref = $oRequest->Get("ref");

    rememberMessageId($messageId);

    log_msg("  CREATED $ref (id=$key)");

    if (!empty($cfg['notify'])) {
        sendAckEmail($cfg, $oRequest, $oPerson, $fromEmail);
    }

    try {
        if (!empty($emailData["images"])) {
            foreach ($emailData["images"] as $cid => $imageInfo) {
                try {
                    $oDoc = new ormDocument(
                        $imageInfo["data"],
                        $imageInfo["mime"],
                        $imageInfo["filename"]
                    );
                    $oInlineImage = MetaModel::NewObject("InlineImage");
                    $oInlineImage->Set("expire", time() + 86400 * 30);
                    $oInlineImage->Set("temp_id", "email-import");
                    $oInlineImage->Set("item_class", $sClass);
                    $oInlineImage->Set("item_id", $key);
                    $oInlineImage->Set("item_org_id", $orgId);
                    $oInlineImage->Set("contents", $oDoc);
                    $oInlineImage->Set("secret", sprintf('%06x', mt_rand(0, 0xFFFFFF)));
                    $imgId = $oInlineImage->DBInsert();

                    $imgUrl = utils::GetAbsoluteUrlAppRoot() . INLINEIMAGE_DOWNLOAD_URL . $imgId . "&s=" . $oInlineImage->Get("secret");
                    $htmlBody = str_replace("cid:$cid", $imgUrl, $htmlBody);

                    log_msg("  Embedded image: " . $imageInfo["filename"]);
                } catch (\Exception $e) {
                    log_msg("  WARNING: Could not embed image " . $imageInfo["filename"] . ": " . $e->getMessage());
                }
            }

            if (strlen($htmlBody) > 65000) {
                $htmlBody = substr($htmlBody, 0, 65000);
                $htmlBody .= "<p><i>[Content truncated - inline images saved separately]</i></p>";
            }

            $oRequest->Set("description", $htmlBody);
            $oRequest->DBUpdate();
        }

        if (!empty($emailData["attachments"])) {
            $attNames = [];
            foreach ($emailData["attachments"] as $attInfo) {
                try {
                    $oDoc = new ormDocument(
                        $attInfo["data"],
                        $attInfo["mime"],
                        $attInfo["filename"]
                    );
                    $oAttachment = MetaModel::NewObject("Attachment");
                    $oAttachment->Set("expire", date('Y-m-d H:i:s', time() + 86400 * 365));
                    $oAttachment->Set("temp_id", "");
                    $oAttachment->Set("item_class", $sClass);
                    $oAttachment->Set("item_id", $key);
                    $oAttachment->Set("item_org_id", $orgId);
                    $oAttachment->Set("contents", $oDoc);
                    $oAttachment->DBInsert();

                    $attNames[] = $attInfo["filename"];
                    log_msg("  Attached file: " . $attInfo["filename"]);

                    if (str_starts_with($attInfo["mime"], "image/")) {
                        $b64 = base64_encode($attInfo["data"]);
                        $safeName = htmlspecialchars($attInfo["filename"], ENT_QUOTES, "UTF-8");
                        $htmlBody .= "<hr/><p><b>Attachment: $safeName</b></p><p><img src=\"data:{$attInfo['mime']};base64,$b64\" style=\"max-width:600px;\" alt=\"$safeName\"/></p>";
                    }
                } catch (\Exception $e) {
                    log_msg("  WARNING: Could not attach " . $attInfo["filename"] . ": " . $e->getMessage());
                }
            }

            if (strlen($htmlBody) > 65000) {
                $htmlBody = substr($htmlBody, 0, 65000);
                $htmlBody .= "<p><i>[Content truncated - attachments saved separately]</i></p>";
            }

            $oRequest->Set("description", $htmlBody);
            $oRequest->DBUpdate();
        }
    } catch (\Exception $e) {
        log_msg("  WARNING: Post-creation update failed for $ref (ticket kept as-is): " . $e->getMessage());
    }
}

function isMessageIdProcessed($messageId)
{
    if (!file_exists(DEDUP_FILE)) {
        return false;
    }
    $lines = @file(DEDUP_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return false;
    }
    $cutoff = time() - 86400 * 30;
    foreach ($lines as $line) {
        $parts = explode("\t", $line, 2);
        if (count($parts) < 2 || (int)$parts[0] < $cutoff) {
            continue;
        }
        if ($parts[1] === $messageId) {
            return true;
        }
    }
    return false;
}

function rememberMessageId($messageId)
{
    if (empty($messageId)) {
        return;
    }
    $lines = [];
    if (file_exists(DEDUP_FILE)) {
        $lines = @file(DEDUP_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            $lines = [];
        }
    }
    $cutoff = time() - 86400 * 30;
    $kept = [];
    foreach ($lines as $line) {
        $parts = explode("\t", $line, 2);
        if (count($parts) >= 2 && (int)$parts[0] >= $cutoff) {
            $kept[] = $line;
        }
    }
    $kept[] = time() . "\t" . $messageId;
    @file_put_contents(DEDUP_FILE, implode("\n", $kept) . "\n", LOCK_EX);
}

function sendAckEmail($cfg, $oRequest, $oPerson, $fromEmail)
{
    $ref = $oRequest->Get("ref");
    $status = $oRequest->Get("status");
    $subject = $oRequest->Get("title");
    $description = $oRequest->Get("description");

    // Strip HTML from description for email
    $cleanDesc = preg_replace("/<style[^>]*>.*?<\/style>/s", "", $description);
    $cleanDesc = preg_replace("/<script[^>]*>.*?<\/script>/s", "", $cleanDesc);
    $cleanDesc = strip_tags($cleanDesc);
    $cleanDesc = html_entity_decode($cleanDesc, ENT_QUOTES, "UTF-8");
    // Strip confidentiality note and similar email signatures
    $cleanDesc = preg_replace("/Confidentiality Note:.*$/is", "", $cleanDesc);
    $cleanDesc = preg_replace("/This email and any files transmitted.*$/is", "", $cleanDesc);
    $cleanDesc = preg_replace("/If you received this in error.*$/is", "", $cleanDesc);
    $cleanDesc = preg_replace("/\n{3,}/", "\n\n", trim($cleanDesc));

    $fromAddr = $cfg['user'];
    $fromLabel = !empty($cfg['notify']['from_label']) ? $cfg['notify']['from_label'] : $fromAddr;
    $toAddr = !empty($cfg['notify']['to']) ? $cfg['notify']['to'] : $fromEmail;
    $ccAddr = !empty($cfg['notify']['cc']) ? $cfg['notify']['cc'] : "";

    try {
        $fullName = trim((string)$oPerson->Get("name"));
    } catch (\Exception $e) {
        $fullName = "";
    }
    if ($fullName === "") {
        $fullName = trim($fromEmail);
    }

    $plain  = "Dear " . $fullName . ",\n\n";
    $plain .= "Your request has been registered with the following details:\n\n";
    $plain .= "Ticket Reference: " . $ref . "\n";
    $plain .= "Title: " . $subject . "\n";
    $plain .= "Status: " . ucfirst($status) . "\n";
    if (!empty($cleanDesc)) {
        $plain .= "Description: " . $cleanDesc . "\n";
    }
    $plain .= "\n";
    $plain .= "We will update you on the progress. You can also check the status at any time.\n\n";
    $plain .= "Best regards,\n";
    $plain .= "PPDA IT Help Desk Team";

    // Plain-text format identical to the assigned/resolved notification emails
    $htmlBody = "<pre>" . htmlspecialchars($plain, ENT_QUOTES, "UTF-8") . "</pre>";

    $emailSubject = "New request $ref has been registered";

    try {
        $smtp = new SMTPClient();
        $smtp->connect(EMAIL_SMTP_HOST, EMAIL_SMTP_PORT, EMAIL_SMTP_SSL);
        $smtp->login($cfg['user'], $cfg['pass']);
        $smtp->send($fromAddr, $fromLabel, $toAddr, $ccAddr, $emailSubject, $htmlBody);
        $smtp->close();
        log_msg("  EMAIL sent to $toAddr (Cc: $ccAddr) about $ref [$status]");
    } catch (\Exception $e) {
        log_msg("  WARNING: Acknowledgment email NOT sent for $ref: " . $e->getMessage());
    }
}

function handleTicketReply($oTicket, $rawEmail, $fromEmail)
{
    $emailData = parseEmail($rawEmail);
    $htmlBody = $emailData["html"];
    $ref = $oTicket->Get("ref");
    $ticketId = $oTicket->GetKey();
    $sClass = get_class($oTicket);
    $orgId = $oTicket->Get("org_id");

    $status = $oTicket->Get("status");
    $operationalStatus = $oTicket->Get("operational_status");

    // Add plain text version of email body to public_log
    // Remove style and script blocks (CKEditor/CSS junk)
    $cleanHtml = preg_replace("/<style[^>]*>.*?<\/style>/s", "", $htmlBody);
    $cleanHtml = preg_replace("/<script[^>]*>.*?<\/script>/s", "", $cleanHtml);
    $cleanHtml = preg_replace("/<!--.*?-->/s", "", $cleanHtml);
    // Strip remaining HTML tags and decode entities
    $plainText = html_entity_decode(strip_tags($cleanHtml), ENT_QUOTES, "UTF-8");
    // Collapse multiple blank lines
    $plainText = preg_replace("/\n{3,}/", "\n\n", $plainText);
    $plainText = trim($plainText);

    if (!empty($emailData["attachments"])) {
        $attNames = [];
        foreach ($emailData["attachments"] as $attInfo) {
            $attNames[] = $attInfo["filename"];
        }
        $plainText .= "\n\nAttachments: " . implode(", ", $attNames);
    }

    $logEntry = "Email reply from $fromEmail:\n" . $plainText;

    // Check if ticket needs to be reopened (resolved/closed -> pending)
    $needsReopen = ($status === "resolved" || $status === "closed"
                    || $operationalStatus === "resolved" || $operationalStatus === "closed");

    if ($needsReopen) {
        try {
            // First reopen: resolved/closed -> assigned
            $oTicket->ApplyStimulus("ev_reopen");
            log_msg("  Applied ev_reopen to $ref (now: " . $oTicket->Get("status") . ")");
        } catch (\Exception $e) {
            log_msg("  WARNING: ev_reopen failed for $ref: " . $e->getMessage());
        }

        try {
            // Set pending with reason
            $oTicket->Set("pending_reason", "User replied via email - not satisfied with resolution");
            $oTicket->ApplyStimulus("ev_pending");
            log_msg("  Applied ev_pending to $ref (now: " . $oTicket->Get("status") . ")");
        } catch (\Exception $e) {
            log_msg("  WARNING: ev_pending failed for $ref: " . $e->getMessage());
        }
    }

    // Add the email content to public_log
    $oTicket->Set("public_log", $logEntry);
    $oTicket->DBUpdate();
    log_msg("  Updated $ref (id=$ticketId) with email reply, status=" . $oTicket->Get("status"));

    if (!empty($emailData["attachments"])) {
        foreach ($emailData["attachments"] as $attInfo) {
            try {
                $oDoc = new ormDocument(
                    $attInfo["data"],
                    $attInfo["mime"],
                    $attInfo["filename"]
                );
                $oAttachment = MetaModel::NewObject("Attachment");
                $oAttachment->Set("expire", date('Y-m-d H:i:s', time() + 86400 * 365));
                $oAttachment->Set("temp_id", "");
                $oAttachment->Set("item_class", $sClass);
                $oAttachment->Set("item_id", $ticketId);
                $oAttachment->Set("item_org_id", $orgId);
                $oAttachment->Set("contents", $oDoc);
                $oAttachment->DBInsert();
                log_msg("  Attached file on reply: " . $attInfo["filename"]);
            } catch (\Exception $e) {
                log_msg("  WARNING: Could not attach " . $attInfo["filename"] . " on reply: " . $e->getMessage());
            }
        }
    }
}

function extractTicketRef($subject)
{
    $decoded = decodeMimeHeader($subject);
    if (preg_match("/([A-Z])-(\d{6})/", $decoded, $m)) {
        return $m[1] . "-" . $m[2];
    }
    return null;
}

function decodeMimeHeader($str)
{
    return preg_replace_callback(
        "/=\?([^?]+)\?([QBqb])\?([^?]*)\?=/",
        function ($m) {
            $charset = $m[1];
            $encoding = strtoupper($m[2]);
            $text = $m[3];
            $decoded = ($encoding === "B") ? base64_decode($text) : quoted_printable_decode($text);
            if (strtoupper($charset) !== "UTF-8") {
                $decoded = @iconv($charset, "UTF-8//IGNORE", $decoded);
            }
            return $decoded;
        },
        $str
    );
}

function moveToProcessed($imap, $msgId)
{
    try {
        $imap->copyToFolder($msgId, PROCESSED_FOLDER);
        $imap->markDeleted($msgId);
        $imap->expunge();
    } catch (\Exception $e) {
        $imap->markAsSeen($msgId);
    }
}

function extractDocxText($docxData)
{
    $text = "";
    $tmpFile = tempnam(sys_get_temp_dir(), "docx_");
    try {
        file_put_contents($tmpFile, $docxData);
        $zip = new ZipArchive();
        if ($zip->open($tmpFile) === true) {
            $xml = $zip->getFromName("word/document.xml");
            if ($xml !== false) {
                $doc = simplexml_load_string($xml);
                if ($doc !== false) {
                    $doc->registerXPathNamespace("w", "http://schemas.openxmlformats.org/wordprocessingml/2006/main");
                    $nodes = $doc->xpath("//w:t");
                    foreach ($nodes as $node) {
                        $text .= (string)$node . " ";
                    }
                }
            }
            $zip->close();
        }
    } catch (\Exception $e) {
    }
    @unlink($tmpFile);
    return trim($text);
}

function extractPdfText($pdfData)
{
    $text = "";
    $tmpFile = tempnam(sys_get_temp_dir(), "pdf_");
    try {
        file_put_contents($tmpFile, $pdfData);
        $content = file_get_contents($tmpFile);
        if ($content !== false) {
            if (preg_match_all('/BT[\s\S]*?ET/', $content, $matches)) {
                foreach ($matches[0] as $btBlock) {
                    if (preg_match_all('/\(([^)]*)\)/', $btBlock, $textMatches)) {
                        foreach ($textMatches[1] as $t) {
                            $decoded = @iconv("UTF-16BE", "UTF-8//IGNORE", $t);
                            $text .= ($decoded !== false) ? $decoded : $t;
                        }
                    }
                    if (preg_match_all('/<([0-9A-Fa-f]+)>/', $btBlock, $hexMatches)) {
                        foreach ($hexMatches[1] as $hex) {
                            $binary = hex2bin($hex);
                            if ($binary !== false) {
                                $decoded = @iconv("UTF-16BE", "UTF-8//IGNORE", $binary);
                                $text .= ($decoded !== false) ? $decoded : "";
                            }
                        }
                    }
                }
            }
        }
    } catch (\Exception $e) {
    }
    @unlink($tmpFile);
    return trim($text);
}

function parseEmail($rawEmail)
{
    $result = [
        "html" => "No readable content",
        "images" => [],
        "attachments" => []
    ];

    $parts = explode("\r\n\r\n", $rawEmail, 2);
    if (count($parts) < 2) {
        $parts = explode("\n\n", $rawEmail, 2);
    }

    $headers = $parts[0] ?? "";
    $bodyPart = $parts[1] ?? $rawEmail;

    $boundary = "";
    if (preg_match("/boundary=\"?([^\"\\r\\n]+)\"?/i", $headers . "\r\n" . $bodyPart, $m)) {
        $boundary = "--" . $m[1];
    }

    if (empty($boundary)) {
        $result["html"] = extractSinglePart($headers, $bodyPart);
        $result["html"] = stripMobileSignatures($result["html"]);
        return $result;
    }

    parseMultipart($bodyPart, $boundary, $result);

    if (isset($result["text_html"])) {
        $result["html"] = $result["text_html"];
    } elseif (isset($result["text_plain"])) {
        $result["html"] = "<pre>" . htmlentities($result["text_plain"], ENT_QUOTES, "UTF-8") . "</pre>";
    } elseif (!empty($result["docx_content"])) {
        $docxText = htmlspecialchars($result["docx_content"], ENT_QUOTES, "UTF-8");
        $result["html"] = "<pre>$docxText</pre>";
    } elseif (!empty($result["attachments"])) {
        $attNames = array_column($result["attachments"], "filename");
        $attList = htmlspecialchars(implode(", ", $attNames), ENT_QUOTES, "UTF-8");
        $result["html"] = "<p><i>Email contained attachment(s): $attList</i></p>";
    } elseif (!empty($result["images"])) {
        $imgCount = count($result["images"]);
        $result["html"] = "<p><i>Email contained $imgCount inline image(s)</i></p>";
    } else {
        $result["html"] = "No readable content";
    }

    if (strlen($result["html"]) > 65000) {
        $fallback = "";
        if (!empty($result["attachments"])) {
            $attNames = array_column($result["attachments"], "filename");
            $fallback = "Email contained attachment(s): " . implode(", ", $attNames);
        } elseif (!empty($result["images"])) {
            $fallback = "Email contained " . count($result["images"]) . " inline image(s)";
        } else {
            $fallback = "No readable content";
        }
        $result["html"] = "<p><i>" . htmlspecialchars($fallback, ENT_QUOTES, "UTF-8") . "</i></p>";
    }

    $result["html"] = stripMobileSignatures($result["html"]);
    return $result;
}

function parseMultipart($bodyPart, $boundary, &$result)
{
    $sections = explode($boundary, $bodyPart);
    foreach ($sections as $section) {
        if (trim($section) === "--" || trim($section) === "") {
            continue;
        }
        $secParts = explode("\r\n\r\n", $section, 2);
        if (count($secParts) < 2) {
            $secParts = explode("\n\n", $section, 2);
        }
        $secHeaders = $secParts[0] ?? "";
        $secBody = $secParts[1] ?? "";

        $secMime = "";
        if (preg_match("/Content-Type:\s*(\S+)/i", $secHeaders, $m)) {
            $secMime = strtolower(trim($m[1], "; \t\r\n"));
        }

        if (str_starts_with($secMime, "multipart/")) {
            if (preg_match("/boundary=\"?([^\"\\r\\n]+)\"?/i", $secHeaders . "\r\n" . $secBody, $m)) {
                parseMultipart($secBody, "--" . $m[1], $result);
            }
            continue;
        }

        handleLeafPart($secHeaders, $secBody, $result);
    }
}

function handleLeafPart($secHeaders, $secBody, &$result)
{
    $secEncoding = "";
    if (preg_match("/Content-Transfer-Encoding:\s*(\S+)/i", $secHeaders, $m)) {
        $secEncoding = strtolower(trim($m[1]));
    }

    $secCharset = "UTF-8";
    if (preg_match("/charset=\"?([^\"\\r\\n;\\s]+)\"?/i", $secHeaders, $m)) {
        $secCharset = strtoupper($m[1]);
    }

    $secContentId = "";
    if (preg_match("/Content-ID:\s*<(.*?)>/i", $secHeaders, $m)) {
        $secContentId = trim($m[1]);
    }

    $secDisposition = "";
    if (preg_match("/Content-Disposition:\s*(\S+)/i", $secHeaders, $m)) {
        $secDisposition = strtolower(trim($m[1], "; \t\r\n"));
    }

    $secFilename = "";
    if (preg_match("/filename=\"?([^\";\\r\\n]+)\"?/i", $secHeaders, $m)) {
        $secFilename = trim($m[1]);
    } elseif (preg_match("/name=\"?([^\";\\r\\n]+)\"?/i", $secHeaders, $m)) {
        $secFilename = trim($m[1]);
    }

    $secMime = "";
    if (preg_match("/Content-Type:\s*(\S+)/i", $secHeaders, $m)) {
        $secMime = strtolower(trim($m[1], "; \t\r\n"));
    }

    $secBody = decodeContent($secBody, $secEncoding, $secCharset);

    if (str_starts_with($secMime, "text/plain")) {
        $result["text_plain"] = $secBody;
    } elseif (str_starts_with($secMime, "text/html")) {
        $result["text_html"] = $secBody;
    } elseif (str_starts_with($secMime, "image/") && !empty($secContentId)) {
        $result["images"][$secContentId] = [
            "data" => $secBody,
            "mime" => $secMime,
            "filename" => $secFilename ?: "image_" . $secContentId,
            "cid" => $secContentId
        ];
    } elseif (str_starts_with($secMime, "image/") && $secDisposition === "inline") {
        $cid = $secContentId ?: "img_" . count($result["images"]);
        $result["images"][$cid] = [
            "data" => $secBody,
            "mime" => $secMime,
            "filename" => $secFilename ?: "image_" . $cid,
            "cid" => $cid
        ];
    } elseif (str_starts_with($secMime, "image/")) {
        $attName = $secFilename ?: ("image_" . count($result["attachments"]) . "." . str_replace("image/", "", $secMime));
        $result["attachments"][] = [
            "data" => $secBody,
            "mime" => $secMime,
            "filename" => $attName
        ];
    } elseif (!empty($secFilename) || $secDisposition === "attachment") {
        $attName = $secFilename ?: ("attachment_" . count($result["attachments"]));
        $result["attachments"][] = [
            "data" => $secBody,
            "mime" => $secMime,
            "filename" => $attName
        ];

        if (str_ends_with(strtolower($attName), ".docx")) {
            $docxText = extractDocxText($secBody);
            if (!empty($docxText)) {
                $result["docx_content"] = $docxText;
            }
        }
    }
}

function extractSinglePart($headers, $body)
{
    $encoding = "";
    $charset = "UTF-8";

    if (preg_match("/Content-Transfer-Encoding:\s*(\S+)/i", $headers, $m)) {
        $encoding = strtolower(trim($m[1]));
    }
    if (preg_match("/charset=\"?([^\"\\r\\n;\\s]+)\"?/i", $headers, $m)) {
        $charset = strtoupper($m[1]);
    }

    $body = decodeContent(trim($body), $encoding, $charset);

    if (stripos($headers, "text/html") !== false) {
        return $body;
    }

    return "<pre>" . htmlentities($body, ENT_QUOTES, "UTF-8") . "</pre>";
}


function tagTolerantPattern($phrase)
{
    $junk = "(?:\\s|<[^>]*>|&nbsp;|&#160;)*";
    $chars = preg_split("//u", $phrase, -1, PREG_SPLIT_NO_EMPTY);
    $parts = [];
    foreach ($chars as $c) {
        if ($c === " ") {
            $parts[] = "\\s";
        } else {
            $parts[] = preg_quote($c, "/") . $junk;
        }
    }
    return "/" . implode("", $parts) . ".*/is";
}

function stripMobileSignatures($html)
{
    $patterns = [
        '/<div[^>]*class=["][^"]*signature[^"]*["][^>]*>.*?<\/div>/is',
        '/<div[^>]*class=["][^"]*AndroidSignature[^"]*["][^>]*>.*?<\/div>/is',
        '/<div[^>]*class=["][^"]*mail-signature[^"]*["][^>]*>.*?<\/div>/is',
        '/<div[^>]*class=["]gmail_signature["][^>]*>.*?<\/div>/is',
        '/<div[^>]*class=["]moz-signature["][^>]*>.*?<\/div>/is',
        '/<br\s*\\/?>\s*--\s*<br\s*\\/?>.*/is',
        '/(Sent\s+from|Get)\s+(my\s+)?(Outlook|iPhone|iPad|iPod|Android|Samsung|Galaxy|Windows\s+Phone|Mobile).*/is',
        tagTolerantPattern("Confidentiality Note:"),
        tagTolerantPattern("This email and any files transmitted"),
        tagTolerantPattern("If you received this in error"),
    ];
    foreach ($patterns as $pattern) {
        $html = preg_replace($pattern, '', $html);
    }
    $html = preg_replace("/(?:\s|<br\s*\/?>|&nbsp;|&#160;)+$/i", "", $html);
    return trim($html);
}

function decodeContent($data, $encoding, $charset)
{
    switch ($encoding) {
        case "base64":
            $data = base64_decode($data);
            break;
        case "quoted-printable":
            $data = quoted_printable_decode($data);
            break;
    }

    if ($charset && strtoupper($charset) !== "UTF-8") {
        $data = @iconv($charset, "UTF-8//IGNORE", $data);
    }

    return $data;
}

$iterations = floor(60 / CRON_INTERVAL);
if ($iterations < 1) $iterations = 1;

for ($i = 0; $i < $iterations; $i++) {
    log_msg("=== Email-to-Ticket run started (iteration " . ($i + 1) . "/$iterations) ===");
    foreach ($MAILBOXES as $cfg) {
        log_msg("=== Polling mailbox " . $cfg['user'] . " ===");
        $count = run_once($cfg);
        log_msg("=== Mailbox " . $cfg['user'] . " processed: $count tickets ===");
    }

    if ($i < $iterations - 1) {
        sleep(CRON_INTERVAL);
    }
}
