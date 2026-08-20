<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SAS
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Application\UI\Base\Component\CopyPaste;
use Combodo\iTop\Application\UI\Base\UIBlock;
use Combodo\iTop\UiCopyPaste\Helper\UiCopypasteHelper;

/**
 * Class CopyPaste
 *
 * @internal
 * @author Odain
 * @package Combodo\iTop\Application\UI\Base\Component\CopyPaste
 */
class CopyPaste extends UIBlock
{
	// Overloaded constants
	public const BLOCK_CODE = 'ibo-copy-paste';

	public const BASE = UiCopypasteHelper::MODULE_NAME . '/templates/base/components/copy-paste/layout';
	public const DEFAULT_HTML_TEMPLATE_REL_PATH = self::BASE;
	public const DEFAULT_JS_TEMPLATE_REL_PATH = self::BASE;
	public const DEFAULT_JS_ON_READY_TEMPLATE_REL_PATH = self::BASE;

	/** @var null|string Text to display as a tooltip */
	protected $sTooltip;

	/** @var string Html element ID to copypaste */
	protected $sCopyPasteElementId;

	/**
	 * CopyPaste constructor.
	 *
	 * @param string $sCopyPasteElementId
	 * @param string $sId
	 * @param null|string $sTooltip
	 */
	public function __construct(string $sCopyPasteElementId, string $sId, ?string $sTooltip=null)
	{
		parent::__construct($sId);

		$this->sCopyPasteElementId = $sCopyPasteElementId;
		if (! is_null($sTooltip)){
			$this->sTooltip = $sTooltip;
		}
	}

	/**
	 * @see static::$sCopyPasteElementId
	 * @return string
	 */
	public function GetCopyPasteElementId(): string
	{
		return $this->sCopyPasteElementId;
	}

	/**
	 * @see static::$sCopyPasteElementId
	 *
	 * @param string $sCopyPasteElementId
	 *
	 * @return $this
	 */
	public function SetCopyPasteElementId(string $sCopyPasteElementId)
	{
		$this->sCopyPasteElementId = $sCopyPasteElementId;

		return $this;
	}

	/**
	 * @see static::$sTooltip
	 * @return string|null
	 */
	public function GetTooltip(): ?string
	{
		return $this->sTooltip;
	}

	/**
	 * @see static::$sTooltip
	 *
	 * @param string|null $sTooltip
	 *
	 * @return $this
	 */
	public function SetTooltip(?string $sTooltip)
	{
		$this->sTooltip = $sTooltip;

		return $this;
	}

	/**
	 * @see static::$sTooltip
	 * @return bool
	 */
	public function HasTooltip(): bool
	{
		return !empty($this->sTooltip);
	}
}
