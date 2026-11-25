<?php
declare(strict_types=1);

namespace ArnoldWender\SuiteHub\CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ArnoldWender\SuiteHub\Core\Hub_Orchestrator;
use ArnoldWender\SuiteHub\Telemetry\Health_Matrix;
use ArnoldWender\SuiteHub\Compliance\SBOM_Generator;
use WP_CLI;
use WP_CLI_Command;

/**
 * Class WM_Hub_CLI
 *
 * Provides native WP-CLI commands for automated CI/CD deployments,
 * server-level telemetry inspections, and CycloneDX SBOM generation.
 *
 * Usage:
 *   wp wm-hub status [--format=<format>] [--force]
 *   wp wm-hub sbom [--output=<file>]
 *   wp wm-hub crypto-verify
 *   wp wm-hub flush-cache
 *   wp wm-hub action <slug> <action> [--params=<json>]
 *   wp wm-hub bundle list|install <id> [--dry-run]
 *   wp wm-hub package verify-signatures
 *   wp wm-hub deduplicate-telemetry
 *
 * @package ArnoldWender\SuiteHub\CLI
 * @since   1.0.0
 */
final class WM_Hub_CLI extends WP_CLI_Command {

    /**
     * Registers the `wp wm-hub` command in WP-CLI.
     *
     * @param Hub_Orchestrator $orchestrator
     * @return void
     */
    public static function register(Hub_Orchestrator $orchestrator): void {
        WP_CLI::add_command('wm-hub', new self($orchestrator));
    }

    /**
     * Hub Orchestrator instance.
     *
     * @var Hub_Orchestrator
     */
    private Hub_Orchestrator $orchestrator;

    /**
     * WM_Hub_CLI constructor.
     *
     * @param Hub_Orchestrator $orchestrator
     */
    public function __construct(Hub_Orchestrator $orchestrator) {
        $this->orchestrator = $orchestrator;
    }

    /**
     * Displays the current health status and inventory of all registered WM Suite modules.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Render output format (table, json, yaml, csv).
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - yaml
     *   - csv
     * ---
     *
     * [--force]
     * : Bypass transient cache and query live modules.
     *
     * ## EXAMPLES
     *
     *     wp wm-hub status
     *     wp wm-hub status --format=json
     *
     * @param array<int, string>   $args
     * @param array<string, mixed> $assoc_args
     * @return void
     */
    public function status(array $args, array $assoc_args): void {
        $registry = $this->orchestrator->get_registry();
        if ($registry === null) {
            WP_CLI::error('Hub-Orchestrierungsregister konnte nicht initialisiert werden.');
            return;
        }

        $force  = isset($assoc_args['force']);
        $format = $assoc_args['format'] ?? 'table';

        $matrix = Health_Matrix::get_matrix($registry, $force);

        if ($format === 'table') {
            WP_CLI::line(WP_CLI::colorize('%G=============================================================%n'));
            WP_CLI::line(WP_CLI::colorize(sprintf('%%B WM SUITE COCKPIT — STATUS: %%W%s%%B | SCORE: %%W%d%%%% %%n', $matrix['global_status'], $matrix['score'])));
            WP_CLI::line(WP_CLI::colorize('%G=============================================================%n'));

            $table_data = [];
            foreach ($matrix['modules'] as $slug => $mod) {
                $table_data[] = [
                    'Slug'         => $slug,
                    'Name'         => $mod['name'],
                    'Version'      => $mod['version'],
                    'Status'       => $mod['status'],
                    'Dependencies' => implode(', ', $mod['dependencies'] ?? []),
                ];
            }

            WP_CLI\Utils\format_items('table', $table_data, ['Slug', 'Name', 'Version', 'Status', 'Dependencies']);
        } else {
            WP_CLI\Utils\format_items($format, [$matrix], array_keys($matrix));
        }
    }

    /**
     * Generates a CycloneDX 1.6 Software Bill of Materials (SBOM) of the hub and the registered modules.
     *
     * ## OPTIONS
     *
     * [--output=<file>]
     * : Target path to save the generated JSON file.
     *
     * [--format=<format>]
     * : Output format (json).
     * ---
     * default: json
     * ---
     *
     * ## EXAMPLES
     *
     *     wp wm-hub sbom
     *     wp wm-hub sbom --output=/var/www/sbom.json
     *
     * @param array<int, string>   $args
     * @param array<string, mixed> $assoc_args
     * @return void
     */
    public function sbom(array $args, array $assoc_args): void {
        $registry = $this->orchestrator->get_registry();
        if ($registry === null) {
            WP_CLI::error('Register nicht verfügbar.');
            return;
        }

        $sbom_array = SBOM_Generator::generate_cyclonedx_array($registry);
        $json       = (string) wp_json_encode($sbom_array, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (!empty($assoc_args['output'])) {
            $output_file = sanitize_text_field((string) $assoc_args['output']);
            if (file_put_contents($output_file, $json) === false) {
                WP_CLI::error("Konnte SBOM nicht schreiben nach: {$output_file}");
                return;
            }
            WP_CLI::success("CycloneDX-1.6-SBOM geschrieben nach: {$output_file}");
        } else {
            WP_CLI::line($json);
        }
    }

    /**
     * Recomputes the HMAC-SHA256 chain of the audit table and names the first row that does not match.
     *
     * ## EXAMPLES
     *
     *     wp wm-hub crypto-verify
     *
     * @subcommand crypto-verify
     * @param array<int, string>   $args
     * @param array<string, mixed> $assoc_args
     * @return void
     */
    public function crypto_verify(array $args, array $assoc_args): void {
        $chainer = $this->orchestrator->get_audit_chainer();
        if ($chainer === null) {
            WP_CLI::error('Audit Chainer nicht initialisiert.');
            return;
        }

        WP_CLI::line('Prüfe kryptographische Konsistenz der Hash-Kette...');
        $result = $chainer->verify_chain_integrity();

        if ($result['intact']) {
            WP_CLI::success(sprintf('Mathematische Integrität bestätigt! (%d Einträge geprüft).', $result['total_checked']));
        } else {
            WP_CLI::error(sprintf('KETTENBRUCH ERKANNT: %s (Gebrochen bei ID #%d)', $result['details'], $result['broken_at_id']));
        }
    }

    /**
     * Flushes the transient cache for fleet telemetry.
     *
     * ## EXAMPLES
     *
     *     wp wm-hub flush-cache
     *
     * @subcommand flush-cache
     * @param array<int, string>   $args
     * @param array<string, mixed> $assoc_args
     * @return void
     */
    public function flush_cache(array $args, array $assoc_args): void {
        Health_Matrix::flush_cache();
        WP_CLI::success('Telemetrie-Zwischenspeicher erfolgreich geleert.');
    }

    /**
     * Dispatches an administrative action to a registered spoke plugin.
     *
     * ## OPTIONS
     *
     * <slug>
     * : The registered module slug.
     *
     * <action>
     * : The action name to execute.
     *
     * [--params=<json>]
     * : Optional JSON-encoded parameters.
     *
     * ## EXAMPLES
     *
     *     wp wm-hub action wm-zero-cdn-asset-localizer purge
     *
     * @param array<int, string>   $args
     * @param array<string, mixed> $assoc_args
     * @return void
     */
    public function action(array $args, array $assoc_args): void {
        $slug   = $args[0] ?? '';
        $action = $args[1] ?? '';

        if (empty($slug) || empty($action)) {
            WP_CLI::error('Verwendung: wp wm-hub action <slug> <action>');
            return;
        }

        $registry = $this->orchestrator->get_registry();
        $module   = $registry ? $registry->get_module($slug) : null;

        if ($module === null) {
            WP_CLI::error("Modul '{$slug}' ist nicht registriert oder inaktiv.");
            return;
        }

        $params = [];
        if (!empty($assoc_args['params'])) {
            $decoded = json_decode((string) $assoc_args['params'], true);
            if (is_array($decoded)) {
                $params = $decoded;
            }
        }

        try {
            $result = $module->execute_action($action, $params);
            if (!empty($result['success'])) {
                WP_CLI::success($result['message'] ?? 'Aktion erfolgreich ausgeführt.');
            } else {
                WP_CLI::warning($result['message'] ?? 'Aktion mit Warnung abgeschlossen.');
            }
        } catch (\Throwable $e) {
            WP_CLI::error("Fehler bei Modulaktion: " . $e->getMessage());
        }
    }

    /**
     * Manages WM Suite Solution Pack Bundles.
     *
     * ## OPTIONS
     *
     * <subcommand>
     * : list | install
     *
     * [<bundle-id>]
     * : Identifier of the target bundle.
     *
     * [--dry-run]
     * : Runs DAG resolution and environment pre-flight audit without filesystem changes.
     * Without it, install runs the same checks and then exits with an error: installing a pack
     * is not available in this version (the REST route answers 501 for the same reason).
     *
     * [--format=<format>]
     * : Output format (table, json, yaml, csv).
     *
     * ## EXAMPLES
     *
     *     wp wm-hub bundle list
     *     wp wm-hub bundle list --format=json
     *     wp wm-hub bundle install security-legal --dry-run
     *
     * @param array<int, string>   $args
     * @param array<string, mixed> $assoc_args
     * @return void
     */
    public function bundle(array $args, array $assoc_args): void {
        $subcommand = $args[0] ?? 'list';
        $format     = $assoc_args['format'] ?? 'table';
        $bundles    = \ArnoldWender\SuiteHub\SharedKernel\Bundles\Manifest_Parser::load_default_manifest();

        if ($subcommand === 'list') {
            $data = [];
            foreach ($bundles as $id => $bundle) {
                $graph = $bundle->get_dependency_graph();
                $order = \ArnoldWender\SuiteHub\Marketplace\DAG\DAG_Resolver::resolve_order($graph);
                $data[] = [
                    'Bundle ID'       => $id,
                    'Suite Name'      => $bundle->get_suite_name(),
                    'Packages'        => count($bundle->get_packages()),
                    'DAG Sequence'    => implode(' → ', $order),
                    'Tags'            => implode(', ', $bundle->get_compliance_tags()),
                ];
            }

            WP_CLI\Utils\format_items($format, $data, ['Bundle ID', 'Suite Name', 'Packages', 'DAG Sequence', 'Tags']);
            return;
        }

        if ($subcommand === 'install') {
            $bundle_id = $args[1] ?? '';
            if (empty($bundle_id) || !isset($bundles[$bundle_id])) {
                WP_CLI::error("Bundle ID erforderlich oder ungültig. Verfügbar: " . implode(', ', array_keys($bundles)));
                return;
            }

            $bundle  = $bundles[$bundle_id];
            $dry_run = isset($assoc_args['dry-run']);

            WP_CLI::line(WP_CLI::colorize(sprintf('%%B=== PRE-FLIGHT AUDIT: %%W%s%%B ===%%n', $bundle->get_suite_name())));
            $audit = \ArnoldWender\SuiteHub\Marketplace\Auditor\Preflight_Auditor::audit($bundle);

            foreach ($audit['checks'] as $key => $check) {
                $status = $check['passed'] ? WP_CLI::colorize('%G[PASS]%n') : WP_CLI::colorize('%R[FAIL]%n');
                WP_CLI::line(sprintf('  %s %s: %s', $status, $check['name'], $check['message']));
            }

            if (!$audit['passed']) {
                WP_CLI::error('Pre-Flight Überprüfung fehlgeschlagen. Installation abgebrochen.');
                return;
            }

            $order = \ArnoldWender\SuiteHub\Marketplace\DAG\DAG_Resolver::resolve_order($bundle->get_dependency_graph());
            WP_CLI::line(WP_CLI::colorize(sprintf('%%G[DAG] Berechnete Installationsfolge: %%W%s%%n', implode(' → ', $order))));

            if ($dry_run) {
                WP_CLI::success(sprintf('Dry-Run Simulation für Suite "%s" erfolgreich abgeschlossen.', $bundle->get_suite_name()));
                return;
            }

            // Until 1.2.2 this branch built an Atomic_Installer, never called it, and printed
            // "erfolgreich bereitgestellt (N Pakete atomar installiert)" while the REST route
            // already answered 501. Nothing is installed, so the command fails like the route.
            WP_CLI::error('Die Installation von Solution Packs ist in dieser Version noch nicht verfügbar; es wurde nichts installiert. Für die Reihenfolge genügt --dry-run.');
            return;
        }

        WP_CLI::error("Unbekannter Unterbefehl '{$subcommand}'. Gültig: list, install.");
    }

    /**
     * Lists the packages of the bundled manifest and whether each is installed.
     *
     * The subcommand is named for the Ed25519 check the install path would need, but no package is
     * published with a signature and no signing key exists yet (D9), so there is nothing to verify:
     * it lists what is installed and exits with an error. Until 1.2.2 it printed "INTACT — SIGNED"
     * for every installed folder and reported success without reading a single signature.
     *
     * ## OPTIONS
     *
     * [<subcommand>]
     * : verify-signatures
     *
     * ## EXAMPLES
     *
     *     wp wm-hub package verify-signatures
     *
     * @param array<int, string>   $args
     * @param array<string, mixed> $assoc_args
     * @return void
     */
    public function package(array $args, array $assoc_args): void {
        $subcommand = $args[0] ?? 'verify-signatures';

        if ($subcommand === 'verify-signatures') {
            $bundles     = \ArnoldWender\SuiteHub\SharedKernel\Bundles\Manifest_Parser::load_default_manifest();
            $plugins_dir = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : '';
            $listed      = [];
            $installed   = 0;

            WP_CLI::line(WP_CLI::colorize('%B=== Pakete des Manifests (keine Signatur veröffentlicht) ===%n'));

            foreach ($bundles as $bundle) {
                foreach ($bundle->get_packages() as $pkg) {
                    $slug = $pkg['slug'];
                    // A plugin in two packs is listed once.
                    if (isset($listed[$slug])) {
                        continue;
                    }
                    $listed[$slug] = true;
                    $is_installed  = $plugins_dir !== '' && is_dir($plugins_dir . '/' . $slug);
                    if ($is_installed) {
                        $installed++;
                    }

                    $status = $is_installed ? WP_CLI::colorize('%G[INSTALLIERT]%n') : WP_CLI::colorize('%Y[NICHT INSTALLIERT]%n');
                    WP_CLI::line(sprintf('  %s %s (%s)', $status, $pkg['name'], $pkg['version_constraint']));
                }
            }

            WP_CLI::error(sprintf('Keine Signatur geprüft: Für diese Pakete wird keine Signatur veröffentlicht, und es gibt keinen Signaturschlüssel. Installiert: %d von %d.', $installed, count($listed)));
            return;
        }

        WP_CLI::error("Unbekannter Unterbefehl '{$subcommand}'. Gültig: verify-signatures.");
    }

    /**
     * Displays the asset deduplication counters of this process.
     *
     * The counters belong to one request. WP-CLI renders no page, so nothing is enqueued or dropped
     * here and they read 0; the counters of an admin page load are shown under WM Suite > Solution Packs.
     *
     * ## EXAMPLES
     *
     *     wp wm-hub deduplicate-telemetry
     *
     * @subcommand deduplicate-telemetry
     * @param array<int, string>   $args
     * @param array<string, mixed> $assoc_args
     * @return void
     */
    public function deduplicate_telemetry(array $args, array $assoc_args): void {
        $deduplicator = \ArnoldWender\SuiteHub\SharedKernel\Deduplication\Asset_Deduplicator::get_instance();
        $telemetry    = $deduplicator->get_telemetry();

        WP_CLI::line(WP_CLI::colorize('%B=============================================================%n'));
        WP_CLI::line(WP_CLI::colorize('%B DEDUPLIZIERUNG — ZÄHLER DIESES PROZESSES                     %n'));
        WP_CLI::line(WP_CLI::colorize('%B=============================================================%n'));
        WP_CLI::line(sprintf('  Entfernte Skripte:         %d', $telemetry['scripts_deduplicated']));
        WP_CLI::line(sprintf('  Entfernte Stylesheets:     %d', $telemetry['styles_deduplicated']));
        WP_CLI::line(sprintf('  Größe der Dateien:         %s KB', round($telemetry['bytes_saved'] / 1024, 2)));
        WP_CLI::line('  WP-CLI lädt keine Seite und entfernt deshalb nichts; die Zähler einer Admin-Seite zeigt WM Suite > Solution Packs.');
        WP_CLI::line(WP_CLI::colorize('%G=============================================================%n'));
    }
}
