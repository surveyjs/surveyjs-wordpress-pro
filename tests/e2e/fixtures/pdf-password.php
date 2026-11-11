<?php
require '/var/www/html/wp-load.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.NotAbsolutePath -- absolute path of WordPress in the demo container; the script is piped to php over stdin, so it has no __DIR__.
if ( ! defined( 'ABSPATH' ) ) exit;

$command = $argv[1] ?? '';
if ( 'create' === $command ) {
    $name = $argv[2] ?? 'E2E PDF Password';
    $json = array(
        'title'    => $name,
        'elements' => array(
            array( 'type' => 'text', 'name' => 'name', 'title' => 'Имя и фамилия' ),
            array( 'type' => 'text', 'name' => 'email', 'title' => 'Email', 'inputType' => 'email' ),
            array( 'type' => 'comment', 'name' => 'comment', 'title' => 'Комментарий' ),
            array( 'type' => 'checkbox', 'name' => 'topics', 'title' => 'Topics', 'choices' => array( 'Billing', 'Support', 'Sales' ) ),
        ),
    );
    $settings = SurveyJS_Form_Settings::sanitize( array(
        'notificationEnabled'      => true,
        'notificationTo'           => 'owner@example.test',
        'notificationReplyTo'      => 'email',
        'autoReplyEnabled'         => true,
        'proPdfAttachNotification' => true,
        'proPdfAttachAutoReply'    => true,
    ) );
    $form_id = SurveyJS_Forms::create( array(
        'name'     => $name,
        'json'     => wp_json_encode( $json ),
        'settings' => wp_json_encode( $settings ),
    ) );
    $entries = array(
        array( 'name' => 'Анна Петрова', 'email' => 'anna@example.test', 'comment' => 'Прошу перезвонить (после 18:00)', 'topics' => array( 'Billing' ) ),
        array( 'name' => 'John Smith', 'email' => 'john@example.test', 'comment' => 'Back\\slash and (parentheses)', 'topics' => array( 'Support', 'Sales' ) ),
    );
    $ids = array();
    foreach ( $entries as $entry ) {
        $ids[] = SurveyJS_Entries::insert( $form_id, wp_json_encode( $entry ), array( 'status' => 'read' ) );
    }
    $page_id = wp_insert_post( array(
        'post_type'    => 'page',
        'post_status'  => 'publish',
        'post_title'   => $name,
        'post_content' => '[surveyjs id="' . (int) $form_id . '"]',
    ) );
    echo wp_json_encode( array( 'id' => $form_id, 'entries' => $ids, 'page' => $page_id, 'url' => get_permalink( $page_id ) ) ), "\n";
} elseif ( 'password' === $command ) {
    $form_id = absint( $argv[2] ?? 0 );
    $stored = (string) get_post_meta( $form_id, SurveyJS_Pro_Pdf_Password::META, true );
    echo wp_json_encode( array(
        'stored'   => '' !== $stored,
        'readable' => '' !== $stored && false !== strpos( $stored, (string) ( $argv[3] ?? '' ) ),
        'password' => SurveyJS_Pro_Pdf_Password::password( $form_id ),
        'enabled'  => SurveyJS_Pro_Pdf_Password::enabled( SurveyJS_Form_Settings::get( SurveyJS_Forms::find( $form_id ) ) ),
    ) ), "\n";
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
