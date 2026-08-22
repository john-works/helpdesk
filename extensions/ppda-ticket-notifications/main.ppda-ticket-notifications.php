<?php

/**
 * Styled HTML notifications for UserRequest lifecycle events:
 *  - Ticket Assigned  -> sent to the assigned agent
 *  - Ticket Resolved  -> sent to the caller (with Agent Response box)
 */
class PPDATicketNotifications implements iApplicationObjectExtension
{
	const SMTP_HOST = 'mail.ppda.go.ug';
	const SMTP_PORT = 465;
	const SMTP_SSL  = true;
	const MAIL_FROM = 'helpdesk@ppda.go.ug';
	const MAIL_USER = 'helpdesk@ppda.go.ug';
	const MAIL_PASS = '23r0@2Q2$';
	const FROM_LABEL = 'PPDA IT Help Desk';
	const LOG_FILE = '/tmp/ppda-notifications.log';

	public function OnDBInsert($oObject, $oChange = null, $sOriginIssue = null)
	{
	}

	public function OnIsModified($oObject)
	{
		return true;
	}

	public function OnCheckToWrite($oObject)
	{
		return array();
	}

	public function OnCheckToDelete($oObject)
	{
		return array();
	}

	public function OnDBUpdate($oObject, $oChange = null, $sOriginIssue = null)
	{
		try {
			if (!($oObject instanceof UserRequest) && !is_subclass_of($oObject, 'UserRequest')) {
				return;
			}

			$sStatus = (string)$oObject->Get('status');
			$sPrevStatus = (string)$oObject->GetOriginal('status');
			if (MetaModel::IsValidAttCode(get_class($oObject), 'agent_id')) {
				$sAgentAtt = 'agent_id';
			} elseif (MetaModel::IsValidAttCode(get_class($oObject), 'assigned_to_id')) {
				$sAgentAtt = 'assigned_to_id';
			} else {
				return;
			}
			$iAgent = (int)$oObject->Get($sAgentAtt);
			$iPrevAgent = (int)$oObject->GetOriginal($sAgentAtt);

			if ($sStatus === 'resolved' && $sPrevStatus !== 'resolved') {
				$this->SendResolved($oObject);
			} elseif ($iAgent > 0 && $iAgent !== $iPrevAgent && $sStatus !== 'resolved' && $sStatus !== 'closed') {
				$this->SendAssigned($oObject, $iAgent);
			}
		} catch (\Exception $e) {
			$this->Log('ERROR: ' . $e->getMessage());
		}
	}

	public function OnDBDelete($oObject, $oChange = null, $sOriginIssue = null)
	{
	}

	protected function SendAssigned($oTicket, $iAgentId)
	{
		$oAgent = MetaModel::GetObject('Person', $iAgentId, false);
		if (!$oAgent) {
			return;
		}
		$sTo = (string)$oAgent->Get('email');
		if ($sTo === '') {
			return;
		}

		$sRef = $oTicket->Get('ref');
		$sTitle = $oTicket->Get('title');
		$sCaller = '';
		$iCallerId = (int)$oTicket->Get('caller_id');
		if ($iCallerId > 0) {
			$oCaller = MetaModel::GetObject('Person', $iCallerId, false);
			if ($oCaller) {
				$sCaller = $oCaller->GetName();
			}
		}
		$sService = '';
		$iServiceId = (int)$oTicket->Get('service_id');
		if ($iServiceId > 0) {
			$oService = MetaModel::GetObject('Service', $iServiceId, false);
			if ($oService) {
				$sService = $oService->GetName();
			}
		}
		$sPriority = $oTicket->Get('priority');
		$sStatus = ucfirst((string)$oTicket->Get('status'));

		$aFields = array(
			'Ticket Reference' => $sRef,
			'Title' => $sTitle,
			'Assigned To' => $oAgent->GetName(),
			'Caller' => $sCaller,
			'Service' => $sService,
			'Priority' => $sPriority,
			'Status' => $sStatus,
		);

		$sHtml = $this->BuildCard(
			'PPDA Help Desk — Ticket Assigned',
			$aFields,
			null
		);
		$this->SendMail($sTo, "[$sRef] Ticket Assigned - $sTitle", $sHtml);
		$this->Log("ASSIGNED $sRef -> agent email $sTo");
	}

	protected function SendResolved($oTicket)
	{
		$iCallerId = (int)$oTicket->Get('caller_id');
		if ($iCallerId <= 0) {
			return;
		}
		$oCaller = MetaModel::GetObject('Person', $iCallerId, false);
		if (!$oCaller) {
			return;
		}
		$sTo = (string)$oCaller->Get('email');
		if ($sTo === '') {
			return;
		}

		$sRef = $oTicket->Get('ref');
		$sTitle = $oTicket->Get('title');

		$sResponse = '';
		try {
			$oLog = $oTicket->Get('resolution');
			if ($oLog instanceof ormCaseLog) {
				$sResponse = trim(strip_tags($oLog->GetText()));
			}
			if ($sResponse === '') {
				$oPub = $oTicket->Get('public_log');
				if ($oPub instanceof ormCaseLog) {
					$sResponse = trim(strip_tags($oPub->GetText()));
				}
			}
		} catch (\Exception $e) {
			$sResponse = '';
		}
		if ($sResponse === '') {
			$sResponse = 'Your request has been resolved.';
		}

		$aFields = array(
			'Ticket Reference' => $sRef,
			'Title' => $sTitle,
			'Status' => 'Resolved',
		);

		$sHtml = $this->BuildCard(
			'PPDA Help Desk — Ticket Resolved',
			$aFields,
			$sResponse
		);
		$this->SendMail($sTo, "[$sRef] Ticket Resolved - $sTitle", $sHtml);
		$this->Log("RESOLVED $sRef -> caller email $sTo");
	}

	protected function BuildCard($sHeader, $aFields, $sAgentResponse)
	{
		$rows = '';
		foreach ($aFields as $sLabel => $sValue) {
			$bBold = in_array($sLabel, array('Ticket Reference', 'Title'));
			$sStyle = $bBold ? 'font-weight:bold;color:#222222;' : 'color:#222222;';
			$rows .= '<tr>'
				. '<td style="padding:6px 10px;color:#555555;font-size:14px;width:160px;vertical-align:top;">' . htmlspecialchars($sLabel, ENT_QUOTES, 'UTF-8') . '</td>'
				. '<td style="padding:6px 10px;font-size:14px;' . $sStyle . '">' . htmlspecialchars((string)$sValue, ENT_QUOTES, 'UTF-8') . '</td>'
				. '</tr>';
		}

		$sResponseBox = '';
		if ($sAgentResponse !== null) {
			$sResponseBox = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:18px;">'
				. '<tr><td style="background-color:#f8f9fa;border-left:4px solid #1a3c6e;padding:12px 16px;border-radius:4px;">'
				. '<div style="color:#555555;font-size:13px;font-weight:bold;margin-bottom:6px;">Agent Response</div>'
				. '<div style="color:#222222;font-size:14px;line-height:1.6;white-space:pre-wrap;">'
				. htmlspecialchars($sAgentResponse, ENT_QUOTES, 'UTF-8')
				. '</div>'
				. '</td></tr></table>';
		}

		return '<!DOCTYPE html><html><body style="margin:0;padding:0;background-color:#f4f6f8;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f8;padding:24px 0;">'
			. '<tr><td align="center">'
			. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px;background-color:#ffffff;border-radius:8px;overflow:hidden;font-family:Arial,sans-serif;font-size:14px;line-height:1.6;color:#222222;">'
			. '<tr><td style="background-color:#1a3c6e;color:#ffffff;font-size:16px;font-weight:bold;padding:14px 20px;">'
			. htmlspecialchars($sHeader, ENT_QUOTES, 'UTF-8')
			. '</td></tr>'
			. '<tr><td style="padding:20px;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $rows . '</table>'
			. $sResponseBox
			. '<p style="margin:22px 0 0 0;color:#222222;">Best regards,<br/>PPDA IT Help Desk</p>'
			. '</td></tr>'
			. '<tr><td style="background-color:#eef1f5;padding:10px 20px;text-align:center;color:#888888;font-size:12px;">'
			. 'Powered by PPDA ICT Team'
			. '</td></tr>'
			. '</table>'
			. '</td></tr></table>'
			. '</body></html>';
	}

	protected function SendMail($sTo, $sSubject, $sHtml)
	{
		require_once __DIR__ . '/../email-to-ticket/class.smtpclient.php';
		$oSmtp = new SMTPClient();
		$oSmtp->connect(self::SMTP_HOST, self::SMTP_PORT, self::SMTP_SSL);
		$oSmtp->login(self::MAIL_USER, self::MAIL_PASS);
		$oSmtp->send(self::MAIL_FROM, self::FROM_LABEL, $sTo, '', $sSubject, $sHtml);
	}

	protected function Log($sMsg)
	{
		@file_put_contents(self::LOG_FILE, date('Y-m-d H:i:s') . ' ' . $sMsg . "\n", FILE_APPEND | LOCK_EX);
	}
}
