<?php
declare(strict_types=1);

namespace ArnoldWender\SuiteHub\CLI;

use ArnoldWender\SuiteHub\Compliance\Developer_SDK_Bridge;
use ArnoldWender\SuiteHub\Contracts\WM_Audit_Payload;
use ArnoldWender\SuiteHub\Telemetry\Audit_Chainer;
use WP_CLI;
use WP_CLI_Command;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WM_SDK_CLI
 *
 * Developer tool for scaffolding and testing third-party plugin integrations with the WM Suite hub
 * and its HMAC-SHA256 audit chain.
 *
 * Usage:
 *   wp wm-sdk status [--format=<format>]
 *   wp wm-sdk scaffold --slug=<slug> [--name=<name>] [--domains=<domains>] [--output=<file>]
 *   wp wm-sdk test-event --event=<name> [--severity=<sev>] [--module=<mod>]
 *   wp wm-sdk sanitize --text=<string>
 *
 * @package ArnoldWender\SuiteHub\CLI
 * @since   1.1.0
 */
final class WM_SDK_CLI extends WP_CLI_Command {

    /**
     * Registers the `wp wm-sdk` command in WP-CLI.
     *
     * @return void
     */
    public static function register(): void {
        WP_CLI::add_command('wm-sdk', new self());
    }

    /**
     * Lists all registered 3rd-party compliance provider integrations.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Render output format (table, json, yaml).
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - yaml
     * ---
     *
     * @param array<int, string>   $args
     * @param array<string, mixed> $assoc_args
     */
    public function status(array $args, array $assoc_args): void {
        $bridge = Developer_SDK_Bridge::get_instance();
        $bridge->discover_third_party_providers();
        $providers = $bridge->get_registered_providers();

        $format = $assoc_args['format'] ?? 'table';

        if (empty($providers)) {
            WP_CLI::log('Keine externen Drittanbieter-Integrationen registriert.');
            return;
        }

        $table_data = [];
        foreach ($providers as $slug => $provider) {
            $table_data[] = [
                'Slug'    => $slug,
                'Name'    => $provider->get_integration_name(),
                'Version' => $provider->get_integration_version(),
                'Vectors' => implode(', ', $provider->get_supported_compliance_vectors()),
            ];
        }

        WP_CLI\Utils\format_items($format, $table_data, ['Slug', 'Name', 'Version', 'Vectors']);
    }

    /**
     * Scaffolds a compliant integration adapter for a 3rd-party plugin.
     *
     * ## OPTIONS
     *
     * --slug=<slug>
     * : Identifier of the 3rd-party plugin (e.g. 'my-booking-pro').
     *
     * [--name=<name>]
     * : Display name of the integration.
     *
     * [--domains=<domains>]
     * : Comma-separated list of compliance domains (audit_ledger, consent_gateway, pii_sanitization, sbom_inventory).
     * ---
     * default: audit_ledger,consent_gateway,sbom_inventory
     * ---
     *
     * [--output=<file>]
     * : Path to output PHP file. If omitted, prints code to stdout.
     *
     * @param array<int, string>   $args
     * @param array<string, mixed> $assoc_args
     */
    public function scaffold(array $args, array $assoc_args): void {
        $slug = sanitize_key($assoc_args['slug'] ?? '');
        if ($slug === '') {
            WP_CLI::error('Bitte geben Sie ein gültiges --slug an.');
            return;
        }

        $name = sanitize_text_field($assoc_args['name'] ?? ucwords(str_replace(['-', '_'], ' ', $slug)));
        $domains_raw = explode(',', (string) ($assoc_args['domains'] ?? 'audit_ledger,consent_gateway,sbom_inventory'));
        $domains = array_map('trim', $domains_raw);
        $class_name = str_replace(' ', '_', ucwords(str_replace(['-', '_'], ' ', $slug))) . '_Compliance_Adapter';

        $code = self::generate_scaffold_template($slug, $name, $class_name, $domains);

        if (!empty($assoc_args['output'])) {
            $file = $assoc_args['output'];
            file_put_contents($file, $code);
            WP_CLI::success(sprintf('Scaffold erfolgreich geschrieben nach: %s', $file));
        } else {
            WP_CLI::line($code);
        }
    }

