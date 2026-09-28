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

/** @var array $articles Array of recent article objects for AI context. */

$moduleclassSfx = htmlspecialchars((string) $params->get('moduleclass_sfx', ''), ENT_QUOTES, 'UTF-8');
?>
<div class="mod-bearsamppai<?php echo $moduleclassSfx ? ' ' . $moduleclassSfx : ''; ?>">
	<?php if ((int) $params->get('show_chat', 0) === 1) : ?>
		<?php require __DIR__ . '/chat.php'; ?>
	<?php endif; ?>
</div>