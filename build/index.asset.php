<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'dependencies' => [
		'wp-block-editor',
		'wp-blocks',
		'wp-components',
		'wp-element',
		'wp-i18n',
		'wp-server-side-render',
	],
	// First 20 hex digits of the MD5 of build/index.js, as wp-scripts writes it, so the editor
	// fetches the script again whenever it changes (it stood at 1.1.5 through 1.2.1). TEST 21 checks it.
	'version'      => '9e081c2f8a5f4f242cdd',
];
