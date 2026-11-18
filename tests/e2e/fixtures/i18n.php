<?php
require '/var/www/html/wp-load.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.NotAbsolutePath -- absolute path of WordPress in the demo container; the script is piped to php over stdin, so it has no __DIR__.
if ( ! defined( 'ABSPATH' ) ) exit;

$user       = get_user_by( 'login', (string) ( $argv[1] ?? '' ) );
$new_locale = (string) ( $argv[2] ?? '' );
if ( $user ) {
    update_user_meta( $user->ID, 'locale', $new_locale );
}
echo wp_json_encode( array( 'user' => $user ? $user->ID : 0 ) ), "\n";
