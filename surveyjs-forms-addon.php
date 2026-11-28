<?php
/*
Plugin Name: SurveyJS Forms Add-on
Plugin URI: https://surveyjs.io/
Description: Adds Survey Creator with the AI assistant, PDF export and analytics to SurveyJS Forms.
Version: 3.2.4
Requires at least: 6.5
Requires PHP: 8.2
Requires Plugins: surveyjs
Update URI: https://surveyjs.io/wordpress-addon
Author: Devsoft Baltic OÜ
Author URI: https://surveyjs.io/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: surveyjs-forms-addon
Domain Path: /languages
*/
if ( ! defined( 'ABSPATH' ) ) exit;

// A second copy of the add-on (another folder name) would redeclare every class.
if ( defined( 'SURVEYJS_ADDON_VERSION' ) ) {
    return;
}

define( 'SURVEYJS_ADDON_VERSION', '3.2.4' );
define( 'SURVEYJS_ADDON_DB_VERSION', '1' );
define( 'SURVEYJS_ADDON_MIN_BASE_VERSION', '3.2.4' );
define( 'SURVEYJS_ADDON_LIBRARY_VERSION', '3.2.8' );
define( 'SURVEYJS_ADDON_FILE', __FILE__ );

require_once __DIR__ . '/includes/plugin.php';
require_once __DIR__ . '/includes/install.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/updater.php';
require_once __DIR__ . '/includes/libs.php';
require_once __DIR__ . '/includes/admin-theme.php';
require_once __DIR__ . '/includes/creator.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/dashboard.php';
require_once __DIR__ . '/includes/pdf.php';
require_once __DIR__ . '/includes/pdf-respondent.php';
require_once __DIR__ . '/includes/pdf-aes256.php';
require_once __DIR__ . '/includes/pdf-encryptor.php';
require_once __DIR__ . '/includes/pdf-password.php';
require_once __DIR__ . '/includes/ai.php';

add_action( 'init', array( 'SurveyJS_Addon', 'load_textdomain' ) );
// The add-on sorts before SurveyJS Forms in the active plugins list, so SurveyJS Forms is checked only after every plugin has loaded.
add_action( 'plugins_loaded', array( 'SurveyJS_Addon', 'boot' ) );
