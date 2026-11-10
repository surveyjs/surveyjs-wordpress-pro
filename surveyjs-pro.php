<?php
/*
Plugin Name: SurveyJS Forms Pro
Plugin URI: https://surveyjs.io/
Description: Adds Survey Creator, PDF export and analytics to SurveyJS Forms.
Version: 0.1.0
Requires at least: 6.4
Requires PHP: 8.2
Requires Plugins: surveyjs
Author: Devsoft Baltic OÜ
Author URI: https://surveyjs.io/
License: See LICENSE.md
Text Domain: surveyjs-pro
*/
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SURVEYJS_PRO_VERSION', '0.1.0' );
define( 'SURVEYJS_PRO_DB_VERSION', '1' );
define( 'SURVEYJS_PRO_MIN_BASE_VERSION', '2.5.6' );
define( 'SURVEYJS_PRO_LIBRARY_VERSION', '3.2.4' );
define( 'SURVEYJS_PRO_FILE', __FILE__ );

require_once __DIR__ . '/includes/plugin.php';
require_once __DIR__ . '/includes/install.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/libs.php';
require_once __DIR__ . '/includes/admin-theme.php';
require_once __DIR__ . '/includes/creator.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/dashboard.php';
require_once __DIR__ . '/includes/pdf.php';
require_once __DIR__ . '/includes/pdf-respondent.php';
require_once __DIR__ . '/includes/pdf-encryptor.php';
require_once __DIR__ . '/includes/pdf-password.php';

// Pro sorts before Base in the active plugins list, so Base is checked only after every plugin has loaded.
add_action( 'plugins_loaded', array( 'SurveyJS_Pro', 'boot' ) );
