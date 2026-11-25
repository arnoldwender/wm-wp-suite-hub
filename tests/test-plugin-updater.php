<?php
/**
 * Standalone tests for packages/plugin-updater/class-wm-plugin-updater.php.
 *
 * Runs without WordPress: the WordPress functions the updater calls are stubbed below, with the
 * HTTP layer and the download replaced by in-memory fakes. Every decision the class makes is
 * pinned: no endpoint means no request, a broken source never reads as "up to date", a package
 * whose SHA-256 or signature does not match is refused before WordPress unpacks it.
 *
 * Usage: php tests/test-plugin-updater.php   (last line: TEST RESULTS: N PASSED | M FAILED)
 */

declare(strict_types=1);

define( 'ABSPATH', sys_get_temp_dir() . '/wm-updater-test/' );
define( 'WP_PLUGIN_DIR', '/srv/wp/wp-content/plugins' );

// ---------------------------------------------------------------------------------------------
// WordPress stubs
// ---------------------------------------------------------------------------------------------

$GLOBALS['wm_test'] = array(
	'filters'    => array(),
	'options'    => array(),
	'transients' => array(),
	'http'       => array(),
	'requests'   => array(),
	'headers'    => array(),
	'downloads'  => array(),
	'deleted'    => array(),
	'installed'  => array(),
);

