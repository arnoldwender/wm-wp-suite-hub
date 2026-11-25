<?php
/**
 * Mutations: each entry undoes one correction this plugin carries, and tests/run-mutations.sh requires
 * tests/test-suite.php to turn red for every one of them. An entry whose search string no longer occurs
 * exactly once is reported as "not applied" and fails the run, so the list cannot rot in silence.
 *
 * Format: [ description, [ [ relative file, search, replace ], ... ] ]
 *
 * @package ArnoldWender\SuiteHub
 */

declare(strict_types=1);

return [
	[
		'the audit chain stops recomputing the signature of each row (tampering with a payload goes unseen)',
		[
			[
				'src/Telemetry/Audit_Chainer.php',
				'if (!hash_equals($recalculated_hash, $row_curr_hash)) {',
				'if (false) {',
			],
		],
	],
	[
		'the chain goes back to comparing digests with !== (the CHANGELOG has promised hash_equals since 1.2.0)',
		[
			[
				'src/Telemetry/Audit_Chainer.php',
				'if (!hash_equals($expected_prev_hash, $row_prev_hash)) {',
				'if ($expected_prev_hash !== $row_prev_hash) {',
			],
		],
	],
	[
		'a bundle install that never happened answers success again instead of 501',
		[
			[
				'src/API/API_Controller.php',
				"'status'         => 501,",
				"'status'         => 200,",
			],
		],
	],
	[
		'the consent bridge accepts a request without a valid REST nonce',
		[
			[
				'src/Bridges/Borlabs_Cookie_Bridge.php',
				"if ('' === \$nonce || !wp_verify_nonce(\$nonce, 'wp_rest')) {",
				'if (false) {',
			],
		],
	],
	[
		'the consent bridge drops its rate limit',
		[
			[
				'src/Bridges/Borlabs_Cookie_Bridge.php',
				'private const RATE_LIMIT_MAX    = 10;',
				'private const RATE_LIMIT_MAX    = 100000;',
			],
		],
	],
	[
		'the consent payload may carry any number of keys again',
		[
			[
				'src/Bridges/Borlabs_Cookie_Bridge.php',
				'private const MAX_CONSENT_KEYS      = 50;',
				'private const MAX_CONSENT_KEYS      = 100000;',
			],
		],
	],
	[
		'the user agent reaches the chain unbounded',
		[
			[
				'src/Bridges/Borlabs_Cookie_Bridge.php',
				'private const MAX_USER_AGENT_LEN    = 255;',
				'private const MAX_USER_AGENT_LEN    = 100000;',
			],
		],
	],
	[
		'the visitor address is sealed into the chain unanonymised',
		[
			[
				'src/Bridges/Borlabs_Cookie_Bridge.php',
				"wp_privacy_anonymize_ip(\$raw_ip) : \$raw_ip;",
				"\$raw_ip : \$raw_ip;",
			],
		],
	],
	[
		'the README documents the WP-CLI subcommand with an underscore instead of its registered @subcommand name',
		[
			[
				'README.md',
				'wp wm-hub crypto-verify  ',
				'wp wm-hub crypto_verify  ',
			],
		],
	],
	[
		'the SDK usage block advertises a verify subcommand the class never implemented',
		[
			[
				'src/CLI/WM_SDK_CLI.php',
				" *   wp wm-sdk test-event",
				" *   wp wm-sdk verify --class=<className>\n *   wp wm-sdk test-event",
			],
		],
	],
	[
		'a dashboard widget rule loses its scope and reaches every other plugin on index.php',
		[
			[
				'src/Admin/Dashboard_Widgets.php',
				'.wm-hub-widget .wm-dash-banner {',
				'.wm-dash-banner {',
			],
		],
	],
	[
		'a plugin-named class rides into the canonical sheet under the family prefix (the shape that walked through the first version of the check)',
		[
			[
				'assets/css/wm-admin-ui.css',
				'.wm-quick-guide {',
				".wm-newsticker-only-speed {\n    color: red;\n}\n\n.wm-quick-guide {",
			],
		],
	],
	[
		'the family header stops clearing the help-tab float and is shortened next to the "Hilfe" toggle again',
		[
			[
				'assets/css/wm-admin-ui.css',
				"    clear: both;\n    background: var(--wm-bg-banner);",
				'    background: var(--wm-bg-banner);',
			],
		],
	],
	[
		'a CLI class stops documenting one of the subcommands it registers',
		[
			[
				'src/CLI/WM_Hub_CLI.php',
				" *   wp wm-hub deduplicate-telemetry\n",
				'',
			],
		],
	],
	[
		'a single plugin rides an alias into the canonical family sheet again',
		[
			[
				'assets/css/wm-admin-ui.css',
				'.wm-quick-guide {',
				".wm-quick-guide,\n.wmmsp-guide-card {",
			],
		],
	],
	[
		'the deduplicator no longer recognises the family sheet as the canonical one',
		[
			[
				'src/SharedKernel/Deduplication/Asset_Deduplicator.php',
				"'canonical_handle' => 'wm-admin-ui',",
				"'canonical_handle' => 'wm-admin-ui-not-registered',",
			],
		],
	],
	[
		'uninstall leaves an autoloaded option behind again (the state uninstall.php promised not to leave)',
		[
			[
				'uninstall.php',
				"delete_option('wm_suite_theme');",
				"// delete_option('wm_suite_theme');",
			],
		],
	],
	[
		'the CRA block points its download button at a REST route this plugin never registers (a 404 behind a transparency claim)',
		[
			[
				'src/Gutenberg/Blocks/cra-security-status/render.php',
				"\$attributes['sbomUrl'] ?? ''",
				"\$attributes['sbomUrl'] ?? rest_url( 'wm-suite/v1/sbom' )",
			],
		],
	],
	[
		'a block template echoes its icon bare again (Plugin Check: OutputNotEscaped)',
		[
			[
				'src/Gutenberg/Blocks/cra-security-status/render.php',
				"echo wp_kses( wm_suite_hub_icon( 'package' ), wm_suite_hub_icon_allowed_html() );",
				"echo wm_suite_hub_icon( 'package' );",
			],
		],
	],
	[
		'the icon allowlist forgets <rect>, so wp_kses would strip calendar, lock and pause silently',
		[
			[
				'src/Gutenberg/icons.php',
				"'rect'   => [ 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true ],",
				'',
			],
		],
	],
	[
		'an emoji comes back into a document that ships inside the package',
		[
			[
				'CHANGELOG.md',
				'### Initial Enterprise Release',
				'### 🚀 Initial Enterprise Release',
			],
		],
	],
	[
		'a registered block stops being documented, so the editor surface goes dark again',
		[
			// whistleblower-wizard is named exactly once in the document, so removing that name
			// really does leave the block undocumented. lksg-esg-scorecard and cra-security-status
			// appear two or three times (heading plus the warning table) and a mutation on their
			// heading alone would survive — the check would look defended and would not be.
			[
				'docs/BLOCKS.md',
				'### `wm/whistleblower-wizard` — WM HinSchG Whistleblower Portal',
				'### WM HinSchG Whistleblower Portal',
			],
		],
	],
	[
		'the update client accepts a package whose SHA-256 differs from the manifest',
		[
			[
				'packages/plugin-updater/class-wm-plugin-updater.php',
				'if ( ! hash_equals( $entry[\'sha256\'], $hash ) ) {',
				'if ( false ) {',
			],
		],
	],
	[
		'the update client accepts a package signed by another key',
		[
			[
				'packages/plugin-updater/class-wm-plugin-updater.php',
				'if ( ! sodium_crypto_sign_verify_detached( $signature, $hash, $key ) ) {',
				'if ( false ) {',
			],
		],
	],
	[
		'the update client accepts an unsigned package although a public key is configured',
		[
			[
				'packages/plugin-updater/class-wm-plugin-updater.php',
				"if ( false === \$signature || 64 !== strlen( \$signature ) ) {\n\t\t\t\treturn 'Das Paket ist nicht signiert.';",
				"if ( false ) {\n\t\t\t\treturn 'Das Paket ist nicht signiert.';",
			],
		],
	],
	[
		'the update client talks to a plain-http endpoint',
		[
			[
				'packages/plugin-updater/class-wm-plugin-updater.php',
				"return 0 === strpos( \$endpoint, 'https://' ) ? \$endpoint : '';",
				'return $endpoint;',
			],
		],
	],
	[
		'the update client offers a package served over http',
		[
			[
				'packages/plugin-updater/class-wm-plugin-updater.php',
				"if ( 0 !== strpos( \$package, 'https://' ) || ! preg_match( '/^[0-9a-f]{64}\$/', \$sha256 ) ) {",
				"if ( ! preg_match( '/^[0-9a-f]{64}\$/', \$sha256 ) ) {",
			],
		],
	],
	[
		'the update client treats a manifest of another schema as valid',
		[
			[
				'packages/plugin-updater/class-wm-plugin-updater.php',
				'|| self::SCHEMA !== (int) $data[\'schema\'] ',
				'',
			],
		],
	],
	[
		'the update client hooks plugins that declare a wordpress.org Update URI',
		[
			[
				'packages/plugin-updater/class-wm-plugin-updater.php',
				"|| 'wordpress.org' === \$host ",
				'',
			],
		],
	],
	[
		'a failed manifest fetch is cached as long as a good one (a dead endpoint looks settled for six hours)',
		[
			[
				'packages/plugin-updater/class-wm-plugin-updater.php',
				'isset( $result[\'error\'] ) ? self::CACHE_FAIL : self::CACHE_OK',
				'self::CACHE_OK',
			],
		],
	],
	[
		'a tampered download is left on disk after it was refused',
		[
			[
				'packages/plugin-updater/class-wm-plugin-updater.php',
				"\t\t\t\twp_delete_file( \$tmp );\n",
				'',
			],
		],
	],
	[
		'the license key is no longer sent with the manifest request',
		[
			[
				'packages/plugin-updater/class-wm-plugin-updater.php',
				"\$headers['X-WM-License-Key'] = \$license;",
				'',
			],
		],
	],
	[
		'an inactive fleet plugin no longer sees its update (only registered, i.e. active, plugins are answered)',
		[
			[
				'packages/plugin-updater/class-wm-plugin-updater.php',
				'$slug = self::slug_of( $plugin_file );',
				'return $update;',
			],
		],
	],
	[
		'the package of an inactive plugin is downloaded by WordPress unchecked (only registered slugs are verified)',
		[
			[
				'packages/plugin-updater/class-wm-plugin-updater.php',
				"foreach ( array_keys( \$manifest['plugins'] ) as \$slug ) {",
				'foreach ( array_keys( self::$plugins ) as $slug ) {',
			],
		],
	],
	[
		'the details dialog answers for a slug whose installed plugin declares another host',
		[
			[
				'packages/plugin-updater/class-wm-plugin-updater.php',
				"isset( self::\$hosts[ self::host_of( (string) \$data['UpdateURI'] ) ] )",
				'true',
			],
		],
	],
	[
		'a nested plugin path counts as a slug',
		[
			[
				'packages/plugin-updater/class-wm-plugin-updater.php',
				"|| false !== strpos( \$slug, '/' ) ",
				'',
			],
		],
	],
	[
		'the hub declares its own licence as Proprietary again instead of reading its header',
		[
			[
				'src/Compliance/SBOM_Generator.php',
				"'licenses'    => self::own_licenses(),",
				"'licenses'    => [['license' => ['id' => 'Proprietary']]],",
			],
		],
	],
	[
		'a spoke that declares no licence is published as Proprietary again',
		[
			[
				'src/Compliance/SBOM_Generator.php',
				"'author'      => \$sbom_data['author'] ?? 'WM Suite',",
				"'author'      => \$sbom_data['author'] ?? 'WM Suite',\n                    'licenses'    => [['license' => ['id' => 'Proprietary']]],",
			],
		],
	],
	[
		'the reference adapter goes back to teaching Proprietary to every spoke copied from it',
		[
			[
				'src/Adapters/Sample_Spoke_Adapter.php',
				"'licenses'    => [['license' => ['id' => 'GPL-2.0-or-later']]],",
				"'licenses'    => [['license' => ['id' => 'Proprietary']]],",
			],
		],
	],
	[
		'Developer_SDK_Bridge ignores wm-sovereign-consent-vault and falls back to Borlabs only',
		[
			[
				'src/Compliance/Developer_SDK_Bridge.php',
				"if (function_exists('wm_consent_has')) {",
				'if (false) {',
			],
		],
	],
	[
		'Developer_SDK_Bridge stops checking Luhn Mod-10 and corrupts non-card 16-digit reference numbers',
		[
			[
				'src/Compliance/Developer_SDK_Bridge.php',
				'if ($digits === \'\' || !$this->is_luhn_valid($digits)) {',
				'if (false) {',
			],
		],
	],
	[
		'Developer_SDK_Bridge stops rejecting preview_img URLs carrying CSS-injection tokens',
		[
			[
				'src/Compliance/Developer_SDK_Bridge.php',
				"\$safe_preview = (\$raw_preview !== '' && 1 !== preg_match('/[\\'\"();\\s\\\\\\\\]/', \$raw_preview)) ? \$raw_preview : '';",
				'$safe_preview = $raw_preview;',
			],
		],
	],
	[
		'a Gutenberg block manifest drops editorScript so it disappears from the Block Inserter again',
		[
			[
				'src/Gutenberg/Blocks/event-grid/block.json',
				'"editorScript": "wm-suite-hub-blocks-editor",',
				'',
			],
		],
	],
	[
		'Block_Manager registers the wrong editor script handle so none of the 16 blocks resolve their script',
		[
			[
				'src/Gutenberg/Block_Manager.php',
				"wp_register_script(\n\t\t\t\t'wm-suite-hub-blocks-editor',",
				"wp_register_script(\n\t\t\t\t'wm-suite-hub-blocks-missing',",
			],
		],
	],
	[
		'uninstall.php drops the opt-in guard and wipes the audit table and options unconditionally again',
		[
			[
				'uninstall.php',
				"if (!(bool) get_option('wm_suite_hub_delete_data_on_uninstall', false)) {",
				'if (false) {',
			],
		],
	],
	[
		'Telemetry_Bridge changes its Block Bindings source away from wm-suite-hub/telemetry',
		[
			[
				'src/Gutenberg/Telemetry_Bridge.php',
				"public const BINDING_SOURCE = 'wm-suite-hub/telemetry';",
				"public const BINDING_SOURCE = 'wm-suite-hub/unregistered';",
			],
		],
	],
	[
		'Telemetry_Bridge changes its GenerateBlocks Dynamic Tag away from wm_hub_telemetry',
		[
			[
				'src/Gutenberg/Telemetry_Bridge.php',
				"public const DYNAMIC_TAG = 'wm_hub_telemetry';",
				"public const DYNAMIC_TAG = 'wm_hub_unregistered';",
			],
		],
	],
	[
		'wm/lksg-esg-scorecard block.json reverts humanRightsScore default to fabricated 98',
		[
			[
				'src/Gutenberg/Blocks/lksg-esg-scorecard/block.json',
				"\"humanRightsScore\": {\n\t\t\t\"type\": \"number\",\n\t\t\t\"default\": -1\n\t\t}",
				"\"humanRightsScore\": {\n\t\t\t\"type\": \"number\",\n\t\t\t\"default\": 98\n\t\t}",
			],
		],
	],
	[
		'wm/lksg-esg-scorecard skips its unconfigured notice and renders percentage report framing with no data',
		[
			[
				'src/Gutenberg/Blocks/lksg-esg-scorecard/render.php',
				'$is_unconfigured = ( $hr_score < 0 && $env_score < 0 && $gov_score < 0 );',
				'$is_unconfigured = false;',
			],
		],
	],
	[
		'wm/event-card block.json reverts price default to fabricated 249,00 €',
		[
			[
				'src/Gutenberg/Blocks/event-card/block.json',
				"\"price\": {\n\t\t\t\"type\": \"string\",\n\t\t\t\"default\": \"\"\n\t\t}",
				"\"price\": {\n\t\t\t\"type\": \"string\",\n\t\t\t\"default\": \"249,00 €\"\n\t\t}",
			],
		],
	],
	[
		'wm/event-card drops its empty-title guard and renders unconfigured card markup without an inspector prompt',
		[
			[
				'src/Gutenberg/Blocks/event-card/render.php',
				"if ( '' === trim( \$title ) ) :",
				'if ( false ) :',
			],
		],
	],
	[
		'wm/event-grid stops querying CyberEvents_Database::get_active_events() when active',
		[
			[
				'src/Gutenberg/Blocks/event-grid/render.php',
				"if ( '' === trim( \$content ) && class_exists( 'CyberEvents_Database' ) && method_exists( 'CyberEvents_Database', 'get_active_events' ) ) {",
				'if ( false ) {',
			],
		],
	],
	[
		'wm-suite-hub.php stops hooking wp_initialize_site for Multisite sub-site provisioning',
		[
			[
				'wm-suite-hub.php',
				"add_action('wp_initialize_site', function (\$new_site): void {",
				"if (false) add_action('wp_initialize_site', function (\$new_site): void {",
			],
		],
	],
	[
		'API_Controller writes wm_suite_theme with autoload=true again',
		[
			[
				'src/API/API_Controller.php',
				"update_option('wm_suite_theme', \$theme, false);",
				"update_option('wm_suite_theme', \$theme, true);",
			],
		],
	],
	[
		'the hub removes the own menu of a spoke that did not register again (a spoke on an older contract is left with no menu entry)',
		[
			[
				'src/Admin/Admin_Menu.php',
				'if (!isset($registered[$spoke_slug])) {',
				'if (false) {',
			],
		],
	],
	[
		'a spoke sub-page returns WM Suite as parent without mapping the removed root (get_admin_page_parent resets it, nothing is highlighted)',
		[
			[
				'src/Admin/Admin_Menu.php',
				"\$GLOBALS['_wp_real_parent_file'][\$parent_file] = 'wm-suite-hub';",
				'',
			],
		],
	],
	[
		'a spoke sub-page no longer marks the spoke entry under WM Suite',
		[
			[
				'src/Admin/Admin_Menu.php',
				'return $this->adopted_submenu_file ?? $submenu_file;',
				'return $submenu_file;',
			],
		],
	],
	[
		'a spoke that did not register has its sub-pages moved under WM Suite although its own menu stays',
		[
			[
				'src/Admin/Admin_Menu.php',
				'if (!isset($registered[$spoke_slug]) || !in_array($parent_file, $root_slugs, true)) {',
				'if (!in_array($parent_file, $root_slugs, true)) {',
			],
		],
	],
	[
		'a spoke the hub lists under WM Suite loses its own-menu mapping (its menu would show twice)',
		[
			[
				'src/Admin/Admin_Menu.php',
				"'wm-newsticker'                => ['wm-newsticker'],",
				"'wm-newsticker-unlisted'       => ['wm-newsticker'],",
			],
		],
	],
	[
		'the cockpit badge reads "System Nominal" again whatever the matrix reports',
		[
			[
				'src/Admin/Views/cockpit-view.php',
				'<span><?php echo esc_html($wm_status_label($global_status)); ?></span>',
				'<span>System Nominal · Zero-CDN</span>',
			],
		],
	],
	// The emoji below are written as PHP escapes, so this file stays free of them; the runner evaluates the
	// array and inserts the real characters.
	[
		'a WM Suite menu category opens with an emoji again (read out before every entry)',
		[
			[
				'src/Admin/Admin_Menu.php',
				"'header'  => 'SICHERHEIT & LEGAL',",
				"'header'  => '\u{1F6E1}\u{FE0F} SICHERHEIT & LEGAL',",
			],
		],
	],
	[
		'a WM Suite menu entry names an icon the map does not draw (the label loses its icon in silence)',
		[
			[
				'src/Admin/Admin_Menu.php',
				"'icon'       => 'ticket',",
				"'icon'       => 'ticket-outline',",
			],
		],
	],
	[
		'a JavaScript catalog ships the emoji msgid of 1.2.0 again',
		[
			[
				'languages/wm-suite-hub-de_DE-dfbff627e6c248bcb3b61d7d06da9ca9.json',
				'"Aktiviert": [',
				"\"\u{1F7E2} Aktiviert\": [",
			],
		],
	],
	[
		'bom.json names a purl that is not the header version',
		[
			[
				'bom.json',
				'"purl": "pkg:wordpress/wm-suite-hub@',
				'"purl": "pkg:wordpress/wm-suite-hub@0.',
			],
		],
	],
	// 1.2.2: what the hub shows is what it has. Each entry below restores one statement or behaviour the
	// release removed; tests/test-suite.php names the case it guards in the test of the same date.
	[
		'an empty registry reads as HEALTHY again',
		[
			[
				'src/Telemetry/Health_Matrix.php',
				'} elseif ($total === 0 || $degraded > 0 || $unconfigured > 0) {',
				'} elseif ($degraded > 0 || $unconfigured > 0) {',
			],
		],
	],
	[
		'the SBOM names another plugin of the fleet as the tool that wrote it',
		[
			[
				'src/Compliance/SBOM_Generator.php',
				"'name'    => 'wm-suite-hub',",
				"'name'    => 'WM Suite SBOM Beacon',",
			],
		],
	],
	[
		'the verifier ships the placeholder "official root key" again',
		[
			[
				'src/Marketplace/Crypto/WM_Ed25519_Verifier.php',
				"public const DEFAULT_MASTER_PUBLIC_KEY = '';",
				"public const DEFAULT_MASTER_PUBLIC_KEY = 'N8q7Q6D4W7dE8gZ0v8B3x7K2p9L5m4N1q8R7s6T5u4V3w2X1y0Z9a8B7c6D5e4F3';",
			],
		],
	],
	[
		'a pack with a dependency reads as having none (the view would call a computed order free)',
		[
			[
				'src/SharedKernel/Bundles/Bundle_Definition.php',
				"if (!empty(\$pkg['dependencies'])) {",
				'if (false) {',
			],
		],
	],
	[
		'the pre-flight check creates wp-content/wm-backups on every dry run again',
		[
			[
				'src/Marketplace/Auditor/Preflight_Auditor.php',
				'$backup_writable = file_exists($backup_dir) ? wp_is_writable($backup_dir) : wp_is_writable(dirname($backup_dir));',
				"wp_mkdir_p(\$backup_dir);\n        \$backup_writable = file_exists(\$backup_dir) && wp_is_writable(\$backup_dir);",
			],
		],
	],
	[
		'the deduplicator adds a fixed estimate instead of the dropped file\'s size',
		[
			[
				'src/SharedKernel/Deduplication/Asset_Deduplicator.php',
				'$bytes = $this->local_size_of($wp_styles, $handle);',
				'$bytes = 24500;',
			],
		],
	],
	[
		'the governance widget lists a plugin as registered although the registry does not have it',
		[
			[
				'src/Admin/Dashboard_Widgets.php',
				'if ( null === $module ) {',
				'if ( false ) {',
			],
		],
	],
	[
		'every module dot in the cockpit widget is green again, whatever health the module reports',
		[
			[
				'src/Admin/Dashboard_Widgets.php',
				"\$dot_color  = 'HEALTHY' === \$dot_status ? '#10b981' : ( 'ERROR' === \$dot_status ? '#ef4444' : '#f59e0b' );",
				"\$dot_color  = '#10b981';",
			],
		],
	],
	[
		'the solution-pack dialog takes the id of the cockpit dialog\'s title again',
		[
			[
				'src/Admin/Views/marketplace-view.php',
				'id="wm-preflight-title"',
				'id="wm-modal-title"',
			],
		],
	],
	[
		'the cockpit labels healthy modules as conformant again',
		[
			[
				'src/Admin/Views/cockpit-view.php',
				"esc_html_e('Gesund', 'wm-suite-hub')",
				"esc_html_e('Aktiv & Konform', 'wm-suite-hub')",
			],
		],
	],
	[
		'verify-signatures reports success without reading a signature',
		[
			[
				'src/CLI/WM_Hub_CLI.php',
				"WP_CLI::error(sprintf('Keine Signatur geprüft",
				"WP_CLI::success(sprintf('Keine Signatur geprüft",
			],
		],
	],
	[
		'bundle install reports success for an install that does not happen',
		[
			[
				'src/CLI/WM_Hub_CLI.php',
				"WP_CLI::error('Die Installation von Solution Packs",
				"WP_CLI::success('Die Installation von Solution Packs",
			],
		],
	],
	[
		'a block notice reaches visitors again',
		[
			[
				'src/Gutenberg/block-notice.php',
				"if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'edit_posts' ) ) {",
				'if ( false ) {',
			],
		],
	],
	[
		'the whistleblower block renders without the plugin whose portal it shows',
		[
			[
				'src/Gutenberg/Blocks/whistleblower-wizard/render.php',
				"! shortcode_exists( 'wm_hinschg_portal' )",
				'false',
			],
		],
	],
	[
		'the consent badge links to something the consent vault does not open',
		[
			[
				'src/Gutenberg/Blocks/consent-badge/render.php',
				'<a href="#wm-consent-settings" class="wm-consent-badge" data-wm-consent-open>',
				'<a href="#wm-privacy-preferences" class="wm-consent-badge">',
			],
		],
	],
	[
		'the whistleblower card points at an anchor nothing defines again',
		[
			[
				'src/Gutenberg/Blocks/whistleblower-card/render.php',
				"\$channel_url = '#whistleblower-wizard';",
				"\$channel_url = '#check-status';",
			],
		],
	],
	[
		'the event card shows its booking button without a booking page',
		[
			[
				'src/Gutenberg/Blocks/event-card/render.php',
				"<?php if ( '' !== \$booking_url && 0 !== \$capacity ) : ?>",
				'<?php if ( 0 !== $capacity ) : ?>',
			],
		],
	],
	[
		'the event grid links an event without a page to #booking again',
		[
			[
				'src/Gutenberg/Blocks/event-grid/render.php',
				"\$ev_url = '';",
				"\$ev_url = '#booking';",
			],
		],
	],
	[
		'a block title cites "BFSGV Anhang 3" again',
		[
			[
				'src/Gutenberg/Blocks/bfsg-statement-embed/block.json',
				'"title": "WM Accessibility Statement",',
				'"title": "WM BFSGV Anhang 3 Accessibility Statement",',
			],
		],
	],
	[
		'an editor setting disappears while block.json still declares it',
		[
			[
				'build/index.js',
				",\n\t\t{ key: 'channelUrl', type: 'string', label: __( 'Link zur Meldestelle', 'wm-suite-hub' ) }",
				'',
			],
		],
	],
	[
		'the grid column count stops reaching the stylesheet',
		[
			[
				'assets/css/wm-blocks-core.css',
				'grid-template-columns: repeat(auto-fill, minmax(max(min(100%, 300px), calc((100% - (var(--wm-grid-cols, 3) - 1) * var(--wm-space-lg)) / var(--wm-grid-cols, 3))), 1fr));',
				'grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));',
			],
		],
	],
	[
		'reduced motion leaves the ticker text outside the viewport again',
		[
			[
				'assets/css/wm-blocks-core.css',
				"animation: none;\n\t\tpadding-left: 0;",
				'padding-left: 0;',
			],
		],
	],
	[
		'one storage read without try stops every block script where site data is blocked',
		[
			[
				'assets/js/wm-blocks-interactivity.js',
				'get: function (key) { try { return localStorage.getItem(key); } catch (e) { return null; } },',
				'get: function (key) { return localStorage.getItem(key); },',
			],
		],
	],
	[
		'the placeholder only un-hides an element whose content has already loaded',
		[
			[
				'assets/js/wm-blocks-interactivity.js',
				"if (target.tagName === 'TEMPLATE') {",
				'if (false) {',
			],
		],
	],
	[
		'the block assets are pinned to a frozen version again',
		[
			[
				'src/Gutenberg/Zero_Duplication_Asset_Engine.php',
				"return defined( 'WM_SUITE_HUB_VERSION' ) ? (string) WM_SUITE_HUB_VERSION : null;",
				"return '1.1.1';",
			],
		],
	],
	[
		'every page with a block prints a REST nonce again',
		[
			[
				'src/Gutenberg/Zero_Duplication_Asset_Engine.php',
				"'i18n' => [",
				"'nonce' => wp_create_nonce( 'wp_rest' ),\n\t\t\t\t'i18n' => [",
			],
		],
	],
	[
		'the booking button is found again under an e-invoicing keyword it has nothing to do with',
		[
			[
				'src/Gutenberg/Blocks/event-booking-cta/block.json',
				'"keywords": ["booking", "cta", "button", "tickets"],',
				'"keywords": ["booking", "cta", "button", "tickets", "e-rechnung"],',
			],
		],
	],
	[
		'the editor script is registered under a frozen version instead of its content hash',
		[
			[
				'src/Gutenberg/Block_Manager.php',
				"isset( \$asset_meta['version'] ) ? (string) \$asset_meta['version'] : null",
				"'1.1.5'",
			],
		],
	],
	// 1.2.3: each entry below restores one statement or defect the release removed (suite TEST 14, 3b, 28, 36).
	[
		'an unknown health status reads as healthy',
		[
			[
				'src/Telemetry/Health_Matrix.php',
				"default        => __('Unbekannt', 'wm-suite-hub'),",
				"default        => __('Gesund', 'wm-suite-hub'),",
			],
		],
	],
	[
		'the runtime card shows a green HTTPS tick whether the site uses HTTPS or not',
		[
			[
				'src/Admin/Views/cockpit-view.php',
				"<?php echo \$wm_https ? 'dashicons-yes-alt wm-check-ok' : 'dashicons-warning wm-check-warn'; ?>",
				"<?php echo 'dashicons-yes-alt wm-check-ok'; ?>",
			],
		],
	],
	[
		'the chain check stops after its first page and calls a table of 1201 entries intact after 500',
		[
			[
				'src/Telemetry/Audit_Chainer.php',
				'} while (count($rows) === self::VERIFY_PAGE_SIZE);',
				'} while (false);',
			],
		],
	],
	[
		'a broken signature is reported as tampering again, although a changed AUTH_SALT breaks it the same way',
		[
			[
				'src/Telemetry/Audit_Chainer.php',
				"__('Die Signatur von Eintrag #%d stimmt nicht: Der Eintrag wurde geändert, oder der Schlüssel der Website (AUTH_SALT) ist nicht mehr der, mit dem er geschrieben wurde.', 'wm-suite-hub')",
				"__('Manipulation erkannt bei Eintrag #%d.', 'wm-suite-hub')",
			],
		],
	],
	[
		'the consent bridge records the empty event detail instead of the decision in the Borlabs cookie',
		[
			[
				'src/Bridges/Borlabs_Cookie_Bridge.php',
				"\$consent_data = \$this->shape_consents(null !== \$cookie ? \$cookie['consents'] : (array) (\$params['consents'] ?? []));",
				"\$consent_data = \$this->shape_consents((array) (\$params['consents'] ?? []));",
			],
		],
	],
	[
		'the consent bridge writes an entry with no consent data again',
		[
			[
				'src/Bridges/Borlabs_Cookie_Bridge.php',
				'if ([] === $consent_data) {',
				'if (false) {',
			],
		],
	],
	[
		'the consent bridge returns the exception text to an anonymous visitor again',
		[
			[
				'src/Bridges/Borlabs_Cookie_Bridge.php',
				"__('Die Einwilligung konnte nicht protokolliert werden.', 'wm-suite-hub'),",
				"'Fehler beim kryptografischen Versiegeln: ' . \$e->getMessage(),",
			],
		],
	],
	[
		'the consent bridge hooks a Borlabs filter that Borlabs Cookie 3.4 does not have',
		[
			[
				'src/Bridges/Borlabs_Cookie_Bridge.php',
				'if ($this->is_borlabs_active()) {',
				"add_filter('borlabsCookie/package/register', static fn(array \$packages): array => \$packages);\n        if (\$this->is_borlabs_active()) {",
			],
		],
	],
	[
		'the consent filter looks for the category among the Borlabs service lists again (always "no consent")',
		[
			[
				'src/Compliance/Developer_SDK_Bridge.php',
				'if (isset($consents[$group]) && is_array($consents[$group]) && $consents[$group] !== []) {',
				'if (in_array($group, $consents, true)) {',
			],
		],
	],
	[
		'the consent filter no longer reads external_media as the Borlabs group external-media',
		[
			[
				'src/Compliance/Developer_SDK_Bridge.php',
				"\$group_keys = array_unique([\$wanted, str_replace('_', '-', \$wanted)]);",
				'$group_keys = [$wanted];',
			],
		],
	],
	[
		'the cockpit script prints a text no catalog can translate',
		[
			[
				'assets/js/wm-cockpit-vanilla.js',
				"showToast(t('themeUpdated'), 'success');",
				"showToast('Design aktualisiert.', 'success');",
			],
		],
	],
	[
		'the pre-flight dialog carries a class no sheet styles again (it opens as a bare block)',
		[
			[
				'src/Admin/Views/marketplace-view.php',
				'id="wm-preflight-modal" class="wm-modal-overlay"',
				'id="wm-preflight-modal" class="wm-modal-backdrop"',
			],
		],
	],
	[
		'an Escape anywhere on the cockpit moves focus again although no dialog is open',
		[
			[
				'assets/js/wm-cockpit-vanilla.js',
				"// Closed already: do nothing (until 1.2.3 an Escape anywhere on the page moved focus away).\n        if (!modal || !modal.classList.contains('is-active')) return;",
				"if (!modal) return;",
			],
		],
	],
	[
		'the busy icon class loses its rule and never turns',
		[
			[
				'assets/css/wm-obsidian-glass.css',
				".wm-spin {\n    animation: wmSpin 1s linear infinite;",
				".wm-spin-unused {\n    animation: wmSpin 1s linear infinite;",
			],
		],
	],
	[
		'a module dot in the cockpit widget is colour only again (no text alternative)',
		[
			[
				'src/Admin/Dashboard_Widgets.php',
				' role="img" aria-label="<?php echo esc_attr( Health_Matrix::status_label( $dot_status ) ); ?>"',
				'',
			],
		],
	],
	[
		'the cockpit status pill is green again whatever the global status says',
		[
			[
				'src/Admin/Views/cockpit-view.php',
				"('CRITICAL' === \$global_status ? ' is-critical' : ' is-warning')",
				"''",
			],
		],
	],
	[
		'the red pill rule is renamed, so a critical status has no colour of its own',
		[
			[
				'assets/css/wm-obsidian-glass.css',
				'.wm-system-status-badge.is-critical {',
				'.wm-system-status-badge.is-danger {',
			],
		],
	],
	[
		'the widget shows an empty audit table as "intakt" on green again',
		[
			[
				'src/Admin/Dashboard_Widgets.php',
				'$chain_empty    = $chain_valid && 0 === (int) $chain_length;',
				'$chain_empty    = false;',
			],
		],
	],
	[
		'a broken audit chain carries is-alert again, a class no sheet styles',
		[
			[
				'src/Admin/Dashboard_Widgets.php',
				"( \$chain_valid ? ' is-valid' : ' is-warning' )",
				"( \$chain_valid ? ' is-valid' : ' is-alert' )",
			],
		],
	],
	[
		'the block sheet hangs on enqueue_block_editor_assets again and never reaches the editor canvas',
		[
			[
				'src/Gutenberg/Zero_Duplication_Asset_Engine.php',
				"add_action( 'enqueue_block_assets', [ \$this, 'enqueue_editor_assets' ] );",
				"add_action( 'enqueue_block_editor_assets', [ \$this, 'enqueue_editor_assets' ] );",
			],
		],
	],
	[
		'the editor hook enqueues the block sheet on every front-end page, with or without a block',
		[
			[
				'src/Gutenberg/Zero_Duplication_Asset_Engine.php',
				"\t\tif ( ! is_admin() ) {\n\t\t\treturn;\n\t\t}\n\t\t\$plugin_url",
				"\t\t\$plugin_url",
			],
		],
	],
];
