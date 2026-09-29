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
use Joomla\CMS\Uri\Uri;

/** @var object $module The current module instance. */
/** @var \Joomla\CMS\Application\SiteApplication $app The site application. */
/** @var \Joomla\Registry\Registry $params The module parameters. */

if ((int) $params->get('show_chat', 0) !== 1) {
	return;
}

// Register chat assets only when the chat is enabled. The files ship inside the
// module folder, so they have to be addressed through it. Pointing at the shared
// /media tree resolves to a file that does not exist and the WebAssetManager then
// silently drops both assets, leaving the chat unstyled and inert.
$moduleBase = rtrim(Uri::root(), '/') . '/modules/' . $module->module;
$assetOpts  = [];
$manifest   = JPATH_ROOT . '/modules/' . $module->module . '/' . $module->module . '.xml';

if (is_readable($manifest)) {
	$manifestXml = simplexml_load_file($manifest);

	if ($manifestXml !== false && isset($manifestXml->version)) {
		$assetOpts['version'] = (string) $manifestXml->version;
	}
}

$wa = $app->getDocument()->getWebAssetManager();
$wa->registerAndUseStyle('mod_bearsamppai.chat', $moduleBase . '/media/css/chat.css', [], $assetOpts);
$wa->registerAndUseScript('mod_bearsamppai.chat', $moduleBase . '/media/js/chat.js', [], ['defer' => true]);

$heading     = trim((string) $params->get('chat_heading', 'Ask Bearsampp'));
$placeholder = trim((string) $params->get('chat_placeholder', 'Ask a question about Bearsampp...'));

if ($heading === '') {
	$heading = Text::_('MOD_BEARSAMPPAI_CHAT_DEFAULT_HEADING');
}

$position = (string) $params->get('chat_position', 'bottom-right');
$positions = ['bottom-right', 'bottom-left', 'middle-right', 'middle-left'];

if (!in_array($position, $positions, true)) {
	$position = 'bottom-right';
}

$width  = max(260, min(900, (int) $params->get('chat_width', 400)));
$height = max(300, min(1200, (int) $params->get('chat_height', 500)));

$offsetX = max(0, min(200, (int) $params->get('chat_offset_x', 20)));
$offsetY = max(0, min(200, (int) $params->get('chat_offset_y', 20)));

$theme = (string) $params->get('chat_theme', 'auto');

if (!in_array($theme, ['auto', 'light', 'dark'], true)) {
	$theme = 'auto';
}

// An explicit light/dark choice is emitted up front so the correct palette
// applies before (or without) the deferred script. 'auto' is left to the
// script, which resolves it from the prefers-color-scheme media query.
$schemeAttr = $theme === 'auto' ? '' : ' data-theme-scheme="' . $theme . '"';

$showCopy = (int) $params->get('chat_show_copy', 1) === 1;

$greeting = trim((string) $params->get('chat_greeting', ''));

$showStatus      = (int) $params->get('chat_show_status', 0) === 1;
$statusInterval  = max(10, min(600, (int) $params->get('chat_status_interval', 30)));
$statusOnline    = Text::_('MOD_BEARSAMPPAI_CHAT_STATUS_ONLINE');
$statusOffline   = Text::_('MOD_BEARSAMPPAI_CHAT_STATUS_OFFLINE');
$statusChecking  = Text::_('MOD_BEARSAMPPAI_CHAT_STATUS_CHECKING');
?>
<div
	class="mod-bearsamppai__chat mod-bearsamppai__chat--<?php echo $position; ?>"
	data-bearsamppai-chat
	data-theme="<?php echo $theme; ?>"<?php echo $schemeAttr; ?>
	style="--mbai-x: <?php echo $offsetX; ?>px; --mbai-y: <?php echo $offsetY; ?>px; --mbai-w: <?php echo $width; ?>px; --mbai-h: <?php echo $height; ?>px;"
	data-theme="<?php echo $theme; ?>"
	data-module-id="<?php echo (int) $module->id; ?>"
	data-endpoint="index.php?option=com_ajax&module=bearsamppai&method=ask&format=json">
	<button
		type="button"
		class="mod-bearsamppai__chat-toggle"
		aria-expanded="false"
		aria-controls="mod-bearsamppai-chat-panel-<?php echo (int) $module->id; ?>"
		data-bearsamppai-chat-toggle>
		<span class="mod-bearsamppai__chat-toggle-icon" aria-hidden="true"></span>
		<span class="mod-bearsamppai__chat-toggle-label"><?php echo htmlspecialchars($heading, ENT_QUOTES, 'UTF-8'); ?></span>
	</button>

	<div
		class="mod-bearsamppai__chat-panel"
		id="mod-bearsamppai-chat-panel-<?php echo (int) $module->id; ?>"
		hidden
		data-bearsamppai-chat-panel>
		<div class="mod-bearsamppai__chat-header">
			<span class="mod-bearsamppai__chat-heading">
				<?php if ($showStatus) : ?>
					<span
						class="mod-bearsamppai__chat-status"
						role="img"
						data-bearsamppai-chat-status
						data-status-interval="<?php echo $statusInterval; ?>"
						data-status-online="<?php echo htmlspecialchars($statusOnline, ENT_QUOTES, 'UTF-8'); ?>"
						data-status-offline="<?php echo htmlspecialchars($statusOffline, ENT_QUOTES, 'UTF-8'); ?>"
						data-status-checking="<?php echo htmlspecialchars($statusChecking, ENT_QUOTES, 'UTF-8'); ?>"
						title="<?php echo htmlspecialchars($statusChecking, ENT_QUOTES, 'UTF-8'); ?>"></span>
				<?php endif; ?>
				<span class="mod-bearsamppai__chat-title"><?php echo htmlspecialchars($heading, ENT_QUOTES, 'UTF-8'); ?></span>
			</span>
			<div class="mod-bearsamppai__chat-tools">
				<?php if ($showCopy) : ?>
					<button
						type="button"
						class="mod-bearsamppai__chat-tool"
						aria-label="<?php echo htmlspecialchars(Text::_('MOD_BEARSAMPPAI_CHAT_COPY'), ENT_QUOTES, 'UTF-8'); ?>"
						title="<?php echo htmlspecialchars(Text::_('MOD_BEARSAMPPAI_CHAT_COPY'), ENT_QUOTES, 'UTF-8'); ?>"
						data-bearsamppai-chat-copy>
						<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
					</button>
				<?php endif; ?>
				<button
					type="button"
					class="mod-bearsamppai__chat-tool"
					aria-label="<?php echo htmlspecialchars(Text::_('MOD_BEARSAMPPAI_CHAT_THEME_TOGGLE'), ENT_QUOTES, 'UTF-8'); ?>"
					title="<?php echo htmlspecialchars(Text::_('MOD_BEARSAMPPAI_CHAT_THEME_TOGGLE'), ENT_QUOTES, 'UTF-8'); ?>"
					aria-pressed="false"
					data-bearsamppai-chat-theme>
					<svg class="mod-bearsamppai__icon-sun" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
					<svg class="mod-bearsamppai__icon-moon" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
				</button>
				<button
					type="button"
					class="mod-bearsamppai__chat-tool"
					aria-label="<?php echo htmlspecialchars(Text::_('MOD_BEARSAMPPAI_CHAT_CLOSE'), ENT_QUOTES, 'UTF-8'); ?>"
					data-bearsamppai-chat-close>
					<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
				</button>
			</div>
		</div>

		<div
			class="mod-bearsamppai__chat-messages"
			role="log"
			aria-live="polite"
			data-bearsamppai-chat-messages>
			<?php if ($greeting !== '') : ?>
				<div class="mod-bearsamppai__msg mod-bearsamppai__msg--assistant" data-greeting="1">
					<div class="mod-bearsamppai__msg-bubble"><?php echo htmlspecialchars($greeting, ENT_QUOTES, 'UTF-8'); ?></div>
				</div>
			<?php endif; ?>
		</div>

		<div
			class="mod-bearsamppai__chat-announce"
			role="status"
			aria-live="polite"
			data-bearsamppai-chat-announce></div>

		<form class="mod-bearsamppai__chat-form" data-bearsamppai-chat-form>
			<label
				class="visually-hidden"
				for="mod-bearsamppai-chat-input-<?php echo (int) $module->id; ?>">
				<?php echo htmlspecialchars(Text::_('MOD_BEARSAMPPAI_CHAT_INPUT_LABEL'), ENT_QUOTES, 'UTF-8'); ?>
			</label>
			<input
				type="text"
				id="mod-bearsamppai-chat-input-<?php echo (int) $module->id; ?>"
				class="mod-bearsamppai__chat-input"
				placeholder="<?php echo htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8'); ?>"
				autocomplete="off"
				data-bearsamppai-chat-input>
			<button type="submit" class="mod-bearsamppai__chat-send btn btn-primary" data-bearsamppai-chat-send>
				<?php echo htmlspecialchars(Text::_('MOD_BEARSAMPPAI_CHAT_SEND'), ENT_QUOTES, 'UTF-8'); ?>
			</button>
		</form>
	</div>
</div>