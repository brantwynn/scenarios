<?php

/**
 * @file
 * Command-line tools to aid performing scenario operations.
 */

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
      'rs dfs_tec' => 'Reset a scenario rolling back content and re-importing.',
    ),
    'drupal dependencies' => array('migrate_tools'),
    'aliases' => array('rs'),
  );

  return $items;
}

/**
 * @param string $scenario
 */
function drush_scenarios_enable_scenario($scenario) {
  if (!$scenario) {
    drush_set_error('ERROR', dt('You must specify a scenario machine name, e.g. dfs_tec.'));
    return;
  }

  // Retrieve alias context.
  $alias_context = drush_get_context('alias');
  $alias = !empty($alias_context) ? $alias_context : '@self';

  // Check if scenario is already enabled and install it.
  if (!Drupal::moduleHandler()->moduleExists($scenario)) {
    if (Drupal::service('module_installer')->install(array($scenario))) {
      drush_log(dt('Installed @name scenario module.', array('@name' => $scenario)), 'ok');
    }
  }
  else {
    drush_set_error('ERROR', dt('The scenario !scenario is already enabled.', array('!scenario' => $scenario)));
    return;
  }

  // If we have pending batches, process them now.
  if (batch_get()) {
    if (php_sapi_name() === 'cli' && function_exists('drush_backend_batch_process')) {
      drush_backend_batch_process();
    }
    else {
      batch_process();
    }
  }

  // Retreive the migrations for the given scenario.
  $migrations = scenarios_scenario_migrations($scenario);
  $log = new DrushLogMigrateMessage();

  // Run the migrations in the provided order.
  /** @var \Drupal\migrate\Plugin\MigrationPluginManager $migration_manager */
  $migration_manager = \Drupal::service('plugin.manager.migration');
  $migration_manager->clearCachedDefinitions();

  foreach ($migrations as $migration) {
    /** @var \Drupal\migrate\Plugin\Migration $migration */
    $migration = $migration_manager->createInstance($migration);

    $executable = new MigrateExecutable($migration, $log);
    if (drush_op(array($executable, 'import'))) {
      drush_log(dt('Imported "@name" migration.', array('@name' => $migration->label())), 'ok');
    }
    \Drupal::moduleHandler()->invokeAll('scenarios_migration_finished', [$migration]);
  }

  // Rebuild cache after enabling scenario.
  drush_invoke_process($alias, 'cache-rebuild');
}

/**
 * @param string $scenario
 */
function drush_scenarios_uninstall_scenario($scenario) {
  if (!$scenario) {
    drush_set_error('ERROR', dt('You must specify a scenario machine name, e.g. dfs_tec'));
    return;
  }

  // Retrieve alias context.
  $alias_context = drush_get_context('alias');
  $alias = !empty($alias_context) ? $alias_context : '@self';

  // Check if scenario is already enabled and uninstall it.
  if (!Drupal::moduleHandler()->moduleExists($scenario)) {
    drush_set_error('ERROR', dt('The scenario !scenario is not enabled.', array('!scenario' => $scenario)));
    return;
  }

  // Retrieve the migrations for the given scenario.
  $migrations = scenarios_scenario_migrations($scenario);

  // Reverse the order of the migrations.
  $migrations = array_reverse($migrations);

  /** @var \Drupal\migrate\Plugin\MigrationPluginManager $migration_manager */
  $migration_manager = \Drupal::service('plugin.manager.migration');
  $migration_manager->clearCachedDefinitions();

  // Rollback the migrations in the modified order.
  foreach ($migrations as $migration) {
    $log = new DrushLogMigrateMessage();

    /** @var \Drupal\migrate\Plugin\Migration $migration */
    $migration = $migration_manager->createInstance($migration);

    $executable = new MigrateExecutable($migration, $log);
    if (drush_op(array($executable, 'rollback'))) {
      drush_log(dt('Rolled back "@name" migration.', array('@name' => $migration->label())), 'ok');
    }
  }

  // Uninstall the scenario.
  if (Drupal::service('module_installer')->uninstall(array($scenario))) {
    drush_log(dt('Uninstalled @name scenario module.', array('@name' => $scenario)), 'ok');
  }
}


/**
 * @param string $scenario
 */
function drush_scenarios_reset_scenario($scenario) {
  drush_log(dt('Initiated reset of @name scenario module.', array('@name' => $scenario)), 'warning');
  // Uninstall the scenario.
  drush_scenarios_uninstall_scenario($scenario);
  // Enable the scenario.
  drush_scenarios_enable_scenario($scenario);
}

