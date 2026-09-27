<?php

/**
 * @package     Bearsampp.Module
 * @subpackage  mod_bearsamppai
 *
 * @author      Bearsampp
 * @copyright   (C) 2026 Bearsampp
 * @license     GNU General Public License version 3; see LICENSE
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/** @var array $articles Array of recent article objects. */
?>
<ul class="mod-bearsamppai__list mod-list">
	<?php foreach ($articles as $item) : ?>
		<li itemscope itemtype="https://schema.org/Article">
			<a href="<?php echo $item->link; ?>" itemprop="url">
				<span itemprop="name">
					<?php echo htmlspecialchars($item->title, ENT_QUOTES, 'UTF-8'); ?>
				</span>
			</a>
			<span class="mod-bearsamppai__meta">
				<?php echo HTMLHelper::_('date', $item->publish_up, Text::_('DATE_FORMAT_LC3')); ?>
			</span>
		</li>
	<?php endforeach; ?>
</ul>