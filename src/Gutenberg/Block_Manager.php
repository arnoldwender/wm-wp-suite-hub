<?php
/**
 * Gutenberg Block Manager for WM Suite Hub.
 *
 * Registers the 16 server-rendered Gutenberg blocks (Block API v3) and loads their assets only
 * on pages that use one. The blocks cover the topics of the WM Suite plugins (events, e-invoicing,
 * consent, whistleblowing, accessibility); none of them states that a site complies with a law.
 *
 * @package ArnoldWender\SuiteHub\Gutenberg
 */

declare(strict_types=1);

namespace ArnoldWender\SuiteHub\Gutenberg;

defined( 'ABSPATH' ) || exit;

/**
 * Class Block_Manager
 */
final class Block_Manager {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Registry of known blocks and their directory mappings.
	 *
	 * @var array<string, string>
	 */
	private array $blocks = [
		// Domain: Events & E-Invoicing
		'wm/event-grid'             => 'event-grid',
		'wm/event-card'             => 'event-card',
		'wm/event-booking-cta'      => 'event-booking-cta',
		'wm/peppol-id-verifier'     => 'peppol-id-verifier',

		// Domain: Sovereign Privacy & Consent
		'wm/consent-badge'          => 'consent-badge',
		'wm/privacy-center'         => 'privacy-center',
		'wm/privacy-eraser-form'    => 'privacy-eraser-form',
		'wm/script-placeholder'     => 'script-placeholder',

		// Domain: Legal, Whistleblower & ESG
		'wm/whistleblower-card'     => 'whistleblower-card',
		'wm/whistleblower-wizard'   => 'whistleblower-wizard',
		'wm/lksg-esg-scorecard'     => 'lksg-esg-scorecard',
		'wm/cra-security-status'    => 'cra-security-status',

		// Domain: Accessibility & Modular Typography
		'wm/a11y-contrast-switch'   => 'a11y-contrast-switch',
		'wm/bfsg-statement-embed'   => 'bfsg-statement-embed',
		'wm/newsticker-banner'      => 'newsticker-banner',
		'wm/modular-type-container' => 'modular-type-container',
	];

	/**
	 * Base directory for blocks.
	 *
	 * @var string
	 */
	private string $blocks_dir;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->blocks_dir = dirname( __DIR__ ) . '/Gutenberg/Blocks';

		// wm_suite_hub_icon() and the notice helpers are called from the render.php files, which are
		// include'd rather than autoloaded, so they have to exist before the first render.
		require_once __DIR__ . '/icons.php';
		require_once __DIR__ . '/block-notice.php';
	}

	/**
	 * Retrieves the singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initializes hooks for block registration and categories.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'block_categories_all', [ $this, 'register_block_category' ], 10, 2 );
		add_action( 'init', [ $this, 'register_all_blocks' ] );

		// Initialize Zero-Duplication Asset Engine
		Zero_Duplication_Asset_Engine::get_instance()->init();

		// Initialize Block Bindings & GenerateBlocks 2.x Telemetry Bridge
		Telemetry_Bridge::get_instance()->init();
	}

	/**
	 * Registers the custom 'wm-suite' block category in Gutenberg.
	 *
	 * @param array<int, array<string, mixed>> $categories
	 * @param mixed                            $block_editor_context
	 * @return array<int, array<string, mixed>>
	 */
	public function register_block_category( array $categories, $block_editor_context ): array {
		// Prevent duplicates
		foreach ( $categories as $cat ) {
			if ( isset( $cat['slug'] ) && 'wm-suite' === $cat['slug'] ) {
				return $categories;
			}
		}

		array_unshift(
			$categories,
			[
				'slug'  => 'wm-suite',
				'title' => __( 'WM Enterprise Suite', 'wm-suite-hub' ),
				'icon'  => 'shield-alt',
			]
		);

		return $categories;
	}

	/**
	 * Registers all Gutenberg blocks using Block API v3 and block.json metadata.
	 *
	 * @return void
	 */
	public function register_all_blocks(): void {
		$plugin_root = dirname( dirname( __DIR__ ) );
		$asset_file  = $plugin_root . '/build/index.asset.php';
		$asset_meta  = file_exists( $asset_file ) ? include $asset_file : null;
		if ( ! is_array( $asset_meta ) ) {
			$asset_meta = [
				'dependencies' => [ 'wp-block-editor', 'wp-blocks', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render' ],
				'version'      => defined( 'WM_SUITE_HUB_VERSION' ) ? WM_SUITE_HUB_VERSION : null,
			];
		}

		if ( function_exists( 'wp_register_script' ) ) {
			$base_url = defined( 'WM_SUITE_HUB_URL' )
				? WM_SUITE_HUB_URL
				: plugin_dir_url( $plugin_root . '/wm-suite-hub.php' );
			wp_register_script(
				'wm-suite-hub-blocks-editor',
				$base_url . 'build/index.js',
				(array) ( $asset_meta['dependencies'] ?? [] ),
				isset( $asset_meta['version'] ) ? (string) $asset_meta['version'] : null,
				true
			);
		}

		if ( function_exists( 'wp_set_script_translations' ) ) {
			$base_path = defined( 'WM_SUITE_HUB_PATH' ) ? WM_SUITE_HUB_PATH : ( $plugin_root . '/' );
			wp_set_script_translations(
				'wm-suite-hub-blocks-editor',
				'wm-suite-hub',
				$base_path . 'languages'
			);
		}

		foreach ( $this->blocks as $block_name => $dir_name ) {
			$block_path = $this->blocks_dir . '/' . $dir_name;
			$json_file  = $block_path . '/block.json';

			if ( file_exists( $json_file ) ) {
				register_block_type_from_metadata(
					$block_path,
					[
						'render_callback' => function ( array $attributes, string $content ) use ( $dir_name, $block_name ): string {
							return $this->render_block_ssr( $dir_name, $block_name, $attributes, $content );
						},
					]
				);
			}
		}
	}

	/**
	 * Executes Server-Side Rendering (SSR) for a given block.
	 *
	 * @param string               $dir_name   Subdirectory name of the block.
	 * @param string               $block_name Full block name (e.g. 'wm/event-grid').
	 * @param array<string, mixed> $attributes Block attributes from editor.
	 * @param string               $content    Inner block content (if applicable).
	 * @return string Rendered HTML.
	 */
	public function render_block_ssr( string $dir_name, string $block_name, array $attributes, string $content ): string {
		$render_file = $this->blocks_dir . '/' . $dir_name . '/render.php';

		if ( ! file_exists( $render_file ) ) {
			return sprintf(
				'<!-- WM Block [%s] Render template missing -->',
				esc_html( $block_name )
			);
		}

		// Clean output buffering
		ob_start();
		// Variables available inside render.php:
		// $attributes, $content, $block_name, $dir_name
		include $render_file;
		return (string) ob_get_clean();
	}

	/**
	 * Gets the list of registered block identifiers.
	 *
	 * @return array<string, string>
	 */
	public function get_registered_blocks(): array {
		return $this->blocks;
	}

	/**
	 * Validates whether a block is part of the WM Suite suite.
	 *
	 * @param string $block_name
	 * @return bool
	 */
	public function is_suite_block( string $block_name ): bool {
		return isset( $this->blocks[ $block_name ] );
	}
}
