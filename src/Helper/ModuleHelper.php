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

use Joomla\CMS\Access\Access;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\Component\Content\Site\Helper\RouteHelper;
use Joomla\Component\Content\Site\Model\ArticlesModel;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Helper for mod_bearsamppai.
 *
 * @since  2.0.0
 */
class ModuleHelper implements DatabaseAwareInterface
{
    use DatabaseAwareTrait;

    /**
     * Retrieve a list of recent articles.
     *
     * @param   Registry        $params  The module parameters.
     * @param   SiteApplication $app     The application instance.
     *
     * @return  object[]
     *
     * @since   2.0.0
     */
    public function getArticles(Registry $params, SiteApplication $app): array
    {
        $model = $this->getArticlesModel($app);

        $model->setState('list.limit', (int) $params->get('articles_count', 5));

        $catid = (int) $params->get('articles_category_id', 0);

        if ($catid > 0) {
            $model->setState('filter.category_id', $catid);
        }

        $tagIds = array_values(array_filter(ArrayHelper::toInteger((array) $params->get('articles_tag_ids', []))));

        if (count($tagIds) === 1) {
            $model->setState('filter.tag', (int) $tagIds[0]);
        } elseif (count($tagIds) > 1) {
            $model->setState('filter.tag', $tagIds);
        }

        $model->setState('list.ordering', 'a.publish_up');
        $model->setState('list.direction', 'DESC');

        $items = $model->getItems();

        $this->addArticleLinks($items, $app);

        return $items;
    }

    /**
     * Retrieve a list of articles to be rendered as FAQ accordion items.
     *
     * @param   Registry        $params  The module parameters.
     * @param   SiteApplication $app     The application instance.
     *
     * @return  object[]
     *
     * @since   2.0.0
     */
    public function getFaqItems(Registry $params, SiteApplication $app): array
    {
        $catid = (int) $params->get('faq_category_id', 0);

        if ($catid < 1) {
            return [];
        }

        $model = $this->getArticlesModel($app);

        $model->setState('list.limit', (int) $params->get('faq_count', 10));
        $model->setState('filter.category_id', $catid);
        $model->setState('list.ordering', 'a.ordering');
        $model->setState('list.direction', 'ASC');

        $items = $model->getItems();

        foreach ($items as &$item) {
            $item->slug         = $item->id . ':' . $item->alias;
            $item->displayIntro = HTMLHelper::_('content.prepare', $item->introtext, $params, 'mod_bearsamppai.faq');
            $item->readmore     = strlen(trim((string) $item->fulltext)) > 0;
        }
        unset($item);

        $this->addArticleLinks($items, $app);

        return $items;
    }

    /**
     * Retrieve a list of the most recent Kunena forum topics.
     *
     * @param   Registry        $params  The module parameters.
     * @param   SiteApplication $app     The application instance.
     *
     * @return  object[]
     *
     * @since   2.0.0
     */
    public function getForumTopics(Registry $params, SiteApplication $app): array
    {
        $limit = (int) $params->get('forum_count', 5);

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select(
            [
                $db->quoteName('t.id'),
                $db->quoteName('t.category_id'),
                $db->quoteName('t.subject'),
                $db->quoteName('t.replies'),
                $db->quoteName('t.hits'),
                $db->quoteName('t.last_post_time'),
                $db->quoteName('c.name', 'category_title'),
                $db->quoteName('c.alias', 'category_alias'),
                $db->quoteName('m.userid', 'last_post_userid'),
                $db->quoteName('m.name', 'last_post_name'),
            ]
        )
            ->from($db->quoteName('#__kunena_topics', 't'))
            ->innerJoin(
                $db->quoteName('#__kunena_categories', 'c')
                . ' ON c.id = t.category_id AND c.published = 1'
            )
            ->innerJoin(
                $db->quoteName('#__kunena_messages', 'm')
                . ' ON m.id = t.last_post_messageid'
            )
            ->where($db->quoteName('t.published') . ' = 1')
            ->where($db->quoteName('t.hold') . ' = 0')
            ->order($db->quoteName('t.last_post_time') . ' DESC');

        $db->setQuery($query, 0, $limit);
        $topics = $db->loadObjectList() ?: [];

        foreach ($topics as &$topic) {
            $topic->subject      = htmlspecialchars((string) $topic->subject, ENT_QUOTES, 'UTF-8');
            $topic->topicLink    = Route::_(
                'index.php?option=com_kunena&view=topic&catid=' . (int) $topic->category_id . '&id=' . (int) $topic->id
            );
            $topic->categoryLink = Route::_(
                'index.php?option=com_kunena&view=category&catid=' . (int) $topic->category_id
            );
        }
        unset($topic);

        return $topics;
    }

    /**
     * Create a site ArticlesModel configured for module use.
     *
     * @param   SiteApplication $app  The application instance.
     *
     * @return  ArticlesModel
     *
     * @since   2.0.0
     */
    private function getArticlesModel(SiteApplication $app): ArticlesModel
    {
        /** @var ArticlesModel $model */
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

        return $model;
    }

    /**
     * Apply article links and readmore state to a list of items.
     *
     * @param   array           $items  The article items.
     * @param   SiteApplication $app    The application instance.
     *
     * @return  void
     *
     * @since   2.0.0
     */
    private function addArticleLinks(array &$items, SiteApplication $app): void
    {
        $showNoauth = (bool) ComponentHelper::getParams('com_content')->get('show_noauth');
        $authorised = Access::getAuthorisedViewLevels($app->getIdentity()->id);

        foreach ($items as &$item) {
            $item->slug = $item->id . ':' . $item->alias;

            if ($showNoauth || in_array($item->access, $authorised)) {
                $item->link = Route::_(RouteHelper::getArticleRoute($item->slug, $item->catid, $item->language));
            } else {
                $item->link = Route::_('index.php?option=com_users&view=login');
            }

            $item->showIntroText = in_array($item->access, $authorised);
        }
        unset($item);
    }
}