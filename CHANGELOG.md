# Changelog — WM Suite Hub

All notable changes to **WM Suite Hub** are documented in this file in adherence to [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Added
- Added measured fleet architecture map `docs/ARCHITECTURE-2026-11-25.md` with Hub-and-Spoke wiring and Open DX API contract.

### Fixed
- Switched `Telemetry_Bridge` GenerateBlocks 2.x Dynamic Tag filtering from `generateblocks_dynamic_tag_output` to `generateblocks_dynamic_tag_replacement` with `mixed` safety.
- Renamed `API_Controller::NAMESPACE` to `REST_NAMESPACE` for PHP 8.5/8.6 compatibility.
- Fixed `Admin_Menu` spoke dispatcher constructors (`wm-dora-ict-register`, `wm-peppol-as4-gateway`), deduplicated `wm-dsgvo-newsletter-suite` (`wm-newsletter-suite`) root slug, and integrated `wm-edu-privacy-portal`, `wm-k9-bh-exam-planner`, and `wm-social-care-accessibility-hub` into `CATEGORIES` and `ROOT_MENU_SLUGS`.
- Fixed `Activator` schema SQL (removed `CREATE TABLE IF NOT EXISTS` incompatible with `dbDelta`) and added `Activator::maybe_upgrade()` hooked on `plugins_loaded` (`wm_suite_hub_db_version` stored with `autoload = false`, removed on opt-in uninstall).

## [1.2.6] - 2026-11-21

### Fixed

- In the block editor the 16 blocks rendered without their stylesheet: `wm-blocks-core.css` hung on `enqueue_block_editor_assets`, which reaches only the admin page around the iframed editor canvas, not the canvas itself (measured in a browser on WordPress 7.1: the sheet absent from the canvas, the consent badge drawn as a plain link with a transparent background). It now loads through `enqueue_block_assets`, which also reaches the canvas; on the front end that hook adds nothing, and the sheet still loads only on pages that contain a block.

## [1.2.5] - 2026-11-21

### Fixed

- On admin screens with WordPress's help tab («Hilfe», «Ansicht anpassen») the family header was shorter than the page: WordPress floats those toggles to the right above the page, and the header, a flex box, may not overlap a float, so it lost the toggle's width (1155 px of 1238 px) while the boxes below kept the full width. The canonical family sheet `assets/css/wm-admin-ui.css` clears the float, so the header starts below the toggle and spans the page; on screens without a help tab nothing moves. Measured in a browser on 21 screens of four WM Suite plugins (Activity Sentinel, Prototype NDA Gate, Newsticker, Cyber Events Core) on WordPress 7.1. Every WM Suite plugin bundles the same sheet and ships it in its next release.

### Changed

- `languages/wm-suite-hub.pot` names the current version in `Project-Id-Version`; it still read 1.2.3.

## [1.2.4] - 2026-11-21

### Fixed

- On a registered WM Suite plugin's own sub-pages (Activity Sentinel: integrity audit, file integrity, export, settings, handbook) the admin menu closed WM Suite and marked nothing, so the page did not show where it belonged. The hub removes each registered plugin's top-level menu and lists one entry for it, and those sub-pages kept the removed menu as their parent. They now keep WM Suite open with the plugin's entry marked. Returning WM Suite from the `parent_file` filter alone would not hold, because WordPress resets the parent right after it (`get_admin_page_parent()` in `menu-header.php`); the removed menu is mapped through `$_wp_real_parent_file` inside that filter, after the page hook was resolved, so every page still loads under its own hook. Measured on WordPress 7.1 with Activity Sentinel's integrity and settings pages.

## [1.2.3] - 2026-11-21

The cockpit shows what it measured, the consent bridge records the decision a visitor saved, and nothing calls the audit table a Merkle tree.

### Changed

- **Runtime card instead of «SOUVERÄNITÄT & SECURITY».** The card ticked three lines green whatever the site did: «Zero-CDN: 100% lokale Assets & Schriften», the PHP version followed by «(Strict Types)», and «Audit-Kette: HMAC-SHA256 Merkle Chaining». It now shows the PHP and WordPress versions, HTTPS and the persistent object cache as `Health_Matrix` read them; only a missing HTTPS carries a verdict (a warning).
- **Status words.** The cockpit badge read «System Nominal · Zero-CDN» whatever the matrix reported; it shows the global status. Every status in the cockpit and both dashboard widgets appears as a translated word (`Health_Matrix::status_label()`: Gesund, Eingeschränkt, Nicht konfiguriert, Fehler, Warnung, Kritisch) instead of `HEALTHY` or `DEGRADED`; an unknown status reads «Unbekannt», never «Gesund».
- **No Merkle.** The tab, heading, menu entry, banner, verification dialog and code comments called the audit table a Merkle tree; it is a linear chain in which each entry is signed with HMAC-SHA256 together with the previous one. «Merkle-Audit-Protokoll», «Forensisches Log» and «Kryptographischen Audit ansehen» read «Audit-Protokoll» and «Audit-Protokoll ansehen».
- **Borlabs Cookie bridge.** Borlabs Cookie 3.4 fires `borlabs-cookie-consent-saved` with an empty detail, so every decision was written as an entry without consents. The route reads the decision from the `borlabs-cookie` cookie — accepted service groups and their services — adds Borlabs' consent uid, keeps a group as a bounded list of service names, and writes nothing (`400`) when no decision can be read. The frontend script only signals the save and survives a browser that blocks site data.
- Every text of the cockpit script comes from `Hub_Orchestrator::cockpit_script_strings()`, so it is translatable; the verification and pre-flight dialogs use classes of the four themes instead of inline colours.
- The plugin description, `bom.json` and the compliance SDK (`composer.json`, `README.md`, a docblock) describe what the plugin does instead of «real-time telemetry», a «cryptographic audit cockpit» and an «immutable Merkle ledger».

### Removed

- From the Borlabs bridge: 18 «service packages» with legal-basis texts and two content-blocker texts, registered on `borlabsCookie/package/register` and `borlabsCookie/contentBlocker/modify` — filters Borlabs Cookie 3.4 does not have, so no visitor ever saw them — and the `script_loader_tag` rewrite of `wm-newsletter-tracker*` and `cybevents-external-map*` to `text/plain`, handles no plugin registers, marked with an attribute Borlabs does not read. The response field `integrity` («HMAC-SHA256-Merkle-Intact») is gone.
- The submenu «CRA & SBOM Hub» (`wm-suite-hub-sbom`), which opened the same cockpit.

### Fixed

- `wm_compliance_has_consent` answered `false` for every category whenever Borlabs Cookie was in use, even after the visitor accepted: it looked for the category among the service lists of the cookie. It reads the group keys the way Borlabs writes them, a single service counts once it is listed, and `external_media` (the SDK's `ConsentCategory::EXTERNAL_MEDIA`) matches Borlabs' `external-media`.
- `verify_chain_integrity()` loaded the whole audit table on every dashboard visit; it reads keyset pages of 500.
- A broken signature read «Manipulation erkannt»; a changed `AUTH_SALT` breaks every signature the same way, so the message names both causes.
- A failed consent write returned its exception text to the anonymous visitor; the reason stays in the debug log.
- Cockpit: the pre-flight dialog carried two classes no sheet styles and opened as a bare block at the end of the page (it uses the cockpit dialog's overlay now, with the same focus trap); an Escape anywhere on the page moved focus to the last element a dialog had opened from; the busy icon class had no rule and never turned; the Solution Packs and Audit-Log menu entries opened at the top of the page instead of their section; the verify button's accessible name differed from its visible text (WCAG 2.5.3); the `.wm-check-warn` icon had no rule.
- Cockpit: the status pill was green whatever word it carried («Kritisch» on green); it is red for a critical status and amber for a warning or an unknown one.
- Dashboard widget: the module dots were colour only (they carry the status as text now), and modules that report no version showed «1.0.0». An empty audit table read «Audit-Kette intakt: 0 Einträge geprüft» on green; it reads «Die Audit-Tabelle ist leer.» without a verdict, and a broken chain, which carried a class no sheet styles, is amber.

## [1.2.2] - 2026-11-21

What the hub shows is what it has: every status, count and statement on its screens and blocks is read from the registry, the health matrix or another plugin, or it is gone.

### Changed

- **The 16 blocks print what they have** ([docs/BLOCKS.md](docs/BLOCKS.md) lists what each one printed until 1.2.1). Four show the output of the WM Suite plugin they belong to through its shortcode instead of forms and statements of their own: the HinSchG portal (`[wm_hinschg_portal]`), the erasure form (`[wm_dsgvo_erasure_portal]`), the accessibility statement (`[wm_bfsg_statement]`) and the consent button (`[wm_consent_settings]`); `wm/consent-badge` opens the consent vault's dialog. 13 blocks render nothing for visitors while their plugin is missing or a required setting is empty, and tell editors what is missing (`src/Gutenberg/block-notice.php`).
- Block attributes removed with what they fed: `marketAuthority` (`wm/bfsg-statement-embed`), `heading` (`wm/whistleblower-wizard`, `wm/privacy-eraser-form`), `eventId` and `showTaxDisclaimer` (`wm/event-booking-cta`), `categoryFilter` (`wm/event-grid`, which filtered on a field the events table does not have). Added: `bookingUrl` and `taxNote` (`wm/event-booking-cta`), `bookingUrl` (`wm/event-card`), `channelUrl` (`wm/whistleblower-card`). A post that saved a removed attribute keeps it in its block comment; the render ignores it.
- "Marketplace" is called **Solution Packs** in the menu, the cockpit and the widgets: nothing can be bought or installed from it. The page shows each pack's packages, the resolved order and whether any package requires another.
- `assets/data/wm-bundles-manifest.json`: `bfsg_compliant` is gone from all five packs, the compliance tags name topics (`CRA`, `HinSchG`, `BFSG`, …) instead of paragraphs, and a package carries slug, name, version constraint and dependencies only. The 12 dependencies the manifest declared between packages are gone, so every pack resolves to its listed order and the dialog says so.
- An empty registry scores 0 and reads `WARNING` (it read 100 and `HEALTHY`); the cockpit no longer falls back to those values, and a telemetry field the hub cannot compute is empty.
- The deduplicator counts the size of the file it actually dropped; it used to add a fixed estimate of 8,500 to 24,500 bytes per rule and derived a "TTFB" saving from it.
- The 59 strings that are new in this version are translated in all 25 catalogs (`.po`, `.mo` and the editor's JSON), and the entries the code no longer uses are purged from them.

### Removed

- From the cockpit: «Enterprise 2026 · BFSG · CRA», «Aggregierte Compliance-Konformität über alle aktiven Satelliten», «A11y: BFSG 2025 / WCAG 2.2 AAA Ready», the «BFSG 2025 / WCAG 2.2 AAA» and «Barrierefrei AAA» badges, «Unveränderliches Merkle-Audit-Protokoll», and «Aktiv & Konform» (now «Gesund»). The high-contrast palette is labelled «High-Contrast» instead of «BFSG 2025 High Contrast (A11Y)».
- From the dashboard: the «Gesetzliche Konformitäts-Matrix», six statutes marked green and «6/6 Bestanden» whatever was installed («BFSG 2025 (WCAG 2.2 AA) 100% Konform», «EU CRA (BSI TR-03183) SBOM v1.6 Aktiv», «DORA Art. 28 … Register Geregelt» and three more), the «/ 18» after the module count, and the «CRA CycloneDX SBOM herunterladen» button. The second widget is called «Module nach Regelwerk» and lists a plugin only when the registry has it; every module dot carries the health the module reports instead of green.
- From Solution Packs: «1-Click Provisionierung … Ed25519-Signaturvalidierung», «TTFB < 80ms», «BFSG 2025 AAA» pills, the statute, role and "synergy" text of every package, and the buttons «1-Click Suite Provisionieren» and «Verbindlich Installieren».
- From WP-CLI: the «BFSG 2025: Ja (AAA)» column of `bundle list` and the TTFB estimate of `deduplicate-telemetry`.
- `WM_Ed25519_Verifier::DEFAULT_MASTER_PUBLIC_KEY`, a 48-byte placeholder labelled as the official root key; the verifier refuses to run until a key is configured.
- The SBOM named «WM Suite SBOM Beacon», another plugin of the fleet, as the tool that wrote it; it names `wm-suite-hub`.

### Fixed

- `wp wm-hub bundle install <pack>` without `--dry-run` called the installer and reported «Suite … erfolgreich bereitgestellt (N Pakete atomar installiert)» although nothing was downloaded; it now exits with an error, like the REST route (501).
- `wp wm-hub package verify-signatures` printed «[INTACT — SIGNED]» for every installed plugin directory without reading a signature; it now exits with an error, because no package is published with one.
- The pre-flight check created `wp-content/wm-backups` on every dry run; it only reads now.
- The Solution Packs dialog reused the id of the cockpit dialog's title, so its `aria-labelledby` named the wrong heading.
- 16 translations in 13 catalogs (bg, cs, da, el, es, fr, ga, hr, it, nl, pt, sk, sv) still named the agency that 1.1.5 removed from the catalogs; they name WM Suite now.
- `wm/event-booking-cta` was offered in the inserter under the keyword «e-rechnung», which it has nothing to do with.
- Blocks: the grid's column setting reaches the stylesheet; with reduced motion the ticker stands still and wraps inside the viewport; a blocked `localStorage` no longer stops every block script; the placeholder inserts a `<template>`, so the embed is not loaded before the click; the whistleblower card links to an anchor that exists; event card and grid show a button only with a page to link to; block assets carry the plugin version and the editor script its content hash instead of the frozen `1.1.1` and `1.1.5`; a page with a block no longer prints a REST nonce.

## [1.2.1] - 2026-11-20

### Changed

- The WM Suite admin menu, the cockpit, the marketplace and both dashboard widgets draw their icons as inline SVG (`wm_suite_hub_icon()`, decorative and hidden from assistive technology) instead of opening their labels with emoji, which screen readers read out as words before each entry. Nine translatable strings lost their emoji prefix; their translations in the 25 catalogs were carried over unchanged. The marketplace close button shows a multiplication sign and the `wp wm-hub` install order joins its steps with a plain arrow.

## [1.2.0] - 2026-11-20

### Changed

- Renamed the vendor namespace to `ArnoldWender\` (`ArnoldWender\SuiteHub\…`, the compliance SDK `ArnoldWender\ComplianceSDK\…`), matching the Composer vendor `arnoldwender/`. The spoke contract is now `ArnoldWender\SuiteHub\Contracts\WM_Plugin_Module_Interface`: WM Suite plugins built against the previous namespace no longer register with the hub until they are updated too.
- The `.wordpress-org/` banners show the current version.

### Fixed

- The hub removes the top-level admin menu of a WM Suite plugin only when it lists that plugin under WM Suite. It used to remove the menus of 18 plugins whether they had registered or not, so a plugin that did not register (one not yet updated to the new contract, for example) lost its only menu entry.

### Removed

- The archived pre-2026-11-19 marketing visuals in `versionen/`: they claimed features and conformity the plugin does not have. They were never part of the installable package.
- "BSI IT-Grundschutz" from the cockpit status badge, from the Borlabs Cookie service description of Activity Sentinel (which now says tamper-evident, not tamper-proof) and from the solution-pack manifest: no WM Suite plugin has been assessed against IT-Grundschutz.

## [1.1.5] - 2026-11-19

- Removed the agency name, links to unrelated projects and the personal mailbox from code, catalogs and documentation; the admin family badge and the hub menu read "WM Suite". The READMEs say the plugin was built with an agentic workflow (AI coding agents) and should be reviewed like any third-party code.

- Added root `SUPPORT.md`, `.github/ISSUE_TEMPLATE/{config.yml,bug_report.yml}`, and explicit zero-support / "AS IS" liability notices across `README.md`, `README.de.md`, `README.es.md`, `readme.txt`, `SECURITY.md`, and `llms.txt`.
- Migrated plugin author/catalog/repository metadata to personal open-source distribution (`Arnold Wender`, `https://arnoldwender.com/wordpress-plugins/wm-suite-hub/`, `https://github.com/arnoldwender/wm-wp-suite-hub`), normalized FSF GPL-2.0 `LICENSE` metadata where applicable, and updated `.wordpress-org/` banner branding to `ARNOLD WENDER · OPEN SOURCE`.

## [Unreleased]

## [1.1.4] - 2026-11-19

### Added

- Official `.wordpress-org/` visual kit (`icon.svg`, `icon-128x128.png`, `icon-256x256.png`, `banner-772x250.png`, `banner-1544x500.png`) and 4 live Playwright WordPress screenshots (`screenshot-1.png` through `screenshot-4.png`) with `== Screenshots ==` in `readme.txt`.
- Direct catalog URLs (`https://arnoldwender.com/wordpress-plugins/wm-suite-hub/` and `/es/plugins-wordpress/wm-suite-hub/`) and live previews across `README.md`, `README.de.md`, `README.es.md`, `docs/translations/README.*.md`, and `llms.txt`.

### Changed

- Archived superseded decorative mockups (`assets/images/suite-hub-banner.jpg` and `assets/images/suite-hub-ui-showcase.jpg`) non-destructively into `versionen/visuals-pre-2026-11-19/` and excluded `versionen/` via `.distignore` (keeping `docs/screenshots/design-family/` intact).
- Removed the obsolete mockup warning in `README.md` and synchronized all version references (`wm-suite-hub.php`, 16 `block.json` manifests, `build/index.asset.php`, `readme.txt`, `README*.md`, `SECURITY.md`, `DEVELOPERS.md`, `llms.txt`, `bom.json`, and `tests/test-suite.php`) at `1.1.4`.

## [1.1.3] - 2026-11-19

### Changed

- Point `Plugin URI` to `https://arnoldwender.com/wordpress-plugins/wm-suite-hub/` and align release metadata at `v1.1.3`.

---

## [1.1.2] — 2026-11-19

### Fixed

- **Plugin Check 2.1.0 Direct File Access Compliance:** Unified direct-execution guards in `packages/compliance-sdk/src/` (`Compliance.php`, `Payloads/AuditPayload.php`, `Enums/ComplianceDomain.php`, `Enums/EventSeverity.php`, `Enums/ConsentCategory.php`) onto canonical `if ( ! defined( 'ABSPATH' ) ) { exit; }`, eliminating all 5 `missing_direct_file_access_protection` errors.

---

## [1.1.1] — 2026-11-18

### Fixed & Security

- **Neutral Block Defaults (`R2-B4`):** Replaced misleading static sample defaults in `wm/lksg-esg-scorecard` (`98%`, `95%`, `100%` -> `-1` with neutral inspector notice and `—` placeholders), `wm/event-card` (`Enterprise Workshop`, `2026-10-15`, `Halle (Saale)`, `12`, `249,00 €` -> empty strings / `-1` with neutral configuration notice), and `wm/event-grid` (hardcoded sample events replaced with `CyberEvents_Database::get_active_events()` lookup when active or an honest empty-state notice). Synced `blocks-src/index.js` and `build/index.js` `RangeControl` bounds/fallbacks to `-1`.
- **Multisite Sub-Site Provisioning & Autoload Hygiene (`R2-B8`):** Hooked `wp_initialize_site` in `wm-suite-hub.php` to provision new Multisite sub-sites via `switch_to_blog` -> `Activator::activate()` -> `restore_current_blog()` when network-activated (with network activation iteration in `Activator::activate()`), and switched internal non-frontend option `wm_suite_theme` in `API_Controller::update_theme_setting()` to `autoload = false`.

---

## [1.1.0] — 2026-11-18

### Added

- **Compiled Gutenberg Block Editor Bundle (`16 / 0` visible in Block Inserter):** Added `blocks-src/index.js`, `build/index.js`, and `build/index.asset.php`, registered script handle `wm-suite-hub-blocks-editor` with `wp_set_script_translations()`, and linked `"editorScript": "wm-suite-hub-blocks-editor"` across all 16 `src/Gutenberg/Blocks/*/block.json` manifests with `InspectorControls` matching every declared attribute and live `ServerSideRender` previews.
- **WordPress 6.5+ Block Bindings & GenerateBlocks 2.x Telemetry Bridges:** Added `src/Gutenberg/Telemetry_Bridge.php` registering Block Bindings source `wm-suite-hub/telemetry` and GenerateBlocks 2.x Dynamic Tag `wm_hub_telemetry` (`health_score`, `global_status`, `active_spokes`, `version`), plus `wm_suite_hub_telemetry_fields` filter and `wm_suite_hub_telemetry_bridged` action.
- **Opt-In Uninstallation Lifecycle (`D-UNINSTALL`):** `uninstall.php` now preserves `{prefix}wm_suite_audit_log`, roles, capabilities, and options by default (`wm_suite_hub_delete_data_on_uninstall = false`), purging data only when explicitly opted in via **WM Suite > Suite Hub > Lifecycle** or `POST /wp-json/wm-hub/v1/settings/lifecycle`.
- **Complete 25-Locale Translation Catalogs (`D-LOCALES`):** Regenerated `languages/wm-suite-hub.pot` (`362` keys) and compiled 25 `.po`, 25 `.mo`, and 25 Gutenberg JED `.json` catalogs covering all 24 official EU languages plus `en_US`.
- **CycloneDX 1.6 SBOM & WordPress Playground Blueprint:** Added root `bom.json` with verified SHA-256 entry integrity hash and `.wordpress-org/blueprints/blueprint.json`.

### Changed

- **WP-CLI Registration & Hyphenated Subcommands:** Added `WM_Hub_CLI::register()` and `WM_SDK_CLI::register()` with explicit `@subcommand` annotations (`crypto-verify`, `flush-cache`, `deduplicate-telemetry`, `test-event`) and synchronized all documentation and suite tests.
- **Canonical 24-Plugin Fleet Architecture Map:** Refreshed `docs/ARCHITECTURE-2026-11-16.md` with live measured fleet totals (`41 / 0` visible/invisible Gutenberg blocks, `24/24` custom hooks, Block Bindings & GenerateBlocks bridges, `0` unconditionally public REST routes, `24/24` opt-in `D-UNINSTALL`, `24/24` `D-LOCALES`).

### Fixed & Security

- **Strict Typing & Direct-Execution Guards:** Enforced `declare(strict_types=1);` across every PHP file in the repository and added `ABSPATH` / CLI / standalone guards in `src/Setup/Activator.php` and `packages/compliance-sdk/src/`.
- **Output Escaping:** Escaped inner block content with `wp_kses_post()` in `event-grid/render.php` and `modular-type-container/render.php`, and escaped dynamic attribute expressions with `esc_attr()` in `src/Admin/Views/marketplace-view.php`.
- **CSS Design Token Hygiene & Debug Guards:** Replaced hardcoded hex values in `assets/css/wm-obsidian-glass.css` and `assets/css/wm-blocks-core.css` with `:root` custom properties while keeping canonical `wm-admin-tokens.css` and `wm-admin-ui.css` byte-identical, and gated all 5 `error_log()` calls behind `defined( 'WP_DEBUG' ) && WP_DEBUG`.

---

### Historical Pre-1.1.0 Audit Notes (2026-09-14 — 2026-09-20)

#### Fixed — the badge and the heading said the hub was translated (2026-09-20)

- **`languages/` carried 28 catalogs and 0 of their 7,975 entries held a translation.** Every filled entry repeated the English source string word for word, which `msgfmt` counts as translated and a reader does not. Meanwhile the badge row said **`Translations — 24 EU Languages` in green** and the section it linked to was headed **"Complete multilingual documentation (24 EU official languages)"**. Two claims of a finished translation over a catalog with nothing in it.
- The badge was changed to `Translated READMEs — 24 (headings only)` in grey, the label three other plugins of the fleet already used, and the heading became the fleet's ordinary "Translated documentation". (Superseded in `1.1.0` once all 25 `.po`/`.mo`/JED `.json` UI catalogs were translated.)
- A badge is read before the prose, so this is not cosmetic: the paragraph underneath can be perfectly honest and the reader still leaves with the green word. The fleet carries a check for this, which fires when a badge claims the *plugin* is translated, wears a success colour, and the catalogs hold under half real translations.

#### Translation catalog (2026-09-20)

- **100 of the 314 translatable strings had no entry in the `.pot`.** A string the code wraps in `__()` but that the catalog does not carry cannot be translated by anyone, to any language, ever, and nothing reports it. The template is generated from the code with `wp i18n make-pot`, every `.po` merged with `msgmerge --no-fuzzy-matching` and every `.mo` rebuilt.
- **The regeneration cost nothing.** Measured across the 26 catalogs before touching them: **0 entries translated anything** — every non-empty `msgstr` repeated its source string. Verified independently of the merge, against the strings the code extracts.
- `tests/test-pot-freshness.php` and a CI step keep the catalog matching the code. This is the hub the spokes copy from, and a defect here has propagated to eleven plugins once already.

#### An update client for the whole fleet (2026-09-17)

- **None of the 24 WM Suite plugins could receive an update** (measured: 0 of 24 carried update or licence code). `packages/plugin-updater/class-wm-plugin-updater.php` is the canonical client the direct-distribution build of every plugin carries. WordPress.org does not allow updaters — Plugin Check reported `plugin_updater_detected` on this repository the moment the package was added — so it stays out of the hub's own package (`.distignore`, Plugin Check back to 0 errors) and out of every WordPress.org build. It uses the mechanism WordPress core has provided since 5.8 for plugins outside WordPress.org — the `Update URI` header and the `update_plugins_{$hostname}` filter, read in `wp_update_plugins()` of WordPress 7.1 — and reads one manifest for the fleet instead of one request per plugin.
- **It refuses rather than assumes.** No request without an `https` endpoint; a dead or malformed source is reported as `unreachable` or `invalid`, never as "up to date"; the downloaded ZIP is checked against the manifest's SHA-256 before WordPress unpacks it, and against an Ed25519 signature (the scheme `WM_Ed25519_Verifier` already uses) when a public key is configured. Where the manifest is hosted is configuration; nothing points at a server that does not exist.
- Tests: `tests/test-plugin-updater.php` (57 assertions, run by suite test 19 in its own process), 10 mutations, and `tests/wp-runtime-plugin-updater.php`, which runs core's own `wp_update_plugins()` on a local WordPress install against a probe plugin: update listed, same version filed as no update, dead source neither. 9/9 on WordPress 7.1.
- **An inactive plugin was left without updates, and its package would have gone unchecked** (client 1.1.0). WordPress asks `update_plugins_{$hostname}` for every installed plugin but loads only the active ones, and the client answered only plugins that had run `register()`: an inactive fleet plugin never did. Worse, `upgrader_pre_download` verified only packages of registered slugs, so if an offer for an inactive plugin had come through any other path, WordPress would have installed that ZIP without the SHA-256 or signature check. The copy loaded by any active sibling now answers for every installed plugin declaring the same host, the details dialog is limited to installed plugins on a hooked host, and every package the manifest lists is verified. Measured on a local WordPress install with a second probe plugin that nothing registers: 13/13; the same run against the 1.0.0 client fails exactly the four new checks. Updater suite 57 -> 67 assertions, four more mutations (35 red, 0 survived).
- The class docblock named `wp-plugin-fleet-updater-sync.py` as the script that copies the client into each plugin; that script never existed. The copy is made by the direct-distribution build, `wp-plugin-fleet-release.py --channel direct`, documented with its measured runs in `DEVELOPERS.md` section 7.
- **Publishing a release is now a checked step, not an rsync.** `wp-plugin-fleet-publish.py` verifies every manifest entry against the local files (digest, the version in the plugin header inside the ZIP, https, signature) before uploading anything, uploads the packages first and the manifest last so the manifest never names a package that is not there yet, and then downloads the manifest and every package from the real URL to check that what is served is what was signed. Documented in `DEVELOPERS.md` section 7.
- Found while reading the verifier: `WM_Ed25519_Verifier::DEFAULT_MASTER_PUBLIC_KEY` decodes to 48 bytes, not the 32 of an Ed25519 key, so verifying against the default throws. It is a placeholder; the installer that would use it still answers 501.

#### Block icons are escaped where they are echoed (Plugin Check 22 -> 0 errors, 2026-09-17)

- **Every block template echoed its icon bare, and Plugin Check reported all 22 as `WordPress.Security.EscapeOutput.OutputNotEscaped`.** The SVG comes from a fixed map in `src/Gutenberg/icons.php` and was never unsafe, but Plugin Check cannot know what a function returns, and escaping at the echo is the WordPress rule. The 22 calls now read `echo wp_kses( wm_suite_hub_icon( ... ), wm_suite_hub_icon_allowed_html() );`, and the allowlist names exactly the elements and attributes the map draws with. Measured on the distribution view: **22 errors -> 0**. The fix is the escaper, not a `phpcs:ignore`.
- **Two risks of `wp_kses()` on SVG were measured, not assumed.** (1) It lower-cases attribute names, so `viewBox` leaves PHP as `viewbox`; SVG attribute names are case-sensitive. The HTML parser adjusts them back when a page is parsed as text/html — `tools/svg-kses-dom-probe.py` renders the original, the kses output and a control with no viewBox in Chromium: the first two render the circle at 7.2 px, the control at 3.6 px, so the probe can tell them apart. (2) An allowlist that forgets an element strips it silently. On a local WordPress install all 18 icons came through `wp_kses()` unchanged; with `rect` removed from the allowlist, exactly calendar, lock and pause changed.
- Suite test 18 fails on a bare icon echo in any block template and on any drawn element missing from the allowlist; two mutations cover both halves. The standalone suite gained a `wp_kses()` stand-in that keeps the allowed elements, because the harness renders blocks through `Block_Manager` and had no such function.

#### The version this file opens on matches the version the plugin ships (D15, 2026-09-17)

- **The plugin was 1.0.0 and the changelog said 1.2.0.** The 1.2.0 entry moved to an appendix at the foot of this file headed *"the entry written for 1.2.0 on 2026-08-29, never released"* — Keep a Changelog reserves `## [x.y.z]` for releases, and nothing was deleted because a changelog is a record and the record is that those claims were made. Each bullet of 1.2.0 and 1.0.0 that does not describe the code carries the measurement underneath it: no shortcode (0 `add_shortcode`), `wm-hub/v1` and not `wm-suite/v1`, no CSV export and no `sanitize_formula()`, `wp wm-hub`/`wp wm-sdk` and not `wp suite`, and the statutory-compliance sentence withdrawn. `wp-plugin-fleet-docs-vs-code.py wm-suite-hub` returns 1/1.
- **`hash_equals()` is real now, instead of being only claimed.** Both comparisons in `verify_chain_integrity()` use `hash_equals()`, pinned by suite test 3b and a mutation that puts `!==` back.

#### Documentation measured against the code (2026-09-16)

- **`README.md`, `README.de.md`, `README.es.md`, `DEVELOPERS.md`, `readme.txt`, `llms.txt`, `CONTRIBUTING.md`, `SECURITY.md`, and `CLAUDE.md` describe what this plugin registers.** Until then they described a `wm_suite_cockpit` shortcode with a `theme` attribute (measured: `add_shortcode` appears nowhere), WP-CLI `wp suite status|verify|export` (measured: `wp wm-hub` and `wp wm-sdk`), REST `/wm-suite/v1/status|submit|export` (measured: `wm-hub/v1` behind `manage_wm_suite`, plus `wm-suite/v1/consent-sync` only with Borlabs Cookie), GoBD PDF/CSV audit exports (measured: no export writer of any kind), three tables (measured: one, `{prefix}wm_suite_audit_log`), three developer hooks that do not exist (the real filters and actions are listed in `DEVELOPERS.md`), `sanitize_formula()` (does not exist), `composer check-cs` (`composer.json` declares no scripts), PHP 8.1 (the header requires 8.2 and the boot enforces it) and unverified statutory conformity claims for CRA Art. 14, DORA, HinSchG and GoBD plus the Planet49 and Munich judgments.
- **`docs/ARCHITECTURE-2026-08-29.md` carries a measured note** naming what no longer holds, its hub section is corrected, and its "23 of 23 Repositories Verified Green" is withdrawn: the script that produced it printed PASS without measuring (fleet audit 2026-09-13 section 2.3).
- **`SECURITY.md`** lists the real attack surface, and states that reports are acknowledged when read instead of promising 24 and 72 hours.
- `AGENTS.md` is a symlink to `CLAUDE.md` instead of a copy that can drift.
- CI compiles every PHP file, runs `tests/test-ui-family.php` and the mutation check.
- **WP-CLI subcommand verification (2026-09-16, updated 2026-11-18):** Suite test 10 parses every document for `wp wm-hub`/`wp wm-sdk` subcommands and requires each one to match a registered method or `@subcommand` annotation on its command class, while suite test 12 verifies the reverse direction against each class `Usage:` block.

#### Contrast measured on the surface each component is rendered on (2026-09-16)

- **A badge carrying the banner inks was illegible on a body row.** `.wm-badge-cyan`, `.wm-badge-green`, `.wm-badge--amber` and `.wm-badge--danger` painted the command-banner tokens (light inks meant for a dark banner) wherever they appeared, and the fleet renders most of them on white cards and striped tables — `wm-zero-cdn-asset-localizer/includes/Admin/AdminDashboard.php:365` puts one inside a `<td>` of a `.wm-table widefat striped`. Measured against the composed surface: **1.91:1** and **1.83:1** (cyan on a white card and on a striped row), **1.67:1** (green), **1.49:1** (amber), **2.21:1** (danger) — all far below WCAG 2.2 SC 1.4.3. The base rules now carry the state tokens, which measure at or above 4.5:1 on body surfaces in all four palettes, and `:is(.wm-admin-header, .wm-obsidian-header, .wm-enterprise-banner)` restores the banner inks inside the banner, so the banner renders as before.
- `.wm-dash-banner-sub` used the muted ink on the tinted banner states: **4.23:1** on `--wm-success-bg` and **4.50:1** on `--wm-warning-bg`. It uses the secondary ink now.
- `--wm-badge-banner-danger-color` in the wp-native palette was `#d63638` on `#fcf0f1` (**4.25:1**) -> `#b32d2e`, the value `--wm-danger` already carried there.
- `--wm-purple` was a dark ink inherited into the two dark palettes: **2.58:1** on obsidian, **2.70:1** on high-contrast. Each palette names its own now (`#d8b4fe`, `#ff88ff`), and `--wm-purple-border` exists: `.wm-badge--purple` referenced a token that was never defined in any palette.
- **The dashboard widget ships its own palette as hardcoded hex inside `wp_add_inline_style()`**, which `tests/test-ui-family.php` never sees because it scans `assets/**.css`. Measured per theme: `.wm-badge-cyan` **3.84:1**, `.wm-dash-kpi-val` **3.91:1**, the primary button's gradient **4.10:1** under white text, the wp-native alert title **4.25:1**. Fixed to `#0369a1`, `#075985` and `#b32d2e`.
- **`wp-plugin-fleet-token-contrast.py` measures what it used to skip.** It composes translucent values over the surface underneath and measures the inline widget CSS per theme: **152 pairs, 0 under 4.5:1**.

#### The dashboard widget's CSS stopped painting other plugins (reader pass, 2026-09-16)

- **`Dashboard_Widgets.php` ships its own palette through `wp_add_inline_style( 'dashboard', ... )`, and its selectors were the family classes every spoke uses.** All 81 selectors are scoped to `.wm-hub-widget` now, a class only the hub's two widgets carry. Suite test 13 refuses any rule in that file that is not scoped, and a mutation that drops one scope turns it red.

#### The gates say only what they measure (reader pass, 2026-09-16)

- **Suite test 11** rejects any class in `assets/css/wm-admin-ui.css` that opens with the slug of a fleet plugin or is neither `wm-*`, a modifier, nor an intentional WordPress class, while `wp-plugin-fleet-ui-sync.py --check` counts unused classes across the fleet.

#### One plugin's classes left the canonical family sheet (2026-09-16)

- **`assets/css/wm-admin-ui.css` carried five aliases of `wm-modularscale`** — `.wmmsp-guide-card`, `.wmmsp-guide-title`, `.wmmsp-guide-text`, `.wmmsp-preset-pills`, `.wmmsp-preset-btn`, across eight selectors. Removed after verifying 0 references outside the sheet copies.

#### Security (2026-09-14)

- **`wm-suite/v1/consent-sync` is gated (HIGH, fleet audit 2026-09-13 section 6.2 #2):** requires the REST nonce (`X-WP-Nonce`, 403 otherwise), applies a fixed-window rate limit of 10 requests per minute per client keyed by a blinded IP (429), keeps only scalar consent flags (<= 50 keys, <= 64 chars each), bounds the user agent to 255 chars and stores the anonymised IP (`wp_privacy_anonymize_ip`). Route and script are only registered when Borlabs Cookie is detected. Tests: suite test 14.5-14.6.
- **Marketplace install no longer seals what it did not do (HIGH, section 6.2 #3):** `POST /wm-hub/v1/marketplace/install-bundle` without `dry_run` answers `501` with the resolved order and writes nothing; `dry_run=true` is unchanged. Test: suite test 13.4.

#### Design family (hackaton phase 3, 2026-09-14)

- **Two canonical family sheets:** `assets/css/wm-admin-tokens.css` and `assets/css/wm-admin-ui.css`. Every plugin of the fleet bundles byte-identical copies; `wp-plugin-fleet-ui-sync.py` propagates them from here and writes `tests/test-ui-family.php` in each plugin, which pins the MD5 of both copies and rejects `outline: none` and hex colours outside token lines.
- **Family sheets on every page the hub dispatches:** a spoke enqueues its own sheet only on `toplevel_page_<own slug>`; that hook survives the hub's menu consolidation when the page slug is the spoke's own, whereas a page slug only the hub registers gets `wm-suite_page_<slug>` (measured across hub page slugs: `wm-activity-sentinel` registers `wm-sentinel-stream`, `wm-prototype-nda-gate` `wm-nda-dashboard`, `wm-dsgvo-newsletter-suite` `wm-newsletter-suite`, `wm-cyber-events-core` `cybevents-dashboard`, and one retired legacy configurator entry had no plugin). The hub enqueues the family sheets on every hub page and every `wm-suite_page_*` page, and the deduplicator drops a spoke's legacy `*-admin-obsidian` copy in favour of the family sheet while the hub is active.
- **Token contrast measured (`wp-plugin-fleet-token-contrast.py`):** Light accent updated to `#0369a1` (5.93:1 on white) and danger text to `#b32d2e` (5.67:1 on tint).

---

## [1.0.0] — 2026-08-25

> **Initial release baseline.** Two of its original bullets did not describe the code; the annotations
> below record the 2026-09-17 measurement.

### Initial Enterprise Release

- Core architectural implementation for Orchestration & Governance.
- WP-CLI management commands under `wp suite`.
  > Measured 2026-09-17: the registered commands are **`wp wm-hub` and `wp wm-sdk`**. `wp suite` is
  > not a registered command.
- Cryptographic HMAC-SHA256 audit ledger and telemetry hooks.
- Statutory compliance claims for EU Cyber Resilience Act (CRA Art. 14), DORA, HinSchG and GoBD (withdrawn).
  > **Withdrawn 2026-09-17.** A plugin cannot make its operator compliant with a statute, and this
  > one performs no legal assessment: it seals an append-only audit chain and reports measured state.

---

## Appendix — the entry written for 1.2.0 on 2026-08-29, never released

> **This was not a released version of this plugin.** Measured 2026-09-17: `git tag` -> 0 and
> `gh release list` -> 0. The entry is preserved here with the 2026-09-17 measurements under each
> bullet that differed from the code.

### Added

- **24 Official European Union Languages:** Binary `.mo` and source `.po` translation packages in `languages/`.
- **Developer Documentation Suite:** Created `DEVELOPERS.md` with architecture diagrams, hook references, database schema, and REST API guides.
- **Decoupled REST API Controller:** Registered namespace `/wp-json/wm-suite/v1` with nonce and permission validation.
  > Measured 2026-09-17: the controller registers **`wm-hub/v1`**, not `wm-suite/v1`. The only route
  > under `wm-suite/v1` is `consent-sync`, added by the Borlabs bridge when Borlabs Cookie is present.
- **Zero-CDN Frontend Shortcode:** Mentioned `wm_suite_cockpit` shortcode with inline SVG icons and CSS design tokens.
  > Measured 2026-09-17: **no shortcode exists.** `add_shortcode` appears 0 times. The cockpit is an
  > admin page, and the inline SVG icons live in `src/Gutenberg/icons.php`.
- **CycloneDX v1.6 SBOM Engine:** Dynamic SHA-256 entry file integrity hashing and spoke adapter integration for **WM Suite Hub**.
- **Enterprise Showcase Assets:** Illustrative 16:9 marketing banner and cockpit showcase mockups.
- **Clean Uninstallation Lifecycle:** `uninstall.php` handler for database and transient cache purging (converted to opt-in in `1.1.0`).

### Security

- **CWE-1236 Mitigation:** Claimed sanitization on CSV exports.
  > Measured 2026-09-17: **there is no CSV export and no `sanitize_formula()`** anywhere in the plugin.
- **Constant-Time Cryptography:** Enforced `hash_equals()` verification on HMAC-SHA256 signature chains (`src/Telemetry/Audit_Chainer.php`, pinned by suite test 3b and mutation).
- **SQL Parameterization:** Queries in `Audit_Chainer.php` taking arguments use `$wpdb->prepare()` with `%s`/`%d` placeholders.

### Quality & Tests

- Expanded standalone deterministic test suite (`tests/test-suite.php`) with full green pass rate and zero external dependencies.
