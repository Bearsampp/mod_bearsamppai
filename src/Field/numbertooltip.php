<?php

/**
 * @package     Bearsampp.Module
 * @subpackage  mod_bearsamppai
 *
 * @author      Bearsampp
 * @copyright   (C) 2026 Bearsampp
 * @license     GNU General Public License version 3; see LICENSE
 */

use Joomla\CMS\Form\Field\NumberField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

class JFormFieldNumbertooltip extends NumberField
{
	protected $type = 'Numbertooltip';

	protected function getLabel()
	{
		$label = parent::getLabel();

		if ($label === '' || $this->description === '') {
			return $label;
		}

		HTMLHelper::_('bootstrap.popover', '.hasPopover');

		$description = $this->translateDescription ? Text::_($this->description) : $this->description;
		$escapedDescription = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
		$labelText = $this->translateLabel ? Text::_((string) $this->element['label']) : (string) $this->element['label'];
		$escapedLabel = htmlspecialchars($labelText, ENT_QUOTES, 'UTF-8');
		$popover = '<button type="button" class="btn btn-link btn-sm hasPopover p-0 ms-1"'
			. ' aria-label="' . $escapedLabel . '"'
			. ' title="' . $escapedLabel . '"'
			. ' data-bs-toggle="popover"'
			. ' data-bs-content="' . $escapedDescription . '">'
			. '<span class="icon-info-circle" aria-hidden="true"></span>'
			. '</button>';

		return str_replace('</label>', '</label>' . $popover, $label);
	}
}
