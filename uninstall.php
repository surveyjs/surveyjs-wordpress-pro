<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

// Forms and entries belong to SurveyJS Forms, so Pro removes only its own options.
function surveyjs_pro_uninstall_site() {
    delete_option( 'surveyjs_pro_settings' );
    delete_option( 'surveyjs_pro_db_version' );
    delete_option( 'surveyjs_pro_upgrade_lock' );
    delete_post_meta_by_key( '_surveyjs_pro_pdf_password' );
}

if ( is_multisite() ) {
    foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $surveyjs_pro_site_id ) {
        switch_to_blog( $surveyjs_pro_site_id );
        surveyjs_pro_uninstall_site();
        restore_current_blog();
    }
} else {
    surveyjs_pro_uninstall_site();
}
