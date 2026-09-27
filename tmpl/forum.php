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

/** @var array $forumTopics Array of recent Kunena topic objects. */
?>
<ul class="mod-bearsamppai__list mod-bearsamppai__forum-list mod-list">
	<?php foreach ($forumTopics as $topic) : ?>
		<li>
			<a href="<?php echo $topic->topicLink; ?>">
				<?php echo $topic->subject; ?>
			</a>
			<span class="mod-bearsamppai__meta">
				<a href="<?php echo $topic->categoryLink; ?>">
					<?php echo htmlspecialchars($topic->category_title, ENT_QUOTES, 'UTF-8'); ?>
				</a>
				&middot;
				<?php echo HTMLHelper::_('date', $topic->last_post_time, Text::_('DATE_FORMAT_LC3')); ?>
				&middot;
				<?php echo Text::sprintf('MOD_BEARSAMPPAI_REPLIES', (int) $topic->replies); ?>
			</span>
		</li>
	<?php endforeach; ?>
</ul>