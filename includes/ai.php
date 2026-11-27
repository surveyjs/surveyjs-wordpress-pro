<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SurveyJS_Pro_AI {

    const SENSITIVE_SETTINGS = array( 'proPdfAttachNotification', 'proPdfAttachAutoReply', 'proPdfEmailLink', 'proPdfLinkDays' );

    const BASE_REQUIRES = array(
        'SurveyJS_Forms::names',
        'SurveyJS_AI_Abilities::register_ability',
        'SurveyJS_AI_Abilities::DATA_NOTICE',
    );

    public static function init() {
        if ( ! SurveyJS_Pro::requires( __CLASS__, self::BASE_REQUIRES ) ) {
            return;
        }
        add_action( 'surveyjs_ai_register_abilities', array( __CLASS__, 'register' ) );
        add_filter( 'surveyjs_ai_form_settings_schema', array( __CLASS__, 'form_settings_schema' ) );
        add_filter( 'surveyjs_ai_sensitive_form_settings', array( __CLASS__, 'sensitive_settings' ) );
        add_filter( 'surveyjs_ai_plugin_guide', array( __CLASS__, 'plugin_guide' ) );
        add_filter( 'surveyjs_ai_secret_values', array( __CLASS__, 'secret_values' ) );
        add_filter( 'surveyjs_ai_admin_urls', array( __CLASS__, 'admin_urls' ) );
    }

    public static function secret_values( $values ) {
        $values = (array) $values;
        $values[] = SurveyJS_Pro_Settings::license_key();
        if ( SurveyJS_Pro_Pdf_Password::available() ) {
            foreach ( SurveyJS_Forms::names() as $form ) {
                $values[] = SurveyJS_Pro_Pdf_Password::password( (int) $form->id );
            }
        }
        return $values;
    }

    public static function admin_urls( $urls ) {
        $urls = (array) $urls;
        $urls['dashboard'] = admin_url( 'admin.php?page=' . SurveyJS_Pro_Dashboard::PAGE );
        return $urls;
    }

    public static function register() {
        SurveyJS_AI_Abilities::register_ability( 'surveyjs-pro/get-settings', array(
            'label'            => __( 'Get SurveyJS Forms Add-on settings', 'surveyjs-pro' ),
            'description'      => 'Returns the site-wide settings of SurveyJS Forms Add-on: whether forms are edited in Survey Creator, the color mode and the style of Survey Creator, Charts and Dashboard, and whether a SurveyJS license key is entered. PDF settings are per form: the proPdf* keys of surveyjs-get-form-settings. ' . SurveyJS_AI_Abilities::DATA_NOTICE,
            'execute_callback' => array( __CLASS__, 'get_settings' ),
            'meta'             => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
        ) );
        SurveyJS_AI_Abilities::register_ability( 'surveyjs-pro/update-settings', array(
            'label'            => __( 'Update SurveyJS Forms Add-on settings', 'surveyjs-pro' ),
            'description'      => 'Changes how the form editor looks for site administrators: creator (edit forms in Survey Creator instead of the JSON editor), admin_theme (color mode), admin_style (match the WordPress admin style). Pass only the keys to change. These settings apply right away, not as a draft, and respondents see no difference. The license key can be changed only by a person in the WordPress admin.',
            'input_schema'     => array(
                'type'       => 'object',
                'properties' => array(
                    'creator'     => array(
                        'type'        => 'boolean',
                        'description' => 'true: edit forms in Survey Creator; false: the JSON editor of the free plugin.',
                    ),
                    'admin_theme' => array(
                        'type'        => 'string',
                        'enum'        => SurveyJS_Pro_Admin_Theme::MODES,
                        'description' => 'auto: match the WordPress admin; system: match the device; light; dark.',
                    ),
                    'admin_style' => array(
                        'type'        => 'boolean',
                        'description' => 'true: Survey Creator takes the WordPress admin colors, fonts and sizes; false: the default SurveyJS look.',
                    ),
                ),
                'minProperties' => 1,
            ),
            'execute_callback' => array( __CLASS__, 'update_settings' ),
            'meta'             => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ),
        ) );
    }

    private static function settings_view() {
        $settings = SurveyJS_Pro_Settings::all();
        return array(
            'creator'             => (bool) $settings['creator'],
            'admin_theme'         => (string) $settings['admin_theme'],
            'admin_style'         => (bool) $settings['admin_style'],
            'license_key_entered' => '' !== SurveyJS_Pro_Settings::license_key(),
        );
    }

    public static function get_settings() {
        return array(
            'settings'     => self::settings_view(),
            'settings_url' => admin_url( 'admin.php?page=surveyjs-settings' ),
        );
    }

    public static function update_settings( $input ) {
        $before = self::settings_view();
        $values = array_intersect_key( is_array( $input ) ? $input : array(), array_flip( array( 'creator', 'admin_theme', 'admin_style' ) ) );
        SurveyJS_Pro_Settings::update( SurveyJS_Pro_Settings::sanitize( array_merge( array_intersect_key( SurveyJS_Pro_Settings::all(), array_flip( array( 'creator', 'admin_style', 'admin_theme' ) ) ), $values ) ) );
        $after = self::settings_view();
        return array(
            'changed'  => array_keys( array_diff_assoc( array_map( 'strval', $after ), array_map( 'strval', $before ) ) ),
            'settings' => $after,
            'message'  => 'Applied right away. These settings change only the WordPress admin, not the forms on the site.',
        );
    }

    public static function plugin_guide( $guide ) {
        $lines = array(
            '',
            '## SurveyJS Forms Add-on (installed on this site)',
            '',
            '- **Survey Creator**: the Config tab of the form editor is the visual Survey Creator (drag and drop, logic, translations, preview, full screen). Turn it off in SurveyJS Forms > Settings > Add-on to get the JSON editor back.',
            '- **Charts**: charts for every question with a date range filter, on the Entries screen of a form (Charts view).',
            '- **Dashboard** (SurveyJS Forms > Dashboard): entries across all forms over time, latest entries and counts per form.',
            '- **PDF tab** of the form editor: page size, orientation, fonts, colors, logo, header, footer, watermark and a live preview; a Download PDF button for respondents; the PDF of the entry attached to the email notification or the auto-reply; {pdf_link} and {pdf_url} tags in emails; password protection. Download entry PDFs from the Entries screen (one entry or Bulk actions > Export PDF) and the blank form as a fillable PDF.',
        );
        return $guide . implode( "\n", $lines ) . "\n";
    }

    public static function sensitive_settings( $keys ) {
        return array_merge( (array) $keys, self::SENSITIVE_SETTINGS );
    }

    public static function form_settings_schema( $schema ) {
        $descriptions = array(
            'proPdfFormat'             => array( 'Page size of PDFs. auto: Letter or A4 from the site language.', array_keys( SurveyJS_Pro_Pdf::formats() ) ),
            'proPdfOrientation'        => array( 'Page orientation of PDFs.', array_keys( SurveyJS_Pro_Pdf::orientations() ) ),
            'proPdfFontSize'           => array( 'Font size of answers in PDFs, 8 to 24 pt.' ),
            'proPdfLabelFontSize'      => array( 'Font size of question titles in PDFs, 8 to 24 pt.' ),
            'proPdfLayout'             => array( 'Spacing of PDFs.', array_keys( SurveyJS_Pro_Pdf::layouts() ) ),
            'proPdfFont'               => array( 'Font of PDFs.', array_keys( SurveyJS_Pro_Pdf::fonts() ) ),
            'proPdfThemeColors'        => array( 'Use the colors of the form theme in PDFs.' ),
            'proPdfAccent'             => array( 'Use proPdfAccentColor as the accent color of PDFs.' ),
            'proPdfAccentColor'        => array( 'Accent color of PDFs, #rrggbb.' ),
            'proPdfFillable'           => array( 'Make the blank form PDF fillable.' ),
            'proPdfEntryHeader'        => array( 'Show the entry number and date at the bottom of each PDF page.' ),
            'proPdfPageNumbers'        => array( 'Show page numbers in PDFs.' ),
            'proPdfLogo'               => array( 'Attachment ID of an image from the Media Library shown as the PDF logo; 0 for none.' ),
            'proPdfHeaderText'         => array( 'Text at the top of each PDF page, up to 100 characters.' ),
            'proPdfFooterText'         => array( 'Text at the bottom of each PDF page, up to 100 characters.' ),
            'proPdfFooterDate'         => array( 'Show the date the PDF was created at the bottom of each page.' ),
            'proPdfWatermark'          => array( 'Watermark text across PDF pages, up to 40 characters.' ),
            'proPdfHideEmpty'          => array( 'Leave questions without an answer out of entry PDFs.' ),
            'proPdfDownload'           => array( 'Show a Download PDF button to the respondent after submitting.' ),
            'proPdfAttachNotification' => array( 'Attach a PDF of the entry to the email notification.' ),
            'proPdfAttachAutoReply'    => array( 'Attach a PDF of the entry to the auto-reply.' ),
            'proPdfEmailLink'          => array( 'Allow {pdf_link} and {pdf_url} in email messages: a link to the PDF of the entry that anyone with the link can open. Needs storeEntries.' ),
            'proPdfLinkDays'           => array( 'Days until a PDF link expires, 0 to 365; 0: never.' ),
            'proPdfPassword'           => array( 'Protect entry PDFs with a password. A person sets the password on the PDF tab of the form editor; without it PDFs are not protected.' ),
        );
        foreach ( $descriptions as $key => $description ) {
            if ( ! isset( $schema[ $key ] ) ) {
                continue;
            }
            $schema[ $key ]['description'] = $description[0];
            if ( isset( $description[1] ) ) {
                $schema[ $key ]['enum'] = $description[1];
            }
        }
        return $schema;
    }
}

add_action( 'surveyjs_pro_loaded', array( 'SurveyJS_Pro_AI', 'init' ) );