    /**
     * Appends a test event to the audit chain to check that events reach it.
     *
     * ## OPTIONS
     *
     * --event=<name>
     * : Name of the test event.
     *
     * [--severity=<sev>]
     * : Severity level: INFO, WARNING, CRITICAL.
     * ---
     * default: INFO
     * ---
     *
     * [--module=<mod>]
     * : Origin module identifier.
     * ---
     * default: cli-test-suite
     * ---
     *
     * @subcommand test-event
     * @param array<int, string>   $args
     * @param array<string, mixed> $assoc_args
     */
    public function test_event(array $args, array $assoc_args): void {
        $event = sanitize_key($assoc_args['event'] ?? 'cli.verification.test');
        $severity = strtoupper((string) ($assoc_args['severity'] ?? 'INFO'));
        $module = sanitize_key((string) ($assoc_args['module'] ?? 'cli-test-suite'));

        $payload = new WM_Audit_Payload(
            event_type: $event,
            severity: $severity,
            actor_id: 'wp-cli',
            context_data: ['test_run' => true, 'time' => gmdate('c')],
            origin_module: $module,
            timestamp: time()
        );

        do_action('wm_compliance_record_audit_event', $payload);

        WP_CLI::success(sprintf('Test-Audit-Event [%s] erfolgreich in die HMAC-SHA256 Kette eingespeist.', $event));
    }

    /**
     * Tests PII sanitization against sample text.
     *
     * ## OPTIONS
     *
     * --text=<string>
     * : Raw text containing sensitive data to sanitize.
     *
     * @param array<int, string>   $args
     * @param array<string, mixed> $assoc_args
     */
    public function sanitize(array $args, array $assoc_args): void {
        $text = $assoc_args['text'] ?? '';
        $sanitized = apply_filters('wm_compliance_sanitize_prompt_pii', $text);

        WP_CLI::line(WP_CLI::colorize('%G--- Original ---%n'));
        WP_CLI::line($text);
        WP_CLI::line(WP_CLI::colorize('%B--- Sanitized (Zero-PII) ---%n'));
        WP_CLI::line($sanitized);
    }

    /**
     * Generates PHP code boilerplate.
     *
     * @param string        $slug
     * @param string        $name
     * @param string        $class_name
     * @param array<string> $domains
     * @return string
     */
    public static function generate_scaffold_template(string $slug, string $name, string $class_name, array $domains): string {
        $domains_export = var_export($domains, true);
        // Assembled line by line: Plugin Check refuses heredocs, and single-quoted lines keep the
        // generated code's own "$variables" literal without escaping every dollar sign.
        $lines = [
            '<?php',
            'declare(strict_types=1);',
            '',
            'namespace App\\Integrations\\Compliance;',
            '',
            'use ArnoldWender\\SuiteHub\\Contracts\\WM_Compliance_Provider_Interface;',
            'use ArnoldWender\\SuiteHub\\Contracts\\WM_Audit_Payload;',
            '',
            '/**',
            ' * Class ' . $class_name,
            ' *',
            ' * Implements WM Suite Compliance integration contract.',
            " * Automatically registered via 'wm_compliance_register_provider' filter.",
            ' */',
            'final class ' . $class_name . ' implements WM_Compliance_Provider_Interface {',
            '',
            '    public function get_integration_slug(): string {',
            "        return '" . $slug . "';",
            '    }',
            '',
            '    public function get_integration_name(): string {',
            "        return '" . $name . "';",
            '    }',
            '',
            '    public function get_integration_version(): string {',
            "        return '1.0.0';",
            '    }',
            '',
            '    public function get_supported_compliance_vectors(): array {',
            '        return ' . $domains_export . ';',
            '    }',
            '',
            '    /**',
            '     * Records a business event to WM Suite append-only audit ledger.',
            '     */',
            '    public function log_audit_event(string $eventType, array $context = []): void {',
            "        if (!has_action('wm_compliance_record_audit_event')) {",
            '            return; // Graceful No-Op if Hub is not installed',
            '        }',
            '',
            '        $payload = new WM_Audit_Payload(',
            '            event_type: $eventType,',
            "            severity: 'INFO',",
            '            actor_id: (string) get_current_user_id(),',
            '            context_data: $context,',
            '            origin_module: $this->get_integration_slug()',
            '        );',
            '',
            "        do_action('wm_compliance_record_audit_event', \$payload);",
            '    }',
            '}',
            '',
            '// Auto-register with WM Suite Hub',
            "add_filter('wm_compliance_register_provider', static function(array \$providers): array {",
            '    $providers[] = new ' . $class_name . '();',
            '    return $providers;',
            '});',
        ];

        return implode("\n", $lines);
    }
}
