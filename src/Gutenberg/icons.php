<?php
/**
 * Inline SVG icons for the server-rendered blocks and, since 1.2.1, for the admin screens.
 *
 * The block templates used emoji (shield, pin, wheelchair, …) for their leading glyphs, and until
 * 1.2.1 the sidebar menu, the cockpit, the marketplace and the dashboard widgets carried 63 more.
 * Two reasons they are gone:
 *
 * 1. A deliverable carries SVG icons, never emoji (WM house rule). Emoji render as a different
 *    picture on every platform and as a coloured bitmap font on most, which no design token can
 *    control.
 * 2. Emoji are text. A screen reader announces "shield" or "wheelchair symbol" in the middle of a
 *    heading or a menu entry, and the wheelchair symbol in front of an accessibility statement is
 *    read aloud as its Unicode name — precisely the surface where that is least acceptable. These
 *    icons are decorative (`aria-hidden="true"`), so the accessible name is now the text alone.
 *
 * Paths are plain geometry drawn for this file; they inherit colour through `currentColor` and
 * size through the `em`-based width, so they follow the surrounding type.
 *
 * @package ArnoldWender\SuiteHub\Gutenberg
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wm_suite_hub_icon' ) ) {
	/**
	 * Returns a decorative inline SVG, or an empty string for an unknown name.
	 *
	 * @param string $name Icon identifier.
	 * @param string $size CSS length for width and height. Defaults to the current font size.
	 * @return string Escaped, ready-to-echo SVG markup.
	 */
	function wm_suite_hub_icon( string $name, string $size = '1em' ): string {
		$paths = [
			'accessibility' => '<circle cx="12" cy="4.5" r="1.8"/><path d="M5 8.5h14M12 8.5v5m0 0 3.5 6M12 13.5l-3.5 6"/>',
			'location'      => '<path d="M12 21s6.5-6 6.5-10.5a6.5 6.5 0 0 0-13 0C5.5 15 12 21 12 21z"/><circle cx="12" cy="10.5" r="2.3"/>',
			'calendar'      => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 9.5h17M8 3v4M16 3v4"/>',
			'shield'        => '<path d="M12 2.8 20 6v6c0 4.6-3.3 8.1-8 9.2-4.7-1.1-8-4.6-8-9.2V6z"/>',
			'document'      => '<path d="M6 2.8h8l4.5 4.5v13.9H6z"/><path d="M14 2.8v4.5h4.5M9 12h6M9 16h6"/>',
			'leaf'          => '<path d="M20 4c0 9-5 13-11 13a5 5 0 0 1 0-10c4 0 7-1 11-3z"/><path d="M4 21c2-6 6-9 11-11"/>',
			'megaphone'     => '<path d="M4 9.5v5l12 5.5V4z"/><path d="M16 8a4 4 0 0 1 0 8"/><path d="M7 15v4.5h3"/>',
			'search'        => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.2 15.2 21 21"/>',
			'lock'          => '<rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/>',
			'pause'         => '<rect x="7" y="4.5" width="3.5" height="15" rx="1"/><rect x="13.5" y="4.5" width="3.5" height="15" rx="1"/>',
			'key'           => '<circle cx="8" cy="8" r="4.5"/><path d="M11.2 11.2 20 20M17 17l2.5-2.5M14.5 14.5 17 12"/>',
			'check'         => '<circle cx="12" cy="12" r="9.2"/><path d="m7.8 12.2 2.9 2.9 5.5-5.9"/>',
			'tag'           => '<path d="M11.5 3H21v9.5L11 22.5 1.5 13z"/><circle cx="17" cy="7" r="1.6"/>',
			'stop'          => '<circle cx="12" cy="12" r="9.2"/><path d="M8.5 8.5 15.5 15.5M15.5 8.5 8.5 15.5"/>',
			'eye'           => '<path d="M1.8 12S5.8 5.5 12 5.5 22.2 12 22.2 12 18.2 18.5 12 18.5 1.8 12 1.8 12z"/><circle cx="12" cy="12" r="3"/>',
			'book'          => '<path d="M4 4.5h6a3 3 0 0 1 3 3v12a2.4 2.4 0 0 0-2.4-2.4H4z"/><path d="M20 4.5h-6a3 3 0 0 0-3 3v12a2.4 2.4 0 0 1 2.4-2.4H20z"/>',
			'trash'         => '<path d="M4.5 6.5h15M9.5 6.5V4h5v2.5"/><path d="M6.5 6.5 7.6 21h8.8l1.1-14.5"/><path d="M10.5 10.5v6.5M13.5 10.5v6.5"/>',
			'download'      => '<path d="M12 3.5v12M7.5 11 12 15.5 16.5 11"/><path d="M4.5 19.5h15"/>',
			// Admin screens (1.2.1): the shapes that replaced the emoji of the menu, the cockpit, the
			// marketplace and the dashboard widgets, on the same grid with the same pen.
			'chart'         => '<path d="M3.5 20.5h17"/><path d="M7 17v-6M12 17V6M17 17v-8.5"/>',
			'chain'         => '<rect x="2.5" y="8.5" width="11" height="7" rx="3.5"/><rect x="10.5" y="8.5" width="11" height="7" rx="3.5"/>',
			'bolt'          => '<path d="M13.5 2.8 5 13.2h6.2l-1.2 8 8.5-10.6h-6.2z"/>',
			'clipboard'     => '<rect x="5" y="4.5" width="14" height="16.5" rx="2"/><path d="M9 4.5V3h6v1.5M8.5 10h7M8.5 13.5h7M8.5 17h4.5"/>',
			'euro'          => '<path d="M17.5 6.2a7 7 0 1 0 0 11.6"/><path d="M4.5 10.2h9M4.5 13.8h9"/>',
			'ruler'         => '<path d="M3.5 17.5 17.5 3.5l3 3-14 14z"/><path d="M7.5 13.5l1.8 1.8M10.5 10.5l1.8 1.8M13.5 7.5l1.8 1.8"/>',
			'bulb'          => '<path d="M8.6 14.2a6.2 6.2 0 1 1 6.8 0c-.6.5-.9 1.2-.9 2v.8h-5v-.8c0-.8-.3-1.5-.9-2z"/><path d="M9.5 18.8h5M10.3 21h3.4"/>',
			'palette'       => '<path d="M12 3a9 9 0 1 0 0 18c1.2 0 1.8-.8 1.8-1.7 0-.5-.2-.9-.5-1.2-.3-.3-.5-.7-.5-1.2 0-1 .8-1.7 1.7-1.7H16a5 5 0 0 0 5-5C21 6.4 17 3 12 3z"/><circle cx="7.5" cy="11.5" r="1.1"/><circle cx="9.5" cy="7.5" r="1.1"/><circle cx="14.5" cy="7.5" r="1.1"/><circle cx="17" cy="11" r="1.1"/>',
			'sliders'       => '<path d="M4 7h9M17 7h3M4 17h3M11 17h9"/><circle cx="15" cy="7" r="2"/><circle cx="9" cy="17" r="2"/>',
			'moon'          => '<path d="M20 14.2A8.2 8.2 0 1 1 9.8 4a8 8 0 0 0 10.2 10.2z"/>',
			'network'       => '<circle cx="12" cy="5" r="2.2"/><circle cx="5" cy="18.5" r="2.2"/><circle cx="19" cy="18.5" r="2.2"/><path d="M12 7.2v4.3M12 11.5l-5.6 5.3M12 11.5l5.6 5.3"/>',
			'flask'         => '<path d="M9.5 3h5M10.5 3v6.2L4.8 18.6A1.6 1.6 0 0 0 6.2 21h11.6a1.6 1.6 0 0 0 1.4-2.4L13.5 9.2V3"/><path d="M7.5 15h9"/>',
			'circle'        => '<circle cx="12" cy="12" r="8.5"/>',
			'landmark'      => '<path d="M3.5 9.5 12 4.5l8.5 5"/><path d="M5 9.5h14M6.5 9.5v8M10.2 9.5v8M13.8 9.5v8M17.5 9.5v8M5 17.5h14M4 20.5h16"/>',
			'scale'         => '<path d="M12 3v17M7 20h10M5 7h14"/><path d="M5 7l-3 6.5a3 3 0 0 0 6 0z"/><path d="M19 7l-3 6.5a3 3 0 0 0 6 0z"/>',
			'package'       => '<path d="M12 3l8 4.5v9L12 21l-8-4.5v-9z"/><path d="M4 7.5l8 4.5 8-4.5M12 12v9"/>',
			'globe'         => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
			'mail'          => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 6.5 12 13l8.5-6.5"/>',
			'ticket'        => '<path d="M3 7h18v3a2 2 0 0 0 0 4v3H3v-3a2 2 0 0 0 0-4z"/><path d="M14 8v1.5M14 11.2v1.6M14 14.5V16"/>',
			'alert'         => '<path d="M12 3.5 22 20H2z"/><path d="M12 10v4.5M12 17.5v.5"/>',
		];

		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}

		return sprintf(
			'<svg class="wm-block-icon" width="%1$s" height="%1$s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
			esc_attr( $size ),
			$paths[ $name ]
		);
	}
}

