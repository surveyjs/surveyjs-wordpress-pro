<?php
/**
 * Plugin Name: SurveyJS Forms loader
 * Description: Loads SurveyJS Forms and SurveyJS Forms Add-on from the plugins folder on WordPress VIP.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( function_exists( 'wpcom_vip_load_plugin' ) ) {
	wpcom_vip_load_plugin( 'surveyjs' );
	wpcom_vip_load_plugin( 'surveyjs-forms-addon' );
}
