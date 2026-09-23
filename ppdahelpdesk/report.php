<?php
use Combodo\iTop\Application\UI\Base\Component\Html\Html;
use Combodo\iTop\Application\UI\Base\Component\Panel\PanelUIBlockFactory;
use Combodo\iTop\Application\WebPage\iTopWebPage;

require_once('../approot.inc.php');
require_once(APPROOT.'/application/application.inc.php');
require_once(APPROOT.'/application/startup.inc.php');
require_once(APPROOT.'/application/loginwebpage.class.inc.php');

LoginWebPage::DoLogin();

$sDateStart = utils::ReadParam('date_start', '', true, 'raw_data');
$sDateEnd = utils::ReadParam('date_end', '', true, 'raw_data');
$sAgentId = utils::ReadParam('agent_id', '', true, 'raw_data');
$sStatus = utils::ReadParam('status', 'closed', true, 'raw_data');

if (empty($sDateStart)) {
	$sDateStart = date('Y-m-d', strtotime('-30 days'));
}
if (empty($sDateEnd)) {
	$sDateEnd = date('Y-m-d');
}

$sFormat = utils::ReadParam('format', '', true, 'raw_data');

if ($sFormat === 'xlsx') {
	require_once(APPROOT.'application/xlsxwriter.class.php');

	$sQuery = "SELECT UserRequest WHERE start_date >= :date_start AND start_date < DATE_ADD(:date_end, INTERVAL 1 DAY)";
	$aParams = array(
		'date_start' => $sDateStart,
		'date_end'   => $sDateEnd,
	);
	if ($sAgentId !== '') {
		$sQuery .= ' AND agent_id = :agent_id';
		$aParams['agent_id'] = (int)$sAgentId;
	}
	if ($sStatus !== '') {
		$sQuery .= ' AND status = :status';
		$aParams['status'] = $sStatus;
	}

	$oFilter = DBObjectSearch::FromOQL($sQuery);
	$oFilter->SetInternalParams($aParams);
	$oSet = new DBObjectSet($oFilter);
	$oSet->OptimizeColumnLoad(array('ref', 'title', 'org_id', 'caller_id', 'team_id', 'agent_id', 'start_date', 'status', 'priority', 'origin'));

	$aHeaders = array('Ref', 'Title', 'Org', 'Caller', 'Team', 'Agent', 'Start Date', 'Status', 'Priority', 'Origin');
	$aData = array();
	while ($oReq = $oSet->Fetch()) {
		$aData[] = array(
			$oReq->Get('ref'),
			$oReq->Get('title'),
			$oReq->Get('org_name'),
			$oReq->Get('caller_name'),
			$oReq->Get('team_name'),
			$oReq->Get('agent_name'),
			date('Y-m-d H:i', strtotime($oReq->Get('start_date'))),
			Dict::S('Class:UserRequest/Attribute:status/Value:'.$oReq->Get('status')),
			Dict::S('Class:UserRequest/Attribute:priority/Value:'.$oReq->Get('priority')),
			Dict::S('Class:UserRequest/Attribute:origin/Value:'.$oReq->Get('origin')),
		);
	}

	$sTmpFile = tempnam(sys_get_temp_dir(), 'ppda_report_');
	$oWriter = new XLSXWriter();
	$oWriter->setAuthor(UserRights::GetUserFriendlyName());
	$oWriter->writeSheet($aData, 'User Requests', array_fill(0, count($aHeaders), 'string'), $aHeaders);
	$oWriter->writeToFile($sTmpFile);

	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment; filename="user-requests-'.$sDateStart.'-to-'.$sDateEnd.'.xlsx"');
	header('Content-Length: '.filesize($sTmpFile));
	readfile($sTmpFile);
	unlink($sTmpFile);
	exit;
}

$oP = new iTopWebPage('User Request Report');
$oP->SetBreadCrumbEntry('ppda-report', 'User Request Report', 'User Request Report', '', 'fas fa-chart-bar', iTopWebPage::ENUM_BREADCRUMB_ENTRY_ICON_TYPE_CSS_CLASSES);

