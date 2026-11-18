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
    global $wpdb;
    foreach ( array( 0 => array( 30, 3 ), 2 => array( 10, 1 ), 40 => array( 500, 50 ) ) as $days_ago => $stats ) {
        $wpdb->insert( SurveyJS_Form_Stats::table(), array(
            'form_id'     => $form_id,
            'stat_date'   => wp_date( 'Y-m-d', time() - $days_ago * DAY_IN_SECONDS ),
            'views'       => $stats[0],
            'submissions' => $stats[1],
        ) );
    }
    echo wp_json_encode( array( 'form' => $form_id, 'entries' => $entries ) ), "\n";
} elseif ( 'views-setting' === $command ) {
    $settings = array_filter( (array) get_option( 'surveyjs-settings', array() ), 'is_string', ARRAY_FILTER_USE_KEY );
    $previous = array_key_exists( SurveyJS_Form_Stats::SETTING, $settings ) ? ( $settings[ SurveyJS_Form_Stats::SETTING ] ? 'on' : 'off' ) : 'default';
    if ( 'default' === ( $argv[2] ?? '' ) ) {
        unset( $settings[ SurveyJS_Form_Stats::SETTING ] );
    } else {
        $settings[ SurveyJS_Form_Stats::SETTING ] = 'on' === ( $argv[2] ?? '' );
    }
    update_option( 'surveyjs-settings', $settings );
    echo wp_json_encode( array( 'previous' => $previous ) ), "\n";
} elseif ( 'cleanup' === $command ) {
    $form_id = absint( $argv[2] ?? 0 );
    if ( $form_id ) {
        SurveyJS_Forms::delete( $form_id );
    }
    echo "{}\n";
}
