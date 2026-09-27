<?php

/**
 * @package     Bearsampp.Module
 * @subpackage  mod_bearsamppai
 *
 * @author      Bearsampp
 * @copyright   (C) 2026 Bearsampp
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Bearsampp\Module\BearsamppAI\Site\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Dispatcher class for mod_bearsamppai.
 *
 * @since  2.0.0
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * Returns the layout data.
     *
     * @return  array
     *
     * @since   2.0.0
     */
    protected function getLayoutData(): array
    {
        $data = parent::getLayoutData();

        $helper = $this->getHelperFactory()->getHelper('ModuleHelper');

        if ((int) $data['params']->get('show_articles', 1) === 1) {
            $data['articles'] = $helper->getArticles($data['params'], $this->getApplication());
        }

        if ((int) $data['params']->get('show_forum', 1) === 1) {
            $data['forumTopics'] = $helper->getForumTopics($data['params'], $this->getApplication());
        }

        if ((int) $data['params']->get('show_faq', 1) === 1) {
            $data['faqItems'] = $helper->getFaqItems($data['params'], $this->getApplication());
        }

        return $data;
    }
}