<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SurveyJS_Pro_Creator {

    const TAB = 'creator';
    const HANDLE = 'surveyjs-pro-creator-editor';

    public static function init() {
        add_filter( 'surveyjs_editor_tabs', array( __CLASS__, 'tabs' ) );
        add_action( 'surveyjs_render_editor', array( __CLASS__, 'render' ) );
        add_action( 'surveyjs_admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
    }

    public static function enabled() {
        return SurveyJS_Pro_Libs::compatible() && apply_filters( 'surveyjs_pro_creator_enabled', SurveyJS_Pro_Settings::feature( 'creator' ) );
    }

    public static function tabs( $tabs ) {
        if ( ! self::enabled() || ! is_array( $tabs ) ) {
            return $tabs;
        }
        unset( $tabs['survey'] );
        return array( self::TAB => __( 'Config', 'surveyjs-pro' ) ) + $tabs;
    }

    public static function render() {
        if ( ! self::enabled() ) {
            return;
        }
        ?>
        <div class="wp-sjs-pro-creator" data-panel="<?php echo esc_attr( self::TAB ); ?>" hidden>
            <div id="wp-sjs-pro-creator" class="wp-sjs-pro-creator__host"><p class="wp-sjs-pro-creator__loading"><?php esc_html_e( 'Loading Creator…', 'surveyjs-pro' ); ?></p></div>
        </div>
        <?php
    }

    public static function enqueue( $page ) {
        if ( 'surveyjs_editor' !== $page || ! self::enabled() || ! SurveyJS_Pro_Libs::enqueue( 'creator' ) ) {
            return;
        }
        wp_enqueue_style( self::HANDLE, self::asset_url( 'creator.css' ), array( SurveyJS_Pro_Libs::handle( 'creator' ), 'dashicons' ), self::asset_version( 'creator.css' ) );
        wp_enqueue_script( self::HANDLE, self::asset_url( 'creator.js' ), array( SurveyJS_Pro_Libs::handle( 'creator' ), SurveyJS_SurveyJS::EDITOR_SCRIPT, 'wp-api-fetch' ), self::asset_version( 'creator.js' ), true );
        wp_add_inline_script( self::HANDLE, 'window.surveyjsProCreator = ' . wp_json_encode( self::config() ) . ';', 'before' );
    }

    private static function asset_url( $file ) {
        return plugins_url( 'assets/' . $file, SURVEYJS_PRO_FILE );
    }

    private static function asset_version( $file ) {
        return SURVEYJS_PRO_VERSION . '.' . filemtime( dirname( SURVEYJS_PRO_FILE ) . '/assets/' . $file );
    }

    private static function config() {
        return array(
            'tab'    => self::TAB,
            'labels' => array(
                'gdprAgreement'    => __( 'GDPR Agreement', 'surveyjs-pro' ),
                'gdprHelp'         => __( 'A required consent checkbox with a link to your Privacy Policy page.', 'surveyjs-pro' ),
                'adaptiveTheme'    => __( 'Adaptive is on in the Theme tab of the form editor, so the form on your site uses your site\'s theme styles instead of this theme. Turn Adaptive off there to use this theme.', 'surveyjs-pro' ),
                'fullscreen'       => __( 'Full screen', 'surveyjs-pro' ),
                'exitFullscreen'   => __( 'Exit full screen', 'surveyjs-pro' ),
                'mediaTitle'       => __( 'Choose a file', 'surveyjs-pro' ),
                'mediaButton'      => __( 'Use this file', 'surveyjs-pro' ),
                'uploadFailed'     => __( 'The file was not uploaded to the Media Library.', 'surveyjs-pro' ),
            ),
        );
    }
}

add_action( 'surveyjs_pro_loaded', array( 'SurveyJS_Pro_Creator', 'init' ) );
