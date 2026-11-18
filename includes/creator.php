<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SurveyJS_Pro_Creator {

    const TAB = 'creator';
    const HANDLE = 'surveyjs-pro-creator-editor';
    const LOCALE_HANDLE = 'surveyjs-pro-creator-i18n';

    // WordPress language or locale => array( file name in libs/survey-creator-core/i18n, Creator locale code ).
    const LOCALES = array(
        'ar' => array( 'arabic', 'ar' ),
        'bg' => array( 'bulgarian', 'bg' ),
        'my' => array( 'burmese', 'mm' ),
        'ca' => array( 'catalan', 'ca' ),
        'hr' => array( 'croatian', 'hr' ),
        'cs' => array( 'czech', 'cs' ),
        'da' => array( 'danish', 'da' ),
        'nl' => array( 'dutch', 'nl' ),
        'fi' => array( 'finnish', 'fi' ),
        'fr' => array( 'french', 'fr' ),
        'de' => array( 'german', 'de' ),
        'el' => array( 'greek', 'el' ),
        'ht' => array( 'haitian-creole', 'ht' ),
        'he' => array( 'hebrew', 'he' ),
        'hu' => array( 'hungarian', 'hu' ),
        'id' => array( 'indonesian', 'id' ),
        'it' => array( 'italian', 'it' ),
        'ja' => array( 'japanese', 'ja' ),
        'ko' => array( 'korean', 'ko' ),
        'ms' => array( 'malay', 'ms' ),
        'mn' => array( 'mongolian', 'mn' ),
        'nb' => array( 'norwegian', 'nb' ),
        'nn' => array( 'norwegian', 'nb' ),
        'fa' => array( 'persian', 'fa' ),
        'pl' => array( 'polish', 'pl' ),
        'pt' => array( 'portuguese', 'pt' ),
        'ro' => array( 'romanian', 'ro' ),
        'ru' => array( 'russian', 'ru' ),
        'zh_CN' => array( 'simplified-chinese', 'zh-cn' ),
        'zh_SG' => array( 'simplified-chinese', 'zh-cn' ),
        'zh_TW' => array( 'traditional-chinese', 'zh-tw' ),
        'zh_HK' => array( 'traditional-chinese', 'zh-tw' ),
        'sk' => array( 'slovak', 'sk' ),
        'sl' => array( 'slovenian', 'sl' ),
        'es' => array( 'spanish', 'es' ),
        'sv' => array( 'swedish', 'sv' ),
        'tg' => array( 'tajik', 'tg' ),
        'th' => array( 'thai', 'th' ),
        'tr' => array( 'turkish', 'tr' ),
    );

    public static function init() {
        add_filter( 'surveyjs_editor_tabs', array( __CLASS__, 'tabs' ) );
        add_filter( 'surveyjs_visual_editor_enabled', array( __CLASS__, 'enabled' ) );
        add_action( 'surveyjs_render_editor', array( __CLASS__, 'render' ) );
        add_action( 'surveyjs_admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
    }

    public static function enabled() {
        return apply_filters( 'surveyjs_pro_creator_enabled', SurveyJS_Pro_Settings::feature( 'creator' ) );
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
        SurveyJS_Pro_Admin_Theme::enqueue();
        wp_enqueue_style( self::HANDLE, self::asset_url( 'creator.css' ), array( SurveyJS_Pro_Libs::handle( 'creator' ), SurveyJS_Pro_Admin_Theme::HANDLE, 'dashicons' ), self::asset_version( 'creator.css' ) );
        $deps = array( SurveyJS_Pro_Libs::handle( 'creator' ), SurveyJS_SurveyJS::EDITOR_SCRIPT, SurveyJS_Pro_Admin_Theme::HANDLE, 'wp-api-fetch' );
        $locale = self::locale();
        if ( $locale ) {
            wp_enqueue_script( self::LOCALE_HANDLE, plugins_url( 'libs/survey-creator-core/i18n/' . $locale[0] . '.min.js', SURVEYJS_PRO_FILE ), array( SurveyJS_Pro_Libs::handle( 'creator-core' ) ), SURVEYJS_PRO_VERSION, true );
            $deps[] = self::LOCALE_HANDLE;
        }
        wp_enqueue_script( self::HANDLE, self::asset_url( 'creator.js' ), $deps, self::asset_version( 'creator.js' ), true );
        wp_add_inline_script( self::HANDLE, 'window.surveyjsProCreator = ' . wp_json_encode( self::config( $locale ? $locale[1] : '' ) ) . ';', 'before' );
    }

    private static function asset_url( $file ) {
        return plugins_url( 'assets/' . $file, SURVEYJS_PRO_FILE );
    }

    private static function asset_version( $file ) {
        return SURVEYJS_PRO_VERSION . '.' . filemtime( dirname( SURVEYJS_PRO_FILE ) . '/assets/' . $file );
    }

    private static function locale() {
        $locale = determine_locale();
        return self::LOCALES[ $locale ] ?? self::LOCALES[ strtok( $locale, '_' ) ] ?? null;
    }

    private static function config( $locale ) {
        $admin_style = SurveyJS_Pro_Settings::feature( 'admin_style' );
        return array(
            'tab'        => self::TAB,
            'locale'     => $locale,
            'adminStyle' => $admin_style,
            // Creator ships only its default light theme; its default dark look is Base's "default-dark" preset.
            'darkTheme'  => $admin_style ? null : ( SurveyJS_Themes::presets()['default-dark'] ?? null ),
            'labels'     => array(
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
