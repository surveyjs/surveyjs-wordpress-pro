<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SurveyJS_Addon_Creator {

    const TAB = 'creator';
    const HANDLE = 'surveyjs-forms-addon-creator-editor';
    const LOCALE_HANDLE = 'surveyjs-forms-addon-creator-i18n';
    const FULLSCREEN_HANDLE = 'surveyjs-forms-addon-creator-fullscreen';
    const FULLSCREEN_KEY = 'surveyjs-forms-addon-editor-fullscreen';

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

    const BASE_REQUIRES = array(
        'SurveyJS_SurveyJS::CORE_SCRIPT',
        'SurveyJS_SurveyJS::UI_SCRIPT',
        'SurveyJS_SurveyJS::CORE_STYLE',
        'SurveyJS_SurveyJS::EDITOR_SCRIPT',
        'SurveyJS_Themes::presets',
    );

    public static function init() {
        if ( ! SurveyJS_Addon::requires( __CLASS__, self::BASE_REQUIRES ) ) {
            return;
        }
        add_filter( 'surveyjs_editor_tabs', array( __CLASS__, 'tabs' ) );
        add_filter( 'surveyjs_visual_editor_enabled', array( __CLASS__, 'enabled' ) );
        add_action( 'surveyjs_render_editor', array( __CLASS__, 'render' ), 10, 2 );
        add_action( 'surveyjs_admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
    }

    public static function enabled() {
        return apply_filters( 'surveyjs_addon_creator_enabled', SurveyJS_Addon_Settings::feature( 'creator' ) );
    }

    public static function tabs( $tabs ) {
        if ( ! self::enabled() || ! is_array( $tabs ) ) {
            return $tabs;
        }
        unset( $tabs['survey'] );
        return array( self::TAB => __( 'Config', 'surveyjs-forms-addon' ) ) + $tabs;
    }

    public static function render( $survey = null, $initial_tab = null ) {
        if ( ! self::enabled() ) {
            return;
        }
        ?>
        <div class="wp-sjs-addon-creator" data-panel="<?php echo esc_attr( self::TAB ); ?>"<?php echo self::TAB === $initial_tab ? '' : ' hidden'; ?>>
            <div id="wp-sjs-addon-creator" class="wp-sjs-addon-creator__host"><p class="wp-sjs-addon-creator__loading"><?php esc_html_e( 'Loading Creator…', 'surveyjs-forms-addon' ); ?></p></div>
        </div>
        <?php
    }

    public static function enqueue( $page ) {
        if ( 'surveyjs_editor' !== $page || ! self::enabled() || ! SurveyJS_Addon_Libs::enqueue( 'creator' ) ) {
            return;
        }
        SurveyJS_Addon_Admin_Theme::enqueue();
        // Full screen is set in <head> so the page is never painted with the admin menu first.
        wp_register_script( self::FULLSCREEN_HANDLE, false, array(), SURVEYJS_ADDON_VERSION, false );
        wp_enqueue_script( self::FULLSCREEN_HANDLE );
        wp_add_inline_script( self::FULLSCREEN_HANDLE, '(function(){try{if(window.localStorage.getItem(' . wp_json_encode( self::FULLSCREEN_KEY ) . ')==="0")return;}catch(e){}document.documentElement.classList.add("wp-sjs-addon-fullscreen-mode");})();' );
        wp_enqueue_style( self::HANDLE, self::asset_url( 'creator.css' ), array( SurveyJS_Addon_Libs::handle( 'creator' ), SurveyJS_Addon_Admin_Theme::HANDLE, 'dashicons' ), self::asset_version( 'creator.css' ) );
        $deps = array( SurveyJS_Addon_Libs::handle( 'creator' ), SurveyJS_SurveyJS::EDITOR_SCRIPT, SurveyJS_Addon_Admin_Theme::HANDLE, 'wp-api-fetch' );
        $locale = self::locale();
        if ( $locale ) {
            wp_enqueue_script( self::LOCALE_HANDLE, plugins_url( 'libs/survey-creator-core/i18n/' . $locale[0] . '.min.js', SURVEYJS_ADDON_FILE ), array( SurveyJS_Addon_Libs::handle( 'creator-core' ) ), SURVEYJS_ADDON_VERSION, true );
            $deps[] = self::LOCALE_HANDLE;
        }
        wp_enqueue_script( self::HANDLE, self::asset_url( 'creator.js' ), $deps, self::asset_version( 'creator.js' ), true );
        wp_add_inline_script( self::HANDLE, 'window.surveyjsAddonCreator = ' . wp_json_encode( self::config( $locale ? $locale[1] : '' ) ) . ';', 'before' );
    }

    private static function asset_url( $file ) {
        return plugins_url( 'assets/' . $file, SURVEYJS_ADDON_FILE );
    }

    private static function asset_version( $file ) {
        return SURVEYJS_ADDON_VERSION . '.' . filemtime( dirname( SURVEYJS_ADDON_FILE ) . '/assets/' . $file );
    }

    private static function locale() {
        $locale = determine_locale();
        return self::LOCALES[ $locale ] ?? self::LOCALES[ strtok( $locale, '_' ) ] ?? null;
    }

    private static function config( $locale ) {
        $admin_style = SurveyJS_Addon_Settings::feature( 'admin_style' );
        return array(
            'tab'        => self::TAB,
            'locale'     => $locale,
            'adminStyle' => $admin_style,
            // Creator ships only its default light theme; its default dark look is SurveyJS Forms' "default-dark" preset.
            'darkTheme'  => $admin_style ? null : ( SurveyJS_Themes::presets()['default-dark'] ?? null ),
            'labels'     => array(
                'gdprAgreement'    => __( 'GDPR Agreement', 'surveyjs-forms-addon' ),
                'gdprHelp'         => __( 'A required consent checkbox with a link to your Privacy Policy page.', 'surveyjs-forms-addon' ),
                'adaptiveTheme'    => __( 'Adaptive is on in the Theme tab of the form editor, so the form on your site uses your site\'s theme styles instead of this theme. Turn Adaptive off there to use this theme.', 'surveyjs-forms-addon' ),
                'fullscreen'       => __( 'Full screen', 'surveyjs-forms-addon' ),
                'exitFullscreen'   => __( 'Exit full screen', 'surveyjs-forms-addon' ),
                'mediaTitle'       => __( 'Choose a file', 'surveyjs-forms-addon' ),
                'mediaButton'      => __( 'Use this file', 'surveyjs-forms-addon' ),
                'uploadFailed'     => __( 'The file was not uploaded to the Media Library.', 'surveyjs-forms-addon' ),
            ),
        );
    }
}

add_action( 'surveyjs_addon_loaded', array( 'SurveyJS_Addon_Creator', 'init' ) );
