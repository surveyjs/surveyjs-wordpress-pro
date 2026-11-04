<?php
require '/var/www/html/wp-load.php';

$command = $argv[1] ?? '';
if ( 'create' === $command ) {
    $json = array(
        'elements' => array(
            array( 'type' => 'radiogroup', 'name' => 'color', 'title' => 'Favorite color', 'choices' => array( 'Red', 'Green', 'Blue' ) ),
        ),
    );
    $form_id = SurveyJS_Forms::create( array( 'name' => 'E2E Analytics', 'json' => wp_json_encode( $json ) ) );
    foreach ( array( 'Red', 'Red', 'Blue' ) as $color ) {
        SurveyJS_Entries::insert( $form_id, wp_json_encode( array( 'color' => $color ) ), array( 'status' => 'read' ) );
    }
    SurveyJS_Entries::insert( $form_id, wp_json_encode( array( 'color' => 'Green' ) ), array( 'status' => 'spam' ) );
    echo wp_json_encode( array( 'id' => $form_id ) ), "\n";
} elseif ( 'cleanup' === $command ) {
    $form_id = absint( $argv[2] ?? 0 );
    if ( $form_id ) {
        SurveyJS_Forms::delete( $form_id );
    }
    echo "{}\n";
}