//---- Agents: only persons belonging to a team ----
$oAgents = new DBObjectSet(DBObjectSearch::FromOQL(
	"SELECT Person AS p JOIN lnkPersonToTeam AS l ON l.person_id = p.id"
));
$oAgents->OptimizeColumnLoad(array('first_name', 'name'));
$sAgentOptions = '<option value="">-- All agents --</option>';
while ($oAgent = $oAgents->Fetch()) {
	$sLabel = trim($oAgent->Get('first_name').' '.$oAgent->Get('name'));
	$sSelected = ($sAgentId !== '' && (int)$sAgentId === (int)$oAgent->GetKey()) ? ' selected' : '';
	$sAgentOptions .= '<option value="'.$oAgent->GetKey().'"'.$sSelected.'>'.utils::EscapeHtml($sLabel).'</option>';
}

$oPanel = PanelUIBlockFactory::MakeWithBrandingPrimaryColor('Report filters');
$oP->AddUiBlock($oPanel);

$aStatuses = array('new', 'waiting_for_approval', 'approved', 'rejected', 'assigned', 'pending', 'escalated_tto', 'escalated_ttr', 'resolved', 'closed');
$sStatusOptions = '<option value="">-- All statuses --</option>';
foreach ($aStatuses as $sStatusValue) {
	$sSelected = ($sStatus === $sStatusValue) ? ' selected' : '';
	$sStatusOptions .= '<option value="'.$sStatusValue.'"'.$sSelected.'>'.utils::EscapeHtml(Dict::S('Class:UserRequest/Attribute:status/Value:'.$sStatusValue)).'</option>';
}

$sForm = <<<HTML
<form method="post" action="report.php" class="ibo-report-filters">
	<label>From: <input type="date" name="date_start" value="$sDateStart" required /></label>
	<label>To: <input type="date" name="date_end" value="$sDateEnd" required /></label>
	<label>Status:
		<select name="status">$sStatusOptions</select>
	</label>
	<label>Agent:
		<select name="agent_id">$sAgentOptions</select>
	</label>
	<button type="submit" class="btn btn-primary">Run report</button>
	<button type="submit" name="format" value="xlsx" class="btn btn-default">Download Excel</button>
</form>
HTML;
$oPanel->AddSubBlock(new Html($sForm));

try {
	$sQuery = "SELECT UserRequest WHERE start_date >= :date_start AND start_date < DATE_ADD(:date_end, INTERVAL 1 DAY)";
	$aParams = array(
		'date_start' => $sDateStart,
		'date_end'   => $sDateEnd,
	);
	if ($sAgentId !== '') {
		$sQuery .= ' AND agent_id = :agent_id';
		$aParams['agent_id'] = (int)$sAgentId;
	}
	if ($sStatus !== '') {
		$sQuery .= ' AND status = :status';
		$aParams['status'] = $sStatus;
	}

	$oFilter = DBObjectSearch::FromOQL($sQuery);
	$oFilter->SetInternalParams($aParams);

	$oSet = new DBObjectSet($oFilter);
	$iCount = $oSet->Count();

	$oResultsPanel = PanelUIBlockFactory::MakeWithBrandingSecondaryColor('Results');
	$oP->AddUiBlock($oResultsPanel);

	$sDescription = "<strong>$iCount</strong> user request(s) from <strong>$sDateStart</strong> to <strong>$sDateEnd</strong>";
	if ($sStatus !== '') {
		$sDescription .= ' - Status: <strong>'.utils::EscapeHtml(Dict::S('Class:UserRequest/Attribute:status/Value:'.$sStatus)).'</strong>';
	}
	if ($sAgentId !== '') {
		$oAgentSel = MetaModel::GetObject('Person', (int)$sAgentId, false);
		if ($oAgentSel) {
			$sDescription .= ' - Agent: <strong>'.utils::EscapeHtml(trim($oAgentSel->Get('first_name').' '.$oAgentSel->Get('name'))).'</strong>';
		}
	}
	$oResultsPanel->AddSubBlock(new Html("<p>$sDescription</p>"));

	$oBlock = new DisplayBlock($oFilter, 'list', false);
	$oResultsPanel->AddSubBlock($oBlock->GetDisplay($oP, 'ppda_report'));
} catch (\Throwable $e) {
	$oP->add('Error: '.$e->getMessage());
}

$oP->output();