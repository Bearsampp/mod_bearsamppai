<?php

/**
 * @package     Bearsampp.Module
 * @subpackage  mod_bearsamppai
 *
 * @author      Bearsampp
 * @copyright   (C) 2026 Bearsampp
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Bearsampp\Module\BearsamppAI\Field;

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Model selector that preserves previously configured custom model IDs.
 *
 * @since  2.4.3
 */
class AimodelField extends ListField
{
	/**
	 * The form field type.
	 *
	 * @var string
	 * @since 2.4.3
	 */
	protected $type = 'Aimodel';

	/**
	 * Keep a saved provider-specific model selectable after the field becomes a list.
	 *
	 * @return  object[]  The configured options and any existing custom model.
	 *
	 * @since   2.4.3
	 */
	protected function getOptions()
	{
		$options = parent::getOptions();
		$value   = (string) $this->value;

		if ($value === '' || $value === '__custom__') {
			return $options;
		}

		foreach ($options as $option) {
			if ((string) $option->value === $value) {
				return $options;
			}
		}

		$options[] = HTMLHelper::_(
			'select.option',
			$value,
			Text::_('MOD_BEARSAMPPAI_FIELD_AI_MODEL_CURRENT_CUSTOM') . ': ' . $value
		);

		return $options;
	}
}
