# WM Suite Hub — Developer & Engineering Manual

> Everything below is measured against the code in this repository (`v1.2.6`, 2026-11-21; `wp-plugin-fleet-docs-vs-code.py` reports no drift). An earlier edition documented REST routes `/wm-suite/v1/status` `/submit` `/export`, the tables `wp_wm_suite_audit_merkle`, `wp_wm_suite_spokes_registry` and `wp_wm_suite_sbom_components`, the hooks `wm_suite_hub_registered_spoke`, `wm_suite_hub_telemetry_payload` and `wm_suite_hub_security_alert`, and spoke registration through `add_action( 'wm_suite_hub_register_spoke' )`; none of those exist here.

---

## 1. Layout

| Path | Role |
| --- | --- |
| `wm-suite-hub.php` | Header, constants (`WM_SUITE_HUB_VERSION` / `_FILE` / `_PATH` / `_URL`), PHP 8.2 guard, PSR-4 autoloader for `ArnoldWender\SuiteHub\` -> `src/`, activation/deactivation hooks, `wp_initialize_site` Multisite provisioning, `includes/helpers.php`, `Hub_Orchestrator::get_instance()->init()`. |
| `src/Core/Hub_Orchestrator.php` | Discovery on `plugins_loaded` (99), REST on `rest_api_init`, admin menu and assets, settings registration, WP-CLI, asset deduplicator, Borlabs bridge, Gutenberg manager, SDK bridge, text domain, cron. |
| `src/Core/Module_Registry.php` | Collects the objects the `wm_register_suite_module` filter and its alias `wm_suite_hub_registered_modules` return, merges both lists and keeps those implementing `WM_Plugin_Module_Interface`. |
| `src/Telemetry/Audit_Chainer.php` | `append_event()`, `verify_chain_integrity()`, `get_recent_logs()`; table suffix `wm_suite_audit_log`. |
| `src/Telemetry/Health_Matrix.php` | Score and per-module state, cached in a transient; an empty registry scores 0 and reads `WARNING`. `status_label()` turns a status into the translated word the screens show (`Unbekannt` for anything it does not know). |
| `src/API/API_Controller.php` | The `wm-hub/v1` routes; every one behind `check_permissions()` (`manage_wm_suite`). |
| `src/Admin/Admin_Menu.php` | Menu *WM Suite*, spoke dispatcher, consolidation of standalone spoke menus; a registered spoke's own sub-pages keep *WM Suite* open with the spoke's entry marked (`parent_file` / `submenu_file`, since 1.2.4). |
| `src/Admin/Dashboard_Widgets.php`, `src/Admin/Views/` | The two dashboard widgets and the cockpit / Solution Packs views; every status they show comes from the registry or the health matrix. |
| `src/Bridges/Borlabs_Cookie_Bridge.php`, `assets/js/wm-borlabs-bridge.js` | `wm-suite/v1/consent-sync`: records the decision the visitor saved in Borlabs Cookie, read from the `borlabs-cookie` cookie. It configures nothing in Borlabs (until 1.2.3 it hooked two filters Borlabs 3.4 does not have and rewrote two script handles no plugin registers). |
| `src/Gutenberg/Block_Manager.php`, `src/Gutenberg/Blocks/` | 16 server-rendered blocks, category `wm-suite`, editor script `wm-suite-hub-blocks-editor` (`build/index.js`, a byte copy of `blocks-src/index.js`). Four show another WM Suite plugin's output through its shortcode; `block-notice.php` keeps an incomplete block empty for visitors and tells editors what is missing ([docs/BLOCKS.md](docs/BLOCKS.md)). |
| `src/Gutenberg/Telemetry_Bridge.php` | WordPress 6.5+ Block Bindings source `wm-suite-hub/telemetry` and GenerateBlocks 2.x Dynamic Tag `wm_hub_telemetry`. |
| `src/Marketplace/` | `DAG/DAG_Resolver.php` (Kahn), `Auditor/Preflight_Auditor.php` (reads the server, writes nothing), `Crypto/WM_Ed25519_Verifier.php`, `Installer/Atomic_Installer.php` (tested, but not called: installing a pack is not wired, see section 5). |
| `src/Compliance/` | `SBOM_Generator.php` (CycloneDX 1.6), `Developer_SDK_Bridge.php` (third-party providers, PII sanitisation, blocked-embed placeholder). |
| `src/CLI/` | `WM_Hub_CLI.php` (`wp wm-hub`), `WM_SDK_CLI.php` (`wp wm-sdk`). |
| `src/Setup/Activator.php` | Creates `{prefix}wm_suite_audit_log`, grants `manage_wm_suite`, registers the role `wm_compliance_officer`, seeds default options. |
| `packages/compliance-sdk/` | Standalone facade (`Compliance::audit()`, `WM_Compliance_Bridge::log_event()`). |
| `packages/plugin-updater/class-wm-plugin-updater.php` | Canonical source of the fleet update client (section 7). Not loaded by the hub and kept out of its package by `.distignore`; the direct-distribution build of each plugin receives a copy. |
| `assets/css/wm-admin-tokens.css`, `assets/css/wm-admin-ui.css` | The canonical family sheets every WM plugin bundles. |
| `bom.json`, `.wordpress-org/blueprints/blueprint.json` | CycloneDX 1.6 Software Bill of Materials and WordPress Playground blueprint. |

There is no `/includes/` tree beyond `includes/helpers.php`, and no `/src/Blocks/` or `/src/REST/`.

---

## 2. Storage & Uninstallation (`D-UNINSTALL`)

`{$wpdb->prefix}wm_suite_audit_log`: `id`, `timestamp`, `module_slug` (64), `event_type` (64), `user_id`, `ip_address` (45), `payload` (longtext, JSON), `previous_hash` (64), `current_hash` (64); keys on module, event, timestamp and hash. It is the only table the plugin creates.

Options:
- `wm_suite_theme`: active cockpit theme (`cyber-events` default, `wp-native`, `obsidian-dark`, `high-contrast`), stored with `autoload = false`.
- `wm_suite_hub_delete_data_on_uninstall`: boolean (`false` by default). When `false`, `uninstall.php` exits immediately so audit logs, roles, capabilities, and options survive plugin updates or reinstallation. When explicitly set to `true` via **WM Suite > Suite Hub > Lifecycle** or `POST /wp-json/wm-hub/v1/settings/lifecycle`, `uninstall.php` removes `manage_wm_suite`, `wm_compliance_officer`, all four options, transients, and `{prefix}wm_suite_audit_log`.
- `wm_suite_hub_version` and `wm_suite_hub_activated_at`: written once at first activation.

Transients: `wm_suite_hub_health_matrix` and `wm_suite_hub_telemetry_cache`. Capability `manage_wm_suite`; role `wm_compliance_officer`.

---

## 3. Audit chain

- **Seal:** `HMAC-SHA256( timestamp|module_slug|event_type|user_id|ip_address|payload_json|previous_hash, wp_salt( 'auth' ) )`. The first row chains onto 64 zeros.
- **`append_event( $module_slug, $event_type, $payload, $user_id = 0, $ip_address = '' )`** returns the sealed hash, or `''` when the insert threw; it also fires `do_action( 'wm_activity_sentinel_log_event', ... )` for the activity spoke.
- **`verify_chain_integrity()`** returns `{intact, total_checked, broken_at_id, details}` and names the first row whose predecessor or signature does not recompute. It reads the table in keyset pages of 500 (`WHERE id > last ORDER BY id LIMIT 500`), so memory holds one page however long the table grows; until 1.2.3 it loaded every row, on every dashboard visit. What it proves: the stored rows were not changed, removed or reordered without the site key. What it does not prove: that an event happened. It is a linear chain, not a Merkle tree.
- **Known limitation:** the salt is `wp_salt( 'auth' )`, so anyone who can read `wp-config.php` can rewrite the chain consistently, and rotating the salts makes every existing row verify as broken — which is why a broken signature is reported as "changed, or written under another `AUTH_SALT`", never as tampering.

---

## 4. Hooks & Dynamic Bindings

| Type | Name | Meaning |
| --- | --- | --- |
| filter | `wm_register_suite_module` | A spoke appends its adapter object; the registry keeps what implements the contract. |
| filter | `wm_suite_hub_registered_modules` | Alias of `wm_register_suite_module`: applied with an empty array of its own, and the registry merges both lists before validating them. |
| filter | `wm_suite_active_theme` | Overrides the cockpit theme (`wm_get_suite_theme()`). |
| filter | `wm_suite_signing_public_key` | The Ed25519 public key the package verifier uses. None ships (D9), the verifier refuses to run without one, and no download calls it yet. |
| filter | `wm_suite_check_sla_violations` | The SLA monitor's findings. |
| filter | `wm_suite_hub_telemetry_fields` | Filters the aggregate telemetry map (`health_score`, `global_status`, `active_spokes`, `version`) exposed to Block Bindings and GenerateBlocks. |
| filter | `wm_compliance_register_provider` | Third-party compliance providers for the SDK bridge. |
| filter | `wm_compliance_has_consent` | Checks `wm_consent_has()` / `wm_consent_has` filter (`wm-sovereign-consent-vault`) first, then the `borlabs-cookie` cookie: the category is a Borlabs service group key (`external_media` also matches `external-media`) or a single service listed in an accepted group. Until 1.2.3 it looked for the category among the service lists and answered `false` for every category. |
| filter | `wm_compliance_sanitize_prompt_pii` | Returns the text with IBAN, VAT id, e-mail, phone and Luhn-valid card numbers replaced. |
| filter | `wm_compliance_render_blocked_placeholder` | The click-to-load placeholder markup for a blocked embed. |
| filter | `wm_cra_sbom_register_component` | Components the SBOM generator adds. |
| action | `wm_suite_hub_discovered` | After discovery, with the `Module_Registry`. |
| action | `wm_suite_hub_telemetry_bridged` | Fired after `Telemetry_Bridge::init()` registers the Block Bindings source and GenerateBlocks Dynamic Tag. |
| action | `wm_compliance_record_audit_event` | Records an event (payload object or array) in the chain. |
| action | `wm_suite_hub_dispatch_audit_event` | The same with unpacked arguments (`$module, $event, $category, $severity, $actor, $resource, $context`). |
| action | `wm_activity_sentinel_log_event` | Fired by the chainer; consumed by the activity spoke when it is active. |

Dynamic editor integrations registered by `src/Gutenberg/Telemetry_Bridge.php`:
- **WordPress 6.5+ Block Bindings source:** `wm-suite-hub/telemetry` (`args.key` in `health_score`, `global_status`, `active_spokes`, `version`).
- **GenerateBlocks 2.x Dynamic Tag:** `{{wm_hub_telemetry field:health_score}}` (`GenerateBlocks_Register_Dynamic_Tag` + `generateblocks_dynamic_tag_output` fallback filter).

A spoke registers like this:

```php
add_filter( 'wm_register_suite_module', static function ( array $modules ): array {
    $modules[] = new My_Spoke_Adapter(); // implements WM_Plugin_Module_Interface
    return $modules;
} );
```

---

## 5. REST

| Method | Route | Capability | Behaviour |
| --- | --- | --- | --- |
| `GET` | `/wp-json/wm-hub/v1/fleet/status` | `manage_wm_suite` | Health matrix of the registered spokes |
| `GET` | `/wp-json/wm-hub/v1/fleet/metrics` | `manage_wm_suite` | Telemetry per module |
| `GET` | `/wp-json/wm-hub/v1/audit/logs` | `manage_wm_suite` | Recent rows of the audit table |
| `GET` | `/wp-json/wm-hub/v1/audit/verify` | `manage_wm_suite` | `verify_chain_integrity()` |
| `POST` | `/wp-json/wm-hub/v1/settings/theme` | `manage_wm_suite` | Stores the cockpit theme (`scope=global` or `scope=user`) |
| `GET` | `/wp-json/wm-hub/v1/settings/lifecycle` | `manage_wm_suite` | Reads `delete_data_on_uninstall` boolean state |
| `POST` | `/wp-json/wm-hub/v1/settings/lifecycle` | `manage_wm_suite` | Updates `wm_suite_hub_delete_data_on_uninstall` and appends `lifecycle_setting_updated` to the audit chain |
| `POST` | `/wp-json/wm-hub/v1/module/<slug>/action` | `manage_wm_suite` | Dispatches an action to one registered spoke and seals `admin_action_executed` |
| `GET` | `/wp-json/wm-hub/v1/marketplace/bundles` | `manage_wm_suite` | Bundles with their resolved DAG order |
| `POST` | `/wp-json/wm-hub/v1/marketplace/preflight` | `manage_wm_suite` | Environment audit for one bundle, its resolved order and `has_dependencies` (whether any package requires another) |
| `POST` | `/wp-json/wm-hub/v1/marketplace/install-bundle` | `manage_wm_suite` | `dry_run=true` returns the order; without it, **501** and nothing is written |
| `GET` | `/wp-json/wm-hub/v1/marketplace/telemetry` | `manage_wm_suite` | Deduplication counters of this request; a REST request enqueues no assets, so they read 0 |
| `POST` | `/wp-json/wm-hub/v1/cache/flush` | `manage_wm_suite` | Drops the telemetry transient |
| `POST` | `/wp-json/wm-suite/v1/consent-sync` | REST nonce + 10/min per client | Only with Borlabs Cookie active. Reads the decision from the `borlabs-cookie` cookie (`consents` = `{group: [service, …]}`, plus `uid`); a body field `consents` only when no such cookie arrives. Stores at most 50 groups of at most 50 services (or scalar flags), Borlabs' uid, an anonymised IP and a user agent bounded to 255 characters as one `consent_recorded` entry; `400` and no entry without a readable decision; a failed write answers `500` with a generic message |

---

## 6. WP-CLI

```bash
wp wm-hub status|sbom|crypto-verify|flush-cache|action|bundle|package|deduplicate-telemetry
wp wm-sdk status|scaffold|test-event|sanitize
```

WP-CLI runs with no user; the capability check applies to REST and the admin screens.

`wp wm-hub bundle install <pack>` without `--dry-run` and `wp wm-hub package verify-signatures` exit with an error: installing a pack is not wired, and no package is published with a signature. `deduplicate-telemetry` prints the counters of its own process, which renders no page, so they read 0.

---

## 7. Fleet update client (`packages/plugin-updater/`)

`WM_Plugin_Updater` provides updates for direct-distribution builds using WordPress core's `Update URI:` header and `update_plugins_{$hostname}` filter (`wp-includes/update.php`, `wp_update_plugins()`).

- **One manifest for the fleet.** Core calls the filter once per plugin, so the client reads one `<endpoint>/manifest.json` per check (cached 6 hours; a failed fetch 30 minutes) instead of one request per plugin.
- **Nothing is contacted until an endpoint is set,** and it must be `https`. `status()` reports `not_configured`, `unreachable` (with the HTTP code or transport error), `invalid` (not schema 1 or no plugin list) or `ok` — a source that could not be read never counts as "up to date".
- **The package is checked before WordPress unpacks it** (`upgrader_pre_download`): the client downloads it itself and refuses it with a `WP_Error` when its SHA-256 differs from the manifest. With a public key configured, every package also needs a detached Ed25519 signature over the lowercase hex SHA-256 of the ZIP — the scheme `Marketplace/Crypto/WM_Ed25519_Verifier` signs and verifies. A refused download is deleted.
- **An entry is usable only** with a numeric version, an `https` package URL and a 64-hex SHA-256; a detail URL that is not `https` is dropped.
- **Plugins with a WordPress.org Update URI are left to core.**
- **An inactive plugin still gets its update.** The copy loaded by any active sibling that declared the same host answers for every installed plugin on that host and verifies every package in `upgrader_pre_download`.

| Setting (constant wins over the site option) | Meaning |
| --- | --- |
| `WM_PLUGIN_UPDATE_ENDPOINT` / `wm_plugin_update_endpoint` | https base URL; the manifest is `<base>/manifest.json` |
| `WM_PLUGIN_UPDATE_PUBLIC_KEY` / `wm_plugin_update_public_key` | base64 Ed25519 public key; when set, unsigned packages are refused |
| `WM_PLUGIN_LICENSE_KEY` / `wm_plugin_license_key` | sent with the manifest request as `X-WM-License-Key` |

Manifest, schema 1:

```json
{
  "schema": 1,
  "plugins": {
    "wm-newsticker": {
      "version": "1.5.0",
      "package": "https://.../wm-newsticker-1.5.0.zip",
      "sha256": "...64 hex...",
      "signature": "...base64, optional...",
      "requires": "6.4",
      "requires_php": "8.2",
      "tested": "7.1",
      "url": "https://...",
      "name": "WM Newsticker",
      "changelog": "<p>...</p>"
    }
  }
}
```

**No public update endpoint is operated** (decided on 2026-11-20). Nothing serves a manifest at the `Update URI` host the direct builds carry, and no signing key exists, so a direct build never receives an update offer. Every release is published as a ZIP on GitHub (`https://github.com/arnoldwender/wm-wp-suite-hub/releases`); updating means installing the newer ZIP from there.

