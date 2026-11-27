<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SurveyJS_Addon {

    private static $disabled = array();

    public static function boot() {
        if ( ! self::base_compatible() ) {
            if ( self::base_active() ) {
                SurveyJS_Addon_Updater::init();
            } else {
                add_action( 'admin_notices', array( __CLASS__, 'base_notice' ) );
            }
            return;
        }
        SurveyJS_Addon_Install::init();
        SurveyJS_Addon_Settings::init();
        do_action( 'surveyjs_addon_loaded' );
    }

    public static function load_textdomain() {
        load_plugin_textdomain( 'surveyjs-forms-addon', false, dirname( plugin_basename( SURVEYJS_ADDON_FILE ) ) . '/languages' );
    }

    public static function base_version() {
        return defined( 'SURVEYJS_VERSION' ) ? (string) SURVEYJS_VERSION : '';
    }

    public static function base_active() {
        return defined( 'SURVEYJS_VERSION' ) || class_exists( 'SurveyJS_SurveyJS' );
    }

    public static function base_compatible() {
        $version = self::base_version();
        return '' !== $version && version_compare( $version, SURVEYJS_ADDON_MIN_BASE_VERSION, '>=' );
    }

    // A newer or older SurveyJS Forms may lack a class, method or constant a feature uses; the feature is then
    // turned off instead of failing on every admin page. Symbols: 'Class', 'Class::method', 'Class::CONSTANT', 'function()'.
    public static function requires( $feature, $symbols ) {
        $missing = array_values( array_filter( (array) $symbols, function ( $symbol ) {
            return ! self::base_provides( $symbol );
        } ) );
        if ( ! $missing ) {
            return true;
        }
        if ( ! self::$disabled ) {
            add_action( 'admin_notices', array( __CLASS__, 'disabled_notice' ) );
            add_action( 'network_admin_notices', array( __CLASS__, 'disabled_notice' ) );
        }
        self::$disabled[ (string) $feature ] = $missing;
        return false;
    }

    public static function base_provides( $symbols ) {
        foreach ( (array) $symbols as $symbol ) {
            $symbol = (string) $symbol;
            if ( str_ends_with( $symbol, '()' ) ) {
                $found = function_exists( substr( $symbol, 0, -2 ) );
            } elseif ( ! str_contains( $symbol, '::' ) ) {
                $found = class_exists( $symbol );
            } else {
                list( $class, $member ) = explode( '::', $symbol, 2 );
                $found = class_exists( $class ) && ( strtoupper( $member ) === $member ? defined( $symbol ) : is_callable( array( $class, $member ) ) );
            }
            if ( ! $found ) {
                return false;
            }
        }
        return true;
    }

    public static function disabled_features() {
        return self::$disabled;
    }

    private static function feature_label( $feature ) {
        $labels = array(
            'SurveyJS_Addon_Creator'        => __( 'Survey Creator', 'surveyjs-forms-addon' ),
            'SurveyJS_Addon_Analytics'      => __( 'Charts', 'surveyjs-forms-addon' ),
            'SurveyJS_Addon_Dashboard'      => __( 'Dashboard', 'surveyjs-forms-addon' ),
            'SurveyJS_Addon_Pdf'            => __( 'PDF export', 'surveyjs-forms-addon' ),
            'SurveyJS_Addon_Pdf_Respondent' => __( 'PDF for respondents and emails', 'surveyjs-forms-addon' ),
            'SurveyJS_Addon_Pdf_Password'   => __( 'PDF passwords', 'surveyjs-forms-addon' ),
            'SurveyJS_Addon_AI'             => __( 'AI abilities', 'surveyjs-forms-addon' ),
        );
        return $labels[ $feature ] ?? $feature;
    }

    public static function disabled_notice() {
        if ( ! self::$disabled || ! current_user_can( 'activate_plugins' ) ) {
            return;
        }
        $message = sprintf(
            /* translators: 1: list of features, 2: installed SurveyJS Forms version */
            __( 'SurveyJS Forms Add-on turned off %1$s: SurveyJS Forms %2$s does not have what they need. Update SurveyJS Forms and SurveyJS Forms Add-on to their latest versions.', 'surveyjs-forms-addon' ),
            implode( ', ', array_map( array( __CLASS__, 'feature_label' ), array_keys( self::$disabled ) ) ),
            self::base_version()
        );
        ?>
        <div class="notice notice-warning surveyjs-forms-addon-disabled-notice">
            <p><?php echo esc_html( $message ); ?> <a href="<?php echo esc_url( self_admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Go to Plugins', 'surveyjs-forms-addon' ); ?></a></p>
            <p class="description"><?php echo esc_html( implode( ', ', array_unique( array_merge( ...array_values( self::$disabled ) ) ) ) ); ?></p>
        </div>
        <?php
    }

    public static function base_notice() {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }
        if ( file_exists( WP_PLUGIN_DIR . '/surveyjs/surveyjs.php' ) ) {
            $message = __( 'SurveyJS Forms Add-on needs the SurveyJS Forms plugin. Activate SurveyJS Forms to use the add-on.', 'surveyjs-forms-addon' );
            $link = array( admin_url( 'plugins.php' ), __( 'Go to Plugins', 'surveyjs-forms-addon' ) );
        } else {
            $message = sprintf(
                /* translators: %s: minimum SurveyJS Forms version */
                __( 'SurveyJS Forms Add-on needs the SurveyJS Forms plugin %s or later. Install and activate SurveyJS Forms to use the add-on.', 'surveyjs-forms-addon' ),
                SURVEYJS_ADDON_MIN_BASE_VERSION
            );
            $link = array( admin_url( 'plugin-install.php?s=surveyjs&tab=search&type=term' ), __( 'Install SurveyJS Forms', 'surveyjs-forms-addon' ) );
        }
        ?>
        <div class="notice notice-error surveyjs-forms-addon-base-notice">
            <p><?php echo esc_html( $message ); ?> <a href="<?php echo esc_url( $link[0] ); ?>"><?php echo esc_html( $link[1] ); ?></a></p>
        </div>
        <?php
    }
}
