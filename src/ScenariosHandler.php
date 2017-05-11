<?php

namespace Drupal\scenarios;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\ProxyClass\Extension\ModuleInstaller;
use Drupal\Core\Extension\InfoParser;
use Drupal\Core\Extension\ThemeHandler;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate_tools\DrushLogMigrateMessage;
use Drupal\Core\Config\ConfigFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ScenariosHandler.
 *
 * @package Drupal\scenarios
 */
class ScenariosHandler implements ContainerInjectionInterface {

  /**
   * Drupal\Core\Extension\ModuleHandler definition.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * Drupal\Core\ProxyClass\Extension\ModuleInstaller definition.
   *
   * @var \Drupal\Core\ProxyClass\Extension\ModuleInstaller
   */
  protected $moduleInstaller;

  /**
   * Drupal\Core\Extension\InfoParser definition.
   *
   * @var \Drupal\Core\Extension\InfoParser
   */
  protected $infoParser;

  /**
   * Drupal\Core\Extension\ThemeHandler definition.
   *
   * @var \Drupal\Core\Extension\ThemeHandler
   */
  protected $themeHandler;

  /**
   * Drupal\migrate\Plugin\MigrationPluginManager definition.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManager
   */
  protected $migrationPluginManager;

  /**
   * Drupal\Core\Config\ConfigFactory definition
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Constructor.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   * @var \Drupal\Core\ProxyClass\Extension\ModuleInstaller
   * @var \Drupal\Core\Extension\InfoParser
   * @var \Drupal\Core\Extension\ThemeHandler
   * @var \Drupal\migrate\Plugin\MigrationPluginManager
   * @var \Drupal\Core\Config\ConfigFactory
   */
  public function __construct(ModuleHandler $module_handler, ModuleInstaller $module_installer, InfoParser $info_parser, ThemeHandler $theme_handler, MigrationPluginManager $migration_plugin_manager, ConfigFactory $config_factory) {
    $this->moduleHandler = $module_handler;
    $this->moduleInstaller = $module_installer;
    $this->infoParser = $info_parser;
    $this->themeHandler = $theme_handler;
    $this->migrationPluginManager = $migration_plugin_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('module_installer'),
      $container->get('info_parser'),
      $container->get('theme_handler'),
      $container->get('plugin.manager.migration'),
      $container->get('config.factory')
    );
  }

  /**
   * Handling for messages.
   *
   * @param string $message
   * @param string $type
   * @param bool $repeat
   */
  public function setMessage($message, $type = 'status', $repeat = FALSE) {
    if (PHP_SAPI === 'cli' && function_exists('drush_log')) {
      $type = ($type == 'status' ? 'ok' : $type);
      drush_log($message, $type);
    }
    else {
      drupal_set_message($message, $type, $repeat);
    }
  }

  /**
   * Handling for errors.
   *
   * @param string $message
   */
  public function setError($message) {
    if (PHP_SAPI === 'cli' && function_exists('drush_set_error')) {
      drush_set_error('ERROR', $message);
    }
    else {
      drupal_set_message($message, 'error');
    }
  }

  /**
   * Necessary cache rebuilding.
   *
   * @param $alias
   */
  public function cacheRebuild($alias) {
    if ($alias !== NULL && function_exists('drush_invoke_process')) {
      drush_invoke_process($alias, 'cache-rebuild');
    }
    else {
      drupal_flush_all_caches();
    }
  }

  /**
   * Retrieve alias context for Drush.
   *
   * @return null|string
   */
  public function getAlias() {
    $alias = NULL;
    if (PHP_SAPI === 'cli' && function_exists('drush_get_context')) {
      $alias_context = drush_get_context('alias');
      $alias = !empty($alias_context) ? $alias_context : '@self';
    }
    return $alias;
  }

  /**
   * Set up the logger.
   *
   * @param $alias
   *
   * @return \Drupal\migrate\MigrateMessage|\Drupal\migrate_tools\DrushLogMigrateMessage
   */
  public function getLog($alias) {
    if ($alias != NULL && function_exists('drush_log')) {
      return new DrushLogMigrateMessage();
    }
    else {
      return new MigrateMessage();
    }
  }

  /**
   * Retrieves scenario information.
   *
   * @param string $scenario
   *   The name of the scenario to retrieve information for.
   *
   * @return string|bool
   *   An associative array of scenario information or FALSE if the scenario
   *   does not exist.
   */
  public function getScenarioInfo($scenario) {
    if ($filename = drupal_get_filename('module', $scenario)) {
      return $this->infoParser->parse($filename);
    }
    else {
      return FALSE;
    }
  }

  /**
   * Retrieves a screenshot of the theme associated with a scenario.
   *
   * @param string $scenario
   *   The name of the scenario to retrieve a screenshot for.
   *
   * @return string|bool
   *   The path to a screenshot of the scenario theme or FALSE if the scenario,
   *   the scenario theme or a screenshot of the scenario theme does not exist.
   */
  public function getScreenshot($scenario) {
    if ($info = $this->getScenarioInfo($scenario)) {
      $themes = $this->themeHandler->rebuildThemeData();
      if (!empty($info['scenarios_theme']) && !empty($themes[$info['scenarios_theme']]) && file_exists($themes[$info['scenarios_theme']]->info['screenshot'])) {
        return $themes[$info['scenarios_theme']]->info['screenshot'];
      }
    }
    return FALSE;
  }

  /**
   * Batch processing.
   *
   * @param $alias
   */
  public function processBatch($alias) {
    if (batch_get()) {
      if ($alias !== NULL && function_exists('drush_backend_batch_process')) {
        drush_backend_batch_process();
      }
      else {
        batch_process();
      }
    }
  }

  /**
   * Migration processing.
   *
   * @param string $command
   * @param $migrations
   * @param null|string $alias
   *
   * @return bool
   */
  public function processMigrations($command, $migrations, $alias) {
    $migration_manager = \Drupal::service('plugin.manager.migration');
    $migration_manager->clearCachedDefinitions();

    // Return the correct log for the Migrate Executable.
    $log = $this->getLog($alias);

    // Set default value for return.
    $result = false;

    // If we have pending batches, process them now.
    $this->processBatch($alias);

    // Run the migrations in the provided order.
    foreach ($migrations as $migration) {
      $migration = $migration_manager->createInstance($migration);
      $executable = new MigrateExecutable($migration, $log);
      $name = $migration->label();
      switch ($command) {
        case "import":
          if ($execute = $executable->import()) {
            // Invoke the 'scenarios_migration_finished' hook from the global
            // container in case a scenario dependency implements that hook.
            \Drupal::moduleHandler()->invokeAll('scenarios_migration_finished', [$migration]);
          }
          break;
        case "rollback":
          $execute = $executable->rollback();
          break;
        default:
          $execute = false;
      }

      // Return migration result.
      $replace = ['@name' => $name, '@command' => $command];
      if ($execute) {
        $this->setMessage(t('Executed @command for "@name" migration.', $replace));
        $result = true;
      }
      else {
        $this->setError(t('Migration "@name" failed to execute @command', $replace));
        $result = false;
      }
    }
    return $result;
  }

  /**
   * Installs a scenario's theme.
   *
   * @param string $scenario
   *   The name of the scenario to install the theme for.
   */
  public function themeInstall($scenario) {
    // Get the scenario module info.
    if ($info = $this->getScenarioInfo($scenario)) {
      // Install the theme declared by the scenario.
      if (!empty($info['scenarios_theme']) && !$this->themeHandler->themeExists($info['scenarios_theme'])) {
        if ($this->themeHandler->install([$info['scenarios_theme']])) {
          $this->setMessage(t('Installed @scenario scenario theme @theme.', ['@scenario' => $scenario, '@theme' => $info['scenarios_theme']]));
        }
      }
    }
  }

  /**
   * Installs a scenario's admin theme.
   *
   * @param string $scenario
   *   The name of the scenario to install the theme for.
   */
  public function themeAdminInstall($scenario) {
    // Get the scenario module info.
    if ($info = $this->getScenarioInfo($scenario)) {
      // Install the admin theme declared by the scenario.
      if (!empty($info['scenarios_admin_theme']) && !$this->themeHandler->themeExists($info['scenarios_admin_theme'])) {
        if ($this->themeHandler->install([$info['scenarios_admin_theme']])) {
          $this->setMessage(t('Installed @scenario scenario admin theme @theme.', ['@scenario' => $scenario, '@theme' => $info['scenarios_admin_theme']]));
        }
      }
    }
  }

  /**
   * Enables a scenario.
   *
   * @param string $scenario
   *   The name of the scenario to enable.
   * @param string $skip
   *   (optional) The name of a scenario element to skip when enabling a
   *   scenario, either 'modules' or 'migrations'.
   */
  public function scenarioEnable($scenario, $skip = NULL) {
    // Get the scenario module info.
    if ($info = $this->getScenarioInfo($scenario)) {
      // Check if scenario is already enabled then install it.
      if (!$this->moduleHandler->moduleExists($scenario) || $skip == 'modules') {
        // If the scenario specifies a theme, enable it before installing the
        // scenario itself.
        $this->themeInstall($scenario);
        // If the scenario specifies an administration theme, enable it before
        // installing the scenario itself.
        $this->themeAdminInstall($scenario);
        // Install the scenario module.
        if ($skip != 'modules' && $this->moduleInstaller->install([$scenario])) {
          $this->setMessage(t('Installed the @scenario scenario.', ['@scenario' => $scenario]));
          $this->configFactory->getEditable('scenarios.settings')->set('scenarios.enabled', $scenario)->save();
        }
      }
      else {
        $this->setError(t('The scenario @scenario is already enabled.', ['@scenario' => $scenario]));
        return;
      }

      // Get the Drush alias.
      $alias = $this->getAlias();

      if ($skip != 'migrations') {
        // Retrieve the migrations for the given scenario.
        $migrations = scenarios_scenario_migrations($scenario);
        // Process the migrations.
        $this->processMigrations('import', $migrations, $alias);
      }

      // Rebuild cache after enabling scenario.
      $this->cacheRebuild($alias);
    }
    else {
      $this->setError(t('The scenario @scenario does not exist.', ['@scenario' => $scenario]));
    }
  }

  /**
   * Uninstalls a scenario.
   *
   * @param string $scenario
   *   The name of the scenario to uninstall.
   * @param string $skip
   *   (optional) The name of a scenario element to skip when uninstalling a
   *   scenario, either 'modules' or 'migrations'.
   */
  public function scenarioUninstall($scenario, $skip = NULL) {
    // Get the scenario module info.
    if ($info = $this->getScenarioInfo($scenario)) {
      // Check if scenario is enabled before uninstalling.
      if (!$this->moduleHandler->moduleExists($scenario)) {
        $this->setError(t('The @scenario scenario module is not enabled.', ['@scenario' => $scenario]));
        return;
      }

      // Get the Drush alias.
      $alias = $this->getAlias();

      if ($skip != 'migrations') {
        // Retrieve the migrations for the given scenario.
        $migrations = scenarios_scenario_migrations($scenario);
        // Reverse the order of the migrations.
        $migrations = array_reverse($migrations);
        // Process the migrations.
        $this->processMigrations('rollback', $migrations, $alias);
      }

      // Uninstall the scenario module.
      if ($skip != 'modules' && $this->moduleInstaller->uninstall([$scenario])) {
        $this->setMessage(t('Uninstalled the @scenario scenario.', ['@scenario' => $scenario]));
      }
    }
    else {
      $this->setError(t('The scenario @scenario does not exist.', ['@scenario' => $scenario]));
    }
  }

  /**
   * Resets a scenario.
   *
   * @param string $scenario
   *   The name of the scenario to reset.
   * @param string $skip
   *   (optional) The name of a scenario element to skip when resetting a
   *   scenario, either 'modules' or 'migrations'.
   */
  public function scenarioReset($scenario, $skip = NULL) {
    // Get the scenario module info.
    if ($info = $this->getScenarioInfo($scenario)) {
      $this->setMessage(t('Initiated reset of @scenario scenario.', ['@scenario' => $scenario]), 'warning');
      if ($skip == 'migrations' || $skip == 'modules') {
        $this->setMessage(t('Scenarios will skip reset for @skipped.', ['@skipped' => $skip]), 'warning');
      }
      // Uninstall the scenario.
      $this->scenarioUninstall($scenario, $skip);
      // Enable the scenario.
      $this->scenarioEnable($scenario, $skip);
    }
    else {
      $this->setError(t('The scenario @scenario does not exist.', ['@scenario' => $scenario]));
    }
  }

}
