<?php

namespace Drupal\scenarios\Controller;

use Drupal\scenarios\ScenariosHandler;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Extension\InfoParser;
use Drupal\Core\Link;
use Drupal\Core\Config\ConfigFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ScenariosController.
 *
 * @package Drupal\scenarios\Controller
 */
class ScenariosPage extends ControllerBase {

  /**
   * Drupal\Core\Extension\ModuleHandler definition.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * Drupal\Core\Extension\InfoParser definition.
   *
   * @var \Drupal\Core\Extension\InfoParser
   */
  protected $infoParser;

  /**
   * Drupal\scenarios\ScenariosHandler.
   *
   * @var \Drupal\scenarios\ScenariosHandler
   */
  protected $scenariosHandler;

  /**
   * Drupal\Core\Config\ConfigFactory definition
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(ModuleHandler $module_handler, InfoParser $info_parser, ScenariosHandler $scenarios_handler, ConfigFactory $config_factory) {
    $this->moduleHandler = $module_handler;
    $this->infoParser = $info_parser;
    $this->scenariosHandler = $scenarios_handler;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('info_parser'),
      $container->get('scenarios_handler'),
      $container->get('config.factory')
    );
  }

  /**
   * Scenario link generation.
   *
   * @param $module_installed
   * @param $scenario
   * @param $link_type
   * @return \Drupal\Core\Link | null
   */
  public function makeActionLink($module_installed, $scenario, $link_type) {
    switch ($module_installed) {
      case 1:
        $action = ($link_type == 'primary' ? 'reset' : 'uninstall');
        break;
      default:
        $action = ($link_type == 'primary' ? 'enable' : null);
    }
    if ($action !== null) {
      return Link::createFromRoute(t(ucwords($action)), 'scenarios.scenarios_controller_output', ['action' => $action, 'scenario' => $scenario]);
    }
    return null;
  }

  /**
   * Scenarios page generation.
   *
   * @param $message
   * @return array
   */
  public function page($message = null) {
    if ($message != null) {
      drupal_set_message($message);
    }
    $default_config = $this->configFactory->get('scenarios.settings');
    $scenarios = [];
    $installed = $this->moduleHandler->getModuleList();
    $modules = system_rebuild_module_data();
    uasort($modules, 'system_sort_modules_by_info_name');
    foreach ($modules as $module) {
      $pathname = $module->getPathname();
      $name = $module->getName();
      $info = $this->infoParser->parse($pathname);
      if (isset($info['scenarios_module']) && $info['scenarios_module'] == $name) {
        $module_installed = array_key_exists($name, $installed);
        $scenarios[] = [
          'name' => $info['name'],
          'description' => $info['description'],
          'screenshot' => $this->scenariosHandler->getScreenshot($name),
          'module' => $info['scenarios_module'],
          'primary_link' => $this->makeActionLink($module_installed, $name, 'primary'),
          'secondary_link' => $this->makeActionLink($module_installed, $name, 'secondary'),
          'drush' => (isset($info['scenarios_drush']) ? $info['scenarios_drush'] : 'both'),
          'installed' => ($module_installed ? 'true' : 'false')
        ];
      }
    }
    return [
      '#theme' => 'scenarios_page',
      '#attached' => [
        'library' => [
          'scenarios/scenarios.module'
        ]
      ],
      '#page_title' => $default_config->get('scenarios.page_title'),
      '#scenarios' => $scenarios,
      '#scenario_enabled' => $default_config->get('scenarios.enabled')
    ];
  }

  /**
   * Output for controller.
   *
   * @param $action
   * @param $scenario
   * @return array
   */
  public function output($action, $scenario) {
    $message = null;
    $replace = ['@scenario' => $scenario];
    switch ($action) {
      case 'enable':
        if ($this->scenariosHandler->scenarioEnable($scenario)) {
          $message = t('Scenario @scenario was enabled.', $replace);
        }
        break;
      case 'uninstall':
        if ($this->scenariosHandler->scenarioUninstall($scenario)) {
          $message = t('scenario @scenario was uninstalled', $replace);
        }
        break;
      case 'reset':
        if ($this->scenariosHandler->scenarioReset($scenario)) {
          $message = t('Scenario @scenario was reset', $replace);
        }
        break;
    }
    return $this->page($message);
  }

}
