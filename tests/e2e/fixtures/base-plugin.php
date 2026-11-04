<?php
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$base = 'surveyjs/surveyjs.php';
if ( 'deactivate' === ( $argv[1] ?? '' ) ) {
    deactivate_plugins( $base, true );
} elseif ( 'activate' === ( $argv[1] ?? '' ) ) {
    activate_plugin( $base, '', false, true );
}
echo wp_json_encode( array( 'active' => is_plugin_active( $base ) ) ), "\n";