if ( ! function_exists( 'wm_suite_hub_icon_allowed_html' ) ) {
	/**
	 * The wp_kses() allowlist for wm_suite_hub_icon(): exactly the elements and attributes the icon
	 * map above uses, nothing else.
	 *
	 * Every template echoes the icon through wp_kses( wm_suite_hub_icon( … ), this ). The SVG is built
	 * from a fixed map and is already safe, but Plugin Check cannot know what a function returns, and
	 * it reported the 22 bare `echo wm_suite_hub_icon()` calls as OutputNotEscaped (measured
	 * 2026-09-17). Escaping at the echo is the WordPress rule, so the fix is the escaper, not a
	 * phpcs:ignore.
	 *
	 * wp_kses() lower-cases attribute names, so `viewBox` leaves PHP as `viewbox`. That is harmless
	 * in a text/html page: the HTML parser adjusts SVG attribute names back (viewbox → viewBox).
	 * Measured in Chromium, not assumed — tools/svg-kses-dom-probe.py renders the original, the
	 * kses output and a control with no viewBox, and fails if the first two differ or the third
	 * does not.
	 *
	 * @return array<string, array<string, bool>>
	 */
	function wm_suite_hub_icon_allowed_html(): array {
		return [
			'svg'    => [
				'class'           => true,
				'width'           => true,
				'height'          => true,
				'viewbox'         => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'aria-hidden'     => true,
				'focusable'       => true,
			],
			'circle' => [ 'cx' => true, 'cy' => true, 'r' => true ],
			'rect'   => [ 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true ],
			'path'   => [ 'd' => true ],
		];
	}
}
