<?php

/**
 * @package     Bearsampp.Module
 * @subpackage  mod_bearsamppai
 *
 * @author      Bearsampp
 * @copyright   (C) 2026 Bearsampp
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Bearsampp\Module\BearsamppAI\Site\Helper;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Knowledge base chat helper used by the com_ajax endpoint.
 *
 * Route (via com_ajax):
 *   index.php?option=com_ajax&module=bearsamppai&method=ask&format=json&module_id=123
 *
 * Answers a visitor question using the Google Gemini API (OpenAI-compatible
 * endpoint, free tier supported) with knowledge gathered from the same content
 * sources rendered by the module (articles, FAQ and Kunena forum topics).
 *
 * @since  2.1.0
 */
class BearsamppaiHelper
{
	/**
	 * Default Gemini OpenAI-compatible endpoint.
	 *
	 * @var string
	 * @since 2.1.0
	 */
	private const DEFAULT_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions';

	/**
	 * Default Gemini model name.
	 *
	 * @var string
	 * @since 2.1.0
	 */
	private const DEFAULT_MODEL = 'gemini-2.5-flash';

	/**
	 * Handle the com_ajax "ask" method.
	 *
	 * @return  array  JSON-ready result (success/answer/error).
	 *
	 * @since   2.1.0
	 */
	public function askAjax(): array
	{
		$app      = Factory::getApplication();
		$input    = $app->getInput();
		$moduleId = $input->getInt('module_id');

		if (!$moduleId) {
			return ['success' => false, 'error' => 'Missing module_id'];
		}

		$module = \Joomla\CMS\Helper\ModuleHelper::getModuleById($moduleId);

		if (!$module || !isset($module->params)) {
			return ['success' => false, 'error' => 'Module not found'];
		}

		$params = new Registry($module->params);

		$message = trim((string) $input->getString('message', ''));

		if ($message === '') {
			return ['success' => false, 'error' => 'Empty message'];
		}

		$apiKey   = trim((string) $params->get('gemini_api_key', ''));
		$model    = trim((string) $params->get('gemini_model', self::DEFAULT_MODEL));
		$endpoint = trim((string) $params->get('gemini_endpoint', self::DEFAULT_ENDPOINT));

		if ($apiKey === '' || $model === '') {
			return ['success' => false, 'error' => 'Missing Gemini API key or model'];
		}

		$noData = trim((string) $params->get('chat_no_data_message', "I'm sorry, I don't know how to answer that"));

		if ($noData === '') {
			$noData = "I'm sorry, I don't know how to answer that";
		}

		// Build the local knowledge base context from the same sources rendered by the module.
		$context = $this->getKnowledgeContext($params);

		if ($context === '') {
			return ['success' => true, 'answer' => $noData, 'kb' => false];
		}

		$siteUrl = Uri::root();

		$systemPrompt = "You are a knowledge base assistant for this Joomla website." . "\n"
			. "Answer using ONLY the content inside <kb>. If the information is not fully supported by <kb>, respond exactly: '" . $noData . "'" . "\n"
			. "\n"
			. "Rules:" . "\n"
			. "1. Use only the <kb> content for facts and instructions." . "\n"
			. "2. Do not use prior knowledge, do not browse the web, and do not guess." . "\n"
			. "3. Prioritize answering from the knowledge base content over providing links." . "\n"
			. "4. Only provide links if they are mentioned in the <kb> content or if the user explicitly asks for page references." . "\n"
			. "5. The website URL is: " . $siteUrl . "\n"
			. "6. Format links as clickable Markdown: [Link Text](URL) only when necessary." . "\n"
			. "\n"
			. "Knowledge base context follows between <kb> tags." . "\n"
			. "<kb>" . $context . '</kb>';

		$payload = [
			'model'       => $model,
			'messages'    => [
				['role' => 'system', 'content' => $systemPrompt],
				['role' => 'user',   'content' => $message],
			],
			'max_tokens'  => (int) $params->get('max_response_tokens', 512),
			'temperature' => (float) $params->get('temperature', 0.2),
		];

		try {
			$http    = HttpFactory::getHttp();
			$headers = [
				'Authorization' => 'Bearer ' . $apiKey,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			];

			$timeout = (int) $params->get('request_timeout', 30);

			if ($timeout < 1) {
				$timeout = 30;
			}

			$response = $http->post($endpoint, json_encode($payload), $headers, $timeout);

			if ($response->code < 200 || $response->code >= 300) {
				$detail = '';
				$body   = json_decode((string) $response->body, true);

				if (is_array($body)) {
					$error = $body['error'] ?? null;

					if (is_array($error) && isset($error['message'])) {
						$detail = (string) $error['message'];
					} elseif (is_string($error)) {
						$detail = $error;
					} elseif (isset($body['message'])) {
						$detail = (string) $body['message'];
					}
				}

				$errorMessage = 'Gemini API request failed (status ' . $response->code . ')';

				if ($detail !== '') {
					$errorMessage .= ': ' . $detail;
				}

				return [
					'success' => false,
					'error'   => $errorMessage,
					'status'  => $response->code,
				];
			}

			$data = json_decode((string) $response->body, true);

			if (!is_array($data) || empty($data['choices'][0]['message']['content'])) {
				return ['success' => false, 'error' => 'Unexpected response from Gemini API'];
			}

			return [
				'success' => true,
				'answer'  => trim((string) $data['choices'][0]['message']['content']),
			];
		} catch (\Throwable $e) {
			return ['success' => false, 'error' => $e->getMessage()];
		}
	}

	/**
	 * Handle the com_ajax "ping" method used by the widget connection status indicator.
	 *
	 * Returns quickly without calling the AI API; only verifies the module instance
	 * is reachable and responsive.
	 *
	 * @return  array  JSON-ready result.
	 *
	 * @since   2.2.0
	 */
	public function pingAjax(): array
	{
		$app      = Factory::getApplication();
		$moduleId = $app->getInput()->getInt('module_id');

		if (!$moduleId) {
			return ['success' => false, 'error' => 'Missing module_id'];
		}

		$module = \Joomla\CMS\Helper\ModuleHelper::getModuleById($moduleId);

		if (!$module || !isset($module->params)) {
			return ['success' => false, 'error' => 'Module not found'];
		}

		return ['success' => true, 'answer' => 'ok'];
	}

	/**
	 * Build a compact knowledge context string from the content sources rendered by the module:
	 * selected articles (category/tags), FAQ articles and Kunena forum topics.
	 *
	 * @param   Registry  $params  The module parameters.
	 *
	 * @return  string  Empty string when no knowledge is available.
	 *
	 * @since   2.1.0
	 */
	public function getKnowledgeContext(Registry $params): string
	{
		$parts = [];
		$total = 0;
		$maxTotal = (int) $params->get('chat_context_limit', 20000);

		if ($maxTotal < 1000) {
			$maxTotal = 20000;
		}

		// Articles (same scope as the rendered module).
		$catid = (int) $params->get('articles_category_id', 0);
		$tags  = array_values(array_filter(ArrayHelper::toInteger((array) $params->get('articles_tag_ids', []))));
		$limit = max((int) $params->get('articles_count', 5), 1);
		$limit = min($limit, 50);

		$items = $this->loadArticles($params, $limit, $catid, $tags);

		foreach ($items as $item) {
			$text = strip_tags((string) ($item->introtext ?? '') . "\n" . (string) ($item->fulltext ?? ''));
			$text = preg_replace('/\s+/', ' ', $text);

			$this->addContextPart($parts, $total, $maxTotal, 'Article: ' . (string) $item->title, $text);
		}

		// FAQ articles (same category as the rendered FAQ accordion).
		$faqCatid = (int) $params->get('faq_category_id', 0);

		if ($faqCatid > 0) {
			$faqLimit = max((int) $params->get('faq_count', 10), 1);
			$faqLimit = min($faqLimit, 100);

			$faqItems = $this->loadArticles($params, $faqLimit, $faqCatid, [], 'ordering', 'ASC');

			foreach ($faqItems as $item) {
				$text = strip_tags((string) ($item->introtext ?? '') . "\n" . (string) ($item->fulltext ?? ''));
				$text = preg_replace('/\s+/', ' ', $text);
				$this->addContextPart($parts, $total, $maxTotal, 'FAQ: ' . (string) $item->title, $text);
			}
		}

		// Kunena forum topics.
		$forumTopics = $this->loadForumTopics($params);

		foreach ($forumTopics as $topic) {
			$subject = trim((string) ($topic->subject ?? 'Forum post'));
			$text    = trim((string) ($topic->message ?? ''));
			$text    = strip_tags($text);
			$text    = preg_replace('/\s+/', ' ', $text);
			$this->addContextPart($parts, $total, $maxTotal, 'Forum: ' . $subject, $text);
		}

		return trim(implode("\n\n---\n\n", $parts));
	}

	/**
	 * Create a site ArticlesModel configured for module context queries.
	 *
	 * @param   Registry   $params       The module parameters.
	 * @param   int        $limit        Maximum number of items.
	 * @param   int        $catid        Category id filter (0 to ignore).
	 * @param   int[]      $tags         Tag id filters (empty to ignore).
	 * @param   string     $ordering     Column to order by (without alias prefix).
	 * @param   string     $direction    Ordering direction.
	 *
	 * @return  \Joomla\Component\Content\Site\Model\ArticlesModel
	 *
	 * @since   2.1.0
	 */
	private function getArticlesModel(Registry $params, int $limit, int $catid, array $tags, string $ordering = 'publish_up', string $direction = 'DESC')
	{
		$app = Factory::getApplication();

		if (!$app instanceof \Joomla\CMS\Application\SiteApplication) {
			return null;
		}

		/** @var \Joomla\Component\Content\Site\Model\ArticlesModel $model */
		$model = $app->bootComponent('com_content')
			->getMVCFactory()
			->createModel('Articles', 'Site', ['ignore_request' => true]);

		$model->setState('params', $app->getParams());
		$model->setState('list.start', 0);
		$model->setState('filter.published', 1);
		$model->setState('filter.condition', 1);
		$model->setState('load_tags', false);
		$model->setState('filter.access', !ComponentHelper::getParams('com_content')->get('show_noauth'));
		$model->setState('filter.language', $app->getLanguageFilter());
		$model->setState('filter.featured', 'show');
		$model->setState('list.limit', $limit);
		$model->setState('list.ordering', 'a.' . $ordering);
		$model->setState('list.direction', $direction);

		if ($catid > 0) {
			$model->setState('filter.category_id', $catid);
		}

		if (count($tags) === 1) {
			$model->setState('filter.tag', (int) $tags[0]);
		} elseif (count($tags) > 1) {
			$model->setState('filter.tag', $tags);
		}

		return $model;
	}

	/**
	 * Load content articles for the given scope.
	 *
	 * @param   Registry  $params     The module parameters.
	 * @param   int       $limit      Maximum number of items.
	 * @param   int       $catid      Category id filter (0 to ignore).
	 * @param   int[]     $tags       Tag id filters (empty to ignore).
	 * @param   string    $ordering   Column to order by.
	 * @param   string    $direction  Ordering direction.
	 *
	 * @return  object[]
	 *
	 * @since   2.1.0
	 */
	private function loadArticles(Registry $params, int $limit, int $catid, array $tags, string $ordering = 'publish_up', string $direction = 'DESC'): array
	{
		try {
			$model = $this->getArticlesModel($params, $limit, $catid, $tags, $ordering, $direction);

			if ($model === null) {
				return [];
			}

			return (array) $model->getItems();
		} catch (\Throwable $e) {
			return [];
		}
	}

	/**
	 * Load recent published Kunena topics with their first message text.
	 *
	 * @param   Registry  $params  The module parameters.
	 *
	 * @return  object[]
	 *
	 * @since   2.1.0
	 */
	private function loadForumTopics(Registry $params): array
	{
		$limit = max((int) $params->get('forum_count', 5), 1);
		$limit = min($limit, 50);

		try {
			$db    = Factory::getContainer()->get('DatabaseDriver');
			$query = $db->getQuery(true);

			$query->select(
				[
					$db->quoteName('t.subject'),
					$db->quoteName('mt.message'),
				]
			)
				->from($db->quoteName('#__kunena_topics', 't'))
				->innerJoin(
					$db->quoteName('#__kunena_categories', 'c')
					. ' ON c.id = t.category_id AND c.published = 1'
				)
				->leftJoin(
					$db->quoteName('#__kunena_messages_text', 'mt')
					. ' ON mt.mesid = t.first_post_messageid'
				)
				->where($db->quoteName('t.published') . ' = 1')
				->where($db->quoteName('t.hold') . ' = 0')
				->order($db->quoteName('t.last_post_time') . ' DESC');

			$db->setQuery($query, 0, $limit);

			return (array) $db->loadObjectList();
		} catch (\Throwable $e) {
			// Kunena not installed or tables missing.
			return [];
		}
	}

	/**
	 * Append a labelled text part to the context while honouring the character budget.
	 *
	 * @param   array   &$parts    Context parts accumulator.
	 * @param   int     &$total    Running character count.
	 * @param   int     $maxTotal  Maximum context length.
	 * @param   string  $label     Source label (e.g. "Article: ...").
	 * @param   string  $text      Plain text content.
	 *
	 * @return  void
	 *
	 * @since   2.1.0
	 */
	private function addContextPart(array &$parts, int &$total, int $maxTotal, string $label, string $text): void
	{
		$text  = trim(str_replace("\n", ' ', $text));
		$part  = $label . "\n" . $text;
		$len   = mb_strlen($part);

		if ($total + $len > $maxTotal) {
			return;
		}

		$parts[] = $part;
		$total  += $len;
	}
}