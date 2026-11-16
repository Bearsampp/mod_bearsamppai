<?php

/**
 * @package     Bearsampp.Module
 * @subpackage  mod_bearsamppai
 *
 * @author      Bearsampp
 * @copyright   (C) 2026 Bearsampp
 * @license     GNU General Public License version 3; see LICENSE
 */

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Model selector that preserves previously configured custom model IDs.
 *
 * @since  2.4.5
 */
class JFormFieldAimodel extends ListField
{
	/**
	 * Free-tier models that OpenCode only permits inside its own application.
	 *
	 * @var string[]
	 * @since  2.4.6
	 */
	private const OPENCODE_APP_ONLY_MODELS = [
		'glm-5.3-flash-free',
		'deepseek-v4.1-flash-free',
	];

	/**
	 * OpenCode Console prices per 1M input/output tokens, verified 2026-10-17.
	 *
	 * @var array<string, array{0: string, 1: string}>
	 * @since  2.4.5
	 */
	private const MODEL_PRICING = [
		'glm-5.3-flash' => ['GLM 5.3 Flash', 'Paid: $0.15 input / $0.50 output', 'opencode'],
		'glm-5.3' => ['GLM 5.3', 'Paid: $1.40 input / $4.40 output', 'opencode'],
		'glm-5.2' => ['GLM 5.2', 'Paid: $1.40 input / $4.40 output', 'opencode'],
		'glm-5.1' => ['GLM 5.1', 'Paid: $1.40 input / $4.40 output', 'opencode'],
		'glm-5' => ['GLM 5', 'Paid: $1.00 input / $3.20 output', 'opencode'],
		'deepseek-v4.1-flash' => ['DeepSeek V4.1 Flash', 'Paid: $0.30 input / $1.20 output', 'opencode'],
		'deepseek-v4-pro' => ['DeepSeek V4 Pro', 'Paid: $1.74 input / $3.48 output', 'opencode'],
		'minimax-m3' => ['MiniMax M3', 'Paid: $0.30 input / $1.20 output', 'opencode'],
		'minimax-m2.7' => ['MiniMax M2.7', 'Paid: $0.30 input / $1.20 output', 'opencode'],
		'kimi-k3' => ['Kimi K3', 'Paid: $3.00 input / $15.00 output', 'opencode'],
		'kimi-k2.7-code' => ['Kimi K2.7 Code', 'Paid: $0.95 input / $4.00 output', 'opencode'],
		'kimi-k2.6' => ['Kimi K2.6', 'Paid: $0.95 input / $4.00 output', 'opencode'],
		'kimi-k2.5' => ['Kimi K2.5', 'Paid: $0.60 input / $3.00 output', 'opencode'],
		'qwen3.8-max' => ['Qwen3.8 Max', 'Paid: $2.00 input / $6.00 output', 'opencode'],
		'openai/gpt-4o' => ['GPT-4o (OpenRouter)', 'Paid: $2.50 input / $10.00 output', 'openrouter'],
		'openai/gpt-4o-mini' => ['GPT-4o mini (OpenRouter)', 'Paid: $0.15 input / $0.60 output', 'openrouter'],
		'openai/gpt-4.1' => ['GPT-4.1 (OpenRouter)', 'Paid: $2.00 input / $8.00 output', 'openrouter'],
		'anthropic/claude-3.5-sonnet' => ['Claude 3.5 Sonnet (OpenRouter)', 'Paid: $3.00 input / $15.00 output', 'openrouter'],
		'anthropic/claude-3.7-sonnet' => ['Claude 3.7 Sonnet (OpenRouter)', 'Paid: $3.00 input / $15.00 output', 'openrouter'],
		'google/gemini-2.0-flash-001' => ['Gemini 2.0 Flash (OpenRouter)', 'Paid: $0.10 input / $0.40 output', 'openrouter'],
		'google/gemini-2.5-pro' => ['Gemini 2.5 Pro (OpenRouter)', 'Paid: $1.25 input / $10.00 output', 'openrouter'],
		'meta-llama/llama-3.3-70b-instruct' => ['Llama 3.3 70B (OpenRouter)', 'Paid: $0.13 input / $0.30 output', 'openrouter'],
		'mistralai/mistral-large' => ['Mistral Large (OpenRouter)', 'Paid: $2.00 input / $6.00 output', 'openrouter'],
		'deepseek/deepseek-chat' => ['DeepSeek V3 (OpenRouter)', 'Paid: $0.27 input / $1.10 output', 'openrouter'],
		'qwen/qwen-2.5-72b-instruct' => ['Qwen 2.5 72B (OpenRouter)', 'Paid: $0.35 input / $0.40 output', 'openrouter'],
	];

	/**
	 * The form field type.
	 *
	 * @var string
	 * @since 2.4.5
	 */
	protected $type = 'Aimodel';

	/**
	 * Keep a saved provider-specific model selectable after the field becomes a list.
	 *
	 * @return  object[]  The configured options and any existing custom model.
	 *
	 * @since   2.4.5
	 */
	protected function getOptions()
	{
		$options = parent::getOptions();
		$value   = (string) $this->value;

		$provider = 'opencode';

		if ($this->form instanceof \Joomla\CMS\Form\Form) {
			$selected = (string) $this->form->getValue('ai_provider', 'params', 'opencode');

			if ($selected !== '') {
				$provider = $selected;
			}
		}

		// Split the inherited (XML-declared) options so the disabled and custom
		// entries keep their fixed positions and only provider-matching models
		// are shown.
		$disabledOption = null;
		$customOption   = null;
		$modelOptions   = [];

		foreach ($options as $option) {
			$model = (string) $option->value;

			if ($model === '') {
				$disabledOption = $option;
				continue;
			}

			if ($model === '__custom__') {
				$customOption = $option;
				continue;
			}

			if (isset(self::MODEL_PRICING[$model]) && self::MODEL_PRICING[$model][2] !== $provider) {
				continue;
			}

			$modelOptions[] = $option;
		}

		// Add any provider models that are not declared in the XML.
		$existing = [];

		foreach ($modelOptions as $option) {
			$existing[] = (string) $option->value;
		}

		foreach (self::MODEL_PRICING as $model => $info) {
			if ($info[2] !== $provider || in_array($model, $existing, true)) {
				continue;
			}

			$modelOptions[] = HTMLHelper::_('select.option', $model, $model);
			$existing[]     = $model;
		}

		// Apply the human-readable labels and prices.
		foreach ($modelOptions as $option) {
			$model = (string) $option->value;

			if (isset(self::MODEL_PRICING[$model])) {
				[$name, $price] = [self::MODEL_PRICING[$model][0], self::MODEL_PRICING[$model][1]];
				$option->text = $name . ' (' . $price . ' per 1M tokens)';
			}
		}

		$options = [];

		if ($disabledOption !== null) {
			$options[] = $disabledOption;
		}

		$options = array_merge($options, $modelOptions);

		if ($customOption !== null) {
			$options[] = $customOption;
		}

		if ($value === '' || $value === '__custom__') {
			return $options;
		}

		if (in_array($value, self::OPENCODE_APP_ONLY_MODELS, true)) {
			$options[] = HTMLHelper::_(
				'select.option',
				$value,
				Text::_('MOD_BEARSAMPPAI_FIELD_AI_MODEL_UNAVAILABLE') . ' (' . $value . ')'
			);

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
