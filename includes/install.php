<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Runs on init, not on activation: on WordPress VIP wpcom_vip_load_plugin() does not fire activation hooks.
class SurveyJS_Addon_Install {

    const VERSION_OPTION = 'surveyjs_addon_db_version';
    const LOCK_OPTION = 'surveyjs_addon_upgrade_lock';
    const LOCK_TIMEOUT = 600;

    public static function init() {
        add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 1 );
    }

    public static function maybe_upgrade() {
        $installed = (int) get_option( self::VERSION_OPTION, 0 );
        $target = (int) SURVEYJS_ADDON_DB_VERSION;
        if ( $installed >= $target || ! self::lock() ) {
            return;
        }
        for ( $version = $installed + 1; $version <= $target; $version++ ) {
            $method = 'upgrade_to_' . $version;
            if ( method_exists( __CLASS__, $method ) ) {
                self::$method();
            }
            update_option( self::VERSION_OPTION, (string) $version );
        }
        delete_option( self::LOCK_OPTION );
    }

    private static function lock() {
        if ( add_option( self::LOCK_OPTION, time(), '', false ) ) {
            return true;
        }
        if ( time() - (int) get_option( self::LOCK_OPTION ) < self::LOCK_TIMEOUT ) {
            return false;
        }
        update_option( self::LOCK_OPTION, time(), false );
        return true;
    }

    private static function upgrade_to_1() {
        if ( '' !== SurveyJS_Addon_Settings::license_key() ) {
            return;
        }
        $base = (array) get_option( 'surveyjs-settings' );
        $key = trim( sanitize_text_field( (string) ( $base['license_key'] ?? '' ) ) );
        if ( '' !== $key ) {
            SurveyJS_Addon_Settings::update( array( 'license_key' => $key ) );
        }
    }
}
