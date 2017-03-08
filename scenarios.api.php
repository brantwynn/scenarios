<?php

use Drupal\migrate\Plugin\MigrationInterface;

/**
 * @file
 * Hooks specific to the Scenarios module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Defines a set of migrations to be used for importing scenario content.
 *
 * @return array
 *   An associative array of migration IDs keyed by the machine name of the
 *   scenario module that provides them.
 *
 * @see hook_scenario_import_alter()
 */
function hook_scenario_import() {
  return [
    'myscenario' => [
      'myscenario_node_page',
      'myscenario_node_articles',
      'myscenario_block_slideshow',
    ],
    'myotherscenario' => [
      'myotherscenario_file_image',
    ],
  ];
}

/**
 * Alters the list of scenario migrations.
 *
 * @param array $info
 *   An associative array of migration IDs keyed by the machine name of the
 *   scenario module that provides them.
 *
 * @see hook_scenario_import()
 */
function hook_scenario_import_alter(&$info) {
  // Remove the existing block slideshow migration and replace it with a node
  // slideshow migration.
  unset($info['myscenario']['myscenario_block_slideshow']);

  $info['myscenario'][] = 'myscenario_node_slideshow';
}

/**
 * Allows modules to act on completion of scenario migrations.
 *
 * @param MigrationInterface $migration
 *   A scenario migration.
 */
function hook_scenarios_migration_finished(MigrationInterface $migration) {
  // Display a message to notify the user that the migration has finished.
  if ($migration->id() == 'myscenario_node_slideshow') {
    drupal_set_message(t('Finished importing the slideshow slides.'));
  }
}

/**
 * @} End of "addtogroup hooks".
 */
