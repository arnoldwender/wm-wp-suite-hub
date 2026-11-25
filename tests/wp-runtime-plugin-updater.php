<?php
/**
 * Runtime check of the fleet update client inside a real WordPress.
 *
 * What the standalone suite cannot show: that WordPress core's own wp_update_plugins() calls the
 * filter the client registers, takes its answer as an available update, and ignores it when the
 * manifest has no newer version. The manifest request is answered through `pre_http_request`, so
 * nothing leaves the machine.
 *
 * Writes, and removes again before it exits (also when an assertion fails):
 *   - two probe plugin directories under WP_PLUGIN_DIR, never activated: wm-updater-probe/, which the
 *     test registers as an active plugin would, and wm-updater-probe-sleeping/, which nothing
 *     registers — the case of an inactive fleet plugin whose active sibling answers for it
 *   - the update_plugins site transient (deleted; WordPress rebuilds it on its next check)
 *   - the manifest cache transient and the endpoint option
 *
 * Usage (a local WordPress install):
 *   docker cp tests/wp-runtime-plugin-updater.php the local WordPress container:/tmp/ && \
 *   docker cp packages/plugin-updater/class-wm-plugin-updater.php the local WordPress container:/tmp/ && \
 *   docker exec the local WordPress container wp eval-file /tmp/wp-runtime-plugin-updater.php --allow-root
 *
 * Exit: 0 when every check passes, 1 otherwise.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$class_file = __DIR__ . '/class-wm-plugin-updater.php';
if ( ! is_file( $class_file ) ) {
	$class_file = dirname( __DIR__ ) . '/packages/plugin-updater/class-wm-plugin-updater.php';
}
require_once $class_file;
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$probe_slug = 'wm-updater-probe';
$probe_dir  = WP_PLUGIN_DIR . '/' . $probe_slug;
$probe_file = $probe_dir . '/' . $probe_slug . '.php';
$probe_rel  = $probe_slug . '/' . $probe_slug . '.php';
$sleep_slug = 'wm-updater-probe-sleeping';
$sleep_dir  = WP_PLUGIN_DIR . '/' . $sleep_slug;
$sleep_file = $sleep_dir . '/' . $sleep_slug . '.php';
$sleep_rel  = $sleep_slug . '/' . $sleep_slug . '.php';
$endpoint   = 'https://updates.wm-runtime.invalid/fleet';
$had_option = get_site_option( 'wm_plugin_update_endpoint', null );

$passed = 0;
$failed = 0;
$check  = static function ( $name, $ok, $detail = '' ) use ( &$passed, &$failed ) {
	if ( $ok ) {
		$passed++;
		WP_CLI::log( '  PASS  ' . $name );
	} else {
		$failed++;
		WP_CLI::log( '  FAIL  ' . $name . ( '' !== $detail ? ' — ' . $detail : '' ) );
	}
};

$cleanup = static function () use ( $probe_dir, $probe_file, $sleep_dir, $sleep_file, $endpoint, $had_option ) {
	foreach ( array( $probe_file => $probe_dir, $sleep_file => $sleep_dir ) as $file => $dir ) {
		if ( is_file( $file ) ) {
			wp_delete_file( $file );
		}
		if ( is_dir( $dir ) ) {
			rmdir( $dir );
		}
	}
	delete_site_transient( 'wm_plugin_update_manifest_' . md5( $endpoint ) );
	delete_site_transient( 'update_plugins' );
	if ( null === $had_option ) {
		delete_site_option( 'wm_plugin_update_endpoint' );
	} else {
		update_site_option( 'wm_plugin_update_endpoint', $had_option );
	}
	wp_clean_plugins_cache( false );
};

// ---------------------------------------------------------------------------------------------
// Probe plugin
// ---------------------------------------------------------------------------------------------

foreach ( array( $probe_dir, $sleep_dir ) as $dir ) {
	if ( is_dir( $dir ) ) {
		WP_CLI::error( "$dir already exists; refusing to overwrite it." );
	}
}
wp_mkdir_p( $probe_dir );
file_put_contents(
	$probe_file,
	"<?php\n/**\n * Plugin Name: WM Updater Probe\n * Version: 1.0.0\n * Update URI: https://arnoldwender.com/wordpress-plugins/wm-updater-probe/\n */\n"
);
wp_mkdir_p( $sleep_dir );
file_put_contents(
	$sleep_file,
	"<?php\n/**\n * Plugin Name: WM Updater Probe Sleeping\n * Version: 2.0.0\n * Update URI: https://arnoldwender.com/wordpress-plugins/wm-updater-probe-sleeping/\n */\n"
);
wp_clean_plugins_cache( false );

