<?php
/**
 * Zero-Duplication Asset Engine for WM Suite Gutenberg Blocks.
 *
 * Enqueues wm-blocks-core.css and wm-blocks-interactivity.js only on pages whose post contains a
 * WM Suite block. Until 1.2.2 this docblock promised a payload size and a TTFB nobody measured.
 *
 * @package ArnoldWender\SuiteHub\Gutenberg
 */

declare(strict_types=1);

namespace ArnoldWender\SuiteHub\Gutenberg;

defined( 'ABSPATH' ) || exit;

/**
 * Class Zero_Duplication_Asset_Engine
 */
final class Zero_Duplication_Asset_Engine {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Constructor.
	 */
	private function __construct() {}

	/**
	 * Version string of the stylesheet and script URLs. It is the plugin version, so an update
	 * reaches browsers that cached the old files; until 1.2.2 it was a constant frozen at 1.1.1.
	 *
	 * @return string|null Null lets WordPress omit the version.
	 */
	private function asset_version(): ?string {
		return defined( 'WM_SUITE_HUB_VERSION' ) ? (string) WM_SUITE_HUB_VERSION : null;
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
	 * Initializes asset hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'conditional_enqueue_assets' ], 20 );
		// enqueue_block_assets, not enqueue_block_editor_assets: the second reaches only the admin page around
		// the iframed canvas, so until 1.2.6 the 16 blocks rendered unstyled in the editor (measured 2026-11-21
		// with wp-plugin-fleet-editor-canvas-probe.py: wm-blocks-editor-core-css absent from the canvas, the
		// consent badge with a transparent background).
		add_action( 'enqueue_block_assets', [ $this, 'enqueue_editor_assets' ] );
	}

	/**
	 * Checks if the current page contains any WM Suite suite blocks.
	 *
	 * @return bool
	 */
	public function has_suite_blocks(): bool {
		if ( is_admin() ) {
			return false;
		}

		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		$manager = Block_Manager::get_instance();
		$blocks  = $manager->get_registered_blocks();

		foreach ( array_keys( $blocks ) as $block_name ) {
			if ( has_block( $block_name, $post ) ) {
				return true;
			}
		}

		// Also check raw post content for partials / nested inner blocks
		if ( false !== strpos( $post->post_content, '<!-- wp:wm/' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Enqueues consolidated frontend styles and micro-scripts conditionally.
	 *
	 * @return void
	 */
	public function conditional_enqueue_assets(): void {
		if ( ! $this->has_suite_blocks() ) {
			return;
		}

		$plugin_url = plugin_dir_url( dirname( __DIR__ ) );

		// Core Design Tokens and Base Layout CSS
		wp_enqueue_style(
			'wm-blocks-core',
			$plugin_url . 'assets/css/wm-blocks-core.css',
			[],
			$this->asset_version()
		);

		// Vanilla script without dependencies or external requests
		wp_enqueue_script(
			'wm-blocks-interactivity',
			$plugin_url . 'assets/js/wm-blocks-interactivity.js',
			[],
			$this->asset_version(),
			true
		);

		// Only the texts the script writes. Until 1.2.2 this also printed a REST nonce, the REST base
		// and the admin-ajax URL into every page with a block, and no script read any of them.
		wp_localize_script(
			'wm-blocks-interactivity',
			'wmSuiteData',
			[
				'i18n' => [
					'validPeppol'   => __( 'Format gültig (Schema:ID)', 'wm-suite-hub' ),
					'invalidPeppol' => __( 'Ungültiges ID-Format', 'wm-suite-hub' ),
					'paused'        => __( 'Pausiert', 'wm-suite-hub' ),
					'playing'       => __( 'Läuft', 'wm-suite-hub' ),
				],
			]
		);
	}

	/**
	 * The block stylesheet in the block editor, canvas included. enqueue_block_assets also fires on the
	 * front end, where conditional_enqueue_assets() decides per page; this method returns there.
	 *
	 * @return void
	 */
	public function enqueue_editor_assets(): void {
		if ( ! is_admin() ) {
			return;
		}
		$plugin_url = plugin_dir_url( dirname( __DIR__ ) );

		wp_enqueue_style(
			'wm-blocks-editor-core',
			$plugin_url . 'assets/css/wm-blocks-core.css',
			[],
			$this->asset_version()
		);
	}
}
