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
	 * OpenCode Console prices per 1M input/output tokens, verified 2026-10-17.
	 *
	 * @var array<string, array{0: string, 1: string}>
	 * @since  2.4.4
	 */
	private const MODEL_PRICING = [
		'glm-5.3-flash' => ['GLM 5.3 Flash', 'Paid: $0.15 input / $0.50 output'],
		'glm-5.3-flash-free' => ['GLM 5.3 Flash Free', 'Free'],
		'glm-5.3' => ['GLM 5.3', 'Paid: $1.40 input / $4.40 output'],
		'glm-5.2' => ['GLM 5.2', 'Paid: $1.40 input / $4.40 output'],
		'glm-5.1' => ['GLM 5.1', 'Paid: $1.40 input / $4.40 output'],
		'glm-5' => ['GLM 5', 'Paid: $1.00 input / $3.20 output'],
		'deepseek-v4.1-flash' => ['DeepSeek V4.1 Flash', 'Paid: $0.30 input / $1.20 output'],
		'deepseek-v4.1-flash-free' => ['DeepSeek V4.1 Flash Free', 'Free'],
		'deepseek-v4-pro' => ['DeepSeek V4 Pro', 'Paid: $1.74 input / $3.48 output'],
		'minimax-m3' => ['MiniMax M3', 'Paid: $0.30 input / $1.20 output'],
		'minimax-m2.7' => ['MiniMax M2.7', 'Paid: $0.30 input / $1.20 output'],
		'kimi-k3' => ['Kimi K3', 'Paid: $3.00 input / $15.00 output'],
		'kimi-k2.7-code' => ['Kimi K2.7 Code', 'Paid: $0.95 input / $4.00 output'],
		'kimi-k2.6' => ['Kimi K2.6', 'Paid: $0.95 input / $4.00 output'],
		'kimi-k2.5' => ['Kimi K2.5', 'Paid: $0.60 input / $3.00 output'],
		'qwen3.8-max' => ['Qwen3.8 Max', 'Paid: $2.00 input / $6.00 output'],
	];

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

		foreach ($options as $option) {
			$model = (string) $option->value;

			if (isset(self::MODEL_PRICING[$model])) {
				[$name, $price] = self::MODEL_PRICING[$model];
				$option->text = $name . ' (' . $price . ' per 1M tokens)';
			}
		}

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