try {
	$plugins = get_plugins();
	$check( 'core reads the probe plugin and its Update URI header', isset( $plugins[ $probe_rel ] ) && '' !== $plugins[ $probe_rel ]['UpdateURI'], wp_json_encode( $plugins[ $probe_rel ] ?? null ) );

	$check( 'register() hooks the probe', true === WM_Plugin_Updater::register( $probe_file ) );
	$check( 'filter registered on the host WordPress derives', false !== has_filter( 'update_plugins_arnoldwender.com', array( 'WM_Plugin_Updater', 'filter_update' ) ) );

	update_site_option( 'wm_plugin_update_endpoint', $endpoint );
	WM_Plugin_Updater::flush();

	$manifest_version = '9.9.9';
	$sleep_package    = 'https://updates.wm-runtime.invalid/fleet/' . $sleep_slug . '-2.1.0.zip';
	$sleep_body       = "PK\x03\x04 sleeping probe package";
	$sleep_served     = $sleep_body;
	$fake             = static function ( $pre, $args, $url ) use ( $endpoint, &$manifest_version, $probe_slug, $sleep_slug, $sleep_package, $sleep_body, &$sleep_served ) {
		if ( $url === $sleep_package ) {
			if ( ! empty( $args['stream'] ) && ! empty( $args['filename'] ) ) {
				file_put_contents( $args['filename'], $sleep_served );
			}
			return array( 'headers' => array(), 'body' => '', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => $args['filename'] ?? null );
		}
		if ( $url === $endpoint . '/manifest.json' ) {
			return array(
				'headers'  => array(),
				'body'     => wp_json_encode(
					array(
						'schema'  => 1,
						'plugins' => array(
							$probe_slug => array(
								'version'      => $manifest_version,
								'package'      => 'https://updates.wm-runtime.invalid/fleet/' . $probe_slug . '-' . $manifest_version . '.zip',
								'sha256'       => str_repeat( 'a', 64 ),
								'requires_php' => '7.4',
								'tested'       => '7.1',
							),
							$sleep_slug => array(
								'version' => '2.1.0',
								'package' => $sleep_package,
								'sha256'  => hash( 'sha256', $sleep_body ),
								'name'    => 'WM Updater Probe Sleeping',
							),
						),
					)
				),
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'cookies'  => array(),
				'filename' => null,
			);
		}
		if ( false !== strpos( $url, 'api.wordpress.org/plugins/update-check' ) ) {
			// Keep core's own WordPress.org request offline too: an empty, valid answer.
			return array(
				'headers'  => array(),
				'body'     => wp_json_encode( array( 'plugins' => array(), 'translations' => array(), 'no_update' => array() ) ),
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'cookies'  => array(),
				'filename' => null,
			);
		}
		return $pre;
	};
	add_filter( 'pre_http_request', $fake, 10, 3 );

	// A newer version in the manifest: core lists the update.
	delete_site_transient( 'update_plugins' );
	wp_update_plugins();
	$updates = get_site_transient( 'update_plugins' );
	$offer   = $updates->response[ $probe_rel ] ?? null;
	$check( 'wp_update_plugins() lists the update from the manifest', is_object( $offer ) && '9.9.9' === $offer->new_version, wp_json_encode( $offer ) );
	$check( 'the update carries the manifest package and the Update URI as id', is_object( $offer ) && false !== strpos( (string) $offer->package, '9.9.9.zip' ) && 0 === strpos( (string) $offer->id, 'https://arnoldwender.com/' ) );

	// The sleeping probe never called register(): the probe's registration answers for it.
	$sleeping = $updates->response[ $sleep_rel ] ?? null;
	$check( 'an installed plugin nothing registered is offered its update through the same host', is_object( $sleeping ) && '2.1.0' === $sleeping->new_version, wp_json_encode( $sleeping ) );
	require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
	$details = plugins_api( 'plugin_information', array( 'slug' => $sleep_slug ) );
	$check( 'its "View details" dialog is answered from the manifest', ! is_wp_error( $details ) && is_object( $details ) && 'WM Updater Probe Sleeping' === $details->name, is_wp_error( $details ) ? $details->get_error_message() : '' );
	$sleep_served = $sleep_body . ' tampered';
	$refused      = WM_Plugin_Updater::filter_pre_download( false, $sleep_package );
	$check( 'its tampered package is refused before WordPress unpacks it', is_wp_error( $refused ) && 'wm_update_package_rejected' === $refused->get_error_code() );
	$sleep_served = $sleep_body;
	$accepted     = WM_Plugin_Updater::filter_pre_download( false, $sleep_package );
	$check( 'its genuine package is verified and handed to the upgrader', is_string( $accepted ) && is_file( $accepted ) );
	if ( is_string( $accepted ) && is_file( $accepted ) ) {
		wp_delete_file( $accepted );
	}

	// The same version: core files it under no_update.
	$manifest_version = '1.0.0';
	WM_Plugin_Updater::flush();
	delete_site_transient( 'update_plugins' );
	wp_update_plugins();
	$updates = get_site_transient( 'update_plugins' );
	$check( 'same version: no update offered', ! isset( $updates->response[ $probe_rel ] ) && isset( $updates->no_update[ $probe_rel ] ) );

	// A dead source: no entry at all, and the status says so.
	remove_filter( 'pre_http_request', $fake, 10 );
	add_filter(
		'pre_http_request',
		static function ( $pre, $args, $url ) use ( $endpoint ) {
			return $url === $endpoint . '/manifest.json' ? new WP_Error( 'http_request_failed', 'offline in runtime test' ) : $pre;
		},
		10,
		3
	);
	WM_Plugin_Updater::flush();
	delete_site_transient( 'update_plugins' );
	wp_update_plugins();
	$updates = get_site_transient( 'update_plugins' );
	$check( 'dead source: neither update nor "up to date" recorded for the probe', ! isset( $updates->response[ $probe_rel ] ) && ! isset( $updates->no_update[ $probe_rel ] ) );
	$check( 'dead source: status unreachable', 'unreachable' === WM_Plugin_Updater::status()['state'] );
} finally {
	$cleanup();
}

$check( 'cleanup: both probe plugins removed', ! is_dir( $probe_dir ) && ! is_dir( $sleep_dir ) );

WP_CLI::log( sprintf( 'TEST RESULTS: %d PASSED | %d FAILED', $passed, $failed ) );
if ( $failed > 0 ) {
	WP_CLI::halt( 1 );
}
