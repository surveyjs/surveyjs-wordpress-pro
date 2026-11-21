<?php
require '/var/www/html/wp-load.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.NotAbsolutePath -- absolute path of WordPress in the demo container; the script is piped to php over stdin, so it has no __DIR__.
if ( ! defined( 'ABSPATH' ) ) exit;

$command = $argv[1] ?? '';
$user = get_user_by( 'login', $argv[2] ?? '' );
$result = array();

if ( 'setup' === $command ) {
    $result['wasEnabled'] = get_option( SurveyJS_AI::ENABLED_OPTION );
    update_option( SurveyJS_AI::ENABLED_OPTION, '1' );
    $result['wasWrite'] = get_option( SurveyJS_AI::WRITE_OPTION );
    update_option( SurveyJS_AI::WRITE_OPTION, '1' );
    $result['adapter'] = SurveyJS_AI::adapter_state();
    $result['proSettings'] = SurveyJS_Pro_Settings::all();
    $result['form'] = SurveyJS_Forms::create( array(
        'name' => 'E2E Pro AI ' . time(),
        'json' => wp_json_encode( array( 'elements' => array( array( 'type' => 'text', 'name' => 'q1' ) ) ) ),
    ) );
    if ( wp_is_application_passwords_supported() ) {
        $created = WP_Application_Passwords::create_new_application_password( $user->ID, array(
            'name'   => 'E2E Pro AI',
            'app_id' => SurveyJS_AI::APP_ID,
        ) );
        $result['header'] = 'Basic ' . base64_encode( $user->user_login . ':' . $created[0] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- builds the HTTP Basic header.
        $result['uuid'] = $created[1]['uuid'];
    }
    $result['endpoint'] = SurveyJS_AI::endpoint_url();
} elseif ( 'pro-settings' === $command ) {
    $result = SurveyJS_Pro_Settings::all();
} elseif ( 'draft' === $command ) {
    $result = SurveyJS_AI_Drafts::get( (int) $argv[3] );
} elseif ( 'cleanup' === $command ) {
    $state = json_decode( $argv[3], true );
    if ( ! empty( $state['uuid'] ) ) {
        WP_Application_Passwords::delete_application_password( $user->ID, $state['uuid'] );
    }
    if ( ! empty( $state['form'] ) ) {
        SurveyJS_Forms::delete( (int) $state['form'] );
    }
    SurveyJS_Pro_Settings::update( $state['proSettings'] );
    update_option( SurveyJS_AI::ENABLED_OPTION, (string) $state['wasEnabled'] );
    update_option( SurveyJS_AI::WRITE_OPTION, (string) $state['wasWrite'] );
}

echo wp_json_encode( $result ), "\n";
