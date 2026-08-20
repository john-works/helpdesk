<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MyAccount\UserInfo\Hook;

use Combodo\iTop\MyAccount\Hook\iMyAccountTabContentExtension;
use Combodo\iTop\MyAccount\UserInfo\Service\UserInfoService;
use utils;

class MyAccountTabContentExtension implements iMyAccountTabContentExtension
{

	/**
	 * @inheritDoc
	 */
	public function GetTemplatePath(): string
	{
		return APPROOT.'env-'.utils::GetCurrentEnvironment().'/combodo-my-account-user-info/templates';
	}

	/**
	 * @inheritDoc
	 */
	public function GetTabCode(): string
	{
		return MyAccountTabExtension::USER_INFO_TAB_TITLE;
	}

	/**
	 * @inheritDoc
	 */
	public function GetTemplateName(): string
	{
		return 'UserInfo';
	}

	/**
	 * @inheritDoc
	 */
	public function GetSectionCallback(): callable
	{
		return [UserInfoService::GetInstance(), 'GetUserInfoParams'];
	}

	/**
	 * @inheritDoc
	 */
	public function GetSectionRank(): float
	{
		return 0;
	}

	public function IsActive(): bool
	{
		return true;
	}
}