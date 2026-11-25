=== WM Suite Hub ===
Contributors: arnoldwender
Tags: compliance, audit log, governance, cra, dora
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.2.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Central cockpit for the WM Suite plugin family: spoke registry, HMAC-SHA256 audit chain, health matrix, 16 Gutenberg blocks, and Borlabs Cookie bridge.

== Description ==

WM Suite Hub is the hub of a hub-and-spoke plugin family. Every WM Suite plugin registers itself as a spoke through the `WM_Plugin_Module_Interface` contract; the hub lists them, collects their health and telemetry, seals audit events into an append-only HMAC-SHA256 chain, and consolidates their admin menus.

* **Cockpit:** WM Suite > Suite Hub, with two dashboard widgets summarising the registered modules, the health each one reports (as a translated word) and the state of the audit chain, and a runtime card with the PHP and WordPress versions, HTTPS and the object cache as the health matrix read them.
* **Audit chain:** `wp_wm_suite_audit_log`, one entry per event, signed with HMAC-SHA256 together with the previous entry (a linear chain); verification reads the table in pages of 500 and reports the first entry that does not check out — changed, or written under another `AUTH_SALT`.
* **Solution packs:** dependency graphs resolved with Kahn's topological sort; `dry_run=true` returns the install order. Installing packs is not available in this version (the REST route answers 501 and `wp wm-hub bundle install` exits with an error).
* **Borlabs Cookie bridge:** when a visitor saves a decision in Borlabs Cookie, `wm-suite/v1/consent-sync` (nonce and rate limit) reads it from the `borlabs-cookie` cookie — accepted service groups, their services and Borlabs' consent id — and writes one audit entry; nothing without a readable decision. It does not configure Borlabs. Measured against Borlabs Cookie 3.4.
* **Gutenberg & dynamic bindings:** 16 server-rendered blocks under the `wm-suite` category with compiled Block Inserter controls (`wm-suite-hub-blocks-editor`), plus WordPress 6.5+ Block Bindings (`wm-suite-hub/telemetry`) and GenerateBlocks 2.x Dynamic Tag (`wm_hub_telemetry`). Four blocks show the output of the WM Suite plugin they belong to; a block whose plugin or required setting is missing renders nothing for visitors. No block checks what it shows. There is no shortcode.
* **Opt-in uninstallation:** `uninstall.php` preserves the audit log table, roles, capabilities, and options by default until `wm_suite_hub_delete_data_on_uninstall` is enabled in the cockpit Lifecycle panel.
* **WP-CLI:** `wp wm-hub` (status, sbom, crypto-verify, flush-cache, action, bundle, package, deduplicate-telemetry) and `wp wm-sdk` (status, scaffold, test-event, sanitize).
* **Localization:** 25 compiled `.po`, `.mo`, and Gutenberg JED `.json` translation catalogs in `languages/`.

Zero-CDN: every script, style and font ships inside the plugin.

**No Support & Zero Liability ("AS IS"):** This plugin is provided free of charge as personal open-source software with **zero support** and **no private support**. Do not contact the author via email, `arnoldwender.com`, or direct messages. Report reproducible defects exclusively at `https://github.com/arnoldwender/wm-wp-suite-hub/issues` (no SLA or response guarantee). Under GPLv2+ Sections 11-12 it is distributed **"AS IS"** without warranty, legal advice, or author liability.

== Installation ==

1. Upload the `wm-suite-hub` folder to `/wp-content/plugins/` and activate it.
2. Open WM Suite > Suite Hub. Spokes that are active on the site appear automatically.
3. Optional: activate Borlabs Cookie to enable the consent bridge.

== Frequently Asked Questions ==

= Is technical or private support provided? =

No. This plugin comes with zero support (`0` support). Private inquiries sent via email, `arnoldwender.com`, or social/chat channels are not answered. Reproducible bugs belong solely on GitHub Issues (`https://github.com/arnoldwender/wm-wp-suite-hub/issues`) with no guarantee of reply or resolution. The software is provided **"AS IS"** at your sole risk with zero warranty or liability (see `SUPPORT.md` in the repository).

== Screenshots ==

1. WM Suite cockpit: health of the registered plugins, runtime environment and theme switcher
2. Audit log: entries of the registered plugins, each chained to the previous one with HMAC-SHA256
3. Gutenberg Block Editor displaying one of the 16 server-rendered Hub blocks (wm/privacy-center)
4. WordPress dashboard widget: registered modules with the health each reports

== Changelog ==

= 1.2.6 =
* Fixed: in the block editor the WM Suite blocks appeared without their styles, because the block stylesheet reached only the page around the editor canvas. It now loads inside the canvas too; the front end does not change.

= 1.2.5 =
* Fixed: on admin screens with WordPress's help tab the WM Suite header was shorter than the page, by the width of the "Hilfe" toggle. The family stylesheet now starts the header below the toggle, so it spans the page; screens without a help tab do not change.

