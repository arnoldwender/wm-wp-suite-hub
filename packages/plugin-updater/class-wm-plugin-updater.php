<?php
/**
 * WM_Plugin_Updater — updates for the WM Suite plugin fleet from a self-hosted manifest.
 *
 * Why this exists: the fleet is not distributed through WordPress.org. Since WordPress 5.8 a
 * plugin can declare `Update URI:` in its header; core then asks the filter
 * `update_plugins_{$hostname}` for that plugin (wp-includes/update.php, wp_update_plugins(),
 * read in WordPress 7.1 on 2026-09-17, lines 508-596) and compares the returned `version` with
 * the installed one. This class answers that filter from ONE JSON manifest for the whole fleet
 * (core calls the filter once per plugin, so one request per plugin would be 24 requests), fills
 * the "View details" dialog (`plugins_api`), and checks the downloaded ZIP before the upgrader
 * unpacks it (`upgrader_pre_download`, WP_Upgrader::download_package(), WordPress 7.1 line 322).
 *
 * Nothing is contacted until an endpoint is configured. Missing or broken data never reads as
 * "up to date": status() says not_configured, unreachable or invalid.
 *
 * Configuration (constant wins over option):
 *   WM_PLUGIN_UPDATE_ENDPOINT    / option wm_plugin_update_endpoint    https base URL; the manifest is <base>/manifest.json
 *   WM_PLUGIN_UPDATE_PUBLIC_KEY  / option wm_plugin_update_public_key  base64 Ed25519 public key; when set, every package must be signed
 *   WM_PLUGIN_LICENSE_KEY        / option wm_plugin_license_key        sent as header X-WM-License-Key with manifest requests
 *
 * Manifest (schema 1):
 *   { "schema": 1, "plugins": { "<slug>": { "version": "1.2.0", "package": "https://…/<slug>-1.2.0.zip",
 *     "sha256": "<64 hex>", "signature": "<base64>", "requires": "6.4", "requires_php": "8.2",
 *     "tested": "7.1", "url": "https://…", "name": "…", "changelog": "<html>" } } }
 *
 * Signature scheme, shared with wm-suite-hub's WM_Ed25519_Verifier: a detached Ed25519 signature
 * over the lowercase hex SHA-256 of the ZIP.
 *
 * The direct-distribution build of each plugin copies this file into `includes/wm-updater/`; the
 * source repositories and the WordPress.org builds carry no copy. The first copy loaded wins through
 * the class_exists guard, so when two plugins carry copies from different releases, VERSION tells
 * which one answers.
 *
 * WordPress loads only ACTIVE plugins, but asks `update_plugins_{$hostname}` for every INSTALLED one.
 * An inactive fleet plugin never runs its own register(); the copy of any active sibling that
 * declared the same host answers for it, and verifies its package the same way.
 *
 * Written for PHP 7.4, the lowest `Requires PHP` in the fleet.
 *
 * @package ArnoldWender\PluginUpdater
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WM_Plugin_Updater', false ) ) {

	/**
	 * Registry and WordPress hooks for fleet updates.
	 */
	final class WM_Plugin_Updater {

		/** Version of this file; bumped when its behaviour changes. */
		const VERSION = '1.1.0';

		/** Manifest schema this class understands. */
		const SCHEMA = 1;

		/** Seconds a good manifest is cached. */
		const CACHE_OK = 21600;

		/** Seconds a failed fetch is remembered, so a dead endpoint is not asked on every page. */
		const CACHE_FAIL = 1800;

		/** @var array<string,string> slug => plugin file (relative to the plugins directory) */
		private static $plugins = array();

		/** @var array<string,bool> hosts whose update_plugins_{host} filter this class answers */
		private static $hosts = array();

		/** @var bool */
		private static $hooked = false;

		/** @var array|null manifest decoded in this request */
		private static $request_manifest = null;

		/**
		 * Registers a plugin for updates. Call from the plugin's main file with __FILE__.
		 *
		 * @param string $plugin_main_file Absolute path of the plugin's main file.
		 * @return bool True when the plugin declares an Update URI and hooks are (or were) added.
		 */
		public static function register( $plugin_main_file ) {
			if ( ! function_exists( 'plugin_basename' ) || ! is_string( $plugin_main_file ) || '' === $plugin_main_file ) {
				return false;
			}
			$basename = plugin_basename( $plugin_main_file );
			$slug     = dirname( $basename );
			if ( '.' === $slug || '' === $slug ) {
				return false;
			}
			$headers = get_file_data( $plugin_main_file, array( 'UpdateURI' => 'Update URI' ) );
			$host    = self::host_of( isset( $headers['UpdateURI'] ) ? $headers['UpdateURI'] : '' );
			if ( '' === $host ) {
				return false;
			}
			self::$plugins[ $slug ] = $basename;
			self::$hosts[ $host ]   = true;
			if ( ! has_filter( 'update_plugins_' . $host, array( __CLASS__, 'filter_update' ) ) ) {
				add_filter( 'update_plugins_' . $host, array( __CLASS__, 'filter_update' ), 10, 4 );
			}
			if ( ! self::$hooked ) {
				add_filter( 'plugins_api', array( __CLASS__, 'filter_plugins_api' ), 10, 3 );
				add_filter( 'upgrader_pre_download', array( __CLASS__, 'filter_pre_download' ), 10, 4 );
				self::$hooked = true;
			}
			return true;
		}

		/**
		 * Slugs registered in this request.
		 *
		 * @return string[]
		 */
		public static function registered() {
			return array_keys( self::$plugins );
		}

		/**
		 * The host WordPress core derives from an Update URI (it runs sanitize_url() first).
		 * WordPress.org hosts are refused: those plugins are updated by core itself.
		 *
		 * @param string $uri Update URI header value.
		 * @return string Lowercase host, or '' when unusable.
		 */
		public static function host_of( $uri ) {
			$uri = trim( (string) $uri );
			if ( '' === $uri ) {
				return '';
			}
			$host = strtolower( (string) wp_parse_url( $uri, PHP_URL_HOST ) );
			if ( '' === $host || 'wordpress.org' === $host || 'w.org' === $host || '.wordpress.org' === substr( $host, -14 ) ) {
				return '';
			}
			return $host;
		}

		/**
		 * `update_plugins_{$hostname}`: the update data for one registered plugin, or the value unchanged.
		 *
		 * @param array|false $update      Value from earlier callbacks.
		 * @param array       $plugin_data Plugin headers.
		 * @param string      $plugin_file Plugin file relative to the plugins directory.
		 * @param string[]    $locales     Installed locales (unused).
		 * @return array|false
		 */
		public static function filter_update( $update, $plugin_data, $plugin_file, $locales = array() ) {
			if ( false !== $update ) {
				return $update;
			}
			$slug = array_search( $plugin_file, self::$plugins, true );
			if ( false === $slug ) {
				// Core calls this filter for every installed plugin that declares this host, active or
				// not (wp_update_plugins() walks get_plugins()). An inactive fleet plugin never runs its
				// own register(), so without this it would not see an update while it is switched off.
				$slug = self::slug_of( $plugin_file );
				if ( '' === $slug ) {
					return $update;
				}
			}
			$entry = self::entry( $slug );
			if ( null === $entry ) {
				return $update;
			}
			$result = array(
				'slug'    => $slug,
				'version' => $entry['version'],
				'url'     => isset( $entry['url'] ) ? $entry['url'] : '',
				'package' => $entry['package'],
			);
			foreach ( array( 'tested', 'requires_php', 'requires' ) as $key ) {
				if ( isset( $entry[ $key ] ) ) {
					$result[ $key ] = $entry[ $key ];
				}
			}
			return $result;
		}

		/**
		 * `plugins_api`: the "View details" dialog for a registered plugin.
		 *
		 * @param false|object|array $result Earlier result.
		 * @param string             $action API action.
		 * @param object             $args   API arguments.
		 * @return false|object|array
		 */
		public static function filter_plugins_api( $result, $action, $args ) {
			if ( false !== $result || 'plugin_information' !== $action || ! is_object( $args ) || empty( $args->slug ) ) {
				return $result;
			}
			$slug = (string) $args->slug;
			if ( ! isset( self::$plugins[ $slug ] ) && ! self::installed_on_hooked_host( $slug ) ) {
				return $result;
			}
			$entry = self::entry( $slug );
			if ( null === $entry ) {
				return $result;
			}
			$info                = new stdClass();
			$info->name          = isset( $entry['name'] ) ? (string) $entry['name'] : $slug;
			$info->slug          = $slug;
			$info->version       = $entry['version'];
			$info->download_link = $entry['package'];
			$info->homepage      = isset( $entry['url'] ) ? (string) $entry['url'] : '';
			$info->requires      = isset( $entry['requires'] ) ? (string) $entry['requires'] : '';
			$info->requires_php  = isset( $entry['requires_php'] ) ? (string) $entry['requires_php'] : '';
			$info->tested        = isset( $entry['tested'] ) ? (string) $entry['tested'] : '';
			$info->sections      = array(
				'changelog' => isset( $entry['changelog'] ) ? wp_kses_post( (string) $entry['changelog'] ) : '',
			);
			return $info;
		}

		/**
		 * `upgrader_pre_download`: downloads a registered plugin's package itself and refuses it when the
		 * SHA-256 differs from the manifest, or when a public key is configured and the signature does
		 * not verify. Packages of anything else are left to WordPress.
		 *
		 * @param false|string|WP_Error $reply      Earlier reply.
		 * @param string                $package    Package URL.
		 * @param object                $upgrader   WP_Upgrader instance (unused).
		 * @param array                 $hook_extra Extra arguments (unused).
		 * @return false|string|WP_Error Path of the verified download, a WP_Error, or $reply.
		 */
		public static function filter_pre_download( $reply, $package, $upgrader = null, $hook_extra = array() ) {
			if ( false !== $reply || ! is_string( $package ) || '' === $package ) {
				return $reply;
			}
			// Every package the manifest lists is verified, not only those of registered plugins: an
			// inactive plugin's update is offered through an active sibling, and in the request that
			// downloads it nothing registered it.
			$manifest = self::manifest();
			if ( isset( $manifest['error'] ) ) {
				return $reply;
			}
			$match = null;
			foreach ( array_keys( $manifest['plugins'] ) as $slug ) {
				$entry = self::entry( (string) $slug );
				if ( null !== $entry && $entry['package'] === $package ) {
					$match = $entry;
					break;
				}
			}
			if ( null === $match ) {
				return $reply;
			}
			if ( ! function_exists( 'download_url' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			$tmp = download_url( $package );
			if ( is_wp_error( $tmp ) ) {
				return $tmp;
			}
			$verdict = self::verify_file( $tmp, $match );
			if ( true !== $verdict ) {
				wp_delete_file( $tmp );
				return new WP_Error( 'wm_update_package_rejected', $verdict );
			}
			return $tmp;
		}

		/**
		 * Checks a downloaded package against its manifest entry.
		 *
		 * @param string $path  Local file.
		 * @param array  $entry Validated manifest entry.
		 * @return true|string True, or the reason for refusing it.
		 */
		public static function verify_file( $path, array $entry ) {
			$hash = is_readable( $path ) ? hash_file( 'sha256', $path ) : false;
			if ( false === $hash ) {
				return 'Das Paket konnte nicht gelesen werden.';
			}
			if ( ! hash_equals( $entry['sha256'], $hash ) ) {
				return 'Die SHA-256-Prüfsumme des Pakets stimmt nicht mit dem Manifest überein.';
			}
			$public_key = self::config( 'WM_PLUGIN_UPDATE_PUBLIC_KEY', 'wm_plugin_update_public_key' );
			if ( '' === $public_key ) {
				return true;
			}
			if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
				return 'Ein Signaturschlüssel ist gesetzt, aber libsodium fehlt: das Paket kann nicht geprüft werden.';
			}
			$key       = base64_decode( $public_key, true );
			$signature = isset( $entry['signature'] ) ? base64_decode( (string) $entry['signature'], true ) : false;
			if ( false === $key || 32 !== strlen( $key ) ) {
				return 'Der konfigurierte öffentliche Schlüssel ist kein Ed25519-Schlüssel.';
			}
			if ( false === $signature || 64 !== strlen( $signature ) ) {
				return 'Das Paket ist nicht signiert.';
			}
			if ( ! sodium_crypto_sign_verify_detached( $signature, $hash, $key ) ) {
				return 'Die Signatur des Pakets ist ungültig.';
			}
			return true;
		}

		/**
		 * The slug WordPress uses for a plugin file relative to the plugins directory: its directory.
		 * A single-file plugin in the plugins root has none.
		 *
		 * @param string $plugin_file e.g. wm-newsticker/wm-newsticker.php
		 * @return string
		 */
		public static function slug_of( $plugin_file ) {
			$slug = dirname( (string) $plugin_file );
			if ( '.' === $slug || '' === $slug || false !== strpos( $slug, '/' ) || false !== strpos( $slug, '\\' ) ) {
				return '';
			}
			return $slug;
		}

		/**
		 * Whether an installed plugin in the directory $slug declares a host this class answers for.
		 * Guards the details dialog, which WordPress opens by slug for any plugin, WordPress.org ones included.
		 *
		 * @param string $slug Plugin slug.
		 * @return bool
		 */
		private static function installed_on_hooked_host( $slug ) {
			if ( array() === self::$hosts ) {
				return false;
			}
			if ( ! function_exists( 'get_plugins' ) ) {
				if ( ! defined( 'ABSPATH' ) || ! is_readable( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
					return false;
				}
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			foreach ( get_plugins() as $file => $data ) {
				if ( self::slug_of( $file ) === $slug && isset( $data['UpdateURI'] ) && isset( self::$hosts[ self::host_of( (string) $data['UpdateURI'] ) ] ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * State of the update source, for health screens and adapters.
		 *
		 * @return array{state:string,endpoint:string,detail:string,plugins:int}
		 */
		public static function status() {
			$endpoint = self::endpoint();
			if ( '' === $endpoint ) {
				return array( 'state' => 'not_configured', 'endpoint' => '', 'detail' => '', 'plugins' => 0 );
			}
			$manifest = self::manifest();
			if ( isset( $manifest['error'] ) ) {
				return array( 'state' => $manifest['error'], 'endpoint' => $endpoint, 'detail' => $manifest['detail'], 'plugins' => 0 );
			}
			return array( 'state' => 'ok', 'endpoint' => $endpoint, 'detail' => '', 'plugins' => count( $manifest['plugins'] ) );
		}

		/**
		 * Validated manifest entry for a slug, or null.
		 *
		 * @param string $slug Plugin slug.
		 * @return array|null
		 */
		public static function entry( $slug ) {
			$manifest = self::manifest();
			if ( isset( $manifest['error'] ) || ! isset( $manifest['plugins'][ $slug ] ) ) {
				return null;
			}
			return self::valid_entry( $manifest['plugins'][ $slug ] );
		}

		/**
		 * An entry is usable only with a version, an https package and a 64-hex SHA-256.
		 *
		 * @param mixed $entry Raw entry.
		 * @return array|null
		 */
		public static function valid_entry( $entry ) {
			if ( ! is_array( $entry ) ) {
				return null;
			}
			$version = isset( $entry['version'] ) ? (string) $entry['version'] : '';
			$package = isset( $entry['package'] ) ? (string) $entry['package'] : '';
			$sha256  = isset( $entry['sha256'] ) ? strtolower( (string) $entry['sha256'] ) : '';
			if ( ! preg_match( '/^\d+(\.\d+){0,3}([-+][0-9A-Za-z.-]+)?$/', $version ) ) {
				return null;
			}
			if ( 0 !== strpos( $package, 'https://' ) || ! preg_match( '/^[0-9a-f]{64}$/', $sha256 ) ) {
				return null;
			}
			$entry['version'] = $version;
			$entry['package'] = $package;
			$entry['sha256']  = $sha256;
			if ( isset( $entry['url'] ) && 0 !== strpos( (string) $entry['url'], 'https://' ) ) {
				unset( $entry['url'] );
			}
			return $entry;
		}

		/**
		 * Configured endpoint base URL (https only), without a trailing slash.
		 *
		 * @return string
		 */
		public static function endpoint() {
			$endpoint = rtrim( self::config( 'WM_PLUGIN_UPDATE_ENDPOINT', 'wm_plugin_update_endpoint' ), '/' );
			return 0 === strpos( $endpoint, 'https://' ) ? $endpoint : '';
		}

		/**
		 * The fleet manifest: cached per request and in a site transient.
		 *
		 * @return array Either {plugins: array} or {error: string, detail: string}.
		 */
		public static function manifest() {
			if ( null !== self::$request_manifest ) {
				return self::$request_manifest;
			}
			$endpoint = self::endpoint();
			if ( '' === $endpoint ) {
				self::$request_manifest = array( 'error' => 'not_configured', 'detail' => '' );
				return self::$request_manifest;
			}
			$cache_key = 'wm_plugin_update_manifest_' . md5( $endpoint );
			$cached    = get_site_transient( $cache_key );
			if ( is_array( $cached ) && ( isset( $cached['plugins'] ) || isset( $cached['error'] ) ) ) {
				self::$request_manifest = $cached;
				return $cached;
			}
			$result = self::fetch( $endpoint . '/manifest.json' );
			set_site_transient( $cache_key, $result, isset( $result['error'] ) ? self::CACHE_FAIL : self::CACHE_OK );
			self::$request_manifest = $result;
			return $result;
		}

		/**
		 * Forgets the cached manifest (after a configuration change, or from a "check now" button).
		 *
		 * @return void
		 */
		public static function flush() {
			self::$request_manifest = null;
			$endpoint               = self::endpoint();
			if ( '' !== $endpoint ) {
				delete_site_transient( 'wm_plugin_update_manifest_' . md5( $endpoint ) );
			}
		}

		/**
		 * Fetches and validates a manifest.
		 *
		 * @param string $url Manifest URL.
		 * @return array
		 */
		private static function fetch( $url ) {
			$headers = array( 'Accept' => 'application/json' );
			$license = self::config( 'WM_PLUGIN_LICENSE_KEY', 'wm_plugin_license_key' );
			if ( '' !== $license ) {
				$headers['X-WM-License-Key'] = $license;
			}
			$response = wp_safe_remote_get( $url, array( 'timeout' => 10, 'headers' => $headers, 'redirection' => 3 ) );
			if ( is_wp_error( $response ) ) {
				return array( 'error' => 'unreachable', 'detail' => $response->get_error_message() );
			}
			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( 200 !== $code ) {
				return array( 'error' => 'unreachable', 'detail' => 'HTTP ' . $code );
			}
			$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $data ) || ! isset( $data['schema'] ) || self::SCHEMA !== (int) $data['schema'] || ! isset( $data['plugins'] ) || ! is_array( $data['plugins'] ) ) {
				return array( 'error' => 'invalid', 'detail' => 'Manifest ohne Schema ' . self::SCHEMA . ' oder ohne Plugin-Liste.' );
			}
			return array( 'plugins' => $data['plugins'] );
		}

		/**
		 * A setting: constant first, then option; always a trimmed string.
		 *
		 * @param string $constant Constant name.
		 * @param string $option   Option name.
		 * @return string
		 */
		private static function config( $constant, $option ) {
			if ( defined( $constant ) ) {
				return trim( (string) constant( $constant ) );
			}
			return function_exists( 'get_site_option' ) ? trim( (string) get_site_option( $option, '' ) ) : '';
		}

		/**
		 * Test seam: clears the in-request state.
		 *
		 * @return void
		 */
		public static function reset_for_tests() {
			self::$plugins          = array();
			self::$hosts            = array();
			self::$hooked           = false;
			self::$request_manifest = null;
		}
	}
}
