<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\ItopAttributeClassSet\Helper;

use LogAPI;

class ItopAttributeClassSetLog extends LogAPI
{
	const CHANNEL_DEFAULT = 'ItopAttributeClassSet';

	protected static $m_oFileLog = null;

	public static function Enable($sTargetFile = null)
	{
		if (empty($sTargetFile)) {
			$sTargetFile = APPROOT.'log/error.log';
		}
		parent::Enable($sTargetFile);
	}
}