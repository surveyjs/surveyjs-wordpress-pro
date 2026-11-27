<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SurveyJS_Pro_Pdf {

    const TAB = 'pdf';
    const BULK_ACTION = 'pro_pdf';
    const AJAX_ACTION = 'surveyjs_pro_pdf_entries';
    const SCRIPT = 'surveyjs-pro-pdf-admin';
    const MAX_ENTRIES = 500;
    const DEFAULT_ACCENT = '#19b394';
    const LETTER_LOCALES = array( 'en_US', 'en_CA', 'fr_CA', 'es_MX', 'es_CL', 'es_CO', 'es_VE', 'es_GT', 'es_CR', 'es_PR', 'es_US' );

    public static function init() {
        add_filter( 'surveyjs_form_settings_defaults', array( __CLASS__, 'defaults' ) );
        add_filter( 'surveyjs_form_settings_sanitize', array( __CLASS__, 'sanitize' ), 10, 2 );
        add_filter( 'surveyjs_editor_tabs', array( __CLASS__, 'editor_tabs' ) );
        add_action( 'surveyjs_render_editor', array( __CLASS__, 'render_editor_panel' ), 10, 2 );
        add_action( 'surveyjs_admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
        add_action( 'surveyjs_results_title_actions', array( __CLASS__, 'render_title_actions' ) );
        add_filter( 'surveyjs_entry_row_actions', array( __CLASS__, 'entry_row_actions' ), 10, 2 );
        add_filter( 'surveyjs_entries_bulk_actions', array( __CLASS__, 'bulk_actions' ) );
        add_action( 'surveyjs_entries_bulk_action_' . self::BULK_ACTION, array( __CLASS__, 'handle_bulk_action' ), 10, 3 );
        add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_entries' ) );
    }

    public static function page_format() {
        return (string) apply_filters( 'surveyjs_pro_pdf_page_format', in_array( get_locale(), self::LETTER_LOCALES, true ) ? 'letter' : 'a4' );
    }

    public static function formats() {
        $sizes = array(
            'a4'     => __( 'A4', 'surveyjs-pro' ),
            'letter' => __( 'Letter', 'surveyjs-pro' ),
            'legal'  => __( 'Legal', 'surveyjs-pro' ),
            'a3'     => __( 'A3', 'surveyjs-pro' ),
            'a5'     => __( 'A5', 'surveyjs-pro' ),
        );
        $auto = $sizes[ self::page_format() ] ?? self::page_format();
        /* translators: %s: page size picked from the site language, e.g. "A4" */
        return array( 'auto' => sprintf( __( 'Automatic: %s, from the site language', 'surveyjs-pro' ), $auto ) ) + $sizes;
    }

    public static function orientations() {
        return array(
            'portrait'  => __( 'Portrait', 'surveyjs-pro' ),
            'landscape' => __( 'Landscape', 'surveyjs-pro' ),
        );
    }

    public static function layouts() {
        return array(
            'compact'  => __( 'Compact', 'surveyjs-pro' ),
            'spacious' => __( 'Spacious', 'surveyjs-pro' ),
        );
    }

    public static function fonts() {
        return array(
            'helvetica' => __( 'Sans-serif (Helvetica)', 'surveyjs-pro' ),
            'times'     => __( 'Serif (Times)', 'surveyjs-pro' ),
            'courier'   => __( 'Monospace (Courier)', 'surveyjs-pro' ),
        );
    }

    public static function pdf_defaults() {
        return array(
            'proPdfFormat'      => 'auto',
            'proPdfOrientation' => 'portrait',
            'proPdfFontSize'    => 14,
            'proPdfLabelFontSize' => 14,
            'proPdfLayout'      => 'compact',
            'proPdfFont'        => 'helvetica',
            'proPdfThemeColors' => false,
            'proPdfAccent'      => false,
            'proPdfAccentColor' => self::DEFAULT_ACCENT,
            'proPdfFillable'    => true,
            'proPdfEntryHeader' => true,
            'proPdfPageNumbers' => true,
            'proPdfLogo'        => 0,
            'proPdfHeaderText'  => '',
            'proPdfFooterText'  => '',
            'proPdfFooterDate'  => false,
            'proPdfWatermark'   => '',
            'proPdfHideEmpty'   => false,
        );
    }

    public static function defaults( $defaults ) {
        return $defaults + self::pdf_defaults();
    }

    public static function sanitize( $sanitized, $input ) {
        $format = (string) $input['proPdfFormat'];
        $sanitized['proPdfFormat'] = isset( self::formats()[ $format ] ) ? $format : self::pdf_defaults()['proPdfFormat'];
        $orientation = (string) ( $input['proPdfOrientation'] ?? '' );
        $sanitized['proPdfOrientation'] = isset( self::orientations()[ $orientation ] ) ? $orientation : self::pdf_defaults()['proPdfOrientation'];
        $layout = (string) $input['proPdfLayout'];
        $sanitized['proPdfLayout'] = isset( self::layouts()[ $layout ] ) ? $layout : self::pdf_defaults()['proPdfLayout'];
        $font = (string) ( $input['proPdfFont'] ?? '' );
        $sanitized['proPdfFont'] = isset( self::fonts()[ $font ] ) ? $font : self::pdf_defaults()['proPdfFont'];
        $sanitized['proPdfAccentColor'] = self::sanitize_color( $input['proPdfAccentColor'] ?? '' );
        $sanitized['proPdfFontSize'] = min( 24, max( 8, (int) $input['proPdfFontSize'] ) );
        $sanitized['proPdfLabelFontSize'] = isset( $input['proPdfLabelFontSize'] ) ? min( 24, max( 8, (int) $input['proPdfLabelFontSize'] ) ) : $sanitized['proPdfFontSize'];
        $logo = absint( $input['proPdfLogo'] );
        $sanitized['proPdfLogo'] = $logo && wp_attachment_is_image( $logo ) ? $logo : 0;
        $sanitized['proPdfHeaderText'] = mb_substr( sanitize_text_field( $input['proPdfHeaderText'] ), 0, 100 );
        $sanitized['proPdfFooterText'] = mb_substr( sanitize_text_field( $input['proPdfFooterText'] ?? '' ), 0, 100 );
        $sanitized['proPdfWatermark'] = mb_substr( sanitize_text_field( $input['proPdfWatermark'] ), 0, 40 );
        foreach ( array( 'proPdfThemeColors', 'proPdfAccent', 'proPdfFillable', 'proPdfEntryHeader', 'proPdfPageNumbers', 'proPdfFooterDate', 'proPdfHideEmpty' ) as $key ) {
            $sanitized[ $key ] = ! empty( $input[ $key ] );
        }
        return $sanitized;
    }

    private static function sanitize_color( $value ) {
        $color = sanitize_hex_color( is_string( $value ) ? trim( $value ) : '' );
        if ( ! $color ) {
            return self::DEFAULT_ACCENT;
        }
        if ( 4 === strlen( $color ) ) {
            $color = '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3];
        }
        return strtolower( $color );
    }

    public static function date_locale() {
        return str_replace( '_', '-', get_locale() );
    }

    private static function site_logo_id() {
        foreach ( array( (int) get_theme_mod( 'custom_logo' ), (int) get_option( 'site_logo' ), (int) get_option( 'site_icon' ) ) as $id ) {
            if ( $id && wp_attachment_is_image( $id ) ) {
                return $id;
            }
        }
        return 0;
    }

    public static function site_branding() {
        $id = self::site_logo_id();
        $url = $id ? wp_get_attachment_image_url( $id, 'large' ) : '';
        return array(
            'logo'  => $url ? array( 'id' => $id, 'url' => $url ) : null,
            'title' => mb_substr( html_entity_decode( wp_strip_all_tags( get_bloginfo( 'name' ) ), ENT_QUOTES, 'UTF-8' ), 0, 100 ),
        );
    }

    public static function client_settings( $settings ) {
        return array_intersect_key( $settings, self::pdf_defaults() ) + array( 'themeMode' => $settings['themeMode'] );
    }

    public static function logo( $survey ) {
        $id = (int) SurveyJS_Form_Settings::get( $survey )['proPdfLogo'];
        $url = $id ? wp_get_attachment_image_url( $id, 'large' ) : '';
        return array( 'id' => $id, 'url' => $url ? $url : '' );
    }

    public static function editor_tabs( $tabs ) {
        return $tabs + array( self::TAB => __( 'PDF', 'surveyjs-pro' ) );
    }

    private static function render_select( $id, $setting, $options ) {
        printf( '<select id="%1$s" data-setting="%2$s">', esc_attr( $id ), esc_attr( $setting ) );
        foreach ( $options as $value => $label ) {
            printf( '<option value="%1$s">%2$s</option>', esc_attr( $value ), esc_html( $label ) );
        }
        echo '</select>';
    }

    public static function render_editor_panel( $survey, $initial_tab = null ) {
        $branding = self::site_branding();
        ?>
        <div class="wp-sjs-editor__settings wp-sjs-pro-pdf" data-panel="<?php echo esc_attr( self::TAB ); ?>"<?php echo self::TAB === $initial_tab ? '' : ' hidden'; ?>>
            <div class="wp-sjs-pro-pdf__settings">
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="wp-sjs-pro-pdf-format"><?php esc_html_e( 'Page size', 'surveyjs-pro' ); ?></label></th>
                    <td>
                        <?php self::render_select( 'wp-sjs-pro-pdf-format', 'proPdfFormat', self::formats() ); ?>
                        <p class="description"><?php esc_html_e( 'Automatic: Letter for US, Canada and Latin America site languages, A4 for others.', 'surveyjs-pro' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wp-sjs-pro-pdf-orientation"><?php esc_html_e( 'Page orientation', 'surveyjs-pro' ); ?></label></th>
                    <td><?php self::render_select( 'wp-sjs-pro-pdf-orientation', 'proPdfOrientation', self::orientations() ); ?></td>
                </tr>
                <tr>
                    <th scope="row"><label for="wp-sjs-pro-pdf-font-size"><?php esc_html_e( 'Font size', 'surveyjs-pro' ); ?></label></th>
                    <td>
                        <input type="number" id="wp-sjs-pro-pdf-font-size" class="small-text" min="8" max="24" step="1" data-setting="proPdfFontSize" /> <?php esc_html_e( 'pt', 'surveyjs-pro' ); ?>
                        <p class="description"><?php esc_html_e( 'Size of answers. Headings and spacing scale with it.', 'surveyjs-pro' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wp-sjs-pro-pdf-label-font-size"><?php esc_html_e( 'Label font size', 'surveyjs-pro' ); ?></label></th>
                    <td>
                        <input type="number" id="wp-sjs-pro-pdf-label-font-size" class="small-text" min="8" max="24" step="1" data-setting="proPdfLabelFontSize" /> <?php esc_html_e( 'pt', 'surveyjs-pro' ); ?>
                        <p class="description"><?php esc_html_e( 'Size of question titles. Keep it equal to Font size for the default proportions.', 'surveyjs-pro' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wp-sjs-pro-pdf-font"><?php esc_html_e( 'Font', 'surveyjs-pro' ); ?></label></th>
                    <td>
                        <?php self::render_select( 'wp-sjs-pro-pdf-font', 'proPdfFont', self::fonts() ); ?>
                        <p class="description"><?php esc_html_e( 'For Cyrillic, Greek, Hebrew and other letters these fonts lack, a suitable font is used instead.', 'surveyjs-pro' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wp-sjs-pro-pdf-layout"><?php esc_html_e( 'Layout', 'surveyjs-pro' ); ?></label></th>
                    <td>
                        <?php self::render_select( 'wp-sjs-pro-pdf-layout', 'proPdfLayout', self::layouts() ); ?>
                        <p class="description"><?php esc_html_e( 'Compact fits more questions on a page. Spacious adds room between questions and is easier to read.', 'surveyjs-pro' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Colors', 'surveyjs-pro' ); ?></th>
                    <td>
                        <label><input type="checkbox" data-setting="proPdfThemeColors" /> <?php esc_html_e( 'Use the colors of the form theme', 'surveyjs-pro' ); ?></label>
                        <p class="description"><?php esc_html_e( 'Only for a light theme with Adaptive off; otherwise the default SurveyJS colors are used.', 'surveyjs-pro' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Accent color', 'surveyjs-pro' ); ?></th>
                    <td>
                        <label><input type="checkbox" data-setting="proPdfAccent" /> <?php esc_html_e( 'Use an accent color', 'surveyjs-pro' ); ?></label>
                        <span data-enabled-by="proPdfAccent"><input type="color" id="wp-sjs-pro-pdf-accent-color" data-setting="proPdfAccentColor" aria-label="<?php esc_attr_e( 'Accent color', 'surveyjs-pro' ); ?>" /></span>
                        <p class="description"><?php esc_html_e( 'Colors titles, selected choices and a line under the header (light colors skip titles to keep them readable). Overrides the main color of the theme.', 'surveyjs-pro' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Logo', 'surveyjs-pro' ); ?></th>
                    <td>
                        <input type="hidden" id="wp-sjs-pro-pdf-logo" data-setting="proPdfLogo" />
                        <img id="wp-sjs-pro-pdf-logo-preview" alt="" style="max-width: 200px; max-height: 48px; margin-right: 8px; vertical-align: middle;" hidden />
                        <button type="button" class="button" id="wp-sjs-pro-pdf-logo-choose" data-frame-title="<?php esc_attr_e( 'Choose a logo for the PDF', 'surveyjs-pro' ); ?>" data-frame-button="<?php esc_attr_e( 'Use this image', 'surveyjs-pro' ); ?>"><?php esc_html_e( 'Choose image', 'surveyjs-pro' ); ?></button>
                        <?php if ( $branding['logo'] ) : ?>
                            <button type="button" class="button" id="wp-sjs-pro-pdf-logo-site"><?php esc_html_e( 'Use site logo', 'surveyjs-pro' ); ?></button>
                        <?php endif; ?>
                        <button type="button" class="button-link button-link-delete" id="wp-sjs-pro-pdf-logo-remove" hidden><?php esc_html_e( 'Remove', 'surveyjs-pro' ); ?></button>
                        <p class="description"><?php esc_html_e( 'Shown at the top left of every page, up to 12 mm high and 50 mm wide.', 'surveyjs-pro' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wp-sjs-pro-pdf-header-text"><?php esc_html_e( 'Header text', 'surveyjs-pro' ); ?></label></th>
                    <td>
                        <input type="text" id="wp-sjs-pro-pdf-header-text" class="regular-text" maxlength="100" data-setting="proPdfHeaderText" />
                        <?php if ( '' !== $branding['title'] ) : ?>
                            <button type="button" class="button" id="wp-sjs-pro-pdf-header-site"><?php esc_html_e( 'Use site title', 'surveyjs-pro' ); ?></button>
                        <?php endif; ?>
                        <p class="description"><?php esc_html_e( 'Shown at the top of every page, next to the logo, for example your company name.', 'surveyjs-pro' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wp-sjs-pro-pdf-watermark"><?php esc_html_e( 'Watermark', 'surveyjs-pro' ); ?></label></th>
                    <td>
                        <input type="text" id="wp-sjs-pro-pdf-watermark" class="regular-text" maxlength="40" data-setting="proPdfWatermark" />
                        <p class="description"><?php esc_html_e( 'Printed diagonally in light gray on every page, for example DRAFT or CONFIDENTIAL.', 'surveyjs-pro' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Blank form', 'surveyjs-pro' ); ?></th>
                    <td>
                        <label><input type="checkbox" data-setting="proPdfFillable" /> <?php esc_html_e( 'Make the blank form a fillable PDF', 'surveyjs-pro' ); ?></label>
                        <p class="description"><?php esc_html_e( 'Respondents can type their answers into the PDF in a PDF viewer. Turn it off for a form to print and fill in by hand. The blank form shows every question, including the ones shown only under conditions. Entries are always exported read-only.', 'surveyjs-pro' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Entries', 'surveyjs-pro' ); ?></th>
                    <td>
                        <label><input type="checkbox" data-setting="proPdfHideEmpty" /> <?php esc_html_e( 'Leave out questions without an answer', 'surveyjs-pro' ); ?></label>
                        <p class="description"><?php esc_html_e( 'Entry PDFs show only the answered questions. The blank form still shows every question.', 'surveyjs-pro' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wp-sjs-pro-pdf-footer-text"><?php esc_html_e( 'Footer text', 'surveyjs-pro' ); ?></label></th>
                    <td>
                        <input type="text" id="wp-sjs-pro-pdf-footer-text" class="regular-text" maxlength="100" data-setting="proPdfFooterText" />
                        <p class="description"><?php esc_html_e( 'Shown at the bottom of every page, for example your address or website.', 'surveyjs-pro' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Header and footer', 'surveyjs-pro' ); ?></th>
                    <td>
                        <fieldset>
                            <label><input type="checkbox" data-setting="proPdfEntryHeader" /> <?php esc_html_e( 'Show the entry number and date at the bottom of each page', 'surveyjs-pro' ); ?></label><br />
                            <label><input type="checkbox" data-setting="proPdfPageNumbers" /> <?php esc_html_e( 'Show page numbers', 'surveyjs-pro' ); ?></label><br />
                            <label><input type="checkbox" data-setting="proPdfFooterDate" /> <?php esc_html_e( 'Show the date the PDF was created at the bottom of each page', 'surveyjs-pro' ); ?></label>
                        </fieldset>
                    </td>
                </tr>
                <?php do_action( 'surveyjs_pro_pdf_editor_rows', $survey ); ?>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Export', 'surveyjs-pro' ); ?></th>
                    <td>
                        <button type="button" class="button" id="wp-sjs-pro-pdf-preview"><?php esc_html_e( 'Preview PDF', 'surveyjs-pro' ); ?></button>
                        <button type="button" class="button" id="wp-sjs-pro-pdf-blank"><?php esc_html_e( 'Download blank form', 'surveyjs-pro' ); ?></button>
                        <span id="wp-sjs-pro-pdf-status" role="status"></span>
                        <p class="description"><?php esc_html_e( 'Uses the form and the settings above as they are now, including unsaved changes. Export entries as PDF on the Entries page: the PDF link under an entry, or Export PDF in Bulk actions.', 'surveyjs-pro' ); ?></p>
                    </td>
                </tr>
            </table>
            </div>
            <div class="wp-sjs-pro-pdf__preview" id="wp-sjs-pro-pdf-preview-panel">
                <div class="wp-sjs-pro-pdf__preview-bar">
                    <button type="button" class="button" id="wp-sjs-pro-pdf-full" aria-controls="wp-sjs-pro-pdf-preview-panel" aria-expanded="false"><span class="dashicons dashicons-fullscreen-alt" aria-hidden="true"></span><?php esc_html_e( 'Full screen', 'surveyjs-pro' ); ?></button>
                    <button type="button" class="button" id="wp-sjs-pro-pdf-full-close" hidden><span class="dashicons dashicons-no-alt" aria-hidden="true"></span><?php esc_html_e( 'Close full screen', 'surveyjs-pro' ); ?></button>
                </div>
                <iframe id="wp-sjs-pro-pdf-live" title="<?php esc_attr_e( 'PDF preview', 'surveyjs-pro' ); ?>"></iframe>
            </div>
        </div>
        <?php
    }

    public static function hebrew_font() {
        return array(
            'normal' => plugins_url( 'assets/fonts/IBMPlexSansHebrew-Regular.ttf', SURVEYJS_PRO_FILE ),
            'bold'   => plugins_url( 'assets/fonts/IBMPlexSansHebrew-Bold.ttf', SURVEYJS_PRO_FILE ),
        );
    }

    public static function unicode_font() {
        return array(
            'normal' => plugins_url( 'assets/fonts/noto-sans/NotoSans-Regular.ttf', SURVEYJS_PRO_FILE ),
            'bold'   => plugins_url( 'assets/fonts/noto-sans/NotoSans-Bold.ttf', SURVEYJS_PRO_FILE ),
        );
    }

    public static function enqueue( $page ) {
        $survey = SurveyJS_Forms::get( absint( $_GET['id'] ?? 0 ) );
        if ( ! $survey || ! SurveyJS_Pro_Libs::enqueue( 'pdf' ) ) {
            return;
        }
        $deps = array( SurveyJS_Pro_Libs::handle( 'pdf' ), SurveyJS_Pro_Libs::handle( 'pdf-spacious' ) );
        if ( 'surveyjs_editor' === $page ) {
            $deps[] = SurveyJS_SurveyJS::EDITOR_SCRIPT;
            wp_enqueue_style( self::SCRIPT, plugins_url( 'assets/pdf.css', SURVEYJS_PRO_FILE ), array(), SURVEYJS_PRO_VERSION );
        }
        wp_enqueue_script( self::SCRIPT, plugins_url( 'assets/pdf.js', SURVEYJS_PRO_FILE ), $deps, SURVEYJS_PRO_VERSION, true );

        $config = array(
            'page'     => 'surveyjs_editor' === $page ? 'editor' : 'results',
            'formId'   => (int) $survey->id,
            'formName' => $survey->name,
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'action'   => self::AJAX_ACTION,
            'nonce'    => wp_create_nonce( self::nonce_action( $survey->id ) ),
            'hebrewFont' => self::hebrew_font(),
            'unicodeFont' => self::unicode_font(),
            'format'   => self::page_format(),
            'logo'     => self::logo( $survey ),
            'locale'   => self::date_locale(),
            'i18n'     => array(
                /* translators: 1: entry ID, 2: entry date */
                'entryHeader' => __( 'Entry #%1$s, %2$s', 'surveyjs-pro' ),
                /* translators: 1: page number, 2: number of pages */
                'page'        => __( 'Page %1$s of %2$s', 'surveyjs-pro' ),
                'generating'  => __( 'Generating PDF…', 'surveyjs-pro' ),
                /* translators: 1: number of the PDF being generated, 2: number of PDFs */
                'progress'    => __( 'Generating PDF %1$s of %2$s…', 'surveyjs-pro' ),
                'done'        => __( 'PDF is ready.', 'surveyjs-pro' ),
                /* translators: %1$s: error message */
                'error'       => __( 'Could not create the PDF: %1$s', 'surveyjs-pro' ),
                'loadError'   => __( 'the entries could not be loaded. Reload the page and try again.', 'surveyjs-pro' ),
                'fontError'   => __( 'the font could not be loaded.', 'surveyjs-pro' ),
                'logoError'   => __( 'the logo image could not be loaded.', 'surveyjs-pro' ),
                'noEntries'   => __( 'the selected entries were not found.', 'surveyjs-pro' ),
                'invalidJson' => __( 'the form JSON has errors. Fix them on the Config tab first.', 'surveyjs-pro' ),
                'popupBlocked' => __( 'the browser blocked the preview window. Allow pop-ups for this site.', 'surveyjs-pro' ),
            ),
        );
        if ( 'surveyjs_editor' === $page ) {
            $config['site'] = self::site_branding();
        }
        if ( 'surveyjs_results' === $page ) {
            $json = SurveyJS_Client::decode_survey_json( $survey->json, false );
            $config['json'] = empty( $json ) ? new stdClass() : $json;
            $config['theme'] = SurveyJS_Themes::decode( $survey->theme );
            $config['settings'] = self::client_settings( SurveyJS_Form_Settings::get( $survey ) );
            $config['autoExport'] = array_values( array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_GET['pro_pdf'] ?? '' ) ) ) ) ) );
        }
        $config = SurveyJS_Pro_Pdf_Password::client_config( $config, $survey );
        wp_add_inline_script( self::SCRIPT, 'window.surveyjsProPdf = ' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP ) . ';', 'before' );
    }

    public static function nonce_action( $form_id ) {
        return 'surveyjs-pro-pdf_' . (int) $form_id;
    }

    public static function render_title_actions() {
        echo '<a href="#" class="page-title-action wp-sjs-pro-pdf-blank">' . esc_html__( 'Blank Form PDF', 'surveyjs-pro' ) . '</a>';
    }

    public static function entry_row_actions( $actions, $item ) {
        $link = sprintf( '<a href="#" class="wp-sjs-pro-pdf-entry" data-entry="%d">%s</a>', (int) $item->id, esc_html__( 'PDF', 'surveyjs-pro' ) );
        $position = array_search( 'view', array_keys( $actions ), true );
        $position = false === $position ? 0 : $position + 1;
        return array_slice( $actions, 0, $position, true ) + array( 'pdf' => $link ) + array_slice( $actions, $position, null, true );
    }

    public static function bulk_actions( $actions ) {
        $delete = isset( $actions['delete'] ) ? array( 'delete' => $actions['delete'] ) : array();
        return array_diff_key( $actions, $delete ) + array( self::BULK_ACTION => __( 'Export PDF', 'surveyjs-pro' ) ) + $delete;
    }

    // The PDF is built in the browser, so without the results page's script intercepting the form,
    // the request comes back to the results page, which starts the export on load.
    public static function handle_bulk_action( $ids, $survey, $results_url ) {
        wp_safe_redirect( add_query_arg( 'pro_pdf', implode( ',', array_slice( $ids, 0, self::MAX_ENTRIES ) ), $results_url ) );
        exit;
    }

    public static function ajax_entries() {
        $form_id = absint( $_POST['form'] ?? 0 );
        check_ajax_referer( self::nonce_action( $form_id ) );
        if ( ! current_user_can( SurveyJS_SurveyJS::CAPABILITY ) ) {
            wp_send_json_error( null, 403 );
        }
        $survey = SurveyJS_Forms::find( $form_id );
        $ids = array_slice( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['entries'] ?? array() ) ) ), 0, self::MAX_ENTRIES );
        if ( ! $survey || ! $ids ) {
            wp_send_json_error( null, 404 );
        }
        $entries = SurveyJS_Entries::query( $survey->id, array(
            'ids'    => $ids,
            'status' => SurveyJS_Entries::STATUSES,
            'order'  => 'ASC',
        ) );
        wp_send_json_success( array_map( function ( $entry ) {
            return array(
                'id'   => $entry->id,
                'date' => SurveyJS_Entries::format_date( $entry->created_at ),
                'data' => (object) SurveyJS_Uploaded_Files::with_admin_urls( $entry->data ),
            );
        }, $entries ) );
    }
}

add_action( 'surveyjs_pro_loaded', array( 'SurveyJS_Pro_Pdf', 'init' ) );
