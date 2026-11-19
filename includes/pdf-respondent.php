<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SurveyJS_Pro_Pdf_Respondent {

    const SCRIPT = 'surveyjs-pro-pdf-respondent';
    const FILE_FIELD = 'ProPdf';
    const QUERY_VAR = 'surveyjs_pdf';
    const SAVE_ACTION = 'SurveyJS_SaveResult';
    const GENERATE_TIMEOUT = 20;

    const DELETE_HOOK = 'surveyjs_pro_delete_pdf';

    private static $attachment = null;

    public static function init() {
        add_action( self::DELETE_HOOK, array( __CLASS__, 'delete_pdf' ) );
        add_filter( 'surveyjs_form_settings_defaults', array( __CLASS__, 'defaults' ) );
        add_filter( 'surveyjs_form_settings_sanitize', array( __CLASS__, 'sanitize' ), 10, 2 );
        add_action( 'surveyjs_pro_pdf_editor_rows', array( __CLASS__, 'render_editor_rows' ) );
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
            'proPdfDownload'           => false,
            'proPdfAttachNotification' => false,
            'proPdfAttachAutoReply'    => false,
            'proPdfEmailLink'          => false,
            'proPdfLinkDays'           => 30,
        );
    }

    public static function defaults( $defaults ) {
        return $defaults + self::respondent_defaults();
    }

    public static function sanitize( $sanitized, $input ) {
        foreach ( array( 'proPdfDownload', 'proPdfAttachNotification', 'proPdfAttachAutoReply', 'proPdfEmailLink' ) as $key ) {
            $sanitized[ $key ] = ! empty( $input[ $key ] );
        }
        $sanitized['proPdfLinkDays'] = min( 365, max( 0, (int) $input['proPdfLinkDays'] ) );
        return $sanitized;
    }

    public static function render_editor_rows() {
        ?>
        <tr>
            <th scope="row"><?php esc_html_e( 'After submit', 'surveyjs-pro' ); ?></th>
            <td>
                <label><input type="checkbox" data-setting="proPdfDownload" /> <?php esc_html_e( 'Show a Download PDF button to the respondent', 'surveyjs-pro' ); ?></label>
                <p class="description"><?php esc_html_e( 'The button appears on the completed page of the form on your site and creates a PDF of the respondent\'s answers in their browser.', 'surveyjs-pro' ); ?></p>
            </td>
        </tr>
        <?php
    }

    public static function render_email_rows( $context ) {
        if ( 'notification' === $context || 'auto_reply' === $context ) {
            $notification = 'notification' === $context;
            ?>
            <tr data-enabled-by="<?php echo esc_attr( $notification ? 'notificationEnabled' : 'autoReplyEnabled' ); ?>">
                <th scope="row"><?php esc_html_e( 'PDF Attachment', 'surveyjs-pro' ); ?></th>
                <td>
                    <label><input type="checkbox" data-setting="<?php echo esc_attr( $notification ? 'proPdfAttachNotification' : 'proPdfAttachAutoReply' ); ?>" /> <?php esc_html_e( 'Attach a PDF of the entry', 'surveyjs-pro' ); ?></label>
                    <p class="description">
                        <?php
                        printf(
                            /* translators: 1: seconds, 2: maximum file size, e.g. "10 MB" */
                            esc_html__( 'The respondent\'s browser creates the PDF when the form is submitted and sends it with the entry. If it is not ready in %1$s seconds or is larger than %2$s, the email is sent without it. The PDF looks as set on the PDF tab.', 'surveyjs-pro' ),
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
        <div role="group" aria-labelledby="wp-sjs-pro-pdf-link-title">
            <h2 class="title" id="wp-sjs-pro-pdf-link-title"><?php esc_html_e( 'PDF Link in Emails', 'surveyjs-pro' ); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e( 'PDF link', 'surveyjs-pro' ); ?></th>
                    <td>
                        <label><input type="checkbox" data-setting="proPdfEmailLink" /> <?php esc_html_e( 'Allow a link to the PDF of the entry in emails', 'surveyjs-pro' ); ?></label>
                        <p class="description"><?php esc_html_e( 'Add {pdf_link} (a Download PDF link) or {pdf_url} (the address only) to the email notification or the auto-reply message. The link opens a page that creates the PDF of that entry in the browser. Anyone with the link can see the entry, so send it only to the respondent or to trusted addresses. Needs "Save submitted entries in the database" on the Entries tab. Turning this option off disables the links already sent.', 'surveyjs-pro' ); ?></p>
                    </td>
                </tr>
                <tr data-enabled-by="proPdfEmailLink">
                    <th scope="row"><label for="wp-sjs-pro-pdf-link-days"><?php esc_html_e( 'Link expires after', 'surveyjs-pro' ); ?></label></th>
                    <td>
                        <input type="number" id="wp-sjs-pro-pdf-link-days" class="small-text" min="0" max="365" step="1" data-setting="proPdfLinkDays" /> <?php esc_html_e( 'days', 'surveyjs-pro' ); ?>
                        <p class="description"><?php esc_html_e( '0 means the link does not expire. Administrators can open expired links while logged in.', 'surveyjs-pro' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    private static function attach_enabled( $settings ) {
        return ( ! empty( $settings['notificationEnabled'] ) && ! empty( $settings['proPdfAttachNotification'] ) )
            || ( ! empty( $settings['autoReplyEnabled'] ) && ! empty( $settings['proPdfAttachAutoReply'] ) );
    }

    public static function max_attachment_size() {
        return min( (int) apply_filters( 'surveyjs_pro_pdf_attachment_max_size', 10 * MB_IN_BYTES ), (int) wp_max_upload_size() );
    }

    public static function client_config( $config, $survey, $settings ) {
        $download = ! empty( $settings['proPdfDownload'] );
        $attach = self::attach_enabled( $settings );
        if ( $download || $attach ) {
            $config['proPdf'] = array(
                'download' => $download,
                'attach'   => $attach,
                'settings' => SurveyJS_Pro_Pdf::client_settings( $settings ),
                'logoUrl'  => SurveyJS_Pro_Pdf::logo( $survey )['url'],
            );
        }
        return $config;
    }

    public static function enqueue( $survey, $settings ) {
        if ( ! empty( $settings['proPdfDownload'] ) || self::attach_enabled( $settings ) ) {
            self::register_script();
            wp_enqueue_script( self::SCRIPT );
        }
    }

    private static function register_script( $extra = array() ) {
        if ( wp_script_is( self::SCRIPT, 'registered' ) || ! SurveyJS_Pro_Libs::register() ) {
            return;
        }
        wp_register_script( self::SCRIPT, plugins_url( 'assets/pdf-respondent.js', SURVEYJS_PRO_FILE ), array( SurveyJS_Pro_Libs::LICENSE_HANDLE ), SURVEYJS_PRO_VERSION, true );
        $config = $extra + array(
            'scripts'     => array(
                'jspdf'    => SurveyJS_Pro_Libs::script_url( 'jspdf' ),
                'pdf'      => SurveyJS_Pro_Libs::script_url( 'pdf' ),
                'spacious' => SurveyJS_Pro_Libs::script_url( 'pdf-spacious' ),
                'pdfJs'    => add_query_arg( 'ver', SURVEYJS_PRO_VERSION, plugins_url( 'assets/pdf.js', SURVEYJS_PRO_FILE ) ),
            ),
            'pdf'         => array(
                'page'     => 'front',
                'format'   => SurveyJS_Pro_Pdf::page_format(),
                'locale'   => SurveyJS_Pro_Pdf::date_locale(),
                'hebrewFont' => SurveyJS_Pro_Pdf::hebrew_font(),
                'unicodeFont' => SurveyJS_Pro_Pdf::unicode_font(),
                'i18n'     => array(
                    /* translators: 1: entry ID, 2: entry date */
                    'entryHeader' => __( 'Entry #%1$s, %2$s', 'surveyjs-pro' ),
                    /* translators: 1: page number, 2: number of pages */
                    'page'        => __( 'Page %1$s of %2$s', 'surveyjs-pro' ),
                    'fontError'   => __( 'the font could not be loaded.', 'surveyjs-pro' ),
                ),
            ),
            'field'       => self::FILE_FIELD,
            'timeout'     => self::GENERATE_TIMEOUT * 1000,
            'maxSize'     => self::max_attachment_size(),
            'postMaxSize' => wp_convert_hr_to_bytes( (string) ini_get( 'post_max_size' ) ),
            'i18n'        => array(
                'download'   => __( 'Download PDF', 'surveyjs-pro' ),
                'generating' => __( 'Generating PDF…', 'surveyjs-pro' ),
                'failed'     => __( 'Could not create the PDF. Try again.', 'surveyjs-pro' ),
                'loadError'  => __( 'the PDF library could not be loaded.', 'surveyjs-pro' ),
                /* translators: %1$s: error message */
                'error'      => __( 'Could not create the PDF: %1$s', 'surveyjs-pro' ),
            ),
        );
        wp_add_inline_script( self::SCRIPT, 'window.surveyjsProPdfRespondent = ' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP ) . ';', 'before' );
    }

    public static function save_result_response( $response, $survey, $entry_id ) {
        $settings = SurveyJS_Form_Settings::get( $survey );
        if ( ! empty( $settings['proPdfDownload'] ) && $entry_id ) {
            $response['proPdf'] = array(
                'id'   => (int) $entry_id,
                'date' => SurveyJS_Entries::format_date( gmdate( 'Y-m-d H:i:s' ) ),
            );
        }
        return $response;
    }

    public static function notification_attachments( $attachments, $recipient, $survey, $settings, $data, $entry_id ) {
        $key = 'owner' === $recipient ? 'proPdfAttachNotification' : 'proPdfAttachAutoReply';
        $path = empty( $settings[ $key ] ) ? false : self::uploaded_pdf();
        if ( $path && 'owner' === $recipient && SurveyJS_Pro_Pdf_Password::enabled( $settings ) ) {
            $path = SurveyJS_Pro_Pdf_Password::encrypted_copy( $survey, $path );
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
        $path = trailingslashit( get_temp_dir() ) . 'surveyjs-pro-' . wp_generate_password( 24, false ) . '.pdf';
        wp_schedule_single_event( time() + DAY_IN_SECONDS, self::DELETE_HOOK, array( wp_basename( $path ) ) );
        return $path;
    }

    public static function delete_pdf( $name ) {
        if ( is_string( $name ) && 1 === preg_match( '/^surveyjs-pro-[A-Za-z0-9]{24}\.pdf$/', $name ) ) {
            wp_delete_file( trailingslashit( get_temp_dir() ) . $name );
        }
    }

    public static function notification_tags( $tags, $recipient, $survey, $data, $entry_id ) {
        $settings = SurveyJS_Form_Settings::get( $survey );
        $url = ! empty( $settings['proPdfEmailLink'] ) && $entry_id ? self::link_url( $survey->id, $entry_id, $settings ) : '';
        $tags['text']['{pdf_url}'] = $url;
        $tags['html']['{pdf_url}'] = esc_url( $url );
        $tags['text']['{pdf_link}'] = $url;
        $tags['html']['{pdf_link}'] = '' === $url ? '' : sprintf( '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', esc_url( $url ), esc_html__( 'Download PDF', 'surveyjs-pro' ) );
        return $tags;
    }

    public static function link_url( $form_id, $entry_id, $settings ) {
        $days = (int) ( $settings['proPdfLinkDays'] ?? 0 );
        $expires = $days > 0 ? time() + $days * DAY_IN_SECONDS : 0;
        $token = implode( '.', array( (int) $form_id, (int) $entry_id, $expires, self::signature( $form_id, $entry_id, $expires ) ) );
        return add_query_arg( self::QUERY_VAR, $token, home_url( '/' ) );
    }

    private static function signature( $form_id, $entry_id, $expires ) {
        return hash_hmac( 'sha256', 'surveyjs-pro-pdf|' . (int) $form_id . '|' . (int) $entry_id . '|' . (int) $expires, wp_salt( 'auth' ) );
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
        if ( empty( $settings['proPdfEmailLink'] ) ) {
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
            wp_die( esc_html__( 'This link is invalid or has expired.', 'surveyjs-pro' ), '', array( 'response' => 403 ) );
        }
        list( $survey, $settings, $entry ) = $linked;

        $json = SurveyJS_Client::decode_survey_json( $survey->json, false );
        $data = current_user_can( SurveyJS_SurveyJS::CAPABILITY ) ? SurveyJS_Uploaded_Files::with_admin_urls( $entry->data ) : self::with_signed_urls( $entry->data );
        self::register_script( array(
            'entryPage' => array(
                'formName' => $survey->name,
                'json'     => empty( $json ) ? new stdClass() : $json,
                'theme'    => SurveyJS_Themes::decode( $survey->theme ),
                'settings' => SurveyJS_Pro_Pdf::client_settings( $settings ),
                'logoUrl'  => SurveyJS_Pro_Pdf::logo( $survey )['url'],
                'entry'    => array(
                    'id'   => (int) $entry->id,
                    'date' => SurveyJS_Entries::format_date( $entry->created_at ),
                    'data' => (object) $data,
                ),
            ),
        ) );
        /* translators: 1: form name, 2: entry ID */
        $title = sprintf( __( '%1$s: entry #%2$s', 'surveyjs-pro' ), $survey->name, (int) $entry->id );
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
.wp-sjs-pro-pdf-button { display: inline-block; padding: 8px 16px; border-radius: 4px; background: #2271b1; color: #fff; text-decoration: none; }
.wp-sjs-pro-pdf-frame { display: block; width: 100%; height: 80vh; margin-top: 16px; border: 1px solid #c3c4c7; background: #fff; }
</style>
</head>
<body>
<main>
<h1><?php echo esc_html( $title ); ?></h1>
<p id="wp-sjs-pro-pdf-status" role="status"><?php esc_html_e( 'Generating PDF…', 'surveyjs-pro' ); ?></p>
<p><a id="wp-sjs-pro-pdf-download" class="wp-sjs-pro-pdf-button" hidden><?php esc_html_e( 'Download PDF', 'surveyjs-pro' ); ?></a></p>
<iframe id="wp-sjs-pro-pdf-frame" class="wp-sjs-pro-pdf-frame" title="<?php echo esc_attr( $title ); ?>" hidden></iframe>
</main>
<?php wp_print_scripts( array( self::SCRIPT ) ); ?>
</body>
</html>
        <?php
        exit;
    }
}

add_action( 'surveyjs_pro_loaded', array( 'SurveyJS_Pro_Pdf_Respondent', 'init' ) );
