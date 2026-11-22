<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SurveyJS_Pro_Updater {

    const BASE = 'surveyjs/surveyjs.php';
    const PACKAGE_URL = 'https://cdn.surveyjs.io/wordpress/surveyjs-pro.zip';
    const ACTION = 'surveyjs_pro_update';
    const ERROR = 'surveyjs_pro_update_error';
    const VERSION_ACTION = 'surveyjs_pro_version';
    const COMPOSER_PACKAGE = 'surveyjs/surveyjs-pro';
    const COMPOSER_UPDATE = 'composer update surveyjs/surveyjs-pro --with-dependencies';
    const COMPOSER_ROOT_DEPTH = 4;

    private static $composer = null;

    public static function init() {
        add_filter( 'plugin_row_meta', array( __CLASS__, 'row_meta' ), 10, 2 );
        add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
        add_action( 'network_admin_notices', array( __CLASS__, 'notice' ) );
        add_action( 'in_plugin_update_message-' . self::BASE, array( __CLASS__, 'base_update_message' ), 10, 2 );
        add_action( 'in_plugin_update_message-' . self::pro_plugin(), array( __CLASS__, 'pro_update_message' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
        add_action( 'wp_ajax_' . self::VERSION_ACTION, array( __CLASS__, 'ajax_version' ) );
        add_filter( 'update_plugins_surveyjs.io', array( __CLASS__, 'offer_update' ), 10, 3 );
        add_filter( 'plugins_api', array( __CLASS__, 'details' ), 10, 3 );
        add_filter( 'upgrader_pre_download', array( __CLASS__, 'pre_download' ), 10, 2 );
        add_filter( 'upgrader_install_package_result', array( __CLASS__, 'install_result' ), 10, 2 );
    }

    public static function pro_plugin() {
        return plugin_basename( SURVEYJS_PRO_FILE );
    }

    public static function offer_update( $update, $plugin_data, $plugin_file ) {
        if ( self::pro_plugin() !== $plugin_file || ! self::enabled() || 'pro' !== self::older() ) {
            return $update;
        }
        return array(
            'slug'         => 'surveyjs-pro',
            'version'      => SurveyJS_Pro::base_version(),
            'url'          => self::download_page_url(),
            'package'      => self::package_url(),
            'requires_php' => '8.2',
        );
    }

    public static function details( $result, $action, $args ) {
        if ( 'plugin_information' !== $action || ! is_object( $args ) || 'surveyjs-pro' !== ( $args->slug ?? '' ) ) {
            return $result;
        }
        $version = 'pro' === self::older() ? SurveyJS_Pro::base_version() : SURVEYJS_PRO_VERSION;
        $page = esc_url( self::download_page_url() );
        return (object) array(
            'name'          => 'SurveyJS Forms Pro',
            'slug'          => 'surveyjs-pro',
            'version'       => $version,
            'author'        => '<a href="https://surveyjs.io/">Devsoft Baltic OÜ</a>',
            'homepage'      => self::download_page_url(),
            'requires'      => '6.5',
            'requires_php'  => '8.2',
            'download_link' => self::package_url(),
            'sections'      => array(
                'description' => '<p>' . esc_html__( 'Adds Survey Creator with the AI assistant, PDF export and analytics to SurveyJS Forms.', 'surveyjs-pro' ) . '</p>'
                    . '<p>' . esc_html__( 'SurveyJS Forms Pro is released together with SurveyJS Forms and always has the same version.', 'surveyjs-pro' ) . '</p>'
                    . '<p><a href="' . $page . '" target="_blank" rel="noopener">' . esc_html__( 'SurveyJS Forms Pro on surveyjs.io', 'surveyjs-pro' ) . '</a></p>',
                'changelog'   => '<p><a href="' . esc_url( self::download_page_url() . '#changelog' ) . '" target="_blank" rel="noopener">' . esc_html__( 'See the changelog on surveyjs.io', 'surveyjs-pro' ) . '</a></p>',
            ),
        );
    }

    public static function pre_download( $reply, $package ) {
        if ( false !== $reply || self::package_url() !== $package ) {
            return $reply;
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $file = self::download();
        if ( is_wp_error( $file ) ) {
            update_site_option( self::ERROR, $file->get_error_message() );
        }
        return $file;
    }

    public static function install_result( $result, $hook_extra ) {
        if ( is_array( $hook_extra ) && self::pro_plugin() === ( $hook_extra['plugin'] ?? '' ) ) {
            if ( is_wp_error( $result ) ) {
                update_site_option( self::ERROR, $result->get_error_message() );
            } else {
                delete_site_option( self::ERROR );
            }
        }
        return $result;
    }

    public static function pro_update_message() {
        if ( '' !== (string) get_site_option( self::ERROR, '' ) ) {
            echo ' <a href="' . esc_url( self::download_page_url() ) . '" target="_blank" rel="noopener">' . esc_html__( 'Or download it from surveyjs.io.', 'surveyjs-pro' ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'surveyjs-pro' ) . '</span></a>';
        }
    }

    public static function base_update_message( $plugin_data, $response ) {
        $new_version = is_object( $response ) ? (string) ( $response->new_version ?? '' ) : '';
        if ( '' === $new_version || ! version_compare( SURVEYJS_PRO_VERSION, $new_version, '<' ) ) {
            return;
        }
        if ( self::enabled() ) {
            echo ' <strong>' . esc_html__( 'SurveyJS Forms Pro will be updated to the same version automatically.', 'surveyjs-pro' ) . '</strong>';
        } elseif ( self::managed_by_composer() ) {
            echo ' <strong>' . esc_html__( 'SurveyJS Forms Pro is installed with Composer and is not updated here: update both plugins with Composer instead.', 'surveyjs-pro' ) . '</strong>';
        }
    }

    public static function ajax_version() {
        check_ajax_referer( self::VERSION_ACTION );
        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_send_json_error( null, 403 );
        }
        $data = get_file_data( SURVEYJS_PRO_FILE, array( 'Version' => 'Version' ) );
        wp_send_json_success( array( 'version' => $data['Version'] ) );
    }

    public static function enqueue( $hook ) {
        if ( 'plugins.php' !== $hook || ! self::enabled() || ! current_user_can( 'update_plugins' ) ) {
            return;
        }
        $config = array(
            'plugin'  => plugin_basename( SURVEYJS_PRO_FILE ),
            'version' => SURVEYJS_PRO_VERSION,
            'action'  => self::VERSION_ACTION,
            'nonce'   => wp_create_nonce( self::VERSION_ACTION ),
        );
        wp_add_inline_script( 'updates', 'var surveyjsProUpdate = ' . wp_json_encode( $config ) . ';' . <<<'JS'
jQuery(document).on("wp-plugin-update-success", function (event, response) {
    if (!response || response.plugin !== "surveyjs/surveyjs.php") {
        return;
    }
    var attempts = 0;
    function refresh() {
        jQuery.post(window.ajaxurl, { action: surveyjsProUpdate.action, _ajax_nonce: surveyjsProUpdate.nonce }).done(function (result) {
            var version = result && result.success ? result.data.version : surveyjsProUpdate.version;
            if (version === surveyjsProUpdate.version) {
                if (++attempts < 10) {
                    setTimeout(refresh, 1500);
                }
                return;
            }
            jQuery('tr[data-plugin="' + surveyjsProUpdate.plugin + '"] .plugin-version-author-uri').contents().each(function () {
                if (this.nodeType === 3) {
                    this.nodeValue = this.nodeValue.replace(surveyjsProUpdate.version, version);
                }
            });
            surveyjsProUpdate.version = version;
            if (version === response.newVersion) {
                jQuery(".surveyjs-pro-version-notice").remove();
            }
        });
    }
    refresh();
});
JS
        );
    }

    public static function enabled() {
        $vip = ( defined( 'WPCOM_IS_VIP_ENV' ) && WPCOM_IS_VIP_ENV ) || defined( 'VIP_GO_APP_ENVIRONMENT' );
        if ( $vip || ( defined( 'SURVEYJS_PRO_DISABLE_UPDATES' ) && SURVEYJS_PRO_DISABLE_UPDATES ) || ! wp_is_file_mod_allowed( 'surveyjs_pro_self_update' ) ) {
            return false;
        }
        $composer = ! defined( 'SURVEYJS_PRO_DISABLE_UPDATES' ) && self::composer();
        return (bool) apply_filters( 'surveyjs_pro_self_update', ! $composer );
    }

    public static function managed_by_composer() {
        return self::composer() && ! self::enabled();
    }

    public static function composer() {
        if ( null === self::$composer ) {
            self::$composer = self::detect_composer();
        }
        return self::$composer;
    }

    private static function detect_composer() {
        $dir = self::real_path( dirname( SURVEYJS_PRO_FILE ) );
        if ( '' === $dir ) {
            return false;
        }
        if ( class_exists( '\Composer\InstalledVersions' ) && method_exists( '\Composer\InstalledVersions', 'getInstallPath' )
            && \Composer\InstalledVersions::isInstalled( self::COMPOSER_PACKAGE )
            && self::real_path( (string) \Composer\InstalledVersions::getInstallPath( self::COMPOSER_PACKAGE ) ) === $dir ) {
            return true;
        }
        $root = $dir;
        for ( $depth = 0; $depth < self::COMPOSER_ROOT_DEPTH; $depth++ ) {
            $parent = dirname( $root );
            if ( $parent === $root || ! self::basedir_allows( $parent ) ) {
                break;
            }
            $root = $parent;
            if ( self::installs_into( $root, $dir ) ) {
                return true;
            }
        }
        return false;
    }

    private static function installs_into( $root, $dir ) {
        if ( ! is_file( $root . '/composer.json' ) ) {
            return false;
        }
        $config = wp_json_file_decode( $root . '/composer.json', array( 'associative' => true ) );
        $vendor = is_array( $config ) && isset( $config['config']['vendor-dir'] ) && is_string( $config['config']['vendor-dir'] ) ? $config['config']['vendor-dir'] : 'vendor';
        $composer_dir = ( path_is_absolute( $vendor ) ? $vendor : $root . '/' . $vendor ) . '/composer';
        if ( ! is_file( $composer_dir . '/installed.json' ) ) {
            return false;
        }
        $installed = wp_json_file_decode( $composer_dir . '/installed.json', array( 'associative' => true ) );
        foreach ( (array) ( $installed['packages'] ?? array() ) as $package ) {
            if ( is_array( $package ) && self::COMPOSER_PACKAGE === ( $package['name'] ?? '' ) && is_string( $package['install-path'] ?? null ) ) {
                $path = $package['install-path'];
                return self::real_path( path_is_absolute( $path ) ? $path : $composer_dir . '/' . $path ) === $dir;
            }
        }
        return false;
    }

    private static function real_path( $path ) {
        $real = '' === $path ? false : realpath( $path );
        return false === $real ? '' : untrailingslashit( wp_normalize_path( $real ) );
    }

    private static function basedir_allows( $path ) {
        $basedir = (string) ini_get( 'open_basedir' );
        if ( '' === $basedir ) {
            return true;
        }
        foreach ( explode( PATH_SEPARATOR, $basedir ) as $allowed ) {
            if ( '' !== $allowed && 0 === strpos( trailingslashit( $path ), trailingslashit( wp_normalize_path( $allowed ) ) ) ) {
                return true;
            }
        }
        return false;
    }

    public static function row_meta( $meta, $plugin_file ) {
        if ( self::pro_plugin() === $plugin_file && self::managed_by_composer() ) {
            $meta[] = '<span class="surveyjs-pro-composer-note">' . sprintf(
                /* translators: %s: Composer command */
                esc_html__( 'Installed with Composer, so it doesn\'t update itself. Update both plugins with %s', 'surveyjs-pro' ),
                '<code>' . esc_html( self::COMPOSER_UPDATE ) . '</code>'
            ) . '</span>';
        }
        return $meta;
    }

    public static function base_updated( $upgrader, $hook_extra ) {
        if ( ! is_array( $hook_extra ) || 'plugin' !== ( $hook_extra['type'] ?? '' ) || 'update' !== ( $hook_extra['action'] ?? '' ) ) {
            return;
        }
        $plugins = isset( $hook_extra['plugins'] ) ? (array) $hook_extra['plugins'] : array( $hook_extra['plugin'] ?? '' );
        if ( in_array( self::BASE, $plugins, true ) && self::enabled() ) {
            // Core's Plugin_Upgrader still has its filters for Base attached during upgrader_process_complete and they reject a second package, so Pro is installed once core has finished (and has rolled back a failed auto-update).
            add_action( 'shutdown', array( __CLASS__, 'follow_base' ) );
        }
    }

    public static function follow_base() {
        $base = get_file_data( WP_PLUGIN_DIR . '/' . self::BASE, array( 'Version' => 'Version' ) );
        if ( '' !== $base['Version'] && SurveyJS_Pro::base_version() !== $base['Version'] && version_compare( SURVEYJS_PRO_VERSION, $base['Version'], '<' ) ) {
            self::update();
        }
    }

    private static function site_url() {
        return function_exists( 'surveyjs_site_url' ) ? surveyjs_site_url() : 'https://surveyjs.io';
    }

    public static function package_url() {
        return apply_filters( 'surveyjs_pro_package_url', self::PACKAGE_URL );
    }

    public static function download_page_url() {
        return self::site_url() . '/wordpress-pro';
    }

    public static function update() {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        $result = self::download();
        if ( ! is_wp_error( $result ) ) {
            $file = $result;
            $upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
            $result = $upgrader->install( $file, array( 'overwrite_package' => true, 'clear_update_cache' => false ) );
            wp_delete_file( $file );
        }
        if ( true === $result ) {
            delete_site_option( self::ERROR );
            return true;
        }
        $message = is_wp_error( $result ) ? $result->get_error_message() : __( 'Could not access the plugin files.', 'surveyjs-pro' );
        update_site_option( self::ERROR, $message );
        return false;
    }

    private static function download() {
        $file = wp_tempnam( 'surveyjs-pro.zip' );
        if ( ! $file ) {
            return new WP_Error( 'surveyjs_pro_download', __( 'Could not create a temporary file.', 'surveyjs-pro' ) );
        }
        $response = wp_safe_remote_get(
            self::package_url(),
            array(
                'timeout'    => 300, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- plugin zip download during an update, same as core's download_url(); never runs on VIP (see enabled()).
                'stream'     => true,
                'filename'   => $file,
                'user-agent' => 'SurveyJS-Forms-Pro/' . SURVEYJS_PRO_VERSION,
            )
        );
        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( is_wp_error( $response ) || 200 !== $code ) {
            wp_delete_file( $file );
            /* translators: %d: HTTP status code */
            return is_wp_error( $response ) ? $response : new WP_Error( 'surveyjs_pro_download', sprintf( __( 'surveyjs.io responded with HTTP %d.', 'surveyjs-pro' ), $code ) );
        }
        return $file;
    }

    public static function handle_button() {
        if ( ! current_user_can( 'update_plugins' ) || ! self::enabled() ) {
            wp_die( esc_html__( 'Sorry, you are not allowed to update plugins for this site.', 'surveyjs-pro' ), 403 );
        }
        check_admin_referer( self::ACTION );
        self::update();
        $back = wp_get_referer();
        wp_safe_redirect( $back ? $back : network_admin_url( 'plugins.php' ) );
        exit;
    }

    public static function older() {
        $base = SurveyJS_Pro::base_version();
        if ( SURVEYJS_PRO_VERSION === $base ) {
            return '';
        }
        return '' === $base || version_compare( $base, SURVEYJS_PRO_VERSION, '<' ) ? 'base' : 'pro';
    }

    public static function notice() {
        $can_update = current_user_can( 'update_plugins' );
        if ( ! $can_update && ( wp_is_file_mod_allowed( 'capability_update_core' ) || ! current_user_can( 'activate_plugins' ) ) ) {
            return;
        }
        $older = self::older();
        if ( '' === $older ) {
            return;
        }
        $url = '';
        $error = '';
        if ( 'base' === $older ) {
            $title = __( 'Please update SurveyJS Forms', 'surveyjs-pro' );
            $message = __( 'SurveyJS Forms Pro needs the latest SurveyJS Forms.', 'surveyjs-pro' );
            $label = __( 'Update SurveyJS Forms', 'surveyjs-pro' );
            if ( $can_update ) {
                $updates = get_site_transient( 'update_plugins' );
                $url = isset( $updates->response[ self::BASE ] )
                    ? wp_nonce_url( network_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( self::BASE ) ), 'upgrade-plugin_' . self::BASE )
                    : network_admin_url( 'plugins.php' );
            }
        } else {
            $title = __( 'Please update SurveyJS Forms Pro', 'surveyjs-pro' );
            $message = __( 'SurveyJS Forms Pro is older than SurveyJS Forms.', 'surveyjs-pro' );
            $label = __( 'Update SurveyJS Forms Pro', 'surveyjs-pro' );
            $error = (string) get_site_option( self::ERROR, '' );
            if ( $can_update && self::enabled() ) {
                $pro = self::pro_plugin();
                $updates = get_site_transient( 'update_plugins' );
                $url = isset( $updates->response[ $pro ] )
                    ? wp_nonce_url( network_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( $pro ) ), 'upgrade-plugin_' . $pro )
                    : wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION ), self::ACTION );
            }
        }
        $composer = 'pro' === $older && self::managed_by_composer();
        $show_download = 'pro' === $older && ! $composer && ( '' !== $error || '' === $url );
        ?>
        <div class="notice notice-warning surveyjs-pro-version-notice">
            <p><strong><?php echo esc_html( $title ); ?></strong></p>
            <p><?php echo esc_html( $message ); ?></p>
            <?php if ( $composer ) : ?>
                <p>
                    <?php
                    printf(
                        /* translators: %s: Composer command */
                        esc_html__( 'SurveyJS Forms Pro is installed with Composer. Update both plugins with %s', 'surveyjs-pro' ),
                        '<code>' . esc_html( self::COMPOSER_UPDATE ) . '</code>'
                    );
                    ?>
                </p>
            <?php endif; ?>
            <?php if ( '' !== $error ) : ?>
                <p>
                    <?php
                    /* translators: %s: error message */
                    echo esc_html( sprintf( __( 'The last update of SurveyJS Forms Pro failed: %s', 'surveyjs-pro' ), $error ) );
                    ?>
                </p>
            <?php endif; ?>
            <?php if ( '' !== $url || $show_download ) : ?>
                <p>
                    <?php if ( '' !== $url ) : ?>
                        <a class="button button-primary" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
                    <?php endif; ?>
                    <?php if ( $show_download ) : ?>
                        <a class="button" href="<?php echo esc_url( self::download_page_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Download from surveyjs.io', 'surveyjs-pro' ); ?><span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'surveyjs-pro' ); ?></span></a>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
}

add_action( 'upgrader_process_complete', array( 'SurveyJS_Pro_Updater', 'base_updated' ), 10, 2 );
add_action( 'admin_post_' . SurveyJS_Pro_Updater::ACTION, array( 'SurveyJS_Pro_Updater', 'handle_button' ) );
add_action( 'surveyjs_pro_loaded', array( 'SurveyJS_Pro_Updater', 'init' ) );
