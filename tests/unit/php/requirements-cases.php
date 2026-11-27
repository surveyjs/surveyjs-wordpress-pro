<?php
require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/../../../surveyjs-pro.php';

const TEST_MODULES = array(
    'SurveyJS_Pro_Creator',
    'SurveyJS_Pro_Analytics',
    'SurveyJS_Pro_Dashboard',
    'SurveyJS_Pro_Pdf',
    'SurveyJS_Pro_Pdf_Respondent',
    'SurveyJS_Pro_Pdf_Password',
    'SurveyJS_Pro_AI',
);

// Declares SurveyJS Forms as empty classes with exactly the symbols the add-on asks for, minus $without.
// The same version as the add-on keeps its update notice quiet, so only what Base provides differs between cases.
function test_declare_base( $version, $without = array() ) {
    define( 'SURVEYJS_VERSION', $version );
    $symbols = array( 'SurveyJS_Form_Stats::enabled', 'SurveyJS_Form_Stats::totals', 'SurveyJS_Form_Stats::timeline', 'SurveyJS_Form_Stats::format_conversion' );
    foreach ( TEST_MODULES as $module ) {
        $symbols = array_merge( $symbols, $module::BASE_REQUIRES );
    }
    $classes = array();
    foreach ( array_diff( array_unique( $symbols ), $without ) as $symbol ) {
        list( $class, $member ) = explode( '::', $symbol, 2 ) + array( 1 => '' );
        if ( in_array( $class, $without, true ) ) {
            continue;
        }
        $classes[ $class ][] = strtoupper( $member ) === $member ? "const $member = 'x';" : "public static function $member() { return false; }";
    }
    foreach ( $classes as $class => $members ) {
        eval( "class $class { " . implode( ' ', $members ) . ' }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- test fixture.
    }
}

function test_admin_page() {
    do_action( 'admin_menu' );
    do_action( 'surveyjs_admin_menu', 'surveyjs', 'manage_options' );
    do_action( 'admin_notices' );
}

test_run( array(
    'base-without-classes' => function () {
        define( 'SURVEYJS_VERSION', SURVEYJS_PRO_VERSION );
        do_action( 'plugins_loaded' );
        test_admin_page();
        return array(
            'disabled'  => array_keys( SurveyJS_Pro::disabled_features() ),
            'dashboard' => has_action( 'surveyjs_admin_menu', array( 'SurveyJS_Pro_Dashboard', 'add_page' ) ),
            'libs'      => array( SurveyJS_Pro_Libs::register(), SurveyJS_Pro_Libs::script_url( 'pdf' ) ),
        );
    },
    'complete-base' => function () {
        test_declare_base( SURVEYJS_PRO_VERSION );
        do_action( 'plugins_loaded' );
        return array(
            'disabled'  => array_keys( SurveyJS_Pro::disabled_features() ),
            'dashboard' => has_action( 'surveyjs_admin_menu', array( 'SurveyJS_Pro_Dashboard', 'add_page' ) ),
        );
    },
    'base-without-a-method' => function () {
        test_declare_base( SURVEYJS_PRO_VERSION, array( 'SurveyJS_Themes::presets', 'SurveyJS_Form_Stats' ) );
        do_action( 'plugins_loaded' );
        return array(
            'disabled' => SurveyJS_Pro::disabled_features(),
            'creator'  => has_action( 'surveyjs_editor_tabs', array( 'SurveyJS_Pro_Creator', 'tabs' ) ),
            'pdf'      => has_action( 'surveyjs_editor_tabs', array( 'SurveyJS_Pro_Pdf', 'editor_tabs' ) ),
            'views'    => SurveyJS_Pro_Dashboard::views_enabled(),
        );
    },
    'older-base' => function () {
        test_declare_base( '3.0.0' );
        do_action( 'plugins_loaded' );
        return array(
            'creator' => has_action( 'surveyjs_editor_tabs', array( 'SurveyJS_Pro_Creator', 'tabs' ) ),
            'updater' => has_action( 'admin_notices', array( 'SurveyJS_Pro_Updater', 'notice' ) ),
        );
    },
    'no-base' => function () {
        do_action( 'plugins_loaded' );
        return array(
            'notice'  => has_action( 'admin_notices', array( 'SurveyJS_Pro', 'base_notice' ) ),
            'creator' => has_action( 'surveyjs_editor_tabs', array( 'SurveyJS_Pro_Creator', 'tabs' ) ),
        );
    },
    'second-copy' => function () {
        require __DIR__ . '/../../../surveyjs-pro.php';
        return SURVEYJS_PRO_VERSION;
    },
) );
