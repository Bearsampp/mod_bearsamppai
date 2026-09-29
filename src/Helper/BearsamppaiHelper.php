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
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
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
 * endpoint, free tier supported) with knowledge gathered from the site's
 * published content: articles from the selected categories, FAQ articles read
 * as question/answer pairs, and Kunena forum topics.
 *
 * The scope is the module's own configuration: `articles_category_id` (any of
 * the selected categories, or every published article when none is selected),
 * `faq_category_id` (included only when set) and `show_forum` (Kunena content
 * is skipped unless this is switched on, so installing Kunena does not silently
 * widen what the model can see). Every item in a selected scope is a candidate;
 * `chat_context_limit` is the only cap, so the newest articles fill the budget
 * first and the remainder is left out. The `show_*` display toggles do not apply
 * here — the chat is the only output, and it draws on every source enabled.
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
	private const DEFAULT_MODEL = 'gemini-3.8-flash';

	/**
	 * Default Gemini model used when the primary model returns HTTP 503.
	 *
	 * @var string
	 * @since 2.3.0
	 */
	private const DEFAULT_FALLBACK_MODEL = 'gemini-2.5-flash-lite';

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

		try {
			$params = $this->getModuleParams($moduleId);
		} catch (\RuntimeException $e) {
			return ['success' => false, 'error' => $e->getMessage()];
		}

		if ($params === null) {
			return ['success' => false, 'error' => 'Module not found or not a mod_bearsamppai site module'];
		}

		$message = trim((string) $input->getString('message', ''));

		if ($message === '') {
			return ['success' => false, 'error' => 'Empty message'];
		}

		$apiKey        = trim((string) $params->get('gemini_api_key', ''));
		$model         = trim((string) $params->get('gemini_model', self::DEFAULT_MODEL));
		$fallbackModel = trim((string) $params->get('gemini_fallback_model', self::DEFAULT_FALLBACK_MODEL));
		$endpoint      = trim((string) $params->get('gemini_endpoint', self::DEFAULT_ENDPOINT));

		$missing = [];

		if ($apiKey === '') {
			$missing[] = 'API key';
		}

		if ($model === '') {
			$missing[] = 'model';
		}

		if ($missing !== []) {
			// Name the missing field and the instance it was read from, so a
			// genuinely unset key is distinguishable from a bad module id.
			return [
				'success' => false,
				'error'   => 'Missing Gemini ' . implode(' and ', $missing)
					. ' for module ' . $moduleId
					. '. Save the AI Chat (Gemini) tab on that module instance.',
			];
		}

		$noData = trim((string) $params->get('chat_no_data_message', "I'm sorry, I don't know how to answer that"));

		if ($noData === '') {
			$noData = "I'm sorry, I don't know how to answer that";
		}

		// Build the knowledge base context from the configured content sources.
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
			. "7. Content is grouped by source. 'FAQ question' is a visitor question and the 'FAQ answer' that follows is the approved answer - match on meaning, not wording." . "\n"
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

		$fallbackAttempted = false;

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

			$fallbackTimeout = (int) $params->get('fallback_timeout', 10);

			if ($fallbackTimeout < 1) {
				$fallbackTimeout = 10;
			}

			try {
				$response = $http->post($endpoint, json_encode($payload), $headers, $timeout);
			} catch (\Throwable $e) {
				if (
					!$this->isTimeoutException($e)
					|| $fallbackModel === ''
					|| $fallbackModel === $model
				) {
					throw $e;
				}

				$fallbackAttempted = true;
				$this->logFallbackAttempt($model, $fallbackModel, 'request timeout');
				$payload['model'] = $fallbackModel;
				$response = $http->post($endpoint, json_encode($payload), $headers, $fallbackTimeout);
			}

			if (
				$response->code === 503
				&& !$fallbackAttempted
				&& $fallbackModel !== ''
				&& $fallbackModel !== $model
			) {
				$fallbackAttempted = true;
				$this->logFallbackAttempt($model, $fallbackModel, 'HTTP 503');
				$payload['model'] = $fallbackModel;
				$response = $http->post($endpoint, json_encode($payload), $headers, $fallbackTimeout);
			}

			if ($response->code < 200 || $response->code >= 300) {
				Log::addLogger(
					['text_file' => 'mod_bearsamppai.php'],
					Log::ALL,
					['mod_bearsamppai']
				);

				$logBody = preg_replace('/[\x00-\x1F\x7F]/', ' ', (string) $response->body);
				$logBody = substr((string) $logBody, 0, 4000);
				Log::add(
					'Gemini API request failed (HTTP ' . $response->code . '); response body: ' . $logBody,
					Log::ERROR,
					'mod_bearsamppai'
				);

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

				if ($response->code === 503 && $fallbackAttempted) {
					$errorMessage = 'The AI service is busy right now. Please try again shortly.';
				} else {
					$errorMessage = 'Gemini API request failed (status ' . $response->code . ')';

					if ($detail !== '') {
						$errorMessage .= ': ' . $detail;
					}
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
			if ($fallbackAttempted && $this->isTimeoutException($e)) {
				return [
					'success' => false,
					'error'   => 'The AI service is taking too long to respond. Please try again shortly.',
				];
			}

			return ['success' => false, 'error' => $e->getMessage()];
		}
	}

	/**
	 * Determine whether an HTTP client exception indicates that the request timed out.
	 *
	 * @param   \Throwable  $exception  The caught exception.
	 *
	 * @return  bool
	 *
	 * @since   2.3.0
	 */
	private function isTimeoutException(\Throwable $exception): bool
	{
		return stripos($exception->getMessage(), 'timed out') !== false
			|| stripos($exception->getMessage(), 'timeout') !== false;
	}

	/**
	 * Record a fallback model attempt.
	 *
	 * @param   string  $primaryModel   The model that failed.
	 * @param   string  $fallbackModel  The model being tried.
	 * @param   string  $reason         Why fallback was triggered.
	 *
	 * @return  void
	 *
	 * @since   2.3.0
	 */
	private function logFallbackAttempt(string $primaryModel, string $fallbackModel, string $reason): void
	{
		Log::addLogger(
			['text_file' => 'mod_bearsamppai.php'],
			Log::ALL,
			['mod_bearsamppai']
		);
		Log::add(
			'Gemini model ' . $primaryModel . ' returned ' . $reason . '; retrying with fallback model ' . $fallbackModel,
			Log::WARNING,
			'mod_bearsamppai'
		);
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

		try {
			$params = $this->getModuleParams($moduleId);
		} catch (\RuntimeException $e) {
			return ['success' => false, 'error' => $e->getMessage()];
		}

		if ($params === null) {
			return ['success' => false, 'error' => 'Module not found or not a mod_bearsamppai site module'];
		}

		return ['success' => true, 'answer' => 'ok'];
	}

	/**
	 * Read a mod_bearsamppai module's saved parameters by module id.
	 *
	 * This deliberately queries #__modules instead of using
	 * Joomla\CMS\Helper\ModuleHelper::getModuleById(). That core method builds
	 * its list through getModuleList(), which filters on the current menu item
	 * (`mm.menuid = :itemId OR mm.menuid <= 0`). A com_ajax request is not a
	 * menu page, so Itemid is 0 and the only rows that survive are those shown
	 * on every menu. A module assigned to a single menu item is therefore
	 * missing from the list, getModuleById() returns a dummy module with empty
	 * params, and every configured value - the Gemini key and model included -
	 * reads back as blank. The direct lookup below is menu-independent.
	 *
	 * @param   int  $moduleId  The module id from the request.
	 *
	 * @return  Registry|null  The parameters, or null when no such site module exists.
	 *
	 * @throws  \RuntimeException  When the database lookup itself fails.
	 *
	 * @since   2.2.1
	 */
	private function getModuleParams(int $moduleId): ?Registry
	{
		if ($moduleId < 1) {
			return null;
		}

		try {
			$db    = Factory::getContainer()->get(DatabaseInterface::class);
			$query = $db->getQuery(true)
				->select($db->quoteName('params'))
				->from($db->quoteName('#__modules'))
				->where($db->quoteName('id') . ' = ' . (int) $moduleId)
				->where($db->quoteName('module') . ' = ' . $db->quote('mod_bearsamppai'))
				->where($db->quoteName('client_id') . ' = 0');

			$db->setQuery($query);

			$raw = $db->loadResult();
		} catch (\Throwable $e) {
			// Deliberately not swallowed: a broken lookup must not be reported
			// as a missing module, which is what made the original bug hard to
			// diagnose. The caller turns this into a distinct error message.
			throw new \RuntimeException('Module settings lookup failed: ' . $e->getMessage(), 0, $e);
		}

		if ($raw === null || $raw === false || $raw === '') {
			return null;
		}

		return new Registry($raw);
	}

	/**
	 * Build a compact knowledge context string from the configured content
	 * sources: articles (optionally restricted to selected categories), FAQ
	 * articles read as question/answer pairs, and Kunena forum topics.
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

		// Articles: the selected categories, or every published article when none
		// are selected. There is no separate item count - the context limit below
		// is what decides how much is actually sent, so the newest items win and
		// the rest are simply left out.
		$categories = array_values(
			array_filter(
				ArrayHelper::toInteger((array) $params->get('articles_category_id', [])),
				static fn ($id) => $id > 0
			)
		);

		foreach ($this->loadArticles($params, 0, $categories) as $item) {
			$text = $this->plainText((string) ($item->introtext ?? '') . "\n" . (string) ($item->fulltext ?? ''));

			if (!$this->addContextPart($parts, $total, $maxTotal, 'Article: ' . (string) $item->title, $text)) {
				// Articles are newest first, so the rest are no more likely to fit.
				break;
			}
		}

		// FAQ articles: the title is the question, the article text is the answer.
		$faqCatid = (int) $params->get('faq_category_id', 0);

		if ($faqCatid > 0) {
			foreach ($this->loadArticles($params, 0, [$faqCatid], 'ordering', 'ASC') as $item) {
				$question = $this->plainText((string) ($item->title ?? ''));
				$answer   = $this->plainText((string) ($item->introtext ?? '') . "\n" . (string) ($item->fulltext ?? ''));

				if ($question === '') {
					continue;
				}

				if (
					!$this->addContextPart(
						$parts,
						$total,
						$maxTotal,
						'FAQ question: ' . $question,
						'FAQ answer: ' . $answer
					)
				) {
					break;
				}
			}
		}

		// Kunena forum topics, excluded unless the site opts in. A source is always
		// given its turn even when an earlier one was too large to fit, so a
		// short FAQ entry or topic can still make it into a full context.
		if ((int) $params->get('show_forum', 0) === 1) {
			foreach ($this->loadForumTopics($params) as $topic) {
				$subject = trim((string) ($topic->subject ?? 'Forum post'));
				$text    = $this->plainText((string) ($topic->message ?? ''));

				if (!$this->addContextPart($parts, $total, $maxTotal, 'Forum: ' . $subject, $text)) {
					break;
				}
			}
		}

		return trim(implode("\n\n---\n\n", $parts));
	}

	/**
	 * Reduce HTML to single-line plain text for the context prompt.
	 *
	 * @param   string  $html  Raw HTML or text.
	 *
	 * @return  string  Whitespace-collapsed plain text.
	 *
	 * @since   2.1.0
	 */
	private function plainText(string $html): string
	{
		$text = strip_tags($html);
		$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

		return trim((string) preg_replace('/\s+/u', ' ', $text));
	}

	/**
	 * Create a site ArticlesModel configured for module context queries.
	 *
	 * @param   Registry   $params       The module parameters.
	 * @param   int        $limit        Maximum number of items, 0 for no limit.
	 * @param   int[]      $categories   Category id filters (empty to ignore).
	 * @param   string     $ordering     Column to order by (without alias prefix).
	 * @param   string     $direction    Ordering direction.
	 *
	 * @return  \Joomla\Component\Content\Site\Model\ArticlesModel
	 *
	 * @since   2.1.0
	 */
	private function getArticlesModel(Registry $params, int $limit, array $categories, string $ordering = 'publish_up', string $direction = 'DESC')
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

		if (count($categories) === 1) {
			$model->setState('filter.category_id', (int) reset($categories));
		} elseif (count($categories) > 1) {
			$model->setState('filter.category_id', $categories);
		}

		return $model;
	}

	/**
	 * Load content articles for the given scope.
	 *
	 * @param   Registry  $params     The module parameters.
	 * @param   int       $limit      Maximum number of items, 0 for no limit.
	 * @param   int[]     $categories Category id filters (empty to ignore).
	 * @param   string    $ordering   Column to order by.
	 * @param   string    $direction  Ordering direction.
	 *
	 * @return  object[]
	 *
	 * @since   2.1.0
	 */
	private function loadArticles(Registry $params, int $limit, array $categories, string $ordering = 'publish_up', string $direction = 'DESC'): array
	{
		try {
			$model = $this->getArticlesModel($params, $limit, $categories, $ordering, $direction);

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

			$db->setQuery($query);

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
	 * @return  boolean  True when the part was added, false when it did not fit.
	 *
	 * @since   2.1.0
	 */
	private function addContextPart(array &$parts, int &$total, int $maxTotal, string $label, string $text): bool
	{
		$text  = trim(str_replace("\n", ' ', $text));
		$part  = $label . "\n" . $text;
		$len   = mb_strlen($part);

		if ($total + $len > $maxTotal) {
			return false;
		}

		$parts[] = $part;
		$total  += $len;

		return true;
	}
}