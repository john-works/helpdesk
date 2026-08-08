<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\ItopAttributeClassSet;

use Combodo\iTop\ItopAttributeClassSet\Controller\ItopAttributeClassSetController;
use Combodo\iTop\ItopAttributeClassSet\Helper\ItopAttributeClassSetHelper;
use Combodo\iTop\ItopAttributeClassSet\Helper\ItopAttributeClassSetLog;

require_once(APPROOT.'application/startup.inc.php');

ItopAttributeClassSetLog::Enable();

$oController = new ItopAttributeClassSetController(MODULESROOT.ItopAttributeClassSetHelper::MODULE_NAME.'/templates', ItopAttributeClassSetHelper::MODULE_NAME);
$oController->SetDefaultOperation('Default');
$oController->HandleOperation();