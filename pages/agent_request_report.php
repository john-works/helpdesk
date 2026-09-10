<?php
/*
 * Agent Request Report
 * Displays UserRequest records filtered by agent and date range.
 */

require_once('../approot.inc.php');
require_once(APPROOT.'/application/application.inc.php');
require_once(APPROOT.'/application/startup.inc.php');
require_once(APPROOT.'/application/loginwebpage.class.inc.php');

LoginWebPage::DoLogin();

$oP = new iTopWebPage('Agent Request Report');

$iAgentId = utils::ReadParam('agent_id', '', false, 'raw_data');
$sStartDate = utils::ReadParam('start_date', '', false, 'raw_data');
$sEndDate = utils::ReadParam('end_date', '', false, 'raw_data');

// Build agent list - only agents who belong to a team
$oAgentFilter = DBObjectSearch::FromOQL('SELECT Person AS p JOIN lnkPersonToTeam AS l ON l.person_id = p.id');
$oAgentSet = new CMDBObjectSet($oAgentFilter, array('p.name' => true));
$aAgents = array();
while ($oAgent = $oAgentSet->fetch()) {
	$aAgents[$oAgent->GetKey()] = $oAgent->GetName();
}

// Render form
$sForm = '<form method="get" style="margin:20px;">
<input type="hidden" name="c[menu]" value="AgentReportMenu" />
<table class="ibo-field-list" style="border-collapse:collapse;">
<tr>
  <td style="padding:8px;font-weight:bold;">Agent:</td>
  <td style="padding:8px;">
    <select name="agent_id" style="padding:6px;width:300px;">
      <option value="">-- Select Agent --</option>';
foreach ($aAgents as $iId => $sName) {
	$sSelected = ($iAgentId == $iId) ? ' selected' : '';
	$sForm .= '<option value="'.htmlspecialchars($iId).'"'.$sSelected.'>'.htmlspecialchars($sName).'</option>';
}
$sForm .= '</select>
  </td>
</tr>
<tr>
  <td style="padding:8px;font-weight:bold;">Start Date:</td>
  <td style="padding:8px;"><input type="date" name="start_date" value="'.htmlspecialchars($sStartDate).'" style="padding:6px;width:200px;" /></td>
</tr>
<tr>
  <td style="padding:8px;font-weight:bold;">End Date:</td>
  <td style="padding:8px;"><input type="date" name="end_date" value="'.htmlspecialchars($sEndDate).'" style="padding:6px;width:200px;" /></td>
</tr>
<tr>
  <td></td>
  <td style="padding:8px;"><input type="submit" value="Run Report" style="padding:8px 20px;background:#1a73e8;color:#fff;border:none;cursor:pointer;border-radius:4px;" /></td>
</tr>
</table>
</form>';

$oP->add($sForm);

// Execute query if parameters provided
if (!empty($iAgentId) && !empty($sStartDate) && !empty($sEndDate)) {
	$sOql = "SELECT UserRequest WHERE agent_id = '$iAgentId' AND start_date >= '$sStartDate' AND start_date <= '$sEndDate'";

	$oFilter = DBObjectSearch::FromOQL($sOql);
	$oResultBlock = new DisplayBlock($oFilter, 'list', false);
	$oP->AddSubBlock($oResultBlock->GetDisplay($oP, 'agent_request_report'));
}

$oP->output();
