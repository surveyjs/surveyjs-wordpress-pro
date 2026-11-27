<?php
require '/var/www/html/wp-load.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.NotAbsolutePath -- absolute path of WordPress in the demo container; the script is piped to php over stdin, so it has no __DIR__.
if ( ! defined( 'ABSPATH' ) ) exit;
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$command = $argv[1] ?? '';
if ( 'offer-base-update' === $command ) {
    $checked = array();
    foreach ( get_plugins() as $file => $data ) {
        $checked[ $file ] = $data['Version'];
    }
    $updates = (object) array(
        'last_checked' => time(),
        'checked'      => $checked,
        'response'     => array(
            SurveyJS_Addon_Updater::BASE => (object) array(
                'slug'        => 'surveyjs',
                'plugin'      => SurveyJS_Addon_Updater::BASE,
                'new_version' => '99.0.0',
                'package'     => '',
                'url'         => 'https://wordpress.org/plugins/surveyjs/',
            ),
        ),
        'no_update'    => array(),
        'translations' => array(),
    );
    set_site_transient( 'update_plugins', $updates );
} elseif ( 'clear-base-update' === $command ) {
    delete_site_transient( 'update_plugins' );
}
echo wp_json_encode(
    array(
        'composer' => SurveyJS_Addon_Updater::composer(),
        'enabled'  => SurveyJS_Addon_Updater::enabled(),
    )
), "\n";
