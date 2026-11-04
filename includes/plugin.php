<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SurveyJS_Pro {

    public static function boot() {
        if ( ! self::base_compatible() ) {
            add_action( 'admin_notices', array( __CLASS__, 'base_notice' ) );
            return;
        }
        SurveyJS_Pro_Install::init();
        SurveyJS_Pro_Settings::init();
        SurveyJS_Pro_Libs::init();
        do_action( 'surveyjs_pro_loaded' );
    }

    public static function base_version() {
        return defined( 'SURVEYJS_VERSION' ) ? (string) SURVEYJS_VERSION : '';
    }

    public static function base_compatible() {
        $version = self::base_version();
        return '' !== $version && version_compare( $version, SURVEYJS_PRO_MIN_BASE_VERSION, '>=' );
    }

    public static function base_notice() {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }
        if ( class_exists( 'SurveyJS_SurveyJS' ) ) {
            $message = sprintf(
                /* translators: %s: minimum SurveyJS Forms version */
                __( 'SurveyJS Forms Pro needs SurveyJS Forms %s or later. Update SurveyJS Forms to use Pro.', 'surveyjs-pro' ),
                SURVEYJS_PRO_MIN_BASE_VERSION
            );
            $link = array( admin_url( 'plugins.php' ), __( 'Go to Plugins', 'surveyjs-pro' ) );
        } elseif ( file_exists( WP_PLUGIN_DIR . '/surveyjs/surveyjs.php' ) ) {
            $message = __( 'SurveyJS Forms Pro needs the SurveyJS Forms plugin. Activate SurveyJS Forms to use Pro.', 'surveyjs-pro' );
            $link = array( admin_url( 'plugins.php' ), __( 'Go to Plugins', 'surveyjs-pro' ) );
        } else {
            $message = sprintf(
                /* translators: %s: minimum SurveyJS Forms version */
                __( 'SurveyJS Forms Pro needs the SurveyJS Forms plugin %s or later. Install and activate SurveyJS Forms to use Pro.', 'surveyjs-pro' ),
                SURVEYJS_PRO_MIN_BASE_VERSION
            );
            $link = array( admin_url( 'plugin-install.php?s=surveyjs&tab=search&type=term' ), __( 'Install SurveyJS Forms', 'surveyjs-pro' ) );
        }
        ?>
        <div class="notice notice-error surveyjs-pro-base-notice">
            <p><?php echo esc_html( $message ); ?> <a href="<?php echo esc_url( $link[0] ); ?>"><?php echo esc_html( $link[1] ); ?></a></p>
        </div>
        <?php
    }
}