class WP_Error {
	private $code;
	private $message;
	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}
	public function get_error_code() {
		return $this->code;
	}
	public function get_error_message() {
		return $this->message;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

function plugin_basename( $file ) {
	return ltrim( str_replace( WP_PLUGIN_DIR, '', $file ), '/' );
}

function get_file_data( $file, $headers ) {
	$out = array();
	foreach ( $headers as $key => $label ) {
		$out[ $key ] = $GLOBALS['wm_test']['headers'][ $file ][ $label ] ?? '';
	}
	return $out;
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['wm_test']['filters'][ $hook ][] = $callback;
	return true;
}

function has_filter( $hook, $callback = false ) {
	return in_array( $callback, $GLOBALS['wm_test']['filters'][ $hook ] ?? array(), true );
}

function get_site_option( $name, $default = false ) {
	return $GLOBALS['wm_test']['options'][ $name ] ?? $default;
}

function get_site_transient( $key ) {
	return $GLOBALS['wm_test']['transients'][ $key ]['value'] ?? false;
}

function set_site_transient( $key, $value, $ttl = 0 ) {
	$GLOBALS['wm_test']['transients'][ $key ] = array( 'value' => $value, 'ttl' => $ttl );
	return true;
}

function delete_site_transient( $key ) {
	unset( $GLOBALS['wm_test']['transients'][ $key ] );
	return true;
}

function wp_safe_remote_get( $url, $args = array() ) {
	$GLOBALS['wm_test']['requests'][] = array( 'url' => $url, 'args' => $args );
	return $GLOBALS['wm_test']['http'][ $url ] ?? new WP_Error( 'http_request_failed', 'no route in test' );
}

function wp_remote_retrieve_response_code( $response ) {
	return $response['response']['code'] ?? 0;
}

function wp_remote_retrieve_body( $response ) {
	return $response['body'] ?? '';
}

function wp_kses_post( $html ) {
	return preg_replace( '#<script\b[^>]*>.*?</script>#is', '', (string) $html );
}

function download_url( $url ) {
	if ( ! isset( $GLOBALS['wm_test']['downloads'][ $url ] ) ) {
		return new WP_Error( 'http_404', 'not found' );
	}
	$tmp = tempnam( sys_get_temp_dir(), 'wmupd' );
	file_put_contents( $tmp, $GLOBALS['wm_test']['downloads'][ $url ] );
	return $tmp;
}

function get_plugins() {
	return $GLOBALS['wm_test']['installed'];
}

function wp_delete_file( $file ) {
	$GLOBALS['wm_test']['deleted'][] = $file;
	@unlink( $file );
}

require __DIR__ . '/../packages/plugin-updater/class-wm-plugin-updater.php';

// ---------------------------------------------------------------------------------------------
// Harness
// ---------------------------------------------------------------------------------------------

$passed = 0;
$failed = 0;

function check( string $name, bool $condition, string $details = '' ): void {
	global $passed, $failed;
	if ( $condition ) {
		$passed++;
		echo "  PASS  $name\n";
	} else {
		$failed++;
		echo "  FAIL  $name" . ( '' !== $details ? " — $details" : '' ) . "\n";
	}
}

function fresh(): void {
	WM_Plugin_Updater::reset_for_tests();
	$GLOBALS['wm_test']['filters']    = array();
	$GLOBALS['wm_test']['options']    = array();
	$GLOBALS['wm_test']['transients'] = array();
	$GLOBALS['wm_test']['http']       = array();
	$GLOBALS['wm_test']['requests']   = array();
	$GLOBALS['wm_test']['downloads']  = array();
	$GLOBALS['wm_test']['deleted']    = array();
	$GLOBALS['wm_test']['installed']  = array();
}

function plugin_file( string $slug ): string {
	return WP_PLUGIN_DIR . '/' . $slug . '/' . $slug . '.php';
}

function declare_plugin( string $slug, string $update_uri ): void {
	$GLOBALS['wm_test']['headers'][ plugin_file( $slug ) ] = array( 'Update URI' => $update_uri );
}

function manifest_response( array $plugins, int $schema = 1, int $code = 200 ): array {
	return array(
		'response' => array( 'code' => $code ),
		'body'     => json_encode( array( 'schema' => $schema, 'plugins' => $plugins ) ),
	);
}

const ENDPOINT = 'https://updates.example.test/wm';
const ZIP_BODY = "PK\x03\x04 fake zip body for the updater test";

function good_entry( string $version = '2.0.0', string $body = ZIP_BODY ): array {
	return array(
		'version'      => $version,
		'package'      => 'https://updates.example.test/wm/wm-demo-' . $version . '.zip',
		'sha256'       => hash( 'sha256', $body ),
		'requires'     => '6.4',
		'requires_php' => '7.4',
		'tested'       => '7.1',
		'url'          => 'https://www.example.test/wm-demo',
		'name'         => 'WM Demo',
		'changelog'    => '<p>Neu</p><script>alert(1)</script>',
	);
}

echo "WM_Plugin_Updater " . WM_Plugin_Updater::VERSION . "\n";

// ---------------------------------------------------------------------------------------------
// 1. Registration
// ---------------------------------------------------------------------------------------------

fresh();
declare_plugin( 'wm-demo', '' );
check( 'register: no Update URI header → not registered', false === WM_Plugin_Updater::register( plugin_file( 'wm-demo' ) ) );
check( 'register: no Update URI header → no hook added', array() === $GLOBALS['wm_test']['filters'] );

fresh();
declare_plugin( 'wm-demo', 'https://wordpress.org/plugins/wm-demo/' );
check( 'register: a wordpress.org Update URI is left to core', false === WM_Plugin_Updater::register( plugin_file( 'wm-demo' ) ) );
declare_plugin( 'wm-demo', 'https://downloads.wordpress.org/plugin/wm-demo.zip' );
check( 'register: a *.wordpress.org Update URI is left to core', false === WM_Plugin_Updater::register( plugin_file( 'wm-demo' ) ) );

fresh();
declare_plugin( 'wm-demo', 'https://arnoldwender.com/wordpress-plugins/wm-demo/' );
declare_plugin( 'wm-other', 'https://arnoldwender.com/wordpress-plugins/wm-other/' );
check( 'register: custom Update URI → registered', true === WM_Plugin_Updater::register( plugin_file( 'wm-demo' ) ) );
WM_Plugin_Updater::register( plugin_file( 'wm-other' ) );
check( 'register: hook on update_plugins_{host} with the lowercase host', isset( $GLOBALS['wm_test']['filters']['update_plugins_arnoldwender.com'] ) );
check( 'register: two plugins on one host add the host filter once', 1 === count( $GLOBALS['wm_test']['filters']['update_plugins_arnoldwender.com'] ) );
check( 'register: plugins_api and upgrader_pre_download hooked once', 1 === count( $GLOBALS['wm_test']['filters']['plugins_api'] ) && 1 === count( $GLOBALS['wm_test']['filters']['upgrader_pre_download'] ) );
check( 'register: both slugs known', array( 'wm-demo', 'wm-other' ) === WM_Plugin_Updater::registered() );

// ---------------------------------------------------------------------------------------------
// 2. No endpoint: nothing is requested, and nothing reads as up to date
// ---------------------------------------------------------------------------------------------

check( 'not configured: filter returns the value unchanged', false === WM_Plugin_Updater::filter_update( false, array( 'Version' => '1.0.0' ), 'wm-demo/wm-demo.php' ) );
check( 'not configured: no HTTP request made', array() === $GLOBALS['wm_test']['requests'] );
check( 'not configured: status says not_configured', 'not_configured' === WM_Plugin_Updater::status()['state'] );

WM_Plugin_Updater::reset_for_tests();
$GLOBALS['wm_test']['options']['wm_plugin_update_endpoint'] = 'http://updates.example.test/wm';
check( 'http endpoint is refused (https only)', '' === WM_Plugin_Updater::endpoint() && 'not_configured' === WM_Plugin_Updater::status()['state'] );
check( 'http endpoint: still no request', array() === $GLOBALS['wm_test']['requests'] );

// ---------------------------------------------------------------------------------------------
// 3. Broken sources
// ---------------------------------------------------------------------------------------------

fresh();
declare_plugin( 'wm-demo', 'https://arnoldwender.com/wordpress-plugins/wm-demo/' );
WM_Plugin_Updater::register( plugin_file( 'wm-demo' ) );
$GLOBALS['wm_test']['options']['wm_plugin_update_endpoint'] = ENDPOINT . '/';
$GLOBALS['wm_test']['http'][ ENDPOINT . '/manifest.json' ] = array( 'response' => array( 'code' => 500 ), 'body' => '' );
check( 'HTTP 500: no update offered', false === WM_Plugin_Updater::filter_update( false, array( 'Version' => '1.0.0' ), 'wm-demo/wm-demo.php' ) );
$status = WM_Plugin_Updater::status();
check( 'HTTP 500: status unreachable with the code', 'unreachable' === $status['state'] && 'HTTP 500' === $status['detail'] );
check( 'trailing slash of the endpoint is dropped', ENDPOINT . '/manifest.json' === $GLOBALS['wm_test']['requests'][0]['url'] );
check( 'failure cached with the short TTL', WM_Plugin_Updater::CACHE_FAIL === $GLOBALS['wm_test']['transients'][ 'wm_plugin_update_manifest_' . md5( ENDPOINT ) ]['ttl'] );
WM_Plugin_Updater::reset_for_tests();
WM_Plugin_Updater::register( plugin_file( 'wm-demo' ) );
WM_Plugin_Updater::filter_update( false, array( 'Version' => '1.0.0' ), 'wm-demo/wm-demo.php' );
check( 'failure cache: a new request cycle does not ask the dead endpoint again', 1 === count( $GLOBALS['wm_test']['requests'] ) );

fresh();
declare_plugin( 'wm-demo', 'https://arnoldwender.com/wordpress-plugins/wm-demo/' );
WM_Plugin_Updater::register( plugin_file( 'wm-demo' ) );
$GLOBALS['wm_test']['options']['wm_plugin_update_endpoint'] = ENDPOINT;
$GLOBALS['wm_test']['http'][ ENDPOINT . '/manifest.json' ] = manifest_response( array( 'wm-demo' => good_entry() ), 2 );
check( 'wrong schema: status invalid', 'invalid' === WM_Plugin_Updater::status()['state'] );
check( 'wrong schema: no update offered', false === WM_Plugin_Updater::filter_update( false, array( 'Version' => '1.0.0' ), 'wm-demo/wm-demo.php' ) );

fresh();
declare_plugin( 'wm-demo', 'https://arnoldwender.com/wordpress-plugins/wm-demo/' );
WM_Plugin_Updater::register( plugin_file( 'wm-demo' ) );
$GLOBALS['wm_test']['options']['wm_plugin_update_endpoint'] = ENDPOINT;
$GLOBALS['wm_test']['http'][ ENDPOINT . '/manifest.json' ] = array( 'response' => array( 'code' => 200 ), 'body' => '<html>not json</html>' );
check( 'non-JSON body: status invalid', 'invalid' === WM_Plugin_Updater::status()['state'] );

// ---------------------------------------------------------------------------------------------
// 4. Entry validation
// ---------------------------------------------------------------------------------------------

check( 'entry: complete entry is valid', null !== WM_Plugin_Updater::valid_entry( good_entry() ) );
$e = good_entry();
$e['package'] = 'http://updates.example.test/wm-demo.zip';
check( 'entry: http package refused', null === WM_Plugin_Updater::valid_entry( $e ) );
$e = good_entry();
$e['sha256'] = 'abc';
check( 'entry: short sha256 refused', null === WM_Plugin_Updater::valid_entry( $e ) );
$e = good_entry();
unset( $e['version'] );
check( 'entry: missing version refused', null === WM_Plugin_Updater::valid_entry( $e ) );
$e = good_entry();
$e['version'] = 'latest';
check( 'entry: non-numeric version refused', null === WM_Plugin_Updater::valid_entry( $e ) );
$e = good_entry();
$e['sha256'] = strtoupper( $e['sha256'] );
check( 'entry: uppercase sha256 normalised to lowercase', strtolower( $e['sha256'] ) === WM_Plugin_Updater::valid_entry( $e )['sha256'] );
$e = good_entry();
$e['url'] = 'javascript:alert(1)';
check( 'entry: non-https detail URL dropped', ! isset( WM_Plugin_Updater::valid_entry( $e )['url'] ) );
check( 'entry: non-array refused', null === WM_Plugin_Updater::valid_entry( 'x' ) );

// ---------------------------------------------------------------------------------------------
// 5. Update offer and details dialog
// ---------------------------------------------------------------------------------------------

fresh();
declare_plugin( 'wm-demo', 'https://arnoldwender.com/wordpress-plugins/wm-demo/' );
WM_Plugin_Updater::register( plugin_file( 'wm-demo' ) );
$GLOBALS['wm_test']['options']['wm_plugin_update_endpoint'] = ENDPOINT;
$GLOBALS['wm_test']['options']['wm_plugin_license_key']     = 'LIC-123';
$GLOBALS['wm_test']['http'][ ENDPOINT . '/manifest.json' ] = manifest_response( array( 'wm-demo' => good_entry( '2.0.0' ) ) );
$offer = WM_Plugin_Updater::filter_update( false, array( 'Version' => '1.0.0' ), 'wm-demo/wm-demo.php' );
check( 'offer: version and package from the manifest', is_array( $offer ) && '2.0.0' === $offer['version'] && good_entry()['package'] === $offer['package'] );
check( 'offer: tested and requires_php passed on', '7.1' === ( $offer['tested'] ?? '' ) && '7.4' === ( $offer['requires_php'] ?? '' ) );
check( 'offer: license key sent as header', 'LIC-123' === ( $GLOBALS['wm_test']['requests'][0]['args']['headers']['X-WM-License-Key'] ?? '' ) );
check( 'offer: good manifest cached with the long TTL', WM_Plugin_Updater::CACHE_OK === $GLOBALS['wm_test']['transients'][ 'wm_plugin_update_manifest_' . md5( ENDPOINT ) ]['ttl'] );
WM_Plugin_Updater::filter_update( false, array( 'Version' => '1.0.0' ), 'wm-demo/wm-demo.php' );
check( 'offer: one manifest request for repeated calls', 1 === count( $GLOBALS['wm_test']['requests'] ) );
check( 'offer: status ok with the plugin count', 'ok' === WM_Plugin_Updater::status()['state'] && 1 === WM_Plugin_Updater::status()['plugins'] );
check( 'offer: a plugin with no manifest entry is left alone', false === WM_Plugin_Updater::filter_update( false, array(), 'other/other.php' ) );
check( 'offer: an earlier callback answer is not overwritten', array( 'version' => '9' ) === WM_Plugin_Updater::filter_update( array( 'version' => '9' ), array(), 'wm-demo/wm-demo.php' ) );

$info = WM_Plugin_Updater::filter_plugins_api( false, 'plugin_information', (object) array( 'slug' => 'wm-demo' ) );
check( 'details: object with name, version and download link', is_object( $info ) && 'WM Demo' === $info->name && '2.0.0' === $info->version && good_entry()['package'] === $info->download_link );
check( 'details: changelog passed through wp_kses_post', is_object( $info ) && false === strpos( $info->sections['changelog'], '<script' ) );
check( 'details: other actions untouched', false === WM_Plugin_Updater::filter_plugins_api( false, 'query_plugins', (object) array( 'slug' => 'wm-demo' ) ) );
check( 'details: other slugs untouched', false === WM_Plugin_Updater::filter_plugins_api( false, 'plugin_information', (object) array( 'slug' => 'akismet' ) ) );

WM_Plugin_Updater::flush();
check( 'flush: cached manifest removed', ! isset( $GLOBALS['wm_test']['transients'][ 'wm_plugin_update_manifest_' . md5( ENDPOINT ) ] ) );

// ---------------------------------------------------------------------------------------------
// 6. Package verification
// ---------------------------------------------------------------------------------------------

$tmp = tempnam( sys_get_temp_dir(), 'wmupd' );
file_put_contents( $tmp, ZIP_BODY );
check( 'verify: matching SHA-256 without a public key → accepted', true === WM_Plugin_Updater::verify_file( $tmp, WM_Plugin_Updater::valid_entry( good_entry() ) ) );
file_put_contents( $tmp, ZIP_BODY . 'x' );
check( 'verify: changed file → refused', is_string( WM_Plugin_Updater::verify_file( $tmp, WM_Plugin_Updater::valid_entry( good_entry() ) ) ) );
check( 'verify: unreadable file → refused', is_string( WM_Plugin_Updater::verify_file( '/nonexistent/wm.zip', WM_Plugin_Updater::valid_entry( good_entry() ) ) ) );

if ( function_exists( 'sodium_crypto_sign_keypair' ) ) {
	$pair       = sodium_crypto_sign_keypair();
	$public_b64 = base64_encode( sodium_crypto_sign_publickey( $pair ) );
	$hash       = hash( 'sha256', ZIP_BODY );
	$signed     = good_entry();
	$signed['signature'] = base64_encode( sodium_crypto_sign_detached( $hash, sodium_crypto_sign_secretkey( $pair ) ) );
	file_put_contents( $tmp, ZIP_BODY );
	$GLOBALS['wm_test']['options']['wm_plugin_update_public_key'] = $public_b64;
	check( 'verify: key set, valid signature over the hex SHA-256 → accepted', true === WM_Plugin_Updater::verify_file( $tmp, WM_Plugin_Updater::valid_entry( $signed ) ) );
	check( 'verify: key set, entry without signature → refused', is_string( WM_Plugin_Updater::verify_file( $tmp, WM_Plugin_Updater::valid_entry( good_entry() ) ) ) );
	$other     = sodium_crypto_sign_keypair();
	$forged    = good_entry();
	$forged['signature'] = base64_encode( sodium_crypto_sign_detached( $hash, sodium_crypto_sign_secretkey( $other ) ) );
	check( 'verify: key set, signature by another key → refused', is_string( WM_Plugin_Updater::verify_file( $tmp, WM_Plugin_Updater::valid_entry( $forged ) ) ) );
	$GLOBALS['wm_test']['options']['wm_plugin_update_public_key'] = base64_encode( 'short' );
	check( 'verify: malformed public key → refused', is_string( WM_Plugin_Updater::verify_file( $tmp, WM_Plugin_Updater::valid_entry( $signed ) ) ) );
	unset( $GLOBALS['wm_test']['options']['wm_plugin_update_public_key'] );
} else {
	check( 'libsodium available for the signature tests', false, 'ext-sodium missing' );
}
@unlink( $tmp );

// ---------------------------------------------------------------------------------------------
// 7. upgrader_pre_download
// ---------------------------------------------------------------------------------------------

fresh();
declare_plugin( 'wm-demo', 'https://arnoldwender.com/wordpress-plugins/wm-demo/' );
WM_Plugin_Updater::register( plugin_file( 'wm-demo' ) );
$GLOBALS['wm_test']['options']['wm_plugin_update_endpoint'] = ENDPOINT;
$GLOBALS['wm_test']['http'][ ENDPOINT . '/manifest.json' ] = manifest_response( array( 'wm-demo' => good_entry( '2.0.0' ) ) );
check( 'pre_download: a package of another plugin is left to WordPress', false === WM_Plugin_Updater::filter_pre_download( false, 'https://downloads.wordpress.org/plugin/akismet.zip' ) );
check( 'pre_download: an earlier reply is kept', 'earlier' === WM_Plugin_Updater::filter_pre_download( 'earlier', good_entry()['package'] ) );

$GLOBALS['wm_test']['downloads'][ good_entry()['package'] ] = ZIP_BODY;
$path = WM_Plugin_Updater::filter_pre_download( false, good_entry()['package'] );
check( 'pre_download: verified package returns its local path', is_string( $path ) && is_file( $path ) && ZIP_BODY === file_get_contents( $path ) );
if ( is_string( $path ) ) {
	@unlink( $path );
}

$GLOBALS['wm_test']['downloads'][ good_entry()['package'] ] = ZIP_BODY . ' tampered';
$refused = WM_Plugin_Updater::filter_pre_download( false, good_entry()['package'] );
check( 'pre_download: tampered package → WP_Error', is_wp_error( $refused ) && 'wm_update_package_rejected' === $refused->get_error_code() );
check( 'pre_download: tampered download deleted', 1 === count( $GLOBALS['wm_test']['deleted'] ) && ! file_exists( $GLOBALS['wm_test']['deleted'][0] ) );

unset( $GLOBALS['wm_test']['downloads'][ good_entry()['package'] ] );
check( 'pre_download: failed download → WP_Error passed on', is_wp_error( WM_Plugin_Updater::filter_pre_download( false, good_entry()['package'] ) ) );

// ---------------------------------------------------------------------------------------------
// 8. Inactive siblings: WordPress asks the host filter for every installed plugin, loads only active ones
// ---------------------------------------------------------------------------------------------

function sleeping_entry( string $body = ZIP_BODY ): array {
	$entry            = good_entry( '3.1.0', $body );
	$entry['package'] = 'https://updates.example.test/wm/wm-sleeping-3.1.0.zip';
	$entry['name']    = 'WM Sleeping';
	return $entry;
}

fresh();
declare_plugin( 'wm-demo', 'https://arnoldwender.com/wordpress-plugins/wm-demo/' );
WM_Plugin_Updater::register( plugin_file( 'wm-demo' ) );
$GLOBALS['wm_test']['options']['wm_plugin_update_endpoint'] = ENDPOINT;
$GLOBALS['wm_test']['http'][ ENDPOINT . '/manifest.json' ] = manifest_response(
	array(
		'wm-demo'     => good_entry( '2.0.0' ),
		'wm-sleeping' => sleeping_entry(),
	)
);

$offer = WM_Plugin_Updater::filter_update( false, array( 'Version' => '3.0.0' ), 'wm-sleeping/wm-sleeping.php' );
check( 'inactive: an unregistered plugin reached through the host filter gets its update', is_array( $offer ) && '3.1.0' === $offer['version'] && 'wm-sleeping' === $offer['slug'] );
check( 'inactive: a single-file plugin in the plugins root is left alone', false === WM_Plugin_Updater::filter_update( false, array(), 'hello.php' ) );
check( 'inactive: a nested plugin path is left alone', false === WM_Plugin_Updater::filter_update( false, array(), 'wm-sleeping/sub/wm-sleeping.php' ) );
check( 'slug_of: directory of the plugin file; none for a root file or a nested path', 'wm-sleeping' === WM_Plugin_Updater::slug_of( 'wm-sleeping/wm-sleeping.php' ) && '' === WM_Plugin_Updater::slug_of( 'hello.php' ) && '' === WM_Plugin_Updater::slug_of( 'a/b/c.php' ) );

$GLOBALS['wm_test']['downloads'][ sleeping_entry()['package'] ] = ZIP_BODY . ' tampered';
$refused = WM_Plugin_Updater::filter_pre_download( false, sleeping_entry()['package'] );
check( 'inactive: a tampered package of an unregistered plugin is refused, not left to WordPress unchecked', is_wp_error( $refused ) && 'wm_update_package_rejected' === $refused->get_error_code() );
$GLOBALS['wm_test']['downloads'][ sleeping_entry()['package'] ] = ZIP_BODY;
$path = WM_Plugin_Updater::filter_pre_download( false, sleeping_entry()['package'] );
check( 'inactive: the genuine package of an unregistered plugin is verified and returned', is_string( $path ) && ZIP_BODY === file_get_contents( $path ) );
if ( is_string( $path ) ) {
	@unlink( $path );
}

$GLOBALS['wm_test']['installed'] = array(
	'wm-sleeping/wm-sleeping.php' => array( 'UpdateURI' => 'https://arnoldwender.com/wordpress-plugins/wm-sleeping/' ),
);
$info = WM_Plugin_Updater::filter_plugins_api( false, 'plugin_information', (object) array( 'slug' => 'wm-sleeping' ) );
check( 'inactive: details dialog for an installed plugin on the hooked host', is_object( $info ) && 'WM Sleeping' === $info->name );
$GLOBALS['wm_test']['installed'] = array(
	'wm-sleeping/wm-sleeping.php' => array( 'UpdateURI' => 'https://updates.someone-else.test/wm-sleeping/' ),
);
check( 'inactive: no details dialog when the installed plugin declares another host', false === WM_Plugin_Updater::filter_plugins_api( false, 'plugin_information', (object) array( 'slug' => 'wm-sleeping' ) ) );
$GLOBALS['wm_test']['installed'] = array();
check( 'inactive: no details dialog for a slug that is not installed', false === WM_Plugin_Updater::filter_plugins_api( false, 'plugin_information', (object) array( 'slug' => 'wm-sleeping' ) ) );

fresh();
$GLOBALS['wm_test']['options']['wm_plugin_update_endpoint'] = ENDPOINT;
$GLOBALS['wm_test']['installed'] = array(
	'wm-sleeping/wm-sleeping.php' => array( 'UpdateURI' => 'https://arnoldwender.com/wordpress-plugins/wm-sleeping/' ),
);
check( 'inactive: with nothing registered, no details dialog and no request', false === WM_Plugin_Updater::filter_plugins_api( false, 'plugin_information', (object) array( 'slug' => 'wm-sleeping' ) ) && array() === $GLOBALS['wm_test']['requests'] );

// ---------------------------------------------------------------------------------------------
// 9. The class guard: a second copy does not redeclare
// ---------------------------------------------------------------------------------------------

require __DIR__ . '/../packages/plugin-updater/class-wm-plugin-updater.php';
check( 'guard: loading a second copy does not fatal', class_exists( 'WM_Plugin_Updater', false ) );

echo "\n";
echo sprintf( " TEST RESULTS: %d PASSED | %d FAILED\n", $passed, $failed );
exit( $failed > 0 ? 1 : 0 );
