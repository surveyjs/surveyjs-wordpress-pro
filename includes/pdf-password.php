<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SurveyJS_Addon_Pdf_Password {

    const SETTING = 'addonPdfPassword';
    const META = '_surveyjs_addon_pdf_password';
    const ENCRYPT_ACTION = 'surveyjs_addon_pdf_encrypt';
    const SAVE_ACTION = 'surveyjs_addon_pdf_password';
    const CIPHER = 'aes-256-gcm';
    const IV_LENGTH = 12;
    const TAG_LENGTH = 16;
    // AES-256 PDF passwords are cut to 127 bytes of UTF-8 by every reader.
    const MAX_BYTES = 127;

    const BASE_REQUIRES = array(
        'SurveyJS_SurveyJS::CAPABILITY',
        'SurveyJS_Form_Settings::get',
        'SurveyJS_Forms::find',
        'SurveyJS_Forms::names',
    );

    public static function init() {
        if ( ! SurveyJS_Addon::requires( __CLASS__, self::BASE_REQUIRES ) ) {
            return;
        }
        add_filter( 'surveyjs_form_settings_defaults', array( __CLASS__, 'defaults' ) );
        add_filter( 'surveyjs_form_settings_sanitize', array( __CLASS__, 'sanitize' ), 10, 2 );
        add_action( 'surveyjs_addon_pdf_editor_rows', array( __CLASS__, 'render_editor_rows' ), 20 );
        add_action( 'wp_ajax_' . self::ENCRYPT_ACTION, array( __CLASS__, 'ajax_encrypt' ) );
        add_action( 'wp_ajax_' . self::SAVE_ACTION, array( __CLASS__, 'ajax_save' ) );
    }

    public static function available() {
        return PHP_VERSION_ID >= 80200 && function_exists( 'openssl_encrypt' ) && function_exists( 'ctype_xdigit' );
    }

    public static function defaults( $defaults ) {
        return $defaults + array( self::SETTING => false );
    }

    public static function sanitize( $sanitized, $input ) {
        $sanitized[ self::SETTING ] = ! empty( $input[ self::SETTING ] );
        return $sanitized;
    }

    public static function enabled( $settings ) {
        return ! empty( $settings[ self::SETTING ] );
    }

    public static function max_size() {
        return (int) apply_filters( 'surveyjs_addon_pdf_encrypt_max_size', 32 * MB_IN_BYTES );
    }

    private static function key() {
        return hash_hmac( 'sha256', 'surveyjs-forms-addon-pdf-password', wp_salt( 'auth' ), true );
    }

    // Kept encrypted with a key from the site's salts, so a database dump alone does not reveal it;
    // the form ID is the associated data, so a value copied to another form does not decrypt.
    public static function password( $form_id ) {
        $stored = base64_decode( (string) get_post_meta( (int) $form_id, self::META, true ), true );
        if ( ! self::available() || ! $stored || strlen( $stored ) <= self::IV_LENGTH + self::TAG_LENGTH ) {
            return '';
        }
        $password = openssl_decrypt(
            substr( $stored, self::IV_LENGTH + self::TAG_LENGTH ),
            self::CIPHER,
            self::key(),
            OPENSSL_RAW_DATA,
            substr( $stored, 0, self::IV_LENGTH ),
            substr( $stored, self::IV_LENGTH, self::TAG_LENGTH ),
            (string) (int) $form_id
        );
        return false === $password ? '' : $password;
    }

    private static function store( $form_id, $password ) {
        $iv = random_bytes( self::IV_LENGTH );
        $tag = '';
        $encrypted = openssl_encrypt( $password, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag, (string) (int) $form_id, self::TAG_LENGTH );
        if ( false === $encrypted ) {
            return false;
        }
        update_post_meta( (int) $form_id, self::META, base64_encode( $iv . $tag . $encrypted ) );
        return self::password( $form_id ) === $password;
    }

    // Readers apply SASLprep to the typed password; NFKC is the part of it that changes common input.
    private static function normalize( $password ) {
        if ( class_exists( 'Normalizer' ) ) {
            $password = Normalizer::normalize( $password, Normalizer::FORM_KC );
        }
        if ( ! is_string( $password ) || '' === $password || strlen( $password ) > self::MAX_BYTES || wp_check_invalid_utf8( $password ) !== $password || preg_match( '/[\x00-\x1F\x7F]/', $password ) ) {
            return '';
        }
        return $password;
    }

    public static function encrypt( $form_id, $pdf ) {
        $password = self::password( $form_id );
        if ( '' === $password ) {
            throw new UnexpectedValueException( 'No password is set.' );
        }
        return SurveyJS_Addon_Pdf_Encryptor::encrypt( $pdf, $password );
    }

    public static function encrypted_copy( $survey, $path ) {
        try {
            $pdf = self::encrypt( $survey->id, (string) file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        } catch ( Throwable $error ) {
            return false;
        }
        $filesystem = SurveyJS_Addon_Pdf_Respondent::temp_filesystem();
        if ( ! $filesystem ) {
            return false;
        }
        $copy = SurveyJS_Addon_Pdf_Respondent::temp_pdf_path();
        return $filesystem->put_contents( $copy, $pdf, FS_CHMOD_FILE ) ? $copy : false;
    }

    public static function client_config( $config, $survey ) {
        if ( ! self::available() ) {
            return $config;
        }
        $config['password'] = array(
            'enabled'    => self::enabled( SurveyJS_Form_Settings::get( $survey ) ),
            'isSet'      => '' !== self::password( $survey->id ),
            'saveAction' => self::SAVE_ACTION,
            'encryptUrl' => add_query_arg( array(
                'action'      => self::ENCRYPT_ACTION,
                'form'        => (int) $survey->id,
                '_ajax_nonce' => $config['nonce'],
            ), admin_url( 'admin-ajax.php' ) ),
        );
        $config['i18n'] += array(
            'encryptError'      => __( 'the PDF could not be protected with the password, so it was not downloaded.', 'surveyjs-forms-addon' ),
            'passwordSaved'     => __( 'PDF password saved.', 'surveyjs-forms-addon' ),
            'passwordRemoved'   => __( 'PDF password removed. PDFs of this form are no longer protected.', 'surveyjs-forms-addon' ),
            'passwordMissing'   => __( 'Enter a PDF password and save the form. Until then, PDFs of this form cannot be downloaded and are not attached to the email notification.', 'surveyjs-forms-addon' ),
            'passwordUnsaved'   => __( 'save the form first to use the new password.', 'surveyjs-forms-addon' ),
            /* translators: %1$s: error message */
            'passwordSaveError' => __( 'The PDF password was not saved: %1$s', 'surveyjs-forms-addon' ),
            'requestError'      => __( 'the server did not respond. Try again.', 'surveyjs-forms-addon' ),
        );
        return $config;
    }

    public static function render_editor_rows( $survey = null ) {
        $available = self::available();
        $is_set = $available && $survey && '' !== self::password( $survey->id );
        ?>
        <tr>
            <th scope="row"><?php esc_html_e( 'Password', 'surveyjs-forms-addon' ); ?></th>
            <td>
                <label><input type="checkbox" data-setting="<?php echo esc_attr( self::SETTING ); ?>" <?php disabled( ! $available ); ?> /> <?php esc_html_e( 'Password protect PDF', 'surveyjs-forms-addon' ); ?></label>
                <p class="description"><?php esc_html_e( 'Encrypts with AES-256 the PDFs you download from Entries or this tab and the PDF attached to the email notification. Not protected: this preview, the respondent\'s Download PDF button, the auto-reply attachment and the {pdf_link} page. Old PDF viewers without AES-256 cannot open them.', 'surveyjs-forms-addon' ); ?></p>
                <?php if ( ! $available ) : ?>
                    <p class="description"><?php esc_html_e( 'Not available on this site: it needs PHP 8.2 or later with the OpenSSL and ctype extensions.', 'surveyjs-forms-addon' ); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <tr data-enabled-by="<?php echo esc_attr( self::SETTING ); ?>">
            <th scope="row"><label for="wp-sjs-addon-pdf-password"><?php esc_html_e( 'PDF password', 'surveyjs-forms-addon' ); ?></label></th>
            <td>
                <input type="password" id="wp-sjs-addon-pdf-password" class="regular-text" autocomplete="new-password" spellcheck="false" />
                <p class="description"><?php esc_html_e( 'One password for all PDFs of this form. To change it, type a new one; to remove it, turn off Password protect PDF and save the form.', 'surveyjs-forms-addon' ); ?></p>
            </td>
        </tr>
        <?php
    }

    private static function form_for_request( $form_id ) {
        if ( ! current_user_can( SurveyJS_SurveyJS::CAPABILITY ) ) {
            wp_send_json_error( null, 403 );
        }
        $survey = SurveyJS_Forms::find( $form_id );
        if ( ! $survey ) {
            wp_send_json_error( null, 404 );
        }
        return $survey;
    }

    private static function fail( $message, $status ) {
        wp_send_json_error( array( 'message' => $message ), $status );
    }

    public static function ajax_save() {
        $form_id = absint( $_POST['form'] ?? 0 );
        check_ajax_referer( SurveyJS_Addon_Pdf::nonce_action( $form_id ) );
        $survey = self::form_for_request( $form_id );
        if ( ! empty( $_POST['remove'] ) ) {
            delete_post_meta( $survey->id, self::META );
            wp_send_json_success( array( 'set' => false ) );
        }
        if ( ! self::available() ) {
            self::fail( __( 'password protection needs PHP 8.2 or later with the OpenSSL and ctype extensions.', 'surveyjs-forms-addon' ), 500 );
        }
        $password = self::normalize( (string) wp_unslash( $_POST['password'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- checked by normalize(), kept as typed.
        if ( '' === $password ) {
            /* translators: %d: maximum password length in bytes */
            self::fail( sprintf( __( 'the password must be 1 to %d bytes long and must not contain control characters.', 'surveyjs-forms-addon' ), self::MAX_BYTES ), 400 );
        }
        if ( ! self::store( $survey->id, $password ) ) {
            self::fail( __( 'the password could not be stored.', 'surveyjs-forms-addon' ), 500 );
        }
        wp_send_json_success( array( 'set' => true ) );
    }

    public static function ajax_encrypt() {
        $form_id = absint( $_GET['form'] ?? 0 );
        check_ajax_referer( SurveyJS_Addon_Pdf::nonce_action( $form_id ) );
        $survey = self::form_for_request( $form_id );
        if ( ! self::available() ) {
            self::fail( __( 'password protection needs PHP 8.2 or later with the OpenSSL and ctype extensions.', 'surveyjs-forms-addon' ), 500 );
        }
        if ( '' === self::password( $survey->id ) ) {
            self::fail( __( 'no PDF password is set for this form. Enter it on the PDF tab of the form editor.', 'surveyjs-forms-addon' ), 409 );
        }
        $max = self::max_size();
        $too_large = sprintf(
            /* translators: %s: maximum file size, e.g. "32 MB" */
            __( 'the PDF is larger than %s.', 'surveyjs-forms-addon' ),
            size_format( $max )
        );
        if ( absint( wp_unslash( $_SERVER['CONTENT_LENGTH'] ?? 0 ) ) > $max ) {
            self::fail( $too_large, 413 );
        }
        $pdf = (string) file_get_contents( 'php://input', false, null, 0, $max + 1 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        if ( strlen( $pdf ) > $max ) {
            self::fail( $too_large, 413 );
        }
        if ( 0 !== strncmp( $pdf, '%PDF-', 5 ) ) {
            self::fail( __( 'the request did not contain a PDF.', 'surveyjs-forms-addon' ), 400 );
        }
        try {
            $pdf = self::encrypt( $survey->id, $pdf );
        } catch ( Throwable $error ) {
            self::fail( __( 'the PDF could not be protected with the password, so it was not downloaded.', 'surveyjs-forms-addon' ), 422 );
        }
        nocache_headers();
        header( 'Content-Type: application/pdf' );
        header( 'Cache-Control: no-store' );
        header( 'X-Content-Type-Options: nosniff' );
        echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary PDF.
        exit;
    }
}

add_action( 'surveyjs_addon_loaded', array( 'SurveyJS_Addon_Pdf_Password', 'init' ) );
