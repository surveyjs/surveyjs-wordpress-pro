<?php
require '/var/www/html/wp-load.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.NotAbsolutePath -- absolute path of WordPress in the container; the script is piped to php over stdin, so it has no __DIR__.
if ( ! defined( 'ABSPATH' ) ) exit;

$command = $argv[1] ?? '';
if ( 'create' === $command ) {
    $name = $argv[2] ?? 'E2E PDF Theme';
    $json = array(
        'title' => $name,
        'pages' => array(
            array(
                'name'     => 'page1',
                'title'    => 'Contact details',
                'elements' => array(
                    array( 'type' => 'text', 'name' => 'name', 'title' => 'Full name' ),
                    array( 'type' => 'radiogroup', 'name' => 'plan', 'title' => 'Plan', 'choices' => array( 'Basic', 'Pro' ) ),
                    array(
                        'type'     => 'panel',
                        'name'     => 'extra',
                        'title'    => 'Extra',
                        'elements' => array(
                            array( 'type' => 'checkbox', 'name' => 'topics', 'title' => 'Topics', 'choices' => array( 'Billing', 'Support', 'Sales' ) ),
                        ),
                    ),
                ),
            ),
        ),
    );
    $settings = SurveyJS_Form_Settings::sanitize( array(
        'notificationEnabled'      => true,
        'notificationTo'           => 'owner@example.test',
        'addonPdfAttachNotification' => true,
        'addonPdfDownload'           => true,
    ) );
    $form_id = SurveyJS_Forms::create( array(
        'name'     => $name,
        'json'     => wp_json_encode( $json ),
        'settings' => wp_json_encode( $settings ),
    ) );
    SurveyJS_Entries::insert( $form_id, wp_json_encode( array( 'name' => 'John Smith', 'plan' => 'Pro', 'topics' => array( 'Support' ) ) ), array( 'status' => 'read' ) );
    $page_id = wp_insert_post( array(
        'post_type'    => 'page',
        'post_status'  => 'publish',
        'post_title'   => $name,
        'post_content' => '[surveyjs id="' . (int) $form_id . '"]',
    ) );
    echo wp_json_encode( array( 'id' => $form_id, 'page' => $page_id, 'url' => get_permalink( $page_id ) ) ), "\n";
} elseif ( 'settings' === $command ) {
    echo wp_json_encode( SurveyJS_Form_Settings::get( SurveyJS_Forms::find( absint( $argv[2] ?? 0 ) ) ) ), "\n";
} elseif ( 'sanitize' === $command ) {
    $input = json_decode( (string) ( $argv[2] ?? '{}' ), true );
    $sanitized = SurveyJS_Form_Settings::sanitize( is_array( $input ) ? $input : array() );
    echo wp_json_encode( array_intersect_key( $sanitized, SurveyJS_Addon_Pdf::pdf_defaults() ) ), "\n";
} elseif ( 'cleanup' === $command ) {
    $form_id = absint( $argv[2] ?? 0 );
    $page_id = absint( $argv[3] ?? 0 );
    if ( $form_id ) {
        SurveyJS_Forms::delete( $form_id );
    }
    if ( $page_id ) {
        wp_delete_post( $page_id, true );
    }
    echo "{}\n";
}
