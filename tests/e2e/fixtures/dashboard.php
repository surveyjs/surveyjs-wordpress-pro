<?php
require '/var/www/html/wp-load.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.NotAbsolutePath -- absolute path of WordPress in the demo container; the script is piped to php over stdin, so it has no __DIR__.
if ( ! defined( 'ABSPATH' ) ) exit;

$command = $argv[1] ?? '';
if ( 'create' === $command ) {
    $form_id = SurveyJS_Forms::create( array(
        'name' => 'E2E Dashboard ' . time(),
        'json' => wp_json_encode( array( 'elements' => array( array( 'type' => 'text', 'name' => 'e2e_name', 'title' => 'What is your name?' ) ) ) ),
    ) );
    $entries = array();
    foreach ( array( 'Ann', 'Bob' ) as $index => $name ) {
        $entries[] = SurveyJS_Entries::insert( $form_id, wp_json_encode( array( 'e2e_name' => $name ) ), array(
            'created_at' => gmdate( 'Y-m-d H:i:s', time() - ( 1 - $index ) * MINUTE_IN_SECONDS ),
        ) );
    }
    echo wp_json_encode( array( 'form' => $form_id, 'entries' => $entries ) ), "\n";
} elseif ( 'cleanup' === $command ) {
    $form_id = absint( $argv[2] ?? 0 );
    if ( $form_id ) {
        SurveyJS_Forms::delete( $form_id );
    }
    echo "{}\n";
}
