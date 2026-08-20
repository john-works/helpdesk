<?php

namespace Combodo\iTop\MyAccount\Controller;

use Combodo\iTop\Application\TwigBase\Controller\Controller;
use Combodo\iTop\Application\UI\Base\Component\Panel\Panel;
use Combodo\iTop\Application\UI\Base\Layout\Object\ObjectDetails;
use Combodo\iTop\Application\UI\Base\Layout\TabContainer\TabContainer;
use Combodo\iTop\Application\UI\Base\UIBlock;
use Combodo\iTop\MyAccount\Helper\MyAccountHelper;
use Combodo\iTop\MyAccount\Hook\iMyAccountTabContentExtension;
use Combodo\iTop\MyAccount\Hook\iMyAccountTabExtension;
use Combodo\iTop\Service\InterfaceDiscovery\InterfaceDiscovery;
use utils;
use function Combodo\iTop\Service\InterfaceDiscovery\InterfaceDiscovery;
use const ITOP_DESIGN_LATEST_VERSION;

/**
 * PersonalToken objects are protected and writable only as Administrator
 * Lot of operations are done here as a workaround to bypass the rights to let user handle his own tokens only from MyAccount menu:
 * creation / modification / refresh / deletion
 */
class MyAccountController extends Controller
{
	public const ROUTE_NAMESPACE = MyAccountHelper::ROUTE_NAMESPACE;

	public function OperationMainPage()
	{
		$aTabs = [];

		// Display the tabs
		foreach (InterfaceDiscovery::GetInstance()->FindItopClasses(iMyAccountTabExtension::class) as $sExtensionClass) {
			/** @var \Combodo\iTop\MyAccount\Hook\iMyAccountTabExtension $oExtension */
			$oExtension = new $sExtensionClass();
			if ($oExtension->IsTabPresent()) {
				$aTabs[] = [
					'code' => $oExtension->GetTabCode(),
					'url' => utils::GetAbsoluteUrlModulePage(MyAccountHelper::MODULE_NAME, 'index.php', ['operation' => 'MyAccountTab', 'tab' => $oExtension->GetTabCode()]),
					'is_cached' => $oExtension->GetTabIsCached(),
					'label' => $oExtension->GetTabLabel(),
					'rank' => $oExtension->GetTabRank(),
				];
			}
		}

		usort($aTabs, function($a, $b) {
			$fRankA = $a['rank'];
			$fRankB = $b['rank'];
			if ($fRankA == $fRankB) {
				return 0;
			}
			return ($fRankA < $fRankB) ? -1 : 1;
		});

		foreach ($aTabs as $aTab) {
			$this->AddAjaxTab($aTab['code'], $aTab['url'], $aTab['is_cached'], $aTab['label']);
		}

		//adding all below js. some in order to avoid a js console error. which is not functional even when displaying token forms
		if (version_compare(ITOP_DESIGN_LATEST_VERSION, '3.2', '<')) { // N°7251 iTop 3.2.0 deprecated lib
			$this->AddLinkedScript(utils::GetAbsoluteUrlAppRoot().'js/json.js');
		}
		$this->AddLinkedScript(utils::GetAbsoluteUrlAppRoot().'js/forms-json-utils.js');
		$this->AddLinkedScript(utils::GetAbsoluteUrlAppRoot().'js/wizardhelper.js');
		$this->AddLinkedScript(utils::GetAbsoluteUrlAppRoot().'js/wizard.utils.js');
		$this->AddLinkedScript(utils::GetAbsoluteUrlAppRoot().'js/jquery.blockUI.js');

		foreach (TabContainer::DEFAULT_JS_FILES_REL_PATH as $sJsFile) {
			$this->AddLinkedScript(utils::GetAbsoluteUrlAppRoot().$sJsFile);
		}

		$aJsFilesArrays = [
			UIBlock::DEFAULT_JS_FILES_REL_PATH,
			Panel::DEFAULT_JS_FILES_REL_PATH,
			TabContainer::DEFAULT_JS_FILES_REL_PATH,
			ObjectDetails::DEFAULT_JS_FILES_REL_PATH,
		];

		foreach ($aJsFilesArrays as $aJsFilesArray) {
			foreach ($aJsFilesArray as $sJsRelFile) {
				$this->AddLinkedScript(utils::GetAbsoluteUrlAppRoot().$sJsRelFile);
			}
		}

		$this->DisplayPage([]);
	}

	public function OperationMyAccountTab()
	{
		$aParams = [];
		$sTabCode = utils::ReadParam('tab', '', false, utils::ENUM_SANITIZATION_FILTER_STRING);

		$aSections = [];

		foreach (InterfaceDiscovery::GetInstance()->FindItopClasses(iMyAccountTabContentExtension::class) as $sExtensionClass) {
			/** @var \Combodo\iTop\MyAccount\Hook\iMyAccountTabContentExtension $oExtension */
			$oExtension = new $sExtensionClass();
			if ($sTabCode !== $oExtension->GetTabCode()) {
				continue;
			}
			$sName = $oExtension->GetTemplateName();
			$aSectionParams = call_user_func($oExtension->GetSectionCallback());
			$aSectionParams['sHtmlTwig'] = "$sName.html.twig";
			$aSectionParams['sReadyJsTwig'] = "$sName.ready.js.twig";
			$aSectionParams['rank'] = $oExtension->GetSectionRank();
			$aSections[] = $aSectionParams;
		}

		usort($aSections, function($a, $b) {
			$fRankA = $a['rank'];
			$fRankB = $b['rank'];
			if ($fRankA == $fRankB) {
				return 0;
			}
			return ($fRankA < $fRankB) ? -1 : 1;
		});

		$aParams['aSections'] = $aSections;

		$this->DisplayAjaxPage($aParams);
	}
}
