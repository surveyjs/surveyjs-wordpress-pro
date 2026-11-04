<?php
add_filter( 'surveyjs_editor_tabs', function ( $tabs ) {
    $tabs['contract'] = 'Contract Tab';
    return $tabs;
} );

add_action( 'surveyjs_render_editor', function ( $survey ) {
    echo '<div class="wp-sjs-editor__settings" data-panel="contract" hidden><label for="contract-option">Contract option</label> <input type="text" id="contract-option" data-setting="contractOption" /></div>';
} );

add_filter( 'surveyjs_form_settings_defaults', function ( $defaults ) {
    $defaults['contractOption'] = '';
    return $defaults;
} );

add_filter( 'surveyjs_form_settings_sanitize', function ( $sanitized, $input ) {
    $sanitized['contractOption'] = sanitize_text_field( $input['contractOption'] );
    return $sanitized;
}, 10, 2 );

add_filter( 'surveyjs_results_views', function ( $views ) {
    $views['contract'] = 'Contract View';
    return $views;
} );

add_action( 'surveyjs_render_results_view', function ( $view, $survey ) {
    if ( 'contract' === $view ) {
        echo '<p id="contract-view">' . esc_html( count( SurveyJS_Entries::query( $survey->id ) ) ) . ' entries</p>';
    }
}, 10, 2 );

add_action( 'surveyjs_results_title_actions', function () {
    echo '<a href="#" class="page-title-action" id="contract-title-action">Contract Action</a>';
} );

add_filter( 'surveyjs_entries_bulk_actions', function ( $actions ) {
    $actions['contract_bulk'] = 'Contract Bulk';
    return $actions;
} );

add_action( 'surveyjs_entries_bulk_action_contract_bulk', function ( $ids ) {
    update_option( 'surveyjs_contract_bulk', $ids, false );
} );

add_filter( 'surveyjs_entry_row_actions', function ( $actions, $item ) {
    $actions['contract'] = '<a href="#" class="contract-row-action">Contract ' . (int) $item->id . '</a>';
    return $actions;
}, 10, 2 );

add_filter( 'surveyjs_form_row_actions', function ( $actions ) {
    $actions['contract'] = '<a href="#" class="contract-form-action">Contract Form</a>';
    return $actions;
} );

add_action( 'surveyjs_admin_menu', function ( $parent, $capability ) {
    add_submenu_page( $parent, 'Contract Page', 'Contract Page', $capability, 'surveyjs-contract', function () {
        echo '<div class="wrap"><h1>Contract Page</h1></div>';
    } );
}, 10, 2 );
