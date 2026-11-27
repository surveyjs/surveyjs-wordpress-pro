<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SurveyJS_Pro_Libs {

    const PREFIX = 'surveyjs-pro-';
    const LICENSE_HANDLE = 'surveyjs-pro-license';

    private static $registered = false;

    public static function handle( $name ) {
        return self::PREFIX . $name;
    }

    private static function scripts() {
        return array(
            'creator-core'        => array( 'survey-creator-core/survey-creator-core.min.js', array( self::LICENSE_HANDLE ) ),
            'creator'             => array( 'survey-creator-js/survey-creator-js.min.js', array( self::handle( 'creator-core' ), SurveyJS_SurveyJS::UI_SCRIPT ) ),
            'jspdf'               => array( 'jspdf/jspdf.umd.min.js', array() ),
            'pdf'                 => array( 'survey-pdf/survey.pdf.min.js', array( self::LICENSE_HANDLE, self::handle( 'jspdf' ) ) ),
            'pdf-spacious'        => array( 'survey-pdf/spacious.min.js', array() ),
            'chartjs'             => array( 'chart.js/chart.umd.min.js', array() ),
            'chartjs-datalabels'  => array( 'chartjs-plugin-datalabels/chartjs-plugin-datalabels.min.js', array( self::handle( 'chartjs' ) ) ),
            'analytics'           => array( 'survey-analytics/survey.analytics.min.js', array( self::LICENSE_HANDLE, self::handle( 'chartjs' ), self::handle( 'chartjs-datalabels' ) ) ),
        );
    }

    private static function styles() {
        return array(
            'creator'             => 'survey-creator-core/survey-creator-core.min.css',
            'analytics'           => 'survey-analytics/survey.analytics.min.css',
        );
    }

    public static function bundles() {
        return array(
            'creator'             => array( 'scripts' => array( 'creator' ), 'styles' => array( 'creator' ) ),
            'pdf'                 => array( 'scripts' => array( 'pdf' ), 'styles' => array() ),
            'analytics'           => array( 'scripts' => array( 'analytics' ), 'styles' => array( 'analytics' ) ),
        );
    }

    public static function register() {
        if ( self::$registered ) {
            return true;
        }
        if ( ! SurveyJS_Pro::base_provides( array( 'SurveyJS_SurveyJS::CORE_SCRIPT', 'SurveyJS_SurveyJS::UI_SCRIPT', 'SurveyJS_SurveyJS::CORE_STYLE' ) ) ) {
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
        if ( ! self::register() ) {
            return '';
        }
        $scripts = self::scripts();
        return isset( $scripts[ $name ] ) ? add_query_arg( 'ver', SURVEYJS_PRO_VERSION, self::url( $scripts[ $name ][0] ) ) : '';
    }

    private static function url( $file ) {
        return plugins_url( 'libs/' . $file, SURVEYJS_PRO_FILE );
    }
}
