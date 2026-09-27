<?php

/**
 * @package     Bearsampp.Module
 * @subpackage  mod_bearsamppai
 *
 * @author      Bearsampp
 * @copyright   (C) 2026 Bearsampp
 * @license     GNU General Public License version 3; see LICENSE
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/*
 * Legacy helper bridge for the com_ajax module endpoint.
 *
 * Route (via com_ajax):
 *   index.php?option=com_ajax&module=bearsamppai&method=ask&format=json&module_id=123
 *
 * com_ajax first resolves the namespaced helper ("BearsamppaiHelper") through
 * the module HelperFactory. If that path is unavailable it falls back to this
 * file, so the logic stays in a single place either way.
 */

require_once __DIR__ . '/src/Helper/BearsamppaiHelper.php';

/**
 * Legacy helper for mod_bearsamppai.
 *
 * @since  2.1.0
 */
class ModBearsamppaiHelper
{
	/**
	 * com_ajax "ask" method (static bridge).
	 *
	 * @return  array
	 *
	 * @since   2.1.0
	 */
	public static function askAjax()
	{
		$helper = new \Bearsampp\Module\BearsamppAI\Site\Helper\BearsamppaiHelper();

		return $helper->askAjax();
	}

	/**
	 * com_ajax "ping" method (static bridge).
	 *
	 * @return  array
	 *
	 * @since   2.2.0
	 */
	public static function pingAjax()
	{
		$helper = new \Bearsampp\Module\BearsamppAI\Site\Helper\BearsamppaiHelper();

		return $helper->pingAjax();
	}
}