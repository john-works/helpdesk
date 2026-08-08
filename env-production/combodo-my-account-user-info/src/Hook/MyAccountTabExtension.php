<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\MyAccount\UserInfo\Hook;

use Combodo\iTop\MyAccount\Hook\iMyAccountTabExtension;

class MyAccountTabExtension implements iMyAccountTabExtension
{
	const USER_INFO_TAB_TITLE = 'MyAccount:UserInfo:Tab:Title';


	/**
	 * @inheritDoc
	 */
	public function IsTabPresent(): bool
	{
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function GetTabRank(): float
	{
		return 0;
	}

	/**
	 * @inheritDoc
	 */
	public function GetTabCode(): string
	{
		return self::USER_INFO_TAB_TITLE;
	}

	/**
	 * @inheritDoc
	 */
	public function GetTabIsCached(): bool
	{
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function GetTabLabel(): string
	{
		return \Dict::S(self::USER_INFO_TAB_TITLE);
	}
}