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

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
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
 * Answers a visitor question through an OpenAI-compatible chat completions
 * endpoint, with knowledge gathered from the site's published content:
 * articles from the selected categories, FAQ articles read as
 * question/answer pairs, and Kunena forum topics.
 *
	 * The default provider is OpenCode Zen (https://opencode.ai/zen/v1) running
	 * a paid model variant. Because the endpoint, key and model are all configurable,
	 * the module is not tied to that provider: any OpenAI-compatible chat completions
	 * service can be used by changing the four settings in the AI Chat tab.
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
	 * Default OpenAI-compatible endpoint (OpenCode Zen).
	 *
	 * @var string
	 * @since 2.1.0
	 */
	private const DEFAULT_ENDPOINT = 'https://opencode.ai/zen/v1/chat/completions';

	/**
	 * Default model name.
	 *
	 * @var string
	 * @since 2.1.0
	 */
	private const DEFAULT_MODEL = 'glm-5.3-flash';

	/**
	 * Default model tried when the primary model is rate limited, times out or
	 * returns a server error.
	 *
	 * @var string
	 * @since 2.3.0
	 */
	private const DEFAULT_FALLBACK_MODEL = 'deepseek-v4-flash';

	/**
	 * Separator inserted between knowledge context parts. Charged against the
	 * context limit so the configured maximum is the maximum actually sent.
	 *
	 * @var string
	 * @since 2.4.0
	 */
	private const CONTEXT_SEPARATOR = "\n\n---\n\n";

	/**
	 * Marker prefixing a cached context, so a foreign or legacy cache value is
	 * ignored rather than being sent to the AI as grounding.
	 *
	 * @var string
	 * @since 2.4.0
	 */
	private const CACHE_PREFIX = "BSKB1:";

	/**
	 * Share of the context budget the FAQ may use.
	 *
	 * The FAQ is loaded first, so an uncapped FAQ consumes the whole limit and
	 * leaves nothing for the article sources. Reserving half keeps a question
	 * answerable from a module page even when the FAQ is large.
	 *
	 * @var float
	 * @since 2.4.0
	 */
	private const FAQ_BUDGET_SHARE = 0.5;

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

		$apiKey        = trim((string) $params->get('ai_api_key', ''));
		$model         = trim((string) $params->get('ai_model', self::DEFAULT_MODEL));
		$fallbackModel = trim((string) $params->get('ai_fallback_model', self::DEFAULT_FALLBACK_MODEL));
		$endpoint      = trim((string) $params->get('ai_endpoint', self::DEFAULT_ENDPOINT));

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
				'error'   => 'Missing ' . implode(' and ', $missing)
					. ' for module ' . $moduleId
					. '. Save the AI Chat tab on that module instance.',
			];
		}

		$noData = trim((string) $params->get('chat_no_data_message', "I'm sorry, I don't know how to answer that"));

		if ($noData === '') {
			$noData = "I'm sorry, I don't know how to answer that";
		}

		// Build the knowledge base context from the configured content sources.
		// Grounding is best effort: if it cannot be assembled the chat must still
		// answer, so a failure here degrades to an empty context rather than
		// failing the request.
		try {
			$context = $this->getKnowledgeContext($params);
		} catch (\Throwable $e) {
			$this->logNotice('KB context failed to build: ' . $e->getMessage());
			$context = '';
		}

		if ($context === '') {
			$this->logNotice('KB empty context: no-data sent without calling the AI');

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

			// Retry once with the fallback model on anything that looks like a
			// transient provider problem: a timeout, a rate limit (429) or any
			// 5xx. Restricting this to 503 left the most common free-tier
			// failure - being rate limited - with no retry at all. A 4xx other
			// than 429 is a real problem with the request itself (bad key, bad
			// model, malformed payload) and retrying would only add latency.
			$retryable = $response->code === 429 || ($response->code >= 500 && $response->code < 600);

			if (
				$retryable
				&& !$fallbackAttempted
				&& $fallbackModel !== ''
				&& $fallbackModel !== $model
			) {
				$fallbackAttempted = true;
				$this->logFallbackAttempt($model, $fallbackModel, 'HTTP ' . $response->code);
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
					'AI API request failed (HTTP ' . $response->code . '); response body: ' . $logBody,
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

				if ($fallbackAttempted) {
					$errorMessage = 'The AI service is busy right now. Please try again shortly.';
				} else {
					$errorMessage = 'AI API request failed (status ' . $response->code . ')';

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
				return ['success' => false, 'error' => 'Unexpected response from the AI API'];
			}

		$answer = trim((string) $data['choices'][0]['message']['content']);

		$this->logNotice(
			'KB answer: chars=' . mb_strlen($context)
			. ' model=' . $model
			. ' fallback=' . ($fallbackAttempted ? 'yes' : 'no')
			. ' noData=' . ($answer === $noData ? 'yes' : 'no')
		);

			return [
				'success' => true,
				'answer'  => $answer,
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
			'AI model ' . $primaryModel . ' returned ' . $reason . '; retrying with fallback model ' . $fallbackModel,
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
	 * params, and every configured value - the API key and model included -
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
	 * sources: FAQ articles read as question/answer pairs, articles (optionally
	 * restricted to selected categories), and Kunena forum topics.
	 *
	 * Sources are added in that order of priority and share one character budget.
	 * FAQ is placed first so a large article selection cannot starve it.
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

		if ($maxTotal < 1000 || $maxTotal > 200000) {
			$maxTotal = 20000;
		}

		// The assembled context only changes when the selected content or the
		// parameters change, so reuse the last result until then. The signature
		// covers both, which means edited or new content invalidates it on its own.
		$signature = $this->getContextSignature($params, $maxTotal);
		$cache     = $this->getContextCache();
		$cacheKey  = 'mod_bearsamppai.context.' . $signature;
		$cached    = $this->readCachedContext($cache, $cacheKey);

		if ($cached !== null) {
			$this->logNotice('KB context: cache hit chars=' . mb_strlen($cached));

			return $cached;
		}

		// FAQ first. FAQ entries are short, targeted, and the content an admin most
		// wants answered, so they are placed ahead of the bulk article sources.
		// Loading them last let a large article selection consume the whole budget
		// and silently drop the FAQ, which looks identical to the model refusing.
		//
		// The FAQ is still capped, but only at half the budget. An uncapped FAQ
		// fills the limit on its own and leaves the articles nothing, so a question
		// whose answer lives on a module page fails even though that page is a
		// selected source.
		$faqCatid = (int) $params->get('faq_category_id', 0);
		$faqSeen  = 0;
		$faqUsed  = 0;
		$faqLimit = (int) round($maxTotal * self::FAQ_BUDGET_SHARE);
		$faqTotal = 0;

		if ($faqCatid > 0) {
			foreach ($this->loadArticles($params, 0, [$faqCatid], 'ordering', 'ASC') as $item) {
				$faqSeen++;
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
						'FAQ answer: ' . $answer,
						$faqLimit - $faqTotal
					)
				) {
					// Skipped, not fatal: a later FAQ entry may be short enough to
					// fit, so the loop continues rather than ending the FAQ source.
					continue;
				}

				$faqTotal += mb_strlen('FAQ question: ' . $question)
					+ mb_strlen('FAQ answer: ' . $answer)
					+ ($parts === [] ? 0 : mb_strlen(self::CONTEXT_SEPARATOR));

				$faqUsed++;
			}
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

		$articleSeen    = 0;
		$articleUsed    = 0;
		$articleSkipped = [];

		foreach ($this->loadArticles($params, 0, $categories) as $item) {
			$articleSeen++;
			$text = $this->plainText((string) ($item->introtext ?? '') . "\n" . (string) ($item->fulltext ?? ''));

			if (!$this->addContextPart($parts, $total, $maxTotal, 'Article: ' . (string) $item->title, $text)) {
				// Skipped, not fatal. Breaking here dropped every article after the
				// first one that did not fit, which is why a module page in a
				// selected category could be missing from the context entirely.
				$articleSkipped[] = (string) $item->title;
				continue;
			}

			$articleUsed++;
		}

		// Kunena forum topics, excluded unless the site opts in. Like articles, a
		// later source only gets a turn when an earlier one left budget.
		$forumSeen = 0;
		$forumUsed = 0;

		if ((int) $params->get('show_forum', 0) === 1) {
			foreach ($this->loadForumTopics($params) as $topic) {
				$forumSeen++;
				$subject = trim((string) ($topic->subject ?? 'Forum post'));
				$text    = $this->plainText((string) ($topic->message ?? ''));

				if (!$this->addContextPart($parts, $total, $maxTotal, 'Forum: ' . $subject, $text)) {
					continue;
				}

				$forumUsed++;
			}
		}

		$context = trim(implode(self::CONTEXT_SEPARATOR, $parts));

		$this->logNotice(
			'KB context: chars=' . mb_strlen($context) . '/' . $maxTotal
			. ' parts=' . count($parts)
			. ' faq=' . $faqUsed . '/' . $faqSeen
			. ' articles=' . $articleUsed . '/' . $articleSeen
			. ' forum=' . $forumUsed . '/' . $forumSeen
		);

		if ($articleSkipped !== []) {
			// A skipped article is the usual reason a question about a page that is
			// a selected source still comes back as no-data, so name them.
			$this->logNotice(
				'KB dropped ' . count($articleSkipped) . ' article(s) for budget: '
				. mb_substr(implode(' | ', $articleSkipped), 0, 300)
			);
		}

		$this->writeCachedContext($cache, $cacheKey, $context);

		return $context;
	}

	/**
	 * Read the cached knowledge context.
	 *
	 * A cache read must never be able to fail the request, so any cache problem
	 * is reported as a miss and the context is rebuilt as normal.
	 *
	 * @param   ?\Joomla\CMS\Cache\CacheController  $cache  The cache handle.
	 * @param   string                              $key    The cache key.
	 *
	 * @return  ?string  The context, or null when there is no usable entry.
	 *
	 * @since   2.4.0
	 */
	private function readCachedContext(?\Joomla\CMS\Cache\CacheController $cache, string $key): ?string
	{
		if ($cache === null) {
			return null;
		}

		try {
			$cached = $cache->get($key);

			if (!is_string($cached) || $cached === '') {
				return null;
			}

			// Entries are stored compressed, so a plain string means a stale or
			// foreign value that must not be treated as context.
			if (!str_starts_with($cached, self::CACHE_PREFIX)) {
				return null;
			}

			$plain = @gzuncompress(substr($cached, strlen(self::CACHE_PREFIX)));

			return is_string($plain) && $plain !== '' ? $plain : null;
		} catch (\Throwable $e) {
			// A cache that cannot be read is simply a miss.
		}

		return null;
	}

	/**
	 * Write the knowledge context to the cache.
	 *
	 * The context can reach the full 200000 character ceiling, which is too large
	 * to store raw in some cache backends, so it is compressed. A cache that
	 * cannot be written is ignored: it only costs a rebuild next time.
	 *
	 * @param   ?\Joomla\CMS\Cache\CacheController  $cache    The cache handle.
	 * @param   string                              $key      The cache key.
	 * @param   string                              $context  The context to store.
	 *
	 * @return  void
	 *
	 * @since   2.4.0
	 */
	private function writeCachedContext(?\Joomla\CMS\Cache\CacheController $cache, string $key, string $context): void
	{
		if ($cache === null) {
			return;
		}

		try {
			$compressed = gzcompress($context);

			if (is_string($compressed) && $compressed !== '') {
				$cache->store(self::CACHE_PREFIX . $compressed, $key);
			}
		} catch (\Throwable $e) {
			// Caching is an optimisation, never a requirement.
		}
	}

	/**
	 * Build the cache signature for the knowledge context.
	 *
	 * Combines the parameters that affect what is loaded with a fingerprint of the
	 * content itself: the highest modification time and the row count of the
	 * articles and forum topics in scope. Publishing, editing or deleting content
	 * changes one or both, so the cached context is rebuilt without any explicit
	 * invalidation.
	 *
	 * @param   Registry  $params    The module parameters.
	 * @param   int       $maxTotal  The character budget in force.
	 *
	 * @return  string
	 *
	 * @since   2.4.0
	 */
	private function getContextSignature(Registry $params, int $maxTotal): string
	{
		$fingerprint = $this->getContentFingerprint(
			(int) $params->get('faq_category_id', 0),
			(int) $params->get('show_forum', 0) === 1
		);

		$parts = [
			'limit'    => $maxTotal,
			'cats'     => ArrayHelper::toInteger((array) $params->get('articles_category_id', [])),
			'faq'      => (int) $params->get('faq_category_id', 0),
			'forum'    => (int) $params->get('show_forum', 0),
			'content'  => $fingerprint,
		];

		return md5(json_encode($parts));
	}

	/**
	 * Fingerprint the article and forum content in scope.
	 *
	 * @param   int   $faqCatid   The FAQ category id, 0 for none.
	 * @param   bool  $showForum  Whether forum topics are in scope.
	 *
	 * @return  string
	 *
	 * @since   2.4.0
	 */
	private function getContentFingerprint(int $faqCatid, bool $showForum): string
	{
		$stamp = [];

		try {
			$app = Factory::getApplication();

			if ($app instanceof \Joomla\CMS\Application\SiteApplication) {
				$db = Factory::getContainer()->get('DatabaseDriver');

				$query = $db->getQuery(true)
					->select(
						[
							'MAX(a.modified) AS bsk_max_modified',
							'COUNT(a.id) AS bsk_count',
						]
					)
					->from($db->quoteName('#__content', 'a'))
					->where($db->quoteName('a.state') . ' = 1')
					->where($db->quoteName('a.catid') . ' > 0');

				if ($faqCatid > 0) {
					$query->where(
						$db->quoteName('a.catid') . ' = ' . (int) $faqCatid
					);
				}

				$db->setQuery($query);
				$row = $db->loadObject();

				if (is_object($row)) {
					$stamp['articles'] = (string) ($row->bsk_max_modified ?? '')
						. ':' . (string) ($row->bsk_count ?? '0');
				}
			}

			if ($showForum) {
				$db    = Factory::getContainer()->get('DatabaseDriver');
				$query = $db->getQuery(true)
					->select(
						[
							'MAX(t.last_post_time) AS bsk_max_posted',
							'COUNT(t.id) AS bsk_count',
						]
					)
					->from($db->quoteName('#__kunena_topics', 't'))
					->where($db->quoteName('t.published') . ' = 1');

				$db->setQuery($query);
				$row = $db->loadObject();

				if (is_object($row)) {
					$stamp['forum'] = (string) ($row->bsk_max_posted ?? '')
						. ':' . (string) ($row->bsk_count ?? '0');
				}
			}
		} catch (\Throwable $e) {
			// A missing table or column just means an empty fingerprint.
		}

		return json_encode($stamp);
	}

	/**
	 * Get the cache used to store the assembled knowledge context.
	 *
	 * @return  ?\Joomla\CMS\Cache\CacheController  Null when unavailable.
	 *
	 * @since   2.4.0
	 */
	private function getContextCache(): ?\Joomla\CMS\Cache\CacheController
	{
		try {
			$cache = Factory::getContainer()->get(CacheControllerFactoryInterface::class);

			return $cache->createCacheController('callback', ['defaultgroup' => 'mod_bearsamppai']);
		} catch (\Throwable $e) {
			// Caching is an optimisation, never a requirement.
		}

		return null;
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
	private function addContextPart(
		array &$parts,
		int &$total,
		int $maxTotal,
		string $label,
		string $text,
		int $sourceLimit = 0
	): bool {
		$text  = trim(str_replace("\n", ' ', $text));
		$part  = $label . "\n" . $text;
		$len   = mb_strlen($part);
		$limit = $sourceLimit > 0 ? min($sourceLimit, $maxTotal) : $maxTotal;

		// The separator is inserted by the final implode, so it has to be charged
		// against the budget too. Otherwise the assembled context overshoots the
		// configured limit once there are enough parts to make it noticeable.
		if ($parts !== []) {
			$len += mb_strlen(self::CONTEXT_SEPARATOR);
		}

		if ($total + $len > $limit) {
			return false;
		}

		$parts[] = $part;
		$total  += $len;

		return true;
	}

	/**
	 * Record a diagnostic line in the module log.
	 *
	 * @param   string  $message  The message to record.
	 *
	 * @return  void
	 *
	 * @since   2.4.0
	 */
	private function logNotice(string $message): void
	{
		// Diagnostics run on every request, so a logging failure must never be
		// able to break the chat itself.
		try {
			Log::addLogger(
				['text_file' => 'mod_bearsamppai.php'],
				Log::ALL,
				['mod_bearsamppai']
			);

			Log::add(
				$message,
				Log::NOTICE,
				'mod_bearsamppai'
			);
		} catch (\Throwable $e) {
			// Intentionally ignored: logging is best effort.
		}
	}

}