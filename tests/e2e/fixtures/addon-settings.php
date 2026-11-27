<?php
require '/var/www/html/wp-load.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.NotAbsolutePath -- absolute path of WordPress in the demo container; the script is piped to php over stdin, so it has no __DIR__.
if ( ! defined( 'ABSPATH' ) ) exit;

if ( 'set' === ( $argv[1] ?? '' ) ) {
    $value = json_decode( (string) ( $argv[2] ?? 'null' ), true );
    if ( null === $value ) {
        delete_option( SurveyJS_Addon_Settings::OPTION );
    } else {
        update_option( SurveyJS_Addon_Settings::OPTION, $value );
    }
}
echo wp_json_encode( get_option( SurveyJS_Addon_Settings::OPTION, null ) ), "\n";
