<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SurveyJS_Pro_Dashboard {

    const PAGE = 'surveyjs-pro-dashboard';
    const SCRIPT = 'surveyjs-pro-dashboard';
    const SUBMITTED = array( 'unread', 'read' );
    const DEFAULT_RANGE = '30d';
    const LATEST = 10;
    const CHART_FORMS = 10;
    const LATEST_SUBQUERIES = 100;

    private static $hook = '';
    private static $data = null;

    public static function init() {
        add_action( 'surveyjs_admin_menu', array( __CLASS__, 'add_page' ), 10, 2 );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
    }

    public static function add_page( $parent, $capability ) {
        self::$hook = (string) add_submenu_page( $parent, __( 'Dashboard', 'surveyjs-pro' ), __( 'Dashboard', 'surveyjs-pro' ), $capability, self::PAGE, array( __CLASS__, 'render' ), 3 );
    }

    public static function url( $args = array() ) {
        return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
    }

    private static function base_url( $page, $args = array() ) {
        return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
    }

    public static function ranges() {
        return array(
            '7d'  => array( 'label' => __( 'Last 7 days', 'surveyjs-pro' ), 'unit' => 'day', 'count' => 7 ),
            '30d' => array( 'label' => __( 'Last 30 days', 'surveyjs-pro' ), 'unit' => 'day', 'count' => 30 ),
            '90d' => array( 'label' => __( 'Last 90 days', 'surveyjs-pro' ), 'unit' => 'day', 'count' => 90 ),
            '12m' => array( 'label' => __( 'Last 12 months', 'surveyjs-pro' ), 'unit' => 'month', 'count' => 12 ),
        );
    }

    public static function current_range() {
        $range = sanitize_key( wp_unslash( $_GET['range'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return isset( self::ranges()[ $range ] ) ? $range : self::DEFAULT_RANGE;
    }

    public static function enqueue_assets( $hook_suffix ) {
        if ( '' === self::$hook || $hook_suffix !== self::$hook ) {
            return;
        }
        SurveyJS_Pro_Admin_Theme::enqueue();
        wp_enqueue_style( self::SCRIPT, plugins_url( 'assets/dashboard.css', SURVEYJS_PRO_FILE ), array( SurveyJS_Pro_Admin_Theme::HANDLE ), SURVEYJS_PRO_VERSION );
        if ( ! SurveyJS_Pro_Libs::register() ) {
            return;
        }
        $data = self::data();
        if ( ! $data['forms'] ) {
            return;
        }
        wp_enqueue_script( self::SCRIPT, plugins_url( 'assets/dashboard.js', SURVEYJS_PRO_FILE ), array( SurveyJS_Pro_Libs::handle( 'chartjs' ), SurveyJS_Pro_Admin_Theme::HANDLE ), SURVEYJS_PRO_VERSION, true );
        wp_add_inline_script( self::SCRIPT, 'var surveyjsProDashboard = ' . wp_json_encode( self::chart_data( $data ), JSON_HEX_TAG | JSON_HEX_AMP ) . ';', 'before' );
    }

    public static function data() {
        if ( null !== self::$data ) {
            return self::$data;
        }
        $range = self::current_range();
        $period = self::period( $range );
        $forms = array();
        foreach ( SurveyJS_Forms::names() as $form ) {
            $forms[ (int) $form->id ] = (object) array(
                'id'         => (int) $form->id,
                'name'       => '' !== (string) $form->name ? (string) $form->name : __( '(no title)', 'surveyjs-pro' ),
                'total'      => 0,
                'unread'     => 0,
                'recent'     => 0,
                'previous'   => 0,
                'last_entry' => '',
                'views'       => 0,
                'submissions' => 0,
                'previous_views'       => 0,
                'previous_submissions' => 0,
            );
        }
        $totals = array( 'total' => 0, 'unread' => 0, 'recent' => 0, 'previous' => 0, 'views' => 0, 'submissions' => 0, 'previous_views' => 0, 'previous_submissions' => 0 );
        $views = self::views_enabled();
        if ( $views ) {
            foreach ( array( '' => array( $period['start_date'], '' ), 'previous_' => array( $period['previous_start_date'], $period['start_date'] ) ) as $prefix => $dates ) {
                foreach ( SurveyJS_Form_Stats::totals( array_keys( $forms ), $dates[0], $dates[1] ) as $form_id => $stats ) {
                    if ( isset( $forms[ $form_id ] ) ) {
                        $forms[ $form_id ]->{ $prefix . 'views' } = $stats->views;
                        $forms[ $form_id ]->{ $prefix . 'submissions' } = $stats->submissions;
                    }
                }
            }
        }
        foreach ( self::form_counts( array_keys( $forms ), $period ) as $row ) {
            $form = $forms[ (int) $row->form_id ] ?? null;
            if ( ! $form ) {
                continue;
            }
            $form->total += (int) $row->total;
            $form->recent += (int) $row->recent;
            $form->previous += (int) $row->previous;
            if ( 'unread' === $row->status ) {
                $form->unread += (int) $row->total;
            }
            if ( (string) $row->last_entry > $form->last_entry ) {
                $form->last_entry = (string) $row->last_entry;
            }
        }
        foreach ( $forms as $form ) {
            foreach ( $totals as $key => $value ) {
                $totals[ $key ] = $value + $form->$key;
            }
        }
        uasort( $forms, function ( $a, $b ) {
            return array( $b->recent, $b->total, $b->last_entry ) <=> array( $a->recent, $a->total, $a->last_entry );
        } );
        $active = array_filter( $forms, function ( $form ) {
            return $form->total > 0 || $form->views > 0;
        } );
        self::$data = array(
            'range'    => $range,
            'period'   => $period,
            'forms'    => $forms,
            'active'   => $active,
            'totals'   => $totals,
            'timeline' => self::timeline( array_keys( $active ), $period ),
            'views'    => $views ? self::views_timeline( array_keys( $forms ), $period ) : null,
            'latest'   => self::latest_entries( array_keys( $active ) ),
        );
        return self::$data;
    }

    private static function period( $range ) {
        $config = self::ranges()[ $range ];
        $timezone = wp_timezone();
        $today = new DateTimeImmutable( 'today', $timezone );
        $count = (int) $config['count'];
        $unit = $config['unit'];
        $start = 'month' === $unit
            ? $today->modify( 'first day of this month' )->modify( '-' . ( $count - 1 ) . ' months' )
            : $today->modify( '-' . ( $count - 1 ) . ' days' );
        $buckets = array();
        for ( $index = 0; $index < $count; $index++ ) {
            $date = $start->modify( '+' . $index . ' ' . $unit );
            $buckets[ $date->format( 'month' === $unit ? 'Y-m' : 'Y-m-d' ) ] = array(
                'label' => wp_date( 'month' === $unit ? 'M Y' : 'M j', $date->getTimestamp() ),
                'title' => wp_date( 'month' === $unit ? 'F Y' : get_option( 'date_format' ), $date->getTimestamp() ),
            );
        }
        $utc = new DateTimeZone( 'UTC' );
        $previous_start = $start->modify( '-' . $count . ' ' . $unit );
        return array(
            'unit'           => $unit,
            'count'          => $count,
            'label'          => $config['label'],
            'start'          => $start->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
            'previous_start' => $previous_start->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
            'start_date'          => $start->format( 'Y-m-d' ),
            'previous_start_date' => $previous_start->format( 'Y-m-d' ),
            'offset'         => $timezone->getOffset( new DateTimeImmutable( 'now', $timezone ) ),
            'buckets'        => $buckets,
        );
    }

    private static function form_counts( $form_ids, $period ) {
        global $wpdb;
        if ( ! $form_ids ) {
            return array();
        }
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT form_id, status, COUNT(*) AS total, SUM( created_at >= %s ) AS recent, SUM( created_at >= %s AND created_at < %s ) AS previous, MAX( created_at ) AS last_entry
            FROM %i WHERE form_id IN ( ' . implode( ',', array_fill( 0, count( $form_ids ), '%d' ) ) . ' ) AND status IN ( ' . implode( ',', array_fill( 0, count( self::SUBMITTED ), '%s' ) ) . ' ) GROUP BY form_id, status',
            array_merge( array( $period['start'], $period['previous_start'], $period['start'], SurveyJS_Entries::table() ), $form_ids, self::SUBMITTED )
        ) );
    }

    private static function timeline( $form_ids, $period ) {
        global $wpdb;
        $values = array_fill_keys( array_keys( $period['buckets'] ), 0 );
        if ( $form_ids ) {
            // Grouped by the site's current UTC offset: MySQL time zone tables are often missing, so CONVERT_TZ() can't be relied on.
            $rows = $wpdb->get_results( $wpdb->prepare(
                'SELECT DATE_FORMAT( DATE_ADD( created_at, INTERVAL %d SECOND ), %s ) AS bucket, COUNT(*) AS total
                FROM %i WHERE form_id IN ( ' . implode( ',', array_fill( 0, count( $form_ids ), '%d' ) ) . ' ) AND status IN ( ' . implode( ',', array_fill( 0, count( self::SUBMITTED ), '%s' ) ) . ' ) AND created_at >= %s GROUP BY bucket',
                array_merge( array( $period['offset'], 'month' === $period['unit'] ? '%Y-%m' : '%Y-%m-%d', SurveyJS_Entries::table() ), $form_ids, self::SUBMITTED, array( $period['start'] ) )
            ) );
            foreach ( $rows as $row ) {
                if ( isset( $values[ $row->bucket ] ) ) {
                    $values[ $row->bucket ] += (int) $row->total;
                }
            }
        }
        return $values;
    }

    public static function views_enabled() {
        return class_exists( 'SurveyJS_Form_Stats' ) && SurveyJS_Form_Stats::enabled();
    }

    private static function views_timeline( $form_ids, $period ) {
        $values = array_fill_keys( array_keys( $period['buckets'] ), array( 'views' => 0, 'submissions' => 0 ) );
        foreach ( SurveyJS_Form_Stats::timeline( $form_ids, $period['start_date'], $period['unit'] ) as $bucket => $stats ) {
            if ( isset( $values[ $bucket ] ) ) {
                $values[ $bucket ] = array( 'views' => $stats->views, 'submissions' => $stats->submissions );
            }
        }
        return $values;
    }

    // One index-ordered subquery per form and status reads at most LATEST rows each,
    // while a single ORDER BY created_at across all forms would sort every entry.
    private static function latest_entries( $form_ids ) {
        global $wpdb;
        $table = SurveyJS_Entries::table();
        $subqueries = array();
        foreach ( $form_ids as $form_id ) {
            foreach ( self::SUBMITTED as $status ) {
                $subqueries[] = $wpdb->prepare( '( SELECT id, form_id, status, user_id, created_at FROM %i WHERE form_id = %d AND status = %s ORDER BY created_at DESC, id DESC LIMIT %d )', $table, $form_id, $status, self::LATEST );
            }
        }
        $entries = array();
        foreach ( array_chunk( $subqueries, self::LATEST_SUBQUERIES ) as $chunk ) {
            $entries = array_merge( $entries, $wpdb->get_results( implode( ' UNION ALL ', $chunk ) . $wpdb->prepare( ' ORDER BY created_at DESC, id DESC LIMIT %d', self::LATEST ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }
        usort( $entries, function ( $a, $b ) {
            return array( $b->created_at, (int) $b->id ) <=> array( $a->created_at, (int) $a->id );
        } );
        $entries = array_slice( $entries, 0, self::LATEST );
        $user_ids = array_unique( array_filter( array_map( 'intval', wp_list_pluck( $entries, 'user_id' ) ) ) );
        if ( $user_ids ) {
            cache_users( $user_ids );
        }
        return $entries;
    }

    private static function chart_data( $data ) {
        $forms = array_values( array_filter( $data['forms'], function ( $form ) {
            return $form->recent > 0;
        } ) );
        $shown = array_slice( $forms, 0, self::CHART_FORMS );
        $chart_forms = array(
            'labels' => wp_list_pluck( $shown, 'name' ),
            'values' => array_map( 'intval', wp_list_pluck( $shown, 'recent' ) ),
            'urls'   => array_map( function ( $form ) {
                return self::base_url( 'surveyjs_results', array( 'id' => $form->id ) );
            }, $shown ),
        );
        $rest = array_slice( $forms, self::CHART_FORMS );
        if ( $rest ) {
            $chart_forms['labels'][] = sprintf(
                /* translators: %s: number of forms */
                _n( 'Other (%s form)', 'Other (%s forms)', count( $rest ), 'surveyjs-pro' ),
                number_format_i18n( count( $rest ) )
            );
            $chart_forms['values'][] = array_sum( wp_list_pluck( $rest, 'recent' ) );
            $chart_forms['urls'][] = '';
        }
        $chart = array(
            'timeline' => array(
                'labels' => array_values( wp_list_pluck( $data['period']['buckets'], 'label' ) ),
                'titles' => array_values( wp_list_pluck( $data['period']['buckets'], 'title' ) ),
                'values' => array_values( $data['timeline'] ),
            ),
            'forms'    => $chart_forms,
            'i18n'     => array(
                'entries'     => __( 'Entries', 'surveyjs-pro' ),
                'views'       => __( 'Views', 'surveyjs-pro' ),
                'submissions' => __( 'Submissions', 'surveyjs-pro' ),
                /* translators: %s: conversion rate, e.g. "12.5%" */
                'conversion'  => __( 'Conversion: %s', 'surveyjs-pro' ),
            ),
        );
        if ( $data['views'] ) {
            $chart['views'] = array(
                'views'       => array_values( wp_list_pluck( $data['views'], 'views' ) ),
                'submissions' => array_values( wp_list_pluck( $data['views'], 'submissions' ) ),
                'conversion'  => array_values( array_map( function ( $bucket ) {
                    return SurveyJS_Form_Stats::format_conversion( $bucket['views'], $bucket['submissions'] );
                }, $data['views'] ) ),
            );
        }
        return $chart;
    }

    private static function change_text( $recent, $previous, $period ) {
        if ( $previous <= 0 ) {
            return '';
        }
        $change = (int) round( ( $recent - $previous ) / $previous * 100 );
        $previous_label = sprintf(
            /* translators: %s: number of days or months */
            'month' === $period['unit'] ? _n( 'previous %s month', 'previous %s months', $period['count'], 'surveyjs-pro' ) : _n( 'previous %s day', 'previous %s days', $period['count'], 'surveyjs-pro' ),
            number_format_i18n( $period['count'] )
        );
        // U+2066/U+2069 isolate the number as left-to-right, or RTL languages show "+12%" as "12%+".
        /* translators: 1: signed percentage change, e.g. "+12%", 2: the comparison period, e.g. "previous 30 days" */
        return sprintf( __( '%1$s vs %2$s', 'surveyjs-pro' ), "\u{2066}" . ( $change > 0 ? '+' : '' ) . number_format_i18n( $change ) . "%\u{2069}", $previous_label );
    }

    private static function conversion_note( $views, $submissions ) {
        return sprintf(
            /* translators: 1: number of submissions, 2: number of views */
            _n( '%1$s submission from %2$s views', '%1$s submissions from %2$s views', $submissions, 'surveyjs-pro' ),
            number_format_i18n( $submissions ),
            number_format_i18n( $views )
        );
    }

    public static function render() {
        if ( ! current_user_can( SurveyJS_SurveyJS::CAPABILITY ) ) {
            wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'surveyjs-pro' ) );
        }
        $data = self::data();
        $period = $data['period'];
        $totals = $data['totals'];
        $forms_url = self::base_url( SurveyJS_SurveyJS::MENU_SLUG );
        $active_forms = $data['active'];
        ?>
        <div class="wrap wp-sjs-pro-dashboard">
            <?php // Notices from WordPress and other plugins go here, above the title, on every plugin page. ?>
            <hr class="wp-header-end">
            <?php // A block title: the range links below float left and would otherwise sit on the title's line. ?>
            <h1><?php esc_html_e( 'Dashboard', 'surveyjs-pro' ); ?></h1>

            <?php if ( ! $data['forms'] ) : ?>
                <div class="wp-sjs-pro-dashboard__panel wp-sjs-pro-dashboard__empty">
                    <p><?php esc_html_e( 'No forms yet. Create a form, add it to a page, and its entries will show up here.', 'surveyjs-pro' ); ?></p>
                    <p><a class="button button-primary" href="<?php echo esc_url( $forms_url ); ?>"><?php esc_html_e( 'Go to Forms', 'surveyjs-pro' ); ?></a></p>
                </div>
            </div>
                <?php
                return;
            endif;
            ?>

            <ul class="subsubsub wp-sjs-pro-dashboard__ranges">
                <?php
                $links = array();
                foreach ( self::ranges() as $key => $range ) {
                    $current = $key === $data['range'];
                    $links[] = sprintf(
                        '<li><a href="%s"%s>%s</a>',
                        esc_url( self::url( self::DEFAULT_RANGE === $key ? array() : array( 'range' => $key ) ) ),
                        $current ? ' class="current" aria-current="page"' : '',
                        esc_html( $range['label'] )
                    );
                }
                echo implode( ' |</li>', $links ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ?>
            </ul>
            <div class="clear"></div>

            <div class="wp-sjs-pro-dashboard__stats">
                <div class="wp-sjs-pro-dashboard__stat">
                    <span class="wp-sjs-pro-dashboard__stat-label"><?php echo esc_html( $period['label'] ); ?></span>
                    <span class="wp-sjs-pro-dashboard__stat-value"><?php echo esc_html( number_format_i18n( $totals['recent'] ) ); ?></span>
                    <span class="wp-sjs-pro-dashboard__stat-note"><?php echo esc_html( self::change_text( $totals['recent'], $totals['previous'], $period ) ); ?></span>
                </div>
                <div class="wp-sjs-pro-dashboard__stat">
                    <span class="wp-sjs-pro-dashboard__stat-label"><?php esc_html_e( 'All entries', 'surveyjs-pro' ); ?></span>
                    <span class="wp-sjs-pro-dashboard__stat-value"><?php echo esc_html( number_format_i18n( $totals['total'] ) ); ?></span>
                </div>
                <div class="wp-sjs-pro-dashboard__stat">
                    <span class="wp-sjs-pro-dashboard__stat-label"><?php esc_html_e( 'Unread', 'surveyjs-pro' ); ?></span>
                    <span class="wp-sjs-pro-dashboard__stat-value"><?php echo esc_html( number_format_i18n( $totals['unread'] ) ); ?></span>
                </div>
                <div class="wp-sjs-pro-dashboard__stat">
                    <span class="wp-sjs-pro-dashboard__stat-label"><?php esc_html_e( 'Forms', 'surveyjs-pro' ); ?></span>
                    <span class="wp-sjs-pro-dashboard__stat-value"><a href="<?php echo esc_url( $forms_url ); ?>"><?php echo esc_html( number_format_i18n( count( $data['forms'] ) ) ); ?></a></span>
                </div>
                <?php if ( $data['views'] ) : ?>
                    <div class="wp-sjs-pro-dashboard__stat">
                        <span class="wp-sjs-pro-dashboard__stat-label"><?php esc_html_e( 'Views', 'surveyjs-pro' ); ?></span>
                        <span class="wp-sjs-pro-dashboard__stat-value"><?php echo esc_html( number_format_i18n( $totals['views'] ) ); ?></span>
                        <span class="wp-sjs-pro-dashboard__stat-note"><?php echo esc_html( self::change_text( $totals['views'], $totals['previous_views'], $period ) ); ?></span>
                    </div>
                    <div class="wp-sjs-pro-dashboard__stat">
                        <span class="wp-sjs-pro-dashboard__stat-label"><?php esc_html_e( 'Conversion', 'surveyjs-pro' ); ?></span>
                        <span class="wp-sjs-pro-dashboard__stat-value"><?php echo esc_html( SurveyJS_Form_Stats::format_conversion( $totals['views'], $totals['submissions'] ) ); ?></span>
                        <span class="wp-sjs-pro-dashboard__stat-note"><?php echo esc_html( self::conversion_note( $totals['views'], $totals['submissions'] ) ); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="wp-sjs-pro-dashboard__charts">
                <section class="wp-sjs-pro-dashboard__panel">
                    <h2><?php esc_html_e( 'Entries over time', 'surveyjs-pro' ); ?></h2>
                    <div class="wp-sjs-pro-dashboard__chart">
                        <canvas id="wp-sjs-pro-dashboard-timeline" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: number of entries, 2: period, e.g. "Last 30 days" */ __( '%1$s entries, %2$s', 'surveyjs-pro' ), number_format_i18n( $totals['recent'] ), $period['label'] ) ); ?>"></canvas>
                    </div>
                </section>
                <section class="wp-sjs-pro-dashboard__panel">
                    <h2><?php esc_html_e( 'Entries by form', 'surveyjs-pro' ); ?></h2>
                    <?php if ( $totals['recent'] > 0 ) : ?>
                        <?php $bars = min( self::CHART_FORMS + 1, count( array_filter( wp_list_pluck( $data['forms'], 'recent' ) ) ) ); ?>
                        <div class="wp-sjs-pro-dashboard__chart" style="height: <?php echo esc_attr( max( 160, 40 + 32 * $bars ) ); ?>px">
                            <canvas id="wp-sjs-pro-dashboard-forms" role="img" aria-label="<?php esc_attr_e( 'Entries by form; the same numbers are in the Forms table below.', 'surveyjs-pro' ); ?>"></canvas>
                        </div>
                    <?php else : ?>
                        <p class="description"><?php esc_html_e( 'No entries in this period.', 'surveyjs-pro' ); ?></p>
                    <?php endif; ?>
                </section>
            </div>

            <section class="wp-sjs-pro-dashboard__panel wp-sjs-pro-dashboard__conversion">
                <h2><?php esc_html_e( 'Views and submissions', 'surveyjs-pro' ); ?></h2>
                <?php if ( ! $data['views'] ) : ?>
                    <p class="description">
                        <?php esc_html_e( 'Form views are not counted, so there is no conversion rate.', 'surveyjs-pro' ); ?>
                        <a href="<?php echo esc_url( self::base_url( 'surveyjs-settings' ) ); ?>"><?php esc_html_e( 'Turn on Form views in Settings', 'surveyjs-pro' ); ?></a>
                    </p>
                <?php elseif ( $totals['views'] > 0 || $totals['submissions'] > 0 ) : ?>
                    <div class="wp-sjs-pro-dashboard__chart">
                        <canvas id="wp-sjs-pro-dashboard-views" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: number of views, 2: number of submissions, 3: period, e.g. "Last 30 days" */ __( '%1$s views and %2$s submissions, %3$s; the same numbers per form are in the Forms table below.', 'surveyjs-pro' ), number_format_i18n( $totals['views'] ), number_format_i18n( $totals['submissions'] ), $period['label'] ) ); ?>"></canvas>
                    </div>
                    <p class="description"><?php esc_html_e( 'Conversion is submissions divided by views. A view is counted when a visitor sees the form on the site; logged-in users who can edit posts and bots are not counted.', 'surveyjs-pro' ); ?></p>
                <?php else : ?>
                    <p class="description"><?php esc_html_e( 'No views in this period. A view is counted when a visitor sees a form on the site; logged-in users who can edit posts are not counted.', 'surveyjs-pro' ); ?></p>
                <?php endif; ?>
            </section>

            <div class="wp-sjs-pro-dashboard__tables">
                <section class="wp-sjs-pro-dashboard__panel">
                    <h2><?php esc_html_e( 'Latest entries', 'surveyjs-pro' ); ?></h2>
                    <?php if ( $data['latest'] ) : ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th scope="col"><?php esc_html_e( 'Entry', 'surveyjs-pro' ); ?></th>
                                    <th scope="col"><?php esc_html_e( 'Form', 'surveyjs-pro' ); ?></th>
                                    <th scope="col"><?php esc_html_e( 'Submitted by', 'surveyjs-pro' ); ?></th>
                                    <th scope="col"><?php esc_html_e( 'Date', 'surveyjs-pro' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $data['latest'] as $entry ) : ?>
                                    <?php
                                    $form = $data['forms'][ (int) $entry->form_id ];
                                    $user = $entry->user_id ? get_userdata( (int) $entry->user_id ) : false;
                                    ?>
                                    <tr<?php echo 'unread' === $entry->status ? ' class="wp-sjs-pro-dashboard__unread"' : ''; ?>>
                                        <td>
                                            <a href="<?php echo esc_url( self::base_url( 'surveyjs_results', array( 'id' => $form->id, 'entry' => (int) $entry->id ) ) ); ?>">#<?php echo esc_html( (int) $entry->id ); ?></a>
                                            <?php if ( 'unread' === $entry->status ) : ?>
                                                <span class="wp-sjs-pro-dashboard__badge"><?php echo esc_html( SurveyJS_Entries::status_label( $entry->status ) ); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><a href="<?php echo esc_url( self::base_url( 'surveyjs_results', array( 'id' => $form->id ) ) ); ?>"><?php echo esc_html( $form->name ); ?></a></td>
                                        <td><?php echo $user ? esc_html( $user->display_name ) : esc_html__( 'Guest', 'surveyjs-pro' ); ?></td>
                                        <td><?php echo esc_html( SurveyJS_Entries::format_date( $entry->created_at ) ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p class="description"><?php esc_html_e( 'No entries yet.', 'surveyjs-pro' ); ?></p>
                    <?php endif; ?>
                </section>

                <section class="wp-sjs-pro-dashboard__panel">
                    <h2><?php esc_html_e( 'Forms', 'surveyjs-pro' ); ?></h2>
                    <?php if ( $active_forms ) : ?>
                        <table class="widefat striped wp-sjs-pro-dashboard__forms">
                            <thead>
                                <tr>
                                    <th scope="col"><?php esc_html_e( 'Form', 'surveyjs-pro' ); ?></th>
                                    <th scope="col" class="num"><?php echo esc_html( $period['label'] ); ?></th>
                                    <th scope="col" class="num"><?php esc_html_e( 'All entries', 'surveyjs-pro' ); ?></th>
                                    <th scope="col" class="num"><?php esc_html_e( 'Unread', 'surveyjs-pro' ); ?></th>
                                    <?php if ( $data['views'] ) : ?>
                                        <th scope="col" class="num"><?php esc_html_e( 'Views', 'surveyjs-pro' ); ?></th>
                                        <th scope="col" class="num"><?php esc_html_e( 'Conversion', 'surveyjs-pro' ); ?></th>
                                    <?php endif; ?>
                                    <th scope="col"><?php esc_html_e( 'Last entry', 'surveyjs-pro' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $active_forms as $form ) : ?>
                                    <?php $results_url = self::base_url( 'surveyjs_results', array( 'id' => $form->id ) ); ?>
                                    <tr>
                                        <td><a href="<?php echo esc_url( self::base_url( 'surveyjs_editor', array( 'id' => $form->id ) ) ); ?>"><?php echo esc_html( $form->name ); ?></a></td>
                                        <td class="num"><?php echo esc_html( number_format_i18n( $form->recent ) ); ?></td>
                                        <td class="num"><a href="<?php echo esc_url( $results_url ); ?>"><?php echo esc_html( number_format_i18n( $form->total ) ); ?></a></td>
                                        <td class="num">
                                            <?php if ( $form->unread ) : ?>
                                                <a href="<?php echo esc_url( add_query_arg( 'status', 'unread', $results_url ) ); ?>"><?php echo esc_html( number_format_i18n( $form->unread ) ); ?></a>
                                            <?php else : ?>
                                                0
                                            <?php endif; ?>
                                        </td>
                                        <?php if ( $data['views'] ) : ?>
                                            <td class="num"><?php echo esc_html( number_format_i18n( $form->views ) ); ?></td>
                                            <td class="num" title="<?php echo esc_attr( self::conversion_note( $form->views, $form->submissions ) ); ?>"><?php echo esc_html( SurveyJS_Form_Stats::format_conversion( $form->views, $form->submissions ) ); ?></td>
                                        <?php endif; ?>
                                        <td><?php echo esc_html( '' !== $form->last_entry ? SurveyJS_Entries::format_date( $form->last_entry ) : "\u{2014}" ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th scope="row"><?php esc_html_e( 'Total', 'surveyjs-pro' ); ?></th>
                                    <td class="num"><?php echo esc_html( number_format_i18n( $totals['recent'] ) ); ?></td>
                                    <td class="num"><?php echo esc_html( number_format_i18n( $totals['total'] ) ); ?></td>
                                    <td class="num"><?php echo esc_html( number_format_i18n( $totals['unread'] ) ); ?></td>
                                    <?php if ( $data['views'] ) : ?>
                                        <td class="num"><?php echo esc_html( number_format_i18n( $totals['views'] ) ); ?></td>
                                        <td class="num"><?php echo esc_html( SurveyJS_Form_Stats::format_conversion( $totals['views'], $totals['submissions'] ) ); ?></td>
                                    <?php endif; ?>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    <?php endif; ?>
                    <?php $empty_forms = count( $data['forms'] ) - count( $active_forms ); ?>
                    <?php if ( $empty_forms ) : ?>
                        <p class="description">
                            <?php
                            /* translators: %s: number of forms */
                            echo esc_html( sprintf( _n( '%s form has no entries yet.', '%s forms have no entries yet.', $empty_forms, 'surveyjs-pro' ), number_format_i18n( $empty_forms ) ) );
                            ?>
                            <a href="<?php echo esc_url( $forms_url ); ?>"><?php esc_html_e( 'All forms', 'surveyjs-pro' ); ?></a>
                        </p>
                    <?php endif; ?>
                </section>
            </div>
        </div>
        <?php
    }
}

add_action( 'surveyjs_pro_loaded', array( 'SurveyJS_Pro_Dashboard', 'init' ) );
