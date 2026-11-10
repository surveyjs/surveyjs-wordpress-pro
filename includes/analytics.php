<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SurveyJS_Pro_Analytics {

    const VIEW_CHARTS = 'chart';
    const SCRIPT = 'surveyjs-pro-analytics-view';
    const AJAX_ACTION = 'surveyjs_pro_analytics_entries';
    const DATE_FIELD = '_surveyjs_pro_date';
    const PAGE_SIZE = 500;
    const MAX_ENTRIES = 10000;

    public static function init() {
        add_filter( 'surveyjs_results_views', array( __CLASS__, 'views' ) );
        add_action( 'surveyjs_render_results_view', array( __CLASS__, 'render' ), 10, 2 );
        add_action( 'surveyjs_admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
        add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_entries' ) );
    }

    public static function views( $views ) {
        if ( ! SurveyJS_Pro_Libs::compatible() ) {
            return $views;
        }
        return $views + array(
            self::VIEW_CHARTS => __( 'Charts', 'surveyjs-pro' ),
        );
    }

    public static function page_size() {
        return max( 1, absint( apply_filters( 'surveyjs_pro_analytics_page_size', self::PAGE_SIZE ) ) );
    }

    public static function max_entries() {
        return absint( apply_filters( 'surveyjs_pro_analytics_max_entries', self::MAX_ENTRIES ) );
    }

    private static function bundle( $view ) {
        $bundles = array(
            self::VIEW_CHARTS => 'analytics',
        );
        return $bundles[ $view ] ?? '';
    }

    private static function nonce_action( $form_id ) {
        return 'surveyjs-pro-analytics_' . (int) $form_id;
    }

    public static function enqueue( $page ) {
        if ( 'surveyjs_results' !== $page || ! current_user_can( SurveyJS_SurveyJS::CAPABILITY ) ) {
            return;
        }
        $survey = SurveyJS_Forms::get( absint( $_GET['id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! $survey ) {
            return;
        }
        $view = SurveyJS_Results::current_view( $survey );
        $bundle = self::bundle( $view );
        if ( '' === $bundle || ! SurveyJS_Pro_Libs::enqueue( $bundle ) ) {
            return;
        }
        SurveyJS_Pro_Admin_Theme::enqueue();
        $library = SurveyJS_Pro_Libs::handle( $bundle );
        wp_enqueue_style( self::SCRIPT, plugins_url( 'assets/analytics.css', SURVEYJS_PRO_FILE ), array( $library, SurveyJS_Pro_Admin_Theme::HANDLE ), SURVEYJS_PRO_VERSION );
        wp_enqueue_script( self::SCRIPT, plugins_url( 'assets/analytics.js', SURVEYJS_PRO_FILE ), array( $library, SurveyJS_Pro_Admin_Theme::HANDLE ), SURVEYJS_PRO_VERSION, true );
        wp_add_inline_script( self::SCRIPT, 'window.surveyjsProAnalytics = ' . wp_json_encode( self::config( $survey, $view ), JSON_HEX_TAG | JSON_HEX_AMP ) . ';', 'before' );
    }

    private static function config( $survey, $view ) {
        $counts = SurveyJS_Entries::counts( $survey->id );
        $json = SurveyJS_Client::decode_survey_json( $survey->json );
        return array(
            'view'       => $view,
            'formId'     => (int) $survey->id,
            'survey'     => empty( $json ) ? new stdClass() : SurveyJS_Client::decode_survey_json( $survey->json, false ),
            'total'      => $counts['unread'] + $counts['read'],
            'maxEntries' => self::max_entries(),
            'pageSize'   => self::page_size(),
            'dateField'  => self::DATE_FIELD,
            'locale'     => self::locale(),
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'action'     => self::AJAX_ACTION,
            'nonce'      => wp_create_nonce( self::nonce_action( $survey->id ) ),
            'i18n'       => array(
                /* translators: 1: number of loaded entries, 2: number of entries to load */
                'loading'     => __( 'Loading entries: %1$s of %2$s…', 'surveyjs-pro' ),
                /* translators: 1: number of shown entries, 2: number of all submitted entries */
                'limited'     => __( 'Showing the latest %1$s of %2$s entries. Export CSV includes all of them.', 'surveyjs-pro' ),
                'noEntries'   => __( 'No entries yet.', 'surveyjs-pro' ),
                'noQuestions' => __( 'This form has no questions yet.', 'surveyjs-pro' ),
                'error'       => __( 'Could not load the entries. Reload the page to try again.', 'surveyjs-pro' ),
            ),
        );
    }

    private static function locale() {
        $language = strtolower( strtok( determine_locale(), '_' ) );
        return in_array( $language, array( 'nb', 'nn' ), true ) ? 'no' : $language;
    }

    public static function render( $view, $survey ) {
        if ( '' === self::bundle( $view ) ) {
            return;
        }
        ?>
        <div id="wp-sjs-pro-analytics" class="wp-sjs-pro-analytics" data-view="<?php echo esc_attr( $view ); ?>" data-form="<?php echo esc_attr( $survey->id ); ?>">
            <div class="wp-sjs-pro-analytics__status" aria-live="polite">
                <p class="wp-sjs-pro-analytics__message"></p>
                <progress class="wp-sjs-pro-analytics__progress" hidden></progress>
            </div>
            <div class="notice notice-info inline wp-sjs-pro-analytics__notice" hidden><p></p></div>
            <div class="wp-sjs-pro-analytics__container"></div>
        </div>
        <?php if ( ! wp_script_is( self::SCRIPT ) ) : ?>
            <div class="notice notice-error inline"><p><?php esc_html_e( 'Analytics libraries are not available.', 'surveyjs-pro' ); ?></p></div>
        <?php endif; ?>
        <?php
    }

    public static function ajax_entries() {
        $form_id = absint( $_POST['form'] ?? 0 );
        check_ajax_referer( self::nonce_action( $form_id ) );
        if ( ! current_user_can( SurveyJS_SurveyJS::CAPABILITY ) ) {
            wp_send_json_error( null, 403 );
        }
        $survey = SurveyJS_Forms::find( $form_id );
        if ( ! $survey ) {
            wp_send_json_error( null, 404 );
        }
        $limit = absint( $_POST['limit'] ?? 0 );
        $entries = SurveyJS_Entries::query( $survey->id, array(
            'limit'  => $limit ? min( $limit, self::page_size() ) : self::page_size(),
            'offset' => absint( $_POST['offset'] ?? 0 ),
        ) );
        wp_send_json_success( array_map( array( __CLASS__, 'entry_row' ), $entries ) );
    }

    private static function entry_row( $entry ) {
        $timestamp = strtotime( $entry->created_at . ' UTC' );
        return array(
            'id'       => $entry->id,
            'date'     => $timestamp > 0 ? gmdate( 'c', $timestamp ) : '',
            'data'     => (object) SurveyJS_Uploaded_Files::with_admin_urls( $entry->data ),
        );
    }
}

add_action( 'surveyjs_pro_loaded', array( 'SurveyJS_Pro_Analytics', 'init' ) );
