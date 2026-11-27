<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SurveyJS_Addon_Settings {

    const OPTION = 'surveyjs_addon_settings';
    const SECTION = 'sjs-addon-section';

    public static function init() {
        add_action( 'surveyjs_register_settings', array( __CLASS__, 'register' ), 10, 2 );
    }

    public static function defaults() {
        return array(
            'creator'     => true,
            'admin_theme' => 'auto',
            'admin_style' => true,
        );
    }

    public static function all() {
        $settings = get_option( self::OPTION, array() );
        return array_intersect_key( array_merge( self::defaults(), is_array( $settings ) ? $settings : array() ), self::defaults() );
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
        add_settings_section( self::SECTION, esc_html__( 'SurveyJS Forms Add-on', 'surveyjs-forms-addon' ), null, $page );
        add_settings_field( 'surveyjs_addon_creator', esc_html__( 'Form editor', 'surveyjs-forms-addon' ), array( __CLASS__, 'render_creator' ), $page, self::SECTION );
    }

    public static function sanitize( $input ) {
        $input = is_array( $input ) ? $input : array();
        $sanitized = self::all();
        $sanitized['creator'] = ! empty( $input['creator'] );
        $sanitized['admin_style'] = ! empty( $input['admin_style'] );
        if ( isset( $input['admin_theme'] ) && in_array( $input['admin_theme'], SurveyJS_Addon_Admin_Theme::MODES, true ) ) {
            $sanitized['admin_theme'] = $input['admin_theme'];
        }
        return $sanitized;
    }

    public static function render_creator() {
        printf(
            '<label><input type="checkbox" name="%1$s[creator]" value="1" %2$s /> %3$s</label><p class="description">%4$s</p>',
            esc_attr( self::OPTION ),
            checked( self::feature( 'creator' ), true, false ),
            esc_html__( 'Edit forms in Survey Creator', 'surveyjs-forms-addon' ),
            esc_html__( 'Turn off to edit forms the classic way, as in the free version: the Config tab (JSON editor) comes back instead of Survey Creator. Your forms stay as they are, and you can turn Creator on again at any time.', 'surveyjs-forms-addon' )
        );
    }
}
