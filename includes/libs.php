<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SurveyJS_Pro_Libs {

    const PREFIX = 'surveyjs-pro-';
    const LICENSE_HANDLE = 'surveyjs-pro-license';

    private static $registered = false;

    public static function init() {
        add_action( 'admin_notices', array( __CLASS__, 'mismatch_notice' ) );
    }

    public static function handle( $name ) {
        return self::PREFIX . $name;
    }

    // survey-core and survey-js-ui come from Base, so a page never loads two copies of the Form Library;
    // Pro's libraries are built for one exact Form Library version and stay off when Base ships another.
    public static function compatible() {
        return method_exists( 'SurveyJS_SurveyJS', 'library_version' ) && SURVEYJS_PRO_LIBRARY_VERSION === SurveyJS_SurveyJS::library_version();
    }

    private static function scripts() {
        return array(
            'creator-core'        => array( 'survey-creator-core/survey-creator-core.min.js', array( self::LICENSE_HANDLE ) ),
            'creator'             => array( 'survey-creator-js/survey-creator-js.min.js', array( self::handle( 'creator-core' ), SurveyJS_SurveyJS::UI_SCRIPT ) ),
            'jspdf'               => array( 'jspdf/jspdf.umd.min.js', array() ),
            'pdf'                 => array( 'survey-pdf/survey.pdf.min.js', array( self::LICENSE_HANDLE, self::handle( 'jspdf' ) ) ),
            'pdf-fonts'           => array( 'survey-pdf/survey.pdf.fonts.min.js', array( self::handle( 'pdf' ) ) ),
            'pdf-spacious'        => array( 'survey-pdf/spacious.min.js', array() ),
            'chartjs'             => array( 'chart.js/chart.umd.min.js', array() ),
            'chartjs-datalabels'  => array( 'chartjs-plugin-datalabels/chartjs-plugin-datalabels.min.js', array( self::handle( 'chartjs' ) ) ),
            'analytics'           => array( 'survey-analytics/survey.analytics.min.js', array( self::LICENSE_HANDLE, self::handle( 'chartjs' ), self::handle( 'chartjs-datalabels' ) ) ),
            'tabulator'           => array( 'tabulator-tables/tabulator.min.js', array() ),
            'analytics-tabulator' => array( 'survey-analytics/survey.analytics.tabulator.min.js', array( self::LICENSE_HANDLE, self::handle( 'tabulator' ) ) ),
        );
    }

    private static function styles() {
        return array(
            'creator'             => 'survey-creator-core/survey-creator-core.min.css',
            'analytics'           => 'survey-analytics/survey.analytics.min.css',
            'analytics-tabulator' => 'survey-analytics/survey.analytics.tabulator.min.css',
        );
    }

    public static function bundles() {
        return array(
            'creator'             => array( 'scripts' => array( 'creator' ), 'styles' => array( 'creator' ) ),
            'pdf'                 => array( 'scripts' => array( 'pdf' ), 'styles' => array() ),
            'analytics'           => array( 'scripts' => array( 'analytics' ), 'styles' => array( 'analytics' ) ),
            'analytics-tabulator' => array( 'scripts' => array( 'analytics-tabulator' ), 'styles' => array( 'analytics-tabulator' ) ),
        );
    }

    public static function register() {
        if ( self::$registered ) {
            return true;
        }
        if ( ! self::compatible() ) {
            return false;
        }
        wp_register_script( self::LICENSE_HANDLE, false, array( SurveyJS_SurveyJS::CORE_SCRIPT ), SURVEYJS_PRO_VERSION );
        $license_key = SurveyJS_Pro_Settings::license_key();
        if ( '' !== $license_key ) {
            wp_add_inline_script( self::LICENSE_HANDLE, 'Survey.setLicenseKey(' . wp_json_encode( $license_key ) . ');' );
        }
        foreach ( self::scripts() as $name => $script ) {
            wp_register_script( self::handle( $name ), self::url( $script[0] ), $script[1], SURVEYJS_PRO_VERSION );
        }
        foreach ( self::styles() as $name => $file ) {
            wp_register_style( self::handle( $name ), self::url( $file ), array( SurveyJS_SurveyJS::CORE_STYLE ), SURVEYJS_PRO_VERSION );
        }
        self::$registered = true;
        return true;
    }

    public static function enqueue( $bundle ) {
        $bundles = self::bundles();
        if ( ! isset( $bundles[ $bundle ] ) || ! self::register() ) {
            return false;
        }
        foreach ( $bundles[ $bundle ]['scripts'] as $name ) {
            wp_enqueue_script( self::handle( $name ) );
        }
        foreach ( $bundles[ $bundle ]['styles'] as $name ) {
            wp_enqueue_style( self::handle( $name ) );
        }
        return true;
    }

    public static function script_url( $name ) {
        $scripts = self::scripts();
        return isset( $scripts[ $name ] ) && self::register() ? add_query_arg( 'ver', SURVEYJS_PRO_VERSION, self::url( $scripts[ $name ][0] ) ) : '';
    }

    private static function url( $file ) {
        return plugins_url( 'libs/' . $file, SURVEYJS_PRO_FILE );
    }

    public static function mismatch_notice() {
        if ( ! current_user_can( 'manage_options' ) || self::compatible() ) {
            return;
        }
        $message = sprintf(
            /* translators: 1: SurveyJS library version bundled with Pro, 2: SurveyJS library version bundled with SurveyJS Forms */
            __( 'SurveyJS Forms Pro is built for SurveyJS library %1$s, but SurveyJS Forms ships %2$s. Survey Creator, PDF export and analytics are turned off until both plugins are updated to versions released together.', 'surveyjs-pro' ),
            SURVEYJS_PRO_LIBRARY_VERSION,
            method_exists( 'SurveyJS_SurveyJS', 'library_version' ) ? SurveyJS_SurveyJS::library_version() : __( 'an unknown version', 'surveyjs-pro' )
        );
        echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
    }
}
