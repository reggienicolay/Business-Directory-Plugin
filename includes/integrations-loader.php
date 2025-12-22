<?php
/**
 * Integrations Loader
 *
 * Loads the IntegrationsManager which auto-detects and loads integrations.
 *
 * @package BusinessDirectory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load IntegrationsManager (handles detection and loading)
require_once BD_PLUGIN_DIR . 'src/Integrations/IntegrationsManager.php';

// Initialize on plugins_loaded (priority 25 to run after other plugins load)
add_action(
	'plugins_loaded',
	function () {
		BD\Integrations\IntegrationsManager::init();
	},
	25
);