= 1.2.4 =
* Fixed: on the own sub-pages of a registered WM Suite plugin (for example Activity Sentinel's integrity audit, export and settings) the admin menu closed WM Suite and marked nothing. They now keep WM Suite open with the plugin's entry marked, and every page still loads under its own hook.

= 1.2.3 =
* The cockpit shows what it measured. The "SOUVERÄNITÄT & SECURITY" card, three ticks that nothing computed, is now the runtime card: PHP and WordPress versions, HTTPS and the object cache as the health matrix read them; only a missing HTTPS carries a warning. The status badge shows the matrix status instead of a fixed "System Nominal · Zero-CDN", and every status (badge, modules, both dashboard widgets) appears as a translated word instead of HEALTHY or DEGRADED; an unknown status reads "Unbekannt".
* "Merkle" is gone from screens, menu, messages and documents: the audit table is a linear chain in which each entry is signed with HMAC-SHA256 together with the previous one, not a Merkle tree.
* Borlabs Cookie bridge: it records the decision the visitor saved. Borlabs Cookie 3.4 fires its event with an empty detail, so every decision used to be written as an entry without consents; the route now reads the groups and services from the `borlabs-cookie` cookie, adds Borlabs' consent id and writes nothing when no decision can be read. Removed: 18 "service packages" with legal-basis texts and two content-blocker texts, hooked to filters Borlabs 3.4 does not have (no visitor ever saw them), and the `text/plain` rewrite of two script handles no plugin registers. A failed write no longer returns its exception text to the visitor.
* Fixed: `wm_compliance_has_consent` answered "no consent" for every category whenever Borlabs Cookie was in use, even after the visitor accepted. It reads the cookie the way Borlabs writes it (service group or single service; `external_media` matches `external-media`).
* Fixed: the audit check loaded the whole table on every dashboard visit; it reads pages of 500. A broken signature no longer reads "Manipulation erkannt": the message names both causes, a changed entry or a changed `AUTH_SALT`.
* Fixed in the cockpit: the pre-flight dialog opened as an unstyled block at the end of the page; Escape anywhere moved focus away; the busy icon never turned; the Solution Packs and Audit-Log menu entries opened at the top of the page instead of their section; the verify button's accessible name differed from its text; the status pill was green whatever word it carried (it is red for a critical status, amber for a warning or an unknown one); the dashboard widget's module dots were colour only and showed "1.0.0" for modules that report no version, and it showed an empty audit table as "intakt" on green (it now says the table is empty) and a broken chain without a colour (now amber). "CRA & SBOM Hub", a submenu that opened the same cockpit, is gone. Every text of the cockpit script is translatable.
* The plugin description, `bom.json` and the compliance SDK describe what the plugin does.

= 1.2.2 =
* The 16 blocks print what they have. The HinSchG portal, the erasure form, the accessibility statement and the privacy center show the output of the WM Suite plugin they belong to instead of forms and conformity statements of their own, and 13 blocks render nothing for visitors while their plugin or a required setting is missing; editors see what is missing. Removed attributes: `marketAuthority`, `heading` (portal, erasure form), `eventId`, `showTaxDisclaimer`, `categoryFilter`. New: `bookingUrl`, `taxNote`, `channelUrl`.
* "Marketplace" is called Solution Packs: its install buttons, the statute and feature texts of the packages and the "BFSG 2025 AAA" pills are gone; the page shows the resolved order and whether any package requires another.
* Removed statements the hub cannot back: the dashboard's statute matrix ("6/6 Bestanden"), "Aktiv & Konform", "BFSG 2025 / WCAG 2.2 AAA", "Barrierefrei AAA", "TTFB < 80ms", the placeholder signing key and an SBOM tool name belonging to another plugin.
* Fixed: `wp wm-hub bundle install` without `--dry-run` and `wp wm-hub package verify-signatures` reported success without installing or reading anything; both exit with an error now. An empty registry no longer reads 100 and HEALTHY. The pre-flight check no longer creates `wp-content/wm-backups`. The grid's column setting, the ticker under reduced motion, blocked `localStorage` and the external-content placeholder work as documented in `docs/BLOCKS.md`.
* Translations: the new strings are translated in all 25 catalogs, and 16 translations in 13 catalogs that still named the agency name WM Suite now.

= 1.2.1 =
* The WM Suite admin menu, the cockpit, the marketplace and both dashboard widgets draw their icons as inline SVG instead of opening their labels with emoji, which screen readers read out before each entry. Nine translatable strings lost their emoji prefix; their translations in all 25 catalogs were kept.

= 1.2.0 =
* Renamed the vendor namespace to `ArnoldWender\` (`ArnoldWender\SuiteHub\…`, the compliance SDK `ArnoldWender\ComplianceSDK\…`), matching the Composer vendor `arnoldwender/`. The spoke contract is now `ArnoldWender\SuiteHub\Contracts\WM_Plugin_Module_Interface`: WM Suite plugins built against the previous namespace no longer register with the hub until they are updated too.
* The `.wordpress-org/` banners show the current version.
* Fixed: The hub removes the top-level admin menu of a WM Suite plugin only when it lists that plugin under WM Suite. It used to remove the menus of 18 plugins whether they had registered or not, so a plugin that did not register (one not yet updated to the new contract, for example) lost its only menu entry.
* Removed: The archived pre-2026-11-19 marketing visuals in `versionen/`: they claimed features and conformity the plugin does not have. They were never part of the installable package.
* Removed: "BSI IT-Grundschutz" from the cockpit status badge, from the Borlabs Cookie service description of Activity Sentinel (which now says tamper-evident, not tamper-proof) and from the solution-pack manifest: no WM Suite plugin has been assessed against IT-Grundschutz.

= 1.1.5 =
* Removed the agency name, links to unrelated projects and the personal mailbox from code, catalogs and documentation; the admin family badge and the hub menu read "WM Suite". The READMEs say the plugin was built with an agentic workflow (AI coding agents) and should be reviewed like any third-party code.
* Added root `SUPPORT.md`, GitHub Issue templates, explicit zero-support & "AS IS" liability notices in READMEs and `readme.txt`, and normalized canonical GPLv2+ `arnoldwender.com` / `arnoldwender/wm-wp-suite-hub` links.
* Updated `.wordpress-org/` visual banners to personal `ARNOLD WENDER · OPEN SOURCE` branding.

= 1.1.4 =
* Add `.wordpress-org/` vector icons, banners (`772x250`, `1544x500`), live WordPress screenshots (`screenshot-1..4.png`), and `== Screenshots ==` descriptions.
* Update trilingual READMEs, `docs/translations/README.*.md`, and `llms.txt` with live visual previews, synchronized `1.1.4` references, and direct catalog link (`https://arnoldwender.com/wordpress-plugins/wm-suite-hub/`).
* Archive superseded decorative mockups into `versionen/visuals-pre-2026-11-19/` and exclude `versionen/` via `.distignore`.

= 1.1.3 =
* Point Plugin URI to the official plugin page.

= 1.1.2 =
* Fixed: Unified `if ( ! defined( 'ABSPATH' ) ) { exit; }` direct-access guards across all files in `packages/compliance-sdk/src/` so WordPress.org Plugin Check 2.1.0 passes with zero errors.

= 1.1.1 =
* Removed misleading static UWG/ESG/price/seat sample defaults from `wm/lksg-esg-scorecard`, `wm/event-card`, and `wm/event-grid`, rendering neutral unconfigured or empty-state notices unless real attributes or active `CyberEvents_Database` events are present.
* Added Multisite `wp_initialize_site` provisioning when network-activated and set `autoload = false` on non-frontend option `wm_suite_theme`.

= 1.1.0 =
* Compiled all 16 `wm/*` Gutenberg blocks (`build/index.js` + `build/index.asset.php`) with `InspectorControls` and `ServerSideRender` live previews so all 16 blocks appear in the Block Inserter.
* Added WordPress 6.5+ Block Bindings source `wm-suite-hub/telemetry` and GenerateBlocks 2.x Dynamic Tag `wm_hub_telemetry` (`Telemetry_Bridge`).
* Implemented opt-in uninstallation (`wm_suite_hub_delete_data_on_uninstall`, default `false`) with cockpit Lifecycle toggle and `GET`/`POST /wm-hub/v1/settings/lifecycle`.
* Registered hyphenated WP-CLI `@subcommand` aliases (`crypto-verify`, `flush-cache`, `deduplicate-telemetry`, `test-event`) via `WM_Hub_CLI::register()` and `WM_SDK_CLI::register()`.
* Shipped 25 complete `.po`, `.mo`, and JED `.json` translation catalogs (`362` entries each), CycloneDX 1.6 `bom.json`, and WordPress Playground `blueprint.json`.
* Hardened output escaping, direct-execution guards, CSS design token usage, and `WP_DEBUG` logging guards across all PHP and CSS assets.
* Documentation rewritten from the code: the `wm_suite_cockpit` shortcode and the `wp suite` command never shipped and do not exist, there is no GoBD PDF/CSV export, and there is one audit table, not three.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.2.6 =
Editor fix only: the WM Suite blocks are styled in the block editor too. No data, settings or front-end change.

= 1.2.5 =
Stylesheet fix only: the WM Suite header spans the page on screens with a help tab. No data or settings change.

= 1.2.4 =
Admin menu fix only: the sub-pages of WM Suite plugins keep the WM Suite menu open. No data or settings change.

= 1.2.3 =
With Borlabs Cookie, consent entries in the audit table now carry the visitor's decision; entries written before 1.2.3 have empty consents. The "CRA & SBOM Hub" submenu is gone; the cockpit shows everything it opened.

= 1.2.2 =
Blocks no longer print statements of their own: four show the output of their WM Suite plugin, and a block whose plugin or required setting is missing renders nothing for visitors. Check the pages that use them.

= 1.2.1 =
The WM Suite menu and the hub screens show SVG icons instead of emoji. No data or settings change.

= 1.2.0 =
Update the WM Suite plugins together with the hub: the hub contract moved to the ArnoldWender\SuiteHub\Contracts namespace. A WM Suite plugin that is not updated keeps working and keeps its own admin menu, but is not listed in the cockpit.
