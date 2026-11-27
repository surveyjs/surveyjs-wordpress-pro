<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SurveyJS_Addon_Pdf {

    const TAB = 'pdf';
    const BULK_ACTION = 'addon_pdf';
    const AJAX_ACTION = 'surveyjs_addon_pdf_entries';
    const SCRIPT = 'surveyjs-forms-addon-pdf-admin';
    const MAX_ENTRIES = 500;
    const DEFAULT_ACCENT = '#19b394';
    const LETTER_LOCALES = array( 'en_US', 'en_CA', 'fr_CA', 'es_MX', 'es_CL', 'es_CO', 'es_VE', 'es_GT', 'es_CR', 'es_PR', 'es_US' );

    const BASE_REQUIRES = array(
        'SurveyJS_SurveyJS::CORE_SCRIPT',
        'SurveyJS_SurveyJS::UI_SCRIPT',
        'SurveyJS_SurveyJS::CORE_STYLE',
        'SurveyJS_SurveyJS::EDITOR_SCRIPT',
        'SurveyJS_SurveyJS::CAPABILITY',
        'SurveyJS_Form_Settings::get',
        'SurveyJS_Forms::get',
        'SurveyJS_Forms::find',
        'SurveyJS_Client::decode_survey_json',
        'SurveyJS_Themes::decode',
        'SurveyJS_Entries::query',
        'SurveyJS_Entries::STATUSES',
        'SurveyJS_Entries::format_date',
        'SurveyJS_Uploaded_Files::with_admin_urls',
    );

    public static function init() {
        if ( ! SurveyJS_Addon::requires( __CLASS__, self::BASE_REQUIRES ) ) {
            return;
        }
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
        return (string) apply_filters( 'surveyjs_addon_pdf_page_format', in_array( get_locale(), self::LETTER_LOCALES, true ) ? 'letter' : 'a4' );
    }

    public static function formats() {
        $sizes = array(
            'a4'     => __( 'A4', 'surveyjs-forms-addon' ),
            'letter' => __( 'Letter', 'surveyjs-forms-addon' ),
            'legal'  => __( 'Legal', 'surveyjs-forms-addon' ),
            'a3'     => __( 'A3', 'surveyjs-forms-addon' ),
            'a5'     => __( 'A5', 'surveyjs-forms-addon' ),
        );
        $auto = $sizes[ self::page_format() ] ?? self::page_format();
        /* translators: %s: page size picked from the site language, e.g. "A4" */
        return array( 'auto' => sprintf( __( 'Automatic: %s, from the site language', 'surveyjs-forms-addon' ), $auto ) ) + $sizes;
    }

    public static function orientations() {
        return array(
            'portrait'  => __( 'Portrait', 'surveyjs-forms-addon' ),
            'landscape' => __( 'Landscape', 'surveyjs-forms-addon' ),
        );
    }

    public static function layouts() {
        return array(
            'compact'  => __( 'Compact', 'surveyjs-forms-addon' ),
            'spacious' => __( 'Spacious', 'surveyjs-forms-addon' ),
        );
    }

    public static function fonts() {
        return array(
            'helvetica' => __( 'Sans-serif (Helvetica)', 'surveyjs-forms-addon' ),
            'times'     => __( 'Serif (Times)', 'surveyjs-forms-addon' ),
            'courier'   => __( 'Monospace (Courier)', 'surveyjs-forms-addon' ),
        );
    }

    public static function pdf_defaults() {
        return array(
            'addonPdfFormat'      => 'auto',
            'addonPdfOrientation' => 'portrait',
            'addonPdfFontSize'    => 14,
            'addonPdfLabelFontSize' => 14,
            'addonPdfLayout'      => 'compact',
            'addonPdfFont'        => 'helvetica',
            'addonPdfThemeColors' => false,
            'addonPdfAccent'      => false,
            'addonPdfAccentColor' => self::DEFAULT_ACCENT,
            'addonPdfFillable'    => true,
            'addonPdfEntryHeader' => true,
            'addonPdfPageNumbers' => true,
            'addonPdfLogo'        => 0,
            'addonPdfHeaderText'  => '',
            'addonPdfFooterText'  => '',
            'addonPdfFooterDate'  => false,
            'addonPdfWatermark'   => '',
            'addonPdfHideEmpty'   => false,
        );
    }

    public static function defaults( $defaults ) {
        return $defaults + self::pdf_defaults();
    }

    public static function sanitize( $sanitized, $input ) {
        $format = (string) $input['addonPdfFormat'];
        $sanitized['addonPdfFormat'] = isset( self::formats()[ $format ] ) ? $format : self::pdf_defaults()['addonPdfFormat'];
        $orientation = (string) ( $input['addonPdfOrientation'] ?? '' );
        $sanitized['addonPdfOrientation'] = isset( self::orientations()[ $orientation ] ) ? $orientation : self::pdf_defaults()['addonPdfOrientation'];
        $layout = (string) $input['addonPdfLayout'];
        $sanitized['addonPdfLayout'] = isset( self::layouts()[ $layout ] ) ? $layout : self::pdf_defaults()['addonPdfLayout'];
        $font = (string) ( $input['addonPdfFont'] ?? '' );
        $sanitized['addonPdfFont'] = isset( self::fonts()[ $font ] ) ? $font : self::pdf_defaults()['addonPdfFont'];
        $sanitized['addonPdfAccentColor'] = self::sanitize_color( $input['addonPdfAccentColor'] ?? '' );
        $sanitized['addonPdfFontSize'] = min( 24, max( 8, (int) $input['addonPdfFontSize'] ) );
        $sanitized['addonPdfLabelFontSize'] = isset( $input['addonPdfLabelFontSize'] ) ? min( 24, max( 8, (int) $input['addonPdfLabelFontSize'] ) ) : $sanitized['addonPdfFontSize'];
        $logo = absint( $input['addonPdfLogo'] );
        $sanitized['addonPdfLogo'] = $logo && wp_attachment_is_image( $logo ) ? $logo : 0;
        $sanitized['addonPdfHeaderText'] = mb_substr( sanitize_text_field( $input['addonPdfHeaderText'] ), 0, 100 );
        $sanitized['addonPdfFooterText'] = mb_substr( sanitize_text_field( $input['addonPdfFooterText'] ?? '' ), 0, 100 );
        $sanitized['addonPdfWatermark'] = mb_substr( sanitize_text_field( $input['addonPdfWatermark'] ), 0, 40 );
        foreach ( array( 'addonPdfThemeColors', 'addonPdfAccent', 'addonPdfFillable', 'addonPdfEntryHeader', 'addonPdfPageNumbers', 'addonPdfFooterDate', 'addonPdfHideEmpty' ) as $key ) {
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
        $id = (int) SurveyJS_Form_Settings::get( $survey )['addonPdfLogo'];
        $url = $id ? wp_get_attachment_image_url( $id, 'large' ) : '';
        return array( 'id' => $id, 'url' => $url ? $url : '' );
    }

    public static function editor_tabs( $tabs ) {
        return $tabs + array( self::TAB => __( 'PDF', 'surveyjs-forms-addon' ) );
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
        <div class="wp-sjs-editor__settings wp-sjs-addon-pdf" data-panel="<?php echo esc_attr( self::TAB ); ?>"<?php echo self::TAB === $initial_tab ? '' : ' hidden'; ?>>
            <div class="wp-sjs-addon-pdf__settings">
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="wp-sjs-addon-pdf-format"><?php esc_html_e( 'Page size', 'surveyjs-forms-addon' ); ?></label></th>
                    <td>
                        <?php self::render_select( 'wp-sjs-addon-pdf-format', 'addonPdfFormat', self::formats() ); ?>
                        <p class="description"><?php esc_html_e( 'Automatic: Letter for US, Canada and Latin America site languages, A4 for others.', 'surveyjs-forms-addon' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wp-sjs-addon-pdf-orientation"><?php esc_html_e( 'Page orientation', 'surveyjs-forms-addon' ); ?></label></th>
                    <td><?php self::render_select( 'wp-sjs-addon-pdf-orientation', 'addonPdfOrientation', self::orientations() ); ?></td>
                </tr>
                <tr>
                    <th scope="row"><label for="wp-sjs-addon-pdf-font-size"><?php esc_html_e( 'Font size', 'surveyjs-forms-addon' ); ?></label></th>
                    <td>
                        <input type="number" id="wp-sjs-addon-pdf-font-size" class="small-text" min="8" max="24" step="1" data-setting="addonPdfFontSize" /> <?php esc_html_e( 'pt', 'surveyjs-forms-addon' ); ?>
                        <p class="description"><?php esc_html_e( 'Size of answers. Headings and spacing scale with it.', 'surveyjs-forms-addon' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wp-sjs-addon-pdf-label-font-size"><?php esc_html_e( 'Label font size', 'surveyjs-forms-addon' ); ?></label></th>
                    <td>
                        <input type="number" id="wp-sjs-addon-pdf-label-font-size" class="small-text" min="8" max="24" step="1" data-setting="addonPdfLabelFontSize" /> <?php esc_html_e( 'pt', 'surveyjs-forms-addon' ); ?>
                        <p class="description"><?php esc_html_e( 'Size of question titles. Keep it equal to Font size for the default proportions.', 'surveyjs-forms-addon' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wp-sjs-addon-pdf-font"><?php esc_html_e( 'Font', 'surveyjs-forms-addon' ); ?></label></th>
                    <td>
                        <?php self::render_select( 'wp-sjs-addon-pdf-font', 'addonPdfFont', self::fonts() ); ?>
                        <p class="description"><?php esc_html_e( 'For Cyrillic, Greek, Hebrew and other letters these fonts lack, a suitable font is used instead.', 'surveyjs-forms-addon' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wp-sjs-addon-pdf-layout"><?php esc_html_e( 'Layout', 'surveyjs-forms-addon' ); ?></label></th>
                    <td>
                        <?php self::render_select( 'wp-sjs-addon-pdf-layout', 'addonPdfLayout', self::layouts() ); ?>
                        <p class="description"><?php esc_html_e( 'Compact fits more questions on a page. Spacious adds room between questions and is easier to read.', 'surveyjs-forms-addon' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Colors', 'surveyjs-forms-addon' ); ?></th>
                    <td>
                        <label><input type="checkbox" data-setting="addonPdfThemeColors" /> <?php esc_html_e( 'Use the colors of the form theme', 'surveyjs-forms-addon' ); ?></label>
                        <p class="description"><?php esc_html_e( 'Only for a light theme with Adaptive off; otherwise the default SurveyJS colors are used.', 'surveyjs-forms-addon' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Accent color', 'surveyjs-forms-addon' ); ?></th>
                    <td>
                        <label><input type="checkbox" data-setting="addonPdfAccent" /> <?php esc_html_e( 'Use an accent color', 'surveyjs-forms-addon' ); ?></label>
                        <span data-enabled-by="addonPdfAccent"><input type="color" id="wp-sjs-addon-pdf-accent-color" data-setting="addonPdfAccentColor" aria-label="<?php esc_attr_e( 'Accent color', 'surveyjs-forms-addon' ); ?>" /></span>
                        <p class="description"><?php esc_html_e( 'Colors titles, selected choices and a line under the header (light colors skip titles to keep them readable). Overrides the main color of the theme.', 'surveyjs-forms-addon' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Logo', 'surveyjs-forms-addon' ); ?></th>
                    <td>
                        <input type="hidden" id="wp-sjs-addon-pdf-logo" data-setting="addonPdfLogo" />
                        <img id="wp-sjs-addon-pdf-logo-preview" alt="" style="max-width: 200px; max-height: 48px; margin-right: 8px; vertical-align: middle;" hidden />
                        <button type="button" class="button" id="wp-sjs-addon-pdf-logo-choose" data-frame-title="<?php esc_attr_e( 'Choose a logo for the PDF', 'surveyjs-forms-addon' ); ?>" data-frame-button="<?php esc_attr_e( 'Use this image', 'surveyjs-forms-addon' ); ?>"><?php esc_html_e( 'Choose image', 'surveyjs-forms-addon' ); ?></button>
                        <?php if ( $branding['logo'] ) : ?>
                            <button type="button" class="button" id="wp-sjs-addon-pdf-logo-site"><?php esc_html_e( 'Use site logo', 'surveyjs-forms-addon' ); ?></button>
                        <?php endif; ?>
                        <button type="button" class="button-link button-link-delete" id="wp-sjs-addon-pdf-logo-remove" hidden><?php esc_html_e( 'Remove', 'surveyjs-forms-addon' ); ?></button>
                        <p class="description"><?php esc_html_e( 'Shown at the top left of every page, up to 12 mm high and 50 mm wide.', 'surveyjs-forms-addon' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wp-sjs-addon-pdf-header-text"><?php esc_html_e( 'Header text', 'surveyjs-forms-addon' ); ?></label></th>
                    <td>
                        <input type="text" id="wp-sjs-addon-pdf-header-text" class="regular-text" maxlength="100" data-setting="addonPdfHeaderText" />
                        <?php if ( '' !== $branding['title'] ) : ?>
                            <button type="button" class="button" id="wp-sjs-addon-pdf-header-site"><?php esc_html_e( 'Use site title', 'surveyjs-forms-addon' ); ?></button>
                        <?php endif; ?>
                        <p class="description"><?php esc_html_e( 'Shown at the top of every page, next to the logo, for example your company name.', 'surveyjs-forms-addon' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wp-sjs-addon-pdf-watermark"><?php esc_html_e( 'Watermark', 'surveyjs-forms-addon' ); ?></label></th>
                    <td>
                        <input type="text" id="wp-sjs-addon-pdf-watermark" class="regular-text" maxlength="40" data-setting="addonPdfWatermark" />
                        <p class="description"><?php esc_html_e( 'Printed diagonally in light gray on every page, for example DRAFT or CONFIDENTIAL.', 'surveyjs-forms-addon' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Blank form', 'surveyjs-forms-addon' ); ?></th>
                    <td>
                        <label><input type="checkbox" data-setting="addonPdfFillable" /> <?php esc_html_e( 'Make the blank form a fillable PDF', 'surveyjs-forms-addon' ); ?></label>
                        <p class="description"><?php esc_html_e( 'Respondents can type their answers into the PDF in a PDF viewer. Turn it off for a form to print and fill in by hand. The blank form shows every question, including the ones shown only under conditions. Entries are always exported read-only.', 'surveyjs-forms-addon' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Entries', 'surveyjs-forms-addon' ); ?></th>
                    <td>
                        <label><input type="checkbox" data-setting="addonPdfHideEmpty" /> <?php esc_html_e( 'Leave out questions without an answer', 'surveyjs-forms-addon' ); ?></label>
                        <p class="description"><?php esc_html_e( 'Entry PDFs show only the answered questions. The blank form still shows every question.', 'surveyjs-forms-addon' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wp-sjs-addon-pdf-footer-text"><?php esc_html_e( 'Footer text', 'surveyjs-forms-addon' ); ?></label></th>
                    <td>
                        <input type="text" id="wp-sjs-addon-pdf-footer-text" class="regular-text" maxlength="100" data-setting="addonPdfFooterText" />
                        <p class="description"><?php esc_html_e( 'Shown at the bottom of every page, for example your address or website.', 'surveyjs-forms-addon' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Header and footer', 'surveyjs-forms-addon' ); ?></th>
                    <td>
                        <fieldset>
                            <label><input type="checkbox" data-setting="addonPdfEntryHeader" /> <?php esc_html_e( 'Show the entry number and date at the bottom of each page', 'surveyjs-forms-addon' ); ?></label><br />
                            <label><input type="checkbox" data-setting="addonPdfPageNumbers" /> <?php esc_html_e( 'Show page numbers', 'surveyjs-forms-addon' ); ?></label><br />
                            <label><input type="checkbox" data-setting="addonPdfFooterDate" /> <?php esc_html_e( 'Show the date the PDF was created at the bottom of each page', 'surveyjs-forms-addon' ); ?></label>
                        </fieldset>
                    </td>
                </tr>
                <?php do_action( 'surveyjs_addon_pdf_editor_rows', $survey ); ?>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Export', 'surveyjs-forms-addon' ); ?></th>
                    <td>
                        <button type="button" class="button" id="wp-sjs-addon-pdf-preview"><?php esc_html_e( 'Preview PDF', 'surveyjs-forms-addon' ); ?></button>
                        <button type="button" class="button" id="wp-sjs-addon-pdf-blank"><?php esc_html_e( 'Download blank form', 'surveyjs-forms-addon' ); ?></button>
                        <span id="wp-sjs-addon-pdf-status" role="status"></span>
                        <p class="description"><?php esc_html_e( 'Uses the form and the settings above as they are now, including unsaved changes. Export entries as PDF on the Entries page: the PDF link under an entry, or Export PDF in Bulk actions.', 'surveyjs-forms-addon' ); ?></p>
                    </td>
                </tr>
            </table>
            </div>
            <div class="wp-sjs-addon-pdf__preview" id="wp-sjs-addon-pdf-preview-panel">
                <div class="wp-sjs-addon-pdf__preview-bar">
                    <button type="button" class="button" id="wp-sjs-addon-pdf-full" aria-controls="wp-sjs-addon-pdf-preview-panel" aria-expanded="false"><span class="dashicons dashicons-fullscreen-alt" aria-hidden="true"></span><?php esc_html_e( 'Full screen', 'surveyjs-forms-addon' ); ?></button>
                    <button type="button" class="button" id="wp-sjs-addon-pdf-full-close" hidden><span class="dashicons dashicons-no-alt" aria-hidden="true"></span><?php esc_html_e( 'Close full screen', 'surveyjs-forms-addon' ); ?></button>
                </div>
                <iframe id="wp-sjs-addon-pdf-live" title="<?php esc_attr_e( 'PDF preview', 'surveyjs-forms-addon' ); ?>"></iframe>
            </div>
        </div>
        <?php
    }

    public static function hebrew_font() {
        return array(
            'normal' => plugins_url( 'assets/fonts/IBMPlexSansHebrew-Regular.ttf', SURVEYJS_ADDON_FILE ),
            'bold'   => plugins_url( 'assets/fonts/IBMPlexSansHebrew-Bold.ttf', SURVEYJS_ADDON_FILE ),
        );
    }

    public static function unicode_font() {
        return array(
            'normal' => plugins_url( 'assets/fonts/noto-sans/NotoSans-Regular.ttf', SURVEYJS_ADDON_FILE ),
            'bold'   => plugins_url( 'assets/fonts/noto-sans/NotoSans-Bold.ttf', SURVEYJS_ADDON_FILE ),
        );
    }

    public static function enqueue( $page ) {
        $survey = SurveyJS_Forms::get( absint( $_GET['id'] ?? 0 ) );
        if ( ! $survey || ! SurveyJS_Addon_Libs::enqueue( 'pdf' ) ) {
            return;
        }
        $deps = array( SurveyJS_Addon_Libs::handle( 'pdf' ), SurveyJS_Addon_Libs::handle( 'pdf-spacious' ) );
        if ( 'surveyjs_editor' === $page ) {
            $deps[] = SurveyJS_SurveyJS::EDITOR_SCRIPT;
            wp_enqueue_style( self::SCRIPT, plugins_url( 'assets/pdf.css', SURVEYJS_ADDON_FILE ), array(), SURVEYJS_ADDON_VERSION );
        }
        wp_enqueue_script( self::SCRIPT, plugins_url( 'assets/pdf.js', SURVEYJS_ADDON_FILE ), $deps, SURVEYJS_ADDON_VERSION, true );

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
                'entryHeader' => __( 'Entry #%1$s, %2$s', 'surveyjs-forms-addon' ),
                /* translators: 1: page number, 2: number of pages */
                'page'        => __( 'Page %1$s of %2$s', 'surveyjs-forms-addon' ),
                'generating'  => __( 'Generating PDF…', 'surveyjs-forms-addon' ),
                /* translators: 1: number of the PDF being generated, 2: number of PDFs */
                'progress'    => __( 'Generating PDF %1$s of %2$s…', 'surveyjs-forms-addon' ),
                'done'        => __( 'PDF is ready.', 'surveyjs-forms-addon' ),
                /* translators: %1$s: error message */
                'error'       => __( 'Could not create the PDF: %1$s', 'surveyjs-forms-addon' ),
                'loadError'   => __( 'the entries could not be loaded. Reload the page and try again.', 'surveyjs-forms-addon' ),
                'fontError'   => __( 'the font could not be loaded.', 'surveyjs-forms-addon' ),
                'logoError'   => __( 'the logo image could not be loaded.', 'surveyjs-forms-addon' ),
                'noEntries'   => __( 'the selected entries were not found.', 'surveyjs-forms-addon' ),
                'invalidJson' => __( 'the form JSON has errors. Fix them on the Config tab first.', 'surveyjs-forms-addon' ),
                'popupBlocked' => __( 'the browser blocked the preview window. Allow pop-ups for this site.', 'surveyjs-forms-addon' ),
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
            $config['autoExport'] = array_values( array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_GET['addon_pdf'] ?? '' ) ) ) ) ) );
        }
        $config = SurveyJS_Addon_Pdf_Password::client_config( $config, $survey );
        wp_add_inline_script( self::SCRIPT, 'window.surveyjsAddonPdf = ' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP ) . ';', 'before' );
    }

    public static function nonce_action( $form_id ) {
        return 'surveyjs-forms-addon-pdf_' . (int) $form_id;
    }

    public static function render_title_actions() {
        echo '<a href="#" class="page-title-action wp-sjs-addon-pdf-blank">' . esc_html__( 'Blank Form PDF', 'surveyjs-forms-addon' ) . '</a>';
    }

    public static function entry_row_actions( $actions, $item ) {
        $link = sprintf( '<a href="#" class="wp-sjs-addon-pdf-entry" data-entry="%d">%s</a>', (int) $item->id, esc_html__( 'PDF', 'surveyjs-forms-addon' ) );
        $position = array_search( 'view', array_keys( $actions ), true );
        $position = false === $position ? 0 : $position + 1;
        return array_slice( $actions, 0, $position, true ) + array( 'pdf' => $link ) + array_slice( $actions, $position, null, true );
    }

    public static function bulk_actions( $actions ) {
        $delete = isset( $actions['delete'] ) ? array( 'delete' => $actions['delete'] ) : array();
        return array_diff_key( $actions, $delete ) + array( self::BULK_ACTION => __( 'Export PDF', 'surveyjs-forms-addon' ) ) + $delete;
    }

    // The PDF is built in the browser, so without the results page's script intercepting the form,
    // the request comes back to the results page, which starts the export on load.
    public static function handle_bulk_action( $ids, $survey, $results_url ) {
        wp_safe_redirect( add_query_arg( 'addon_pdf', implode( ',', array_slice( $ids, 0, self::MAX_ENTRIES ) ), $results_url ) );
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

add_action( 'surveyjs_addon_loaded', array( 'SurveyJS_Addon_Pdf', 'init' ) );
