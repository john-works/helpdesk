<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Oauth2Client\Hook;

use AbstractApplicationUIExtension;
use Combodo\iTop\Application\UI\Base\Component\Field\FieldUIBlockFactory;
use Combodo\iTop\Application\UI\Base\Component\FieldSet\FieldSetUIBlockFactory;
use Combodo\iTop\Oauth2Client\Helper\Oauth2ClientHelper;
use Dict;
use Oauth2Client;
use utils;
use WebPage;

class ApplicationUIExtension extends AbstractApplicationUIExtension
{

	public function OnDisplayProperties($oObject, WebPage $oPage, $bEditMode = false)
	{
		if (!$bEditMode || ! ($oObject instanceof Oauth2Client)) {
			return;
		}
		$oFieldSet = FieldSetUIBlockFactory::MakeStandard(Dict::S('Oauth2Client:UI:IDPParameters'));

		$oField = FieldUIBlockFactory::MakeSmall(Dict::S('Oauth2Client:UI:AppURL'), utils::GetAbsoluteUrlAppRoot(), Dict::S('Oauth2Client:UI:AppURL+'));
		$oFieldSet->AddSubBlock($oField);

		$oField = FieldUIBlockFactory::MakeSmall(Dict::S('Oauth2Client:UI:LandingURL'), Oauth2ClientHelper::GetLandingURL(), Dict::S('Oauth2Client:UI:LandingURL+'));
		$oFieldSet->AddSubBlock($oField);

		$oPage->AddUiBlock($oFieldSet);
	}
}
