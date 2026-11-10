<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SurveyJS_Pro_Settings {

    const OPTION = 'surveyjs_pro_settings';
    const SECTION = 'sjs-pro-section';

    public static function init() {
        add_action( 'surveyjs_register_settings', array( __CLASS__, 'register' ), 10, 2 );
    }

    public static function defaults() {
        return array(
            'license_key' => '',
            'creator'     => true,
            'admin_theme' => 'auto',
        );
    }

    public static function all() {
        $settings = get_option( self::OPTION, array() );
        return array_merge( self::defaults(), is_array( $settings ) ? $settings : array() );
    }

    public static function license_key() {
        return (string) self::all()['license_key'];
    }

    public static function feature( $name ) {
        return (bool) self::all()[ $name ];
    }

    public static function update( $values ) {
        return update_option( self::OPTION, array_merge( self::all(), $values ) );
    }

    public static function register( $page, $option_group ) {
        register_setting( $option_group, self::OPTION, array(
            'type'              => 'array',
            'sanitize_callback' => array( __CLASS__, 'sanitize' ),
            'default'           => self::defaults(),
        ) );
        add_settings_section( self::SECTION, esc_html__( 'SurveyJS Forms Pro', 'surveyjs-pro' ), array( __CLASS__, 'render_section' ), $page );
        add_settings_field( 'surveyjs_pro_creator', esc_html__( 'Form editor', 'surveyjs-pro' ), array( __CLASS__, 'render_creator' ), $page, self::SECTION );
        add_settings_field( 'surveyjs_pro_license_key', esc_html__( 'SurveyJS License Key', 'surveyjs-pro' ), array( __CLASS__, 'render_license_key' ), $page, self::SECTION, array( 'label_for' => 'sjs-pro-license-key' ) );
    }

    public static function sanitize( $input ) {
        $input = is_array( $input ) ? $input : array();
        $sanitized = self::all();
        if ( isset( $input['license_key'] ) ) {
            $sanitized['license_key'] = trim( sanitize_text_field( (string) $input['license_key'] ) );
        }
        $sanitized['creator'] = ! empty( $input['creator'] );
        if ( isset( $input['admin_theme'] ) && in_array( $input['admin_theme'], SurveyJS_Pro_Admin_Theme::MODES, true ) ) {
            $sanitized['admin_theme'] = $input['admin_theme'];
        }
        return array_intersect_key( $sanitized, self::defaults() );
    }

    public static function render_section() {
        echo '<p>' . esc_html__( 'One SurveyJS license key covers Survey Creator, PDF Generator and Dashboard used by this plugin.', 'surveyjs-pro' ) . '</p>';
    }

    public static function render_creator() {
        printf(
            '<label><input type="checkbox" name="%1$s[creator]" value="1" %2$s /> %3$s</label><p class="description">%4$s</p>',
            esc_attr( self::OPTION ),
            checked( self::feature( 'creator' ), true, false ),
            esc_html__( 'Edit forms in Survey Creator', 'surveyjs-pro' ),
            esc_html__( 'Turn off to edit forms the classic way, as in the free version: the Config tab (JSON editor) comes back instead of Survey Creator. Your forms stay as they are, and you can turn Creator on again at any time.', 'surveyjs-pro' )
        );
    }

    public static function render_license_key() {
        printf(
            '<input type="text" name="%1$s[license_key]" id="sjs-pro-license-key" class="regular-text code" value="%2$s" autocomplete="off" spellcheck="false" />',
            esc_attr( self::OPTION ),
            esc_attr( self::license_key() )
        );
    }
}
