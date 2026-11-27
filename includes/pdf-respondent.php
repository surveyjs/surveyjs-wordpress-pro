<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SurveyJS_Addon_Pdf_Respondent {

    const SCRIPT = 'surveyjs-forms-addon-pdf-respondent';
    const FILE_FIELD = 'AddonPdf';
    const QUERY_VAR = 'surveyjs_pdf';
    const SAVE_ACTION = 'SurveyJS_SaveResult';
    const GENERATE_TIMEOUT = 20;

    const DELETE_HOOK = 'surveyjs_addon_delete_pdf';

    private static $attachment = null;

    const BASE_REQUIRES = array(
        'SurveyJS_SurveyJS::CORE_SCRIPT',
        'SurveyJS_SurveyJS::UI_SCRIPT',
        'SurveyJS_SurveyJS::CORE_STYLE',
        'SurveyJS_SurveyJS::CAPABILITY',
        'SurveyJS_Form_Settings::get',
        'SurveyJS_Forms::find',
        'SurveyJS_Entries::find_entry',
        'SurveyJS_Entries::format_date',
        'SurveyJS_Notification::MAX_ATTACHMENTS_SIZE',
        'SurveyJS_Client::decode_survey_json',
        'SurveyJS_Themes::decode',
        'SurveyJS_Uploaded_Files::stored_name',
        'SurveyJS_Uploaded_Files::signed_url',
        'SurveyJS_Uploaded_Files::with_admin_urls',
    );

    public static function init() {
        if ( ! SurveyJS_Addon::requires( __CLASS__, self::BASE_REQUIRES ) ) {
            return;
        }
        add_action( self::DELETE_HOOK, array( __CLASS__, 'delete_pdf' ) );
        add_filter( 'surveyjs_form_settings_defaults', array( __CLASS__, 'defaults' ) );
        add_filter( 'surveyjs_form_settings_sanitize', array( __CLASS__, 'sanitize' ), 10, 2 );
        add_action( 'surveyjs_addon_pdf_editor_rows', array( __CLASS__, 'render_editor_rows' ) );
        add_action( 'surveyjs_editor_email_rows', array( __CLASS__, 'render_email_rows' ) );
        add_filter( 'surveyjs_form_client_config', array( __CLASS__, 'client_config' ), 10, 3 );
        add_action( 'surveyjs_enqueue_form_assets', array( __CLASS__, 'enqueue' ), 10, 2 );
        add_filter( 'surveyjs_save_result_response', array( __CLASS__, 'save_result_response' ), 10, 3 );
        add_filter( 'surveyjs_notification_attachments', array( __CLASS__, 'notification_attachments' ), 10, 6 );
        add_filter( 'surveyjs_notification_tags', array( __CLASS__, 'notification_tags' ), 10, 5 );
        add_action( 'template_redirect', array( __CLASS__, 'render_link_page' ), -1 );
    }

    public static function respondent_defaults() {
        return array(
            'addonPdfDownload'           => false,
            'addonPdfAttachNotification' => false,
            'addonPdfAttachAutoReply'    => false,
            'addonPdfEmailLink'          => false,
            'addonPdfLinkDays'           => 30,
        );
    }

    public static function defaults( $defaults ) {
        return $defaults + self::respondent_defaults();
    }

    public static function sanitize( $sanitized, $input ) {
        foreach ( array( 'addonPdfDownload', 'addonPdfAttachNotification', 'addonPdfAttachAutoReply', 'addonPdfEmailLink' ) as $key ) {
            $sanitized[ $key ] = ! empty( $input[ $key ] );
        }
        $sanitized['addonPdfLinkDays'] = min( 365, max( 0, (int) $input['addonPdfLinkDays'] ) );
        return $sanitized;
    }

    public static function render_editor_rows() {
        ?>
        <tr>
            <th scope="row"><?php esc_html_e( 'After submit', 'surveyjs-forms-addon' ); ?></th>
            <td>
                <label><input type="checkbox" data-setting="addonPdfDownload" /> <?php esc_html_e( 'Show a Download PDF button to the respondent', 'surveyjs-forms-addon' ); ?></label>
                <p class="description"><?php esc_html_e( 'Shown on the completed page; the PDF of the answers is created in the respondent\'s browser.', 'surveyjs-forms-addon' ); ?></p>
            </td>
        </tr>
        <?php
    }

    public static function render_email_rows( $context ) {
        if ( 'notification' === $context || 'auto_reply' === $context ) {
            $notification = 'notification' === $context;
            ?>
            <tr data-enabled-by="<?php echo esc_attr( $notification ? 'notificationEnabled' : 'autoReplyEnabled' ); ?>">
                <th scope="row"><?php esc_html_e( 'PDF Attachment', 'surveyjs-forms-addon' ); ?></th>
                <td>
                    <label><input type="checkbox" data-setting="<?php echo esc_attr( $notification ? 'addonPdfAttachNotification' : 'addonPdfAttachAutoReply' ); ?>" /> <?php esc_html_e( 'Attach a PDF of the entry', 'surveyjs-forms-addon' ); ?></label>
                    <p class="description">
                        <?php
                        printf(
                            /* translators: 1: seconds, 2: maximum file size, e.g. "10 MB" */
                            esc_html__( 'Created in the respondent\'s browser on submit, styled as set on the PDF tab. If it takes over %1$s seconds or exceeds %2$s, the email is sent without it.', 'surveyjs-forms-addon' ),
                            (int) self::GENERATE_TIMEOUT,
                            esc_html( size_format( self::max_attachment_size() ) )
                        );
                        ?>
                    </p>
                </td>
            </tr>
            <?php
            return;
        }
        if ( 'end' !== $context ) {
            return;
        }
        ?>
        <div role="group" aria-labelledby="wp-sjs-addon-pdf-link-title">
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row" id="wp-sjs-addon-pdf-link-title"><?php esc_html_e( 'PDF link in emails', 'surveyjs-forms-addon' ); ?></th>
                    <td>
                        <label><input type="checkbox" data-setting="addonPdfEmailLink" /> <?php esc_html_e( 'Allow a link to the PDF of the entry in emails', 'surveyjs-forms-addon' ); ?></label>
                        <p class="description"><?php esc_html_e( 'Add {pdf_link} (a Download PDF link) or {pdf_url} (the address only) to the notification or auto-reply. Anyone with the link can see the entry, so send it only to the respondent or trusted addresses. Needs Store entries on the Entries tab. Turning this off disables links already sent.', 'surveyjs-forms-addon' ); ?></p>
                    </td>
                </tr>
                <tr data-enabled-by="addonPdfEmailLink">
                    <th scope="row"><label for="wp-sjs-addon-pdf-link-days"><?php esc_html_e( 'Link expires after', 'surveyjs-forms-addon' ); ?></label></th>
                    <td>
                        <input type="number" id="wp-sjs-addon-pdf-link-days" class="small-text" min="0" max="365" step="1" data-setting="addonPdfLinkDays" /> <?php esc_html_e( 'days', 'surveyjs-forms-addon' ); ?>
                        <p class="description"><?php esc_html_e( '0 means the link does not expire. Administrators can open expired links while logged in.', 'surveyjs-forms-addon' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    private static function attach_enabled( $settings ) {
        return ( ! empty( $settings['notificationEnabled'] ) && ! empty( $settings['addonPdfAttachNotification'] ) )
            || ( ! empty( $settings['autoReplyEnabled'] ) && ! empty( $settings['addonPdfAttachAutoReply'] ) );
    }

    public static function max_attachment_size() {
        return min( (int) apply_filters( 'surveyjs_addon_pdf_attachment_max_size', 10 * MB_IN_BYTES ), (int) wp_max_upload_size() );
    }

    public static function client_config( $config, $survey, $settings ) {
        $download = ! empty( $settings['addonPdfDownload'] );
        $attach = self::attach_enabled( $settings );
        if ( $download || $attach ) {
            $config['addonPdf'] = array(
                'download' => $download,
                'attach'   => $attach,
                'settings' => SurveyJS_Addon_Pdf::client_settings( $settings ),
                'logoUrl'  => SurveyJS_Addon_Pdf::logo( $survey )['url'],
            );
        }
        return $config;
    }

    public static function enqueue( $survey, $settings ) {
        if ( ! empty( $settings['addonPdfDownload'] ) || self::attach_enabled( $settings ) ) {
            self::register_script();
            wp_enqueue_script( self::SCRIPT );
        }
    }

    private static function register_script( $extra = array() ) {
        if ( wp_script_is( self::SCRIPT, 'registered' ) || ! SurveyJS_Addon_Libs::register() ) {
            return;
        }
        wp_register_script( self::SCRIPT, plugins_url( 'assets/pdf-respondent.js', SURVEYJS_ADDON_FILE ), array( SurveyJS_SurveyJS::CORE_SCRIPT ), SURVEYJS_ADDON_VERSION, true );
        $config = $extra + array(
            'scripts'     => array(
                'jspdf'    => SurveyJS_Addon_Libs::script_url( 'jspdf' ),
                'pdf'      => SurveyJS_Addon_Libs::script_url( 'pdf' ),
                'spacious' => SurveyJS_Addon_Libs::script_url( 'pdf-spacious' ),
                'pdfJs'    => add_query_arg( 'ver', SURVEYJS_ADDON_VERSION, plugins_url( 'assets/pdf.js', SURVEYJS_ADDON_FILE ) ),
            ),
            'pdf'         => array(
                'page'     => 'front',
                'format'   => SurveyJS_Addon_Pdf::page_format(),
                'locale'   => SurveyJS_Addon_Pdf::date_locale(),
                'hebrewFont' => SurveyJS_Addon_Pdf::hebrew_font(),
                'unicodeFont' => SurveyJS_Addon_Pdf::unicode_font(),
                'i18n'     => array(
                    /* translators: 1: entry ID, 2: entry date */
                    'entryHeader' => __( 'Entry #%1$s, %2$s', 'surveyjs-forms-addon' ),
                    /* translators: 1: page number, 2: number of pages */
                    'page'        => __( 'Page %1$s of %2$s', 'surveyjs-forms-addon' ),
                    'fontError'   => __( 'the font could not be loaded.', 'surveyjs-forms-addon' ),
                ),
            ),
            'field'       => self::FILE_FIELD,
            'timeout'     => self::GENERATE_TIMEOUT * 1000,
            'maxSize'     => self::max_attachment_size(),
            'postMaxSize' => wp_convert_hr_to_bytes( (string) ini_get( 'post_max_size' ) ),
            'i18n'        => array(
                'download'   => __( 'Download PDF', 'surveyjs-forms-addon' ),
                'generating' => __( 'Generating PDF…', 'surveyjs-forms-addon' ),
                'failed'     => __( 'Could not create the PDF. Try again.', 'surveyjs-forms-addon' ),
                'loadError'  => __( 'the PDF library could not be loaded.', 'surveyjs-forms-addon' ),
                /* translators: %1$s: error message */
                'error'      => __( 'Could not create the PDF: %1$s', 'surveyjs-forms-addon' ),
            ),
        );
        wp_add_inline_script( self::SCRIPT, 'window.surveyjsAddonPdfRespondent = ' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP ) . ';', 'before' );
    }

    public static function save_result_response( $response, $survey, $entry_id ) {
        $settings = SurveyJS_Form_Settings::get( $survey );
        if ( ! empty( $settings['addonPdfDownload'] ) && $entry_id ) {
            $response['addonPdf'] = array(
                'id'   => (int) $entry_id,
                'date' => SurveyJS_Entries::format_date( gmdate( 'Y-m-d H:i:s' ) ),
            );
        }
        return $response;
    }

    public static function notification_attachments( $attachments, $recipient, $survey, $settings, $data, $entry_id ) {
        $key = 'owner' === $recipient ? 'addonPdfAttachNotification' : 'addonPdfAttachAutoReply';
        $path = empty( $settings[ $key ] ) ? false : self::uploaded_pdf();
        if ( $path && 'owner' === $recipient && SurveyJS_Addon_Pdf_Password::enabled( $settings ) ) {
            $path = SurveyJS_Addon_Pdf_Password::encrypted_copy( $survey, $path );
        }
        if ( ! $path ) {
            return $attachments;
        }
        $total = (int) filesize( $path );
        foreach ( $attachments as $file ) {
            $total += is_string( $file ) && is_file( $file ) ? (int) filesize( $file ) : 0;
        }
        if ( $total > SurveyJS_Notification::MAX_ATTACHMENTS_SIZE ) {
            return $attachments;
        }
        $name = $survey->name . ( $entry_id ? '-entry-' . (int) $entry_id : '' ) . '.pdf';
        $attachments[ sanitize_file_name( $name ) ] = $path;
        return $attachments;
    }

    // The PDF comes from the respondent's browser, so it is only checked to be a PDF of a sane size, kept out of
    // the public uploads and deleted a day later (mail plugins that queue emails read attachments later);
    // the stored entry stays the source of truth.
    private static function uploaded_pdf() {
        if ( null !== self::$attachment ) {
            return self::$attachment;
        }
        self::$attachment = false;
        if ( ! doing_action( 'wp_ajax_' . self::SAVE_ACTION ) && ! doing_action( 'wp_ajax_nopriv_' . self::SAVE_ACTION ) ) {
            return false;
        }
        $file = $_FILES[ self::FILE_FIELD ] ?? null; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if ( ! is_array( $file ) || UPLOAD_ERR_OK !== ( $file['error'] ?? null ) || ! is_string( $file['tmp_name'] ?? null ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
            return false;
        }
        $size = (int) filesize( $file['tmp_name'] );
        $signature = (string) file_get_contents( $file['tmp_name'], false, null, 0, 5 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        if ( $size <= 0 || $size > self::max_attachment_size() || '%PDF-' !== $signature ) {
            return false;
        }
        $filesystem = self::temp_filesystem();
        if ( ! $filesystem ) {
            return false;
        }
        $path = self::temp_pdf_path();
        if ( ! $filesystem->move( $file['tmp_name'], $path ) ) {
            return false;
        }
        self::$attachment = $path;
        return $path;
    }

    // Temp files are not WordPress files, so the method is chosen for get_temp_dir() and only needs it to be writable.
    public static function temp_filesystem() {
        global $wp_filesystem;
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        return WP_Filesystem( false, get_temp_dir(), true ) ? $wp_filesystem : null;
    }

    public static function temp_pdf_path() {
        $path = trailingslashit( get_temp_dir() ) . 'surveyjs-forms-addon-' . wp_generate_password( 24, false ) . '.pdf';
        wp_schedule_single_event( time() + DAY_IN_SECONDS, self::DELETE_HOOK, array( wp_basename( $path ) ) );
        return $path;
    }

    public static function delete_pdf( $name ) {
        if ( is_string( $name ) && 1 === preg_match( '/^surveyjs-forms-addon-[A-Za-z0-9]{24}\.pdf$/', $name ) ) {
            wp_delete_file( trailingslashit( get_temp_dir() ) . $name );
        }
    }

    public static function notification_tags( $tags, $recipient, $survey, $data, $entry_id ) {
        $settings = SurveyJS_Form_Settings::get( $survey );
        $url = ! empty( $settings['addonPdfEmailLink'] ) && $entry_id ? self::link_url( $survey->id, $entry_id, $settings ) : '';
        $tags['text']['{pdf_url}'] = $url;
        $tags['html']['{pdf_url}'] = esc_url( $url );
        $tags['text']['{pdf_link}'] = $url;
        $tags['html']['{pdf_link}'] = '' === $url ? '' : sprintf( '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', esc_url( $url ), esc_html__( 'Download PDF', 'surveyjs-forms-addon' ) );
        return $tags;
    }

    public static function link_url( $form_id, $entry_id, $settings ) {
        $days = (int) ( $settings['addonPdfLinkDays'] ?? 0 );
        $expires = $days > 0 ? time() + $days * DAY_IN_SECONDS : 0;
        $token = implode( '.', array( (int) $form_id, (int) $entry_id, $expires, self::signature( $form_id, $entry_id, $expires ) ) );
        return add_query_arg( self::QUERY_VAR, $token, home_url( '/' ) );
    }

    private static function signature( $form_id, $entry_id, $expires ) {
        return hash_hmac( 'sha256', 'surveyjs-forms-addon-pdf|' . (int) $form_id . '|' . (int) $entry_id . '|' . (int) $expires, wp_salt( 'auth' ) );
    }

    private static function linked_entry( $token ) {
        if ( ! preg_match( '/^(\d+)\.(\d+)\.(\d+)\.([a-f0-9]{64})$/', $token, $match ) ) {
            return null;
        }
        list( , $form_id, $entry_id, $expires, $signature ) = $match;
        if ( ! hash_equals( self::signature( $form_id, $entry_id, $expires ), $signature ) ) {
            return null;
        }
        if ( (int) $expires && (int) $expires < time() && ! current_user_can( SurveyJS_SurveyJS::CAPABILITY ) ) {
            return null;
        }
        $survey = SurveyJS_Forms::find( (int) $form_id );
        $settings = $survey ? SurveyJS_Form_Settings::get( $survey ) : array();
        if ( empty( $settings['addonPdfEmailLink'] ) ) {
            return null;
        }
        $entry = SurveyJS_Entries::find_entry( $survey->id, (int) $entry_id );
        return $entry ? array( $survey, $settings, $entry ) : null;
    }

    private static function with_signed_urls( $value ) {
        if ( ! is_array( $value ) ) {
            return $value;
        }
        if ( null !== SurveyJS_Uploaded_Files::stored_name( $value ) ) {
            $value['content'] = SurveyJS_Uploaded_Files::signed_url( $value, DAY_IN_SECONDS );
            return $value;
        }
        return array_map( array( __CLASS__, 'with_signed_urls' ), $value );
    }

    public static function render_link_page() {
        if ( ! isset( $_GET[ self::QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }
        nocache_headers();
        header( 'X-Robots-Tag: noindex, nofollow' );
        header( 'Referrer-Policy: no-referrer' );
        $linked = self::linked_entry( sanitize_text_field( wp_unslash( $_GET[ self::QUERY_VAR ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! $linked ) {
            wp_die( esc_html__( 'This link is invalid or has expired.', 'surveyjs-forms-addon' ), '', array( 'response' => 403 ) );
        }
        list( $survey, $settings, $entry ) = $linked;

        $json = SurveyJS_Client::decode_survey_json( $survey->json, false );
        $data = current_user_can( SurveyJS_SurveyJS::CAPABILITY ) ? SurveyJS_Uploaded_Files::with_admin_urls( $entry->data ) : self::with_signed_urls( $entry->data );
        self::register_script( array(
            'entryPage' => array(
                'formName' => $survey->name,
                'json'     => empty( $json ) ? new stdClass() : $json,
                'theme'    => SurveyJS_Themes::decode( $survey->theme ),
                'settings' => SurveyJS_Addon_Pdf::client_settings( $settings ),
                'logoUrl'  => SurveyJS_Addon_Pdf::logo( $survey )['url'],
                'entry'    => array(
                    'id'   => (int) $entry->id,
                    'date' => SurveyJS_Entries::format_date( $entry->created_at ),
                    'data' => (object) $data,
                ),
            ),
        ) );
        /* translators: 1: form name, 2: entry ID */
        $title = sprintf( __( '%1$s: entry #%2$s', 'surveyjs-forms-addon' ), $survey->name, (int) $entry->id );
        ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="robots" content="noindex, nofollow" />
<meta name="referrer" content="no-referrer" />
<title><?php echo esc_html( $title ); ?></title>
<style>
body { margin: 0; padding: 24px 16px; font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #1d2327; background: #f0f0f1; }
main { max-width: 960px; margin: 0 auto; }
h1 { font-size: 20px; margin: 0 0 12px; }
.wp-sjs-addon-pdf-button { display: inline-block; padding: 8px 16px; border-radius: 4px; background: #2271b1; color: #fff; text-decoration: none; }
.wp-sjs-addon-pdf-frame { display: block; width: 100%; height: 80vh; margin-top: 16px; border: 1px solid #c3c4c7; background: #fff; }
</style>
</head>
<body>
<main>
<h1><?php echo esc_html( $title ); ?></h1>
<p id="wp-sjs-addon-pdf-status" role="status"><?php esc_html_e( 'Generating PDF…', 'surveyjs-forms-addon' ); ?></p>
<p><a id="wp-sjs-addon-pdf-download" class="wp-sjs-addon-pdf-button" hidden><?php esc_html_e( 'Download PDF', 'surveyjs-forms-addon' ); ?></a></p>
<iframe id="wp-sjs-addon-pdf-frame" class="wp-sjs-addon-pdf-frame" title="<?php echo esc_attr( $title ); ?>" hidden></iframe>
</main>
<?php wp_print_scripts( array( self::SCRIPT ) ); ?>
</body>
</html>
        <?php
        exit;
    }
}

add_action( 'surveyjs_addon_loaded', array( 'SurveyJS_Addon_Pdf_Respondent', 'init' ) );
