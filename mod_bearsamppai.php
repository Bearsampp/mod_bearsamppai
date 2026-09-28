<?php
/**
 * @package     Bearsampp.Module
 * @subpackage  mod_bearsamppai
 *
 * @author      Bearsampp
 * @copyright   (C) 2026 Bearsampp
 * @license     GNU General Public License version 3; see LICENSE
 */

defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\DispatcherFactoryInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper as CoreModuleHelper;

// Boot the dispatcher via the DI container (registered in services/provider.php)
/** @var DispatcherFactoryInterface $dispatcherFactory */
$dispatcherFactory = Factory::getContainer()->get(DispatcherFactoryInterface::class);

$module = CoreModuleHelper::getModule('mod_bearsamppai', $params->get('module_id', 0));

if (!$module) {
    return;
}

$dispatcher = $dispatcherFactory->createDispatcher('Bearsampp\Module\BearsamppAI', $module, $params);
echo $dispatcher->dispatch();