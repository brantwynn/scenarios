<?php

/**
 * @file
 * Command-line tools to aid performing scenario operations.
 */

use Drupal\scenarios\ScenariosHandler;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate_tools\DrushLogMigrateMessage;

/**
 * Implements hook_drush_command().
 */
function scenarios_drush_command() {
  $items = array();

  $items['enable-scenario'] = array(
    'description' => 'Enables a scenario',
    'arguments' => array(
      'scenario' => 'The name of the module that defines the scenario.',
    ),
    'examples' => array(
      'es dfs_tec' => 'Install and import migrations for a scenario.',
    ),
    'drupal dependencies' => array('migrate_tools'),
    'aliases' => array('es'),
  );

  $items['uninstall-scenario'] = array(
    'description' => 'Uninstalls a scenario',
    'arguments' => array(
      'scenario' => 'The name of the module that defines the scenario.',
    ),
    'examples' => array(
      'us dfs_tec' => 'Uninstall and roll back migrations for a scenario.',
    ),
    'drupal dependencies' => array('migrate_tools'),
    'aliases' => array('us'),
  );

  $items['reset-scenario'] = array(
    'description' => 'Resets a scenario',
    'arguments' => array(
      'scenario' => 'The name of the module that defines the scenario.',
    ),
    'examples' => array(
      'res dfs_tec' => 'Reset a scenario rolling back content and re-importing.',
    ),
    'drupal dependencies' => array('migrate_tools'),
    'aliases' => array('res'),
  );

  return $items;
}

/**
 * @param string $scenario
 */
function drush_scenarios_enable_scenario($scenario) {
  \Drupal::service(scenarios_handler)->scenarioEnable($scenario);
}

/**
 * @param string $scenario
 */
function drush_scenarios_uninstall_scenario($scenario) {
  \Drupal::service(scenarios_handler)->scenarioUninstall($scenario);
}

/**
 * @param string $scenario
 */
function drush_scenarios_reset_scenario($scenario) {
  \Drupal::service(scenarios_handler)->scenarioReset($scenario);
}

