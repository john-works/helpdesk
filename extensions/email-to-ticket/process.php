<?php

define("EMAIL_IMAP_HOST", "mail.ppda.go.ug");
define("EMAIL_IMAP_PORT", 993);
define("EMAIL_IMAP_SSL", true);
define("IMAP_MAILBOX", "INBOX");
define("PROCESSED_FOLDER", "Processed");
define("LOG_FILE", "/tmp/email-to-ticket.log");
define("CRON_INTERVAL", 30);
define("EMAIL_SMTP_HOST", "mail.ppda.go.ug");
define("EMAIL_SMTP_PORT", 465);
define("EMAIL_SMTP_SSL", true);

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
        'team_id'         => 179, // Helpdesk
        'allowed_domains' => ['@ppda.go.ug'], // Only internal senders
        'notify'          => [
            'from_label' => 'PPDA IT Helpdesk',
            'cc'         => 'it@ppda.go.ug',
        ],
    ],
    [
        'user'            => 'registryhelpdesk@ppda.go.ug',
        'pass'            => 'ppda2026!',
        'class'           => 'UserRequest',
        'service_id'      => 40,  // Letter Request (Registry Service family)
        'team_id'         => 293, // Registry Help Desk
        'allowed_domains' => null, // Accept senders from any mail domain
        'notify'          => [
            'from_label' => 'PPDA IT Helpdesk',
            'cc'         => 'library@ppda.go.ug',
        ],
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
        foreach ($unseen as $msgId) {
            try {
                processEmail($imap, $msgId, $cfg);
                $count++;
            } catch (\Exception $e) {
                log_msg("ERROR processing msg $msgId: " . $e->getMessage());
                try { $imap->markAsSeen($msgId); } catch (\Exception $e2) {}
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
                moveToProcessed($imap, $msgId);
                return;
            } else {
                log_msg("  Ticket $ticketRef is $status (not resolved), adding public log entry only...");
                handleTicketReply($oTicket, $rawEmail, $fromEmail);
                moveToProcessed($imap, $msgId);
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

    log_msg("  CREATED $ref (id=$key)");

    if (!empty($cfg['notify'])) {
        sendAckEmail($cfg, $oRequest, $oPerson, $fromEmail);
    }

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

        $oRequest->Set("description", $htmlBody);
        $oRequest->DBUpdate();
    }

    moveToProcessed($imap, $msgId);
}

function sendAckEmail($cfg, $oRequest, $oPerson, $fromEmail)
{
    $ref = $oRequest->Get("ref");
    $status = $oRequest->Get("status");
    $subject = $oRequest->Get("title");
    $firstName = $oPerson->Get("first_name");
    $fullName = $oPerson->GetName();

    $fromAddr = $cfg['user'];
    $fromLabel = !empty($cfg['notify']['from_label']) ? $cfg['notify']['from_label'] : $fromAddr;
    $toAddr = !empty($cfg['notify']['to']) ? $cfg['notify']['to'] : $fromEmail;
    $ccAddr = !empty($cfg['notify']['cc']) ? $cfg['notify']['cc'] : "";

    $greeting = "Dear " . (trim($firstName) ?: $fullName) . ",";

    $htmlBody = "<html><body style='font-family:Arial,sans-serif;font-size:14px;color:#222;'>";
    $htmlBody .= "<p>" . htmlspecialchars($greeting, ENT_QUOTES, "UTF-8") . "</p>";
    $htmlBody .= "<p>We have received your email. A ticket has been created as follows:</p>";
    $htmlBody .= "<table cellspacing='0' cellpadding='4' border='0' style='border-collapse:collapse;'>";
    $htmlBody .= "<tr><td style='padding:4px 8px;'><b>Ticket:</b></td><td>" . htmlspecialchars($ref, ENT_QUOTES, "UTF-8") . "</td></tr>";
    $htmlBody .= "<tr><td style='padding:4px 8px;'><b>Subject:</b></td><td>" . htmlspecialchars($subject, ENT_QUOTES, "UTF-8") . "</td></tr>";
    $htmlBody .= "<tr><td style='padding:4px 8px;'><b>Status:</b></td><td>" . htmlspecialchars($status, ENT_QUOTES, "UTF-8") . "</td></tr>";
    $htmlBody .= "</table>";
    $htmlBody .= "<p>Our team will work on it and update you.</p>";
    $htmlBody .= "<p>Regards,<br>" . htmlspecialchars($fromLabel, ENT_QUOTES, "UTF-8") . "</p>";
    $htmlBody .= "</body></html>";

    $emailSubject = "Ticket $ref - $status";

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

function parseEmail($rawEmail)
{
    $result = [
        "html" => "No readable content",
        "images" => []
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

    $sections = explode($boundary, $bodyPart);
    $parts_by_type = [];

    foreach ($sections as $section) {
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

        $secBody = decodeContent($secBody, $secEncoding, $secCharset);

        if (str_starts_with($secMime, "text/plain")) {
            $parts_by_type["text/plain"] = $secBody;
        } elseif (str_starts_with($secMime, "text/html")) {
            $parts_by_type["text/html"] = $secBody;
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
        }
    }

    if (isset($parts_by_type["text/html"])) {
        $result["html"] = $parts_by_type["text/html"];
    } elseif (isset($parts_by_type["text/plain"])) {
        $result["html"] = "<pre>" . htmlentities($parts_by_type["text/plain"], ENT_QUOTES, "UTF-8") . "</pre>";
    } else {
        $result["html"] = "No readable content";
    }

    $result["html"] = stripMobileSignatures($result["html"]);
    return $result;
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
    ];
    foreach ($patterns as $pattern) {
        $html = preg_replace($pattern, '', $html);
    }
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
