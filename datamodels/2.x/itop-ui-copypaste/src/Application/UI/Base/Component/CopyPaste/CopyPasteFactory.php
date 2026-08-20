<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SAS
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Application\UI\Base\Component\CopyPaste;

use Combodo\iTop\Application\UI\Base\AbstractUIBlockFactory;
use Combodo\iTop\Application\UI\Base\Component\Form\Form;

/**
 * Class CopyPasteFactory
 *
 * @author Odain
 * @package Combodo\iTop\Application\UI\Base\Component\CopyPaste
 */
class CopyPasteFactory extends AbstractUIBlockFactory
{
	/** @inheritDoc */
	public const TWIG_TAG_NAME = 'UICopyPaste';
	/** @inheritDoc */
	public const UI_BLOCK_CLASS_NAME = CopyPaste::class;

	/**
	 * @param string $sCopyPasteElementId
	 * @param string $sId
	 *
	 * @return \Combodo\iTop\Application\UI\Base\Component\CopyPaste\CopyPaste
	 * @throws \CoreException
	 */
	public static function MakeStandard(string $sCopyPasteElementId, string $sId, ?string $sTooltip=null)
	{
		$CopyPaste = new CopyPaste($sCopyPasteElementId, $sId, $sTooltip);

		return $CopyPaste;
	}
}
