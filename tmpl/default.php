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

use Joomla\CMS\Language\Text;

/** @var array $articles Array of recent article objects. */
/** @var array $forumTopics Array of recent Kunena topic objects. */
/** @var array $faqItems Array of FAQ article objects. */

$moduleclassSfx = htmlspecialchars((string) $params->get('moduleclass_sfx', ''), ENT_QUOTES, 'UTF-8');
?>
<div class="mod-bearsamppai<?php echo $moduleclassSfx ? ' ' . $moduleclassSfx : ''; ?>">
	<?php if (isset($articles) && is_array($articles) && $articles) : ?>
		<section class="mod-bearsamppai__block mod-bearsamppai__articles">
			<h3><?php echo Text::_('MOD_BEARSAMPPAI_ARTICLES_TITLE'); ?></h3>
			<?php require __DIR__ . '/articles.php'; ?>
		</section>
	<?php endif; ?>

	<?php if (isset($forumTopics) && is_array($forumTopics) && $forumTopics) : ?>
		<section class="mod-bearsamppai__block mod-bearsamppai__forum">
			<h3><?php echo Text::_('MOD_BEARSAMPPAI_FORUM_TITLE'); ?></h3>
			<?php require __DIR__ . '/forum.php'; ?>
		</section>
	<?php endif; ?>

	<?php if (isset($faqItems) && is_array($faqItems) && $faqItems) : ?>
		<section class="mod-bearsamppai__block mod-bearsamppai__faq">
			<h3><?php echo Text::_('MOD_BEARSAMPPAI_FAQ_TITLE'); ?></h3>
			<?php require __DIR__ . '/faq.php'; ?>
		</section>
	<?php endif; ?>

	<?php if ((int) $params->get('show_chat', 0) === 1) : ?>
		<section class="mod-bearsamppai__block mod-bearsamppai__chat-section">
			<?php require __DIR__ . '/chat.php'; ?>
		</section>
	<?php endif; ?>
</div>