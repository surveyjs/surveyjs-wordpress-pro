<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SurveyJS_Pro_Admin_Theme {

    const HANDLE = 'surveyjs-pro-admin-theme';
    const MODES = array( 'auto', 'system', 'light', 'dark' );

    public static function init() {
        add_action( 'surveyjs_register_settings', array( __CLASS__, 'register_setting_field' ), 20, 2 );
    }

    public static function mode() {
        $mode = (string) apply_filters( 'surveyjs_pro_admin_theme_mode', SurveyJS_Pro_Settings::all()['admin_theme'] );
        return in_array( $mode, self::MODES, true ) ? $mode : 'auto';
    }

    public static function register() {
        if ( wp_script_is( self::HANDLE, 'registered' ) ) {
            return;
        }
        wp_register_style( self::HANDLE, self::asset_url( 'admin-theme.css' ), array(), self::asset_version( 'admin-theme.css' ) );
        wp_register_script( self::HANDLE, self::asset_url( 'admin-theme.js' ), array(), self::asset_version( 'admin-theme.js' ), false );
        wp_add_inline_script( self::HANDLE, 'window.surveyjsProAdminThemeConfig = ' . wp_json_encode( array(
            'mode'          => self::mode(),
            'darkSelectors' => array_values( array_filter( (array) apply_filters( 'surveyjs_pro_admin_theme_dark_selectors', array() ), 'is_string' ) ),
        ) ) . ';', 'before' );
    }

    public static function enqueue() {
        self::register();
        wp_enqueue_style( self::HANDLE );
        wp_enqueue_script( self::HANDLE );
    }

    private static function asset_url( $file ) {
        return plugins_url( 'assets/' . $file, SURVEYJS_PRO_FILE );
    }

    private static function asset_version( $file ) {
        return SURVEYJS_PRO_VERSION . '.' . filemtime( dirname( SURVEYJS_PRO_FILE ) . '/assets/' . $file );
    }

    public static function modes() {
        return array(
            'auto'   => __( 'Match the WordPress admin', 'surveyjs-pro' ),
            'system' => __( 'Match the device (light or dark system setting)', 'surveyjs-pro' ),
            'light'  => __( 'Light', 'surveyjs-pro' ),
            'dark'   => __( 'Dark', 'surveyjs-pro' ),
        );
    }

    public static function register_setting_field( $page ) {
        add_settings_field( 'surveyjs_pro_admin_theme', esc_html__( 'Color mode', 'surveyjs-pro' ), array( __CLASS__, 'render_setting' ), $page, SurveyJS_Pro_Settings::SECTION, array( 'label_for' => 'sjs-pro-admin-theme' ) );
    }

    public static function render_setting() {
        $current = SurveyJS_Pro_Settings::all()['admin_theme'];
        echo '<select name="' . esc_attr( SurveyJS_Pro_Settings::OPTION ) . '[admin_theme]" id="sjs-pro-admin-theme">';
        foreach ( self::modes() as $value => $label ) {
            echo '<option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__( 'Light or dark colors for Survey Creator, Charts and Dashboard. Accent colors always follow your admin color scheme (Users > Profile). WordPress has no dark admin of its own: "Match the WordPress admin" turns dark when a dark mode plugin darkens the admin.', 'surveyjs-pro' ) . '</p>';
    }
}

add_action( 'surveyjs_pro_loaded', array( 'SurveyJS_Pro_Admin_Theme', 'init' ) );