**Two builds, because WordPress.org forbids updaters.** Source repositories stay WordPress.org-clean: no `Update URI` header, no updater copy, no loader. The direct-distribution release builder injects all three when packaging `--channel direct` (`Update URI` header, `includes/wm-updater/class-wm-plugin-updater.php`, and `WM_Plugin_Updater::register( __FILE__ )`), while `--channel wporg` packages the repository minus `.distignore` and refuses to package any file carrying updater markers.

```bash
# WordPress.org package: repo minus .distignore; refused if it carries an Update URI or updater code
wp-plugin-fleet-release.py wm-suite-hub --channel wporg --ref v1.2.6 --out dist
# direct package: header, client and loader injected; manifest entry written; signed when a key is given
WM_SIGN_KEY=... wp-plugin-fleet-release.py wm-suite-hub --channel direct --ref v1.2.6 --out dist \
  --base-url https://<update host>/<path> --sign-key-env WM_SIGN_KEY
```

---

## 8. Tests

```bash
php tests/test-suite.php        # registry and error boundary, health matrix (incl. an empty registry), audit chain and tamper detection, SBOM, DAG and cycles, pre-flight (writes nothing), Ed25519 sign/verify/tamper and no default key, atomic install and rollback, solution-pack REST (incl. the 501), lifecycle & opt-in uninstall, Borlabs bridge (decision from the cookie, nothing without one, no dead Borlabs hooks, nonce, rate limit, payload shape), consent filter against the Borlabs 3.4 cookie, paged chain verification, status labels, 16 compiled blocks (SSR, visitor vs editor notices, delegation to the spoke shortcodes, editor controls vs attributes, asset versions), dashboard widgets, cockpit ids, runtime card, dialog styles and translated script strings, the family header clearing the help-tab float, CLI failures, a scan for the claims removed in 1.2.2 and 1.2.3, telemetry bridges, SDK bridge, CLI subcommands, and the update client's own suite in a separate process
php tests/test-plugin-updater.php  # the update client alone: registration, no endpoint, dead and invalid sources, entry validation, update offer, details dialog, SHA-256 and Ed25519 checks, upgrader_pre_download, inactive siblings
php tests/test-pot-freshness.php # every translatable string in PHP/JS has an entry in languages/wm-suite-hub.pot
bash tests/run-mutations.sh     # each entry of tests/mutations.php must turn the suite red when it is undone
```

---

## 9. Security disclosure

See [SECURITY.md](SECURITY.md).
