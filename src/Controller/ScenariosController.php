<?php

namespace Drupal\scenarios\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Extension\InfoParser;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ScenariosController.
 *
 * @package Drupal\scenarios\Controller
 */
class ScenariosController extends ControllerBase {

  /**
   * Drupal\Core\Extension\InfoParser definition.
   *
   * @var \Drupal\Core\Extension\InfoParser
   */
  protected $infoParser;

  /**
   * Drupal\Core\Extension\ModuleHandler definition.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function __construct(ModuleHandler $module_handler, InfoParser $info_parser) {
    $this->moduleHandler = $module_handler;
    $this->infoParser = $info_parser;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('info_parser')
    );
  }

  /**
   * Scenarios page.
   */
  public function page() {
    $scenarios = [];
    $installed = $this->moduleHandler->getModuleList();
    $modules = system_rebuild_module_data();
    uasort($modules, 'system_sort_modules_by_info_name');
    foreach ($modules as $module) {
      $pathname = $module->getPathname();
      $name = $module->getName();
      $info = $this->infoParser->parse($pathname);
      if (isset($info['scenarios_module']) && $info['scenarios_module'] == $name) {
        $scenarios[] = [
          'name' => $info['name'],
          'module' => $info['scenarios_module'],
          'enabled' => array_key_exists($name, $installed)
        ];
      }
    }
    return [
      '#theme' => 'scenarios_page',
      '#scenarios' => $scenarios
    ];
  }

}
