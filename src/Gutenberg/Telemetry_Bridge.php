<?php
/**
 * WordPress 6.5+ Block Bindings & GenerateBlocks 2.x Dynamic Tag Bridge.
 *
 * Exposes non-sensitive aggregate suite telemetry (health score, global status,
 * active spoke count, and Hub version) to core blocks via `wm-suite-hub/telemetry`
 * and to GenerateBlocks 2.x via the `wm_hub_telemetry` dynamic tag. A field the hub
 * cannot compute yet is empty, never a default that reads as healthy.
 *
 * @package ArnoldWender\SuiteHub\Gutenberg
 * @since   1.1.0
 */

declare(strict_types=1);

namespace ArnoldWender\SuiteHub\Gutenberg;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ArnoldWender\SuiteHub\Core\Hub_Orchestrator;
use ArnoldWender\SuiteHub\Telemetry\Health_Matrix;

/**
 * Class Telemetry_Bridge
 */
final class Telemetry_Bridge {

	/**
	 * Block Bindings source identifier.
	 */
	public const BINDING_SOURCE = 'wm-suite-hub/telemetry';

	/**
	 * GenerateBlocks 2.x dynamic tag identifier.
	 */
	public const DYNAMIC_TAG = 'wm_hub_telemetry';

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
	 * Hooks registration into WordPress `init`.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', [ $this, 'register_bridges' ], 20 );
	}

	/**
	 * Registers both the WP 6.5+ Block Bindings source and GenerateBlocks 2.x Dynamic Tag.
	 *
	 * @return void
	 */
	public function register_bridges(): void {
		if ( function_exists( 'register_block_bindings_source' ) ) {
			register_block_bindings_source(
				self::BINDING_SOURCE,
				[
					'label'              => __( 'WM Suite Hub Telemetry', 'wm-suite-hub' ),
					'get_value_callback' => [ $this, 'resolve_block_binding' ],
					'uses_context'       => [ 'postId', 'postType' ],
				]
			);
		}

		if ( class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
			new \GenerateBlocks_Register_Dynamic_Tag(
				[
					'title'    => __( 'WM Suite Hub Telemetry', 'wm-suite-hub' ),
					'tag'      => self::DYNAMIC_TAG,
					'type'     => 'wm-suite',
					'supports' => [ 'source' ],
					'options'  => [
						'field' => [
							'type'    => 'select',
							'label'   => __( 'Telemetry Field', 'wm-suite-hub' ),
							'options' => [
								[
									'label' => __( 'Health Score (%)', 'wm-suite-hub' ),
									'value' => 'health_score',
								],
								[
									'label' => __( 'Global Status', 'wm-suite-hub' ),
									'value' => 'global_status',
								],
								[
									'label' => __( 'Active Spokes Count', 'wm-suite-hub' ),
									'value' => 'active_spokes',
								],
								[
									'label' => __( 'Hub Version', 'wm-suite-hub' ),
									'value' => 'version',
								],
							],
						],
					],
					'return'   => [ $this, 'render_dynamic_tag' ],
				]
			);
		}

		add_filter( 'generateblocks_dynamic_tag_replacement', [ $this, 'filter_generateblocks_dynamic_tag_output' ], 10, 2 );

		/**
		 * Fires after WM Suite Hub registers its Block Bindings and GenerateBlocks telemetry bridges.
		 *
		 * @param self $bridge Bridge instance.
		 */
		do_action( 'wm_suite_hub_telemetry_bridged', $this );
	}

	/**
	 * Resolves a single non-sensitive aggregate telemetry field.
	 *
	 * @param string $field Requested field key.
	 * @return string Escaped plain string value, or empty string when unknown.
	 */
	public function resolve_field( string $field ): string {
		$key      = sanitize_key( $field );
		$registry = Hub_Orchestrator::get_instance()->get_registry();
		// get_registry() discovers the modules when needed, so null is not expected here; if it ever is,
		// there is no status to show and the fields stay empty. Until 1.2.2 that branch read 100 and HEALTHY.
		$matrix = null !== $registry ? Health_Matrix::get_matrix( $registry ) : [];

		$fields = [
			'health_score'  => isset( $matrix['score'] ) ? (string) (int) $matrix['score'] : '',
			'global_status' => isset( $matrix['global_status'] ) ? (string) $matrix['global_status'] : '',
			'active_spokes' => null !== $registry ? (string) $registry->count() : '',
			'version'       => defined( 'WM_SUITE_HUB_VERSION' ) ? (string) WM_SUITE_HUB_VERSION : '',
		];

		/**
		 * Filters the non-sensitive aggregate telemetry map exposed to Block Bindings and GenerateBlocks.
		 *
		 * @param array<string, scalar> $fields Key/value map of telemetry fields.
		 */
		$filtered = (array) apply_filters( 'wm_suite_hub_telemetry_fields', $fields );

		if ( '' === $key || ! isset( $filtered[ $key ] ) || ! is_scalar( $filtered[ $key ] ) ) {
			return '';
		}

		return esc_html( (string) $filtered[ $key ] );
	}

	/**
	 * Callback for WordPress 6.5+ `register_block_bindings_source`.
	 *
	 * @param array<string, mixed> $source_args Binding arguments (`key` or `field`).
	 * @param mixed                $block       WP_Block instance or null in tests.
	 * @param string               $attr_name   Target block attribute name.
	 * @return string
	 */
	public function resolve_block_binding( array $source_args, $block = null, string $attr_name = 'content' ): string {
		$field = (string) ( $source_args['key'] ?? $source_args['field'] ?? 'health_score' );
		return $this->resolve_field( $field );
	}

	/**
	 * Callback for `GenerateBlocks_Register_Dynamic_Tag`.
	 *
	 * @param array<string, mixed> $options  Tag options (`field` or `key`).
	 * @param array<string, mixed> $block    Block array.
	 * @param mixed                $instance Block instance.
	 * @return string
	 */
	public function render_dynamic_tag( array $options = [], array $block = [], $instance = null ): string {
		$field = (string) ( $options['field'] ?? $options['key'] ?? 'health_score' );
		return $this->resolve_field( $field );
	}

	/**
	 * Fallback filter for `generateblocks_dynamic_tag_replacement` when `{{wm_hub_telemetry}}` is evaluated.
	 *
	 * @param mixed $output         Current dynamic tag output/replacement.
	 * @param mixed $tag_data       Tag descriptor (`['tag' => ..., 'options' => ...]`) or legacy tag string.
	 * @param mixed $options_or_raw Optional legacy options array or raw scalar output.
	 * @return mixed
	 */
	public function filter_generateblocks_dynamic_tag_output( mixed $output, mixed $tag_data = null, mixed $options_or_raw = null ): mixed {
		$tag_name = '';
		$options  = [];
		if ( is_string( $tag_data ) ) {
			$tag_name = $tag_data;
			$options  = is_array( $options_or_raw ) ? $options_or_raw : [];
		} elseif ( is_array( $tag_data ) ) {
			$tag_name = (string) ( $tag_data['tag'] ?? $tag_data['name'] ?? '' );
			if ( isset( $tag_data['options'] ) && is_array( $tag_data['options'] ) ) {
				$options = $tag_data['options'];
			}
		}

		if ( self::DYNAMIC_TAG !== $tag_name ) {
			return $output;
		}

		$field = (string) ( $options['field'] ?? $options['key'] ?? 'health_score' );
		return $this->resolve_field( $field );
	}
}
