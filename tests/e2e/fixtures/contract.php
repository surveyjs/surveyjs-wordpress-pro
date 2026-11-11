<?php
require '/var/www/html/wp-load.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.NotAbsolutePath -- absolute path of WordPress in the demo container; the script is piped to php over stdin, so it has no __DIR__.
if ( ! defined( 'ABSPATH' ) ) exit;

$command = $argv[1] ?? '';
$form_id = absint( $argv[2] ?? 0 );
if ( 'insert-entry' === $command ) {
    echo wp_json_encode( array( 'id' => SurveyJS_Entries::insert( $form_id, wp_json_encode( array( 'e2e_question' => 'Contract' ) ) ) ) ), "\n";
} elseif ( 'state' === $command ) {
    $form = SurveyJS_Forms::find( $form_id );
    echo wp_json_encode( array(
        'bulk'     => get_option( 'surveyjs_contract_bulk' ),
        'settings' => $form ? json_decode( $form->settings, true ) : null,
        'json'     => $form ? json_decode( $form->json, true ) : null,
    ) ), "\n";
} elseif ( 'cleanup' === $command ) {
    delete_option( 'surveyjs_contract_bulk' );
    if ( $form_id ) {
        SurveyJS_Forms::delete( $form_id );
    }
    echo "{}\n";
}
