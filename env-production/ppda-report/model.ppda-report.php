<?php
use Symfony\Component\DependencyInjection\Container;

class PPDAReportMenuExtension extends AbstractPortalUIExtension
{
	public function GetNavigationMenuHTML(Container $oContainer)
	{
		$sUrl = utils::GetAbsoluteUrlAppRoot().'ppdahelpdesk/report.php';

		return <<<HTML
<ul class="nav navbar-nav">
	<li class="brick_menu_item">
		<a href="{$sUrl}">
			<span class="brick_icon fas fa-chart-bar fa-2x"></span>
			Report
		</a>
	</li>
</ul>
HTML;
	}
}