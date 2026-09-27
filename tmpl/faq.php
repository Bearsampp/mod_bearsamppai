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

/** @var array $faqItems Array of FAQ article objects. */
?>
<div class="accordion mod-bearsamppai__faq-accordion" id="mod-bearsamppai-faq">
	<?php foreach ($faqItems as $i => $item) : ?>
		<div class="accordion-item" id="faq-<?php echo (int) $item->id; ?>">
			<h4 class="accordion-header" id="mod-bearsamppai-faq-head-<?php echo (int) $item->id; ?>">
				<button
					class="accordion-button<?php echo $i > 0 ? ' collapsed' : ''; ?>"
					type="button"
					data-bs-toggle="collapse"
					data-bs-target="#mod-bearsamppai-faq-body-<?php echo (int) $item->id; ?>"
					aria-expanded="<?php echo $i === 0 ? 'true' : 'false'; ?>"
					aria-controls="mod-bearsamppai-faq-body-<?php echo (int) $item->id; ?>"
				>
					<?php echo htmlspecialchars($item->title, ENT_QUOTES, 'UTF-8'); ?>
				</button>
			</h4>
			<div
				id="mod-bearsamppai-faq-body-<?php echo (int) $item->id; ?>"
				class="accordion-collapse collapse<?php echo $i === 0 ? ' show' : ''; ?>"
				aria-labelledby="mod-bearsamppai-faq-head-<?php echo (int) $item->id; ?>"
				data-bs-parent="#mod-bearsamppai-faq"
			>
				<div class="accordion-body">
					<?php echo $item->displayIntro; ?>
					<?php if ($item->readmore && $item->showIntroText) : ?>
						<a class="readmore" href="<?php echo $item->link; ?>">
							<?php echo Text::_('MOD_BEARSAMPPAI_READ_MORE'); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
		</div>
	<?php endforeach; ?>
</div>