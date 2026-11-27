<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

// Forms and entries belong to SurveyJS Forms, so the add-on removes only its own options.
function surveyjs_addon_uninstall_site() {
    delete_option( 'surveyjs_addon_settings' );
    delete_option( 'surveyjs_addon_db_version' );
    delete_option( 'surveyjs_addon_upgrade_lock' );
    delete_post_meta_by_key( '_surveyjs_addon_pdf_password' );
}

if ( is_multisite() ) {
    foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $surveyjs_addon_site_id ) {
        switch_to_blog( $surveyjs_addon_site_id );
        surveyjs_addon_uninstall_site();
        restore_current_blog();
    }
} else {
    surveyjs_addon_uninstall_site();
}

delete_site_option( 'surveyjs_addon_update_error' );
