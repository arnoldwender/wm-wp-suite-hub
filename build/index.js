( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.element || ! wp.blockEditor || ! wp.components ) {
		return;
	}

	var registerBlockType  = wp.blocks.registerBlockType;
	var el                 = wp.element.createElement;
	var Fragment           = wp.element.Fragment;
	var useBlockProps      = wp.blockEditor.useBlockProps;
	var InspectorControls  = wp.blockEditor.InspectorControls;
	var PanelBody          = wp.components.PanelBody;
	var TextControl        = wp.components.TextControl;
	var ToggleControl      = wp.components.ToggleControl;
	var RangeControl       = wp.components.RangeControl;
	var __                 = wp.i18n ? wp.i18n.__ : function ( s ) { return s; };
	var ServerSideRender   = wp.serverSideRender || ( wp.components && wp.components.ServerSideRender );

	function renderFieldControl( field, attributes, setAttributes ) {
		var key = field.key;
		var val = attributes[ key ];

		if ( field.type === 'boolean' ) {
			return el( ToggleControl, {
				key: key,
				label: field.label,
				checked: Boolean( val ),
				onChange: function ( next ) {
					var patch = {};
					patch[ key ] = Boolean( next );
					setAttributes( patch );
				},
				__nextHasNoMarginBottom: true
			} );
		}

		if ( field.type === 'number' ) {
			return el( RangeControl, {
				key: key,
				label: field.label,
				value: typeof val === 'number' ? val : field.fallback,
				min: field.min,
				max: field.max,
				onChange: function ( next ) {
					var patch = {};
					patch[ key ] = typeof next === 'number' ? next : field.fallback;
					setAttributes( patch );
				},
				__nextHasNoMarginBottom: true
			} );
		}

		return el( TextControl, {
			key: key,
			label: field.label,
			value: typeof val === 'string' ? val : '',
			onChange: function ( next ) {
				var patch = {};
				patch[ key ] = String( next || '' );
				setAttributes( patch );
			},
			__nextHasNoMarginBottom: true
		} );
	}

	function createSuiteBlock( blockName, fields ) {
		return {
			edit: function EditSuiteBlock( props ) {
				var blockProps    = useBlockProps();
				var attributes    = props.attributes;
				var setAttributes = props.setAttributes;

				// Blocks that show another plugin's output have nothing to set; an empty panel would
				// suggest settings that do not exist.
				return el(
					Fragment,
					null,
					fields.length ? el(
						InspectorControls,
						null,
						el(
							PanelBody,
							{ title: __( 'Block-Einstellungen', 'wm-suite-hub' ), initialOpen: true },
							fields.map( function ( field ) {
								return renderFieldControl( field, attributes, setAttributes );
							} )
						)
					) : null,
					el(
						'div',
						blockProps,
						ServerSideRender
							? el( ServerSideRender, {
								block: blockName,
								attributes: attributes
							} )
							: el( 'p', null, blockName )
					)
				);
			},
			save: function () {
				return null;
			}
		};
	}

	registerBlockType( 'wm/event-grid', createSuiteBlock( 'wm/event-grid', [
		{ key: 'columns', type: 'number', label: __( 'Spaltenanzahl', 'wm-suite-hub' ), min: 1, max: 6, fallback: 3 },
		{ key: 'limit', type: 'number', label: __( 'Maximale Einträge', 'wm-suite-hub' ), min: 1, max: 50, fallback: 6 }
	] ) );

	registerBlockType( 'wm/event-card', createSuiteBlock( 'wm/event-card', [
		{ key: 'title', type: 'string', label: __( 'Veranstaltungstitel', 'wm-suite-hub' ) },
		{ key: 'category', type: 'string', label: __( 'Kategorie', 'wm-suite-hub' ) },
		{ key: 'date', type: 'string', label: __( 'Datum', 'wm-suite-hub' ) },
		{ key: 'location', type: 'string', label: __( 'Veranstaltungsort', 'wm-suite-hub' ) },
		{ key: 'capacityRemaining', type: 'number', label: __( 'Freie Plätze', 'wm-suite-hub' ), min: -1, max: 500, fallback: -1 },
		{ key: 'price', type: 'string', label: __( 'Preis', 'wm-suite-hub' ) },
		{ key: 'bookingUrl', type: 'string', label: __( 'Buchungsseite (URL)', 'wm-suite-hub' ) }
	] ) );

	registerBlockType( 'wm/event-booking-cta', createSuiteBlock( 'wm/event-booking-cta', [
		{ key: 'bookingUrl', type: 'string', label: __( 'Buchungsseite (URL)', 'wm-suite-hub' ) },
		{ key: 'buttonText', type: 'string', label: __( 'Button-Beschriftung', 'wm-suite-hub' ) },
		{ key: 'taxNote', type: 'string', label: __( 'Hinweis unter dem Button', 'wm-suite-hub' ) }
	] ) );

	registerBlockType( 'wm/peppol-id-verifier', createSuiteBlock( 'wm/peppol-id-verifier', [
		{ key: 'heading', type: 'string', label: __( 'Überschrift', 'wm-suite-hub' ) },
		{ key: 'placeholder', type: 'string', label: __( 'Platzhaltertext', 'wm-suite-hub' ) }
	] ) );

	registerBlockType( 'wm/consent-badge', createSuiteBlock( 'wm/consent-badge', [
		{ key: 'label', type: 'string', label: __( 'Badge-Beschriftung', 'wm-suite-hub' ) }
	] ) );

	registerBlockType( 'wm/privacy-center', createSuiteBlock( 'wm/privacy-center', [
		{ key: 'heading', type: 'string', label: __( 'Überschrift', 'wm-suite-hub' ) }
	] ) );

	registerBlockType( 'wm/privacy-eraser-form', createSuiteBlock( 'wm/privacy-eraser-form', [] ) );

	registerBlockType( 'wm/script-placeholder', createSuiteBlock( 'wm/script-placeholder', [
		{ key: 'serviceName', type: 'string', label: __( 'Dienstname', 'wm-suite-hub' ) },
		{ key: 'targetElementId', type: 'string', label: __( 'Ziel-Element-ID', 'wm-suite-hub' ) }
	] ) );

	registerBlockType( 'wm/whistleblower-card', createSuiteBlock( 'wm/whistleblower-card', [
		{ key: 'companyName', type: 'string', label: __( 'Unternehmensname', 'wm-suite-hub' ) },
		{ key: 'channelUrl', type: 'string', label: __( 'Link zur Meldestelle', 'wm-suite-hub' ) }
	] ) );

	registerBlockType( 'wm/whistleblower-wizard', createSuiteBlock( 'wm/whistleblower-wizard', [] ) );

	registerBlockType( 'wm/lksg-esg-scorecard', createSuiteBlock( 'wm/lksg-esg-scorecard', [
		{ key: 'humanRightsScore', type: 'number', label: __( 'Menschenrechte (%)', 'wm-suite-hub' ), min: -1, max: 100, fallback: -1 },
		{ key: 'environmentScore', type: 'number', label: __( 'Umweltschutz (%)', 'wm-suite-hub' ), min: -1, max: 100, fallback: -1 },
		{ key: 'governanceScore', type: 'number', label: __( 'Governance (%)', 'wm-suite-hub' ), min: -1, max: 100, fallback: -1 }
	] ) );

	registerBlockType( 'wm/cra-security-status', createSuiteBlock( 'wm/cra-security-status', [
		{ key: 'version', type: 'string', label: __( 'Versionskennzeichnung', 'wm-suite-hub' ) },
		{ key: 'sbomUrl', type: 'string', label: __( 'SBOM-Download-URL', 'wm-suite-hub' ) }
	] ) );

	registerBlockType( 'wm/a11y-contrast-switch', createSuiteBlock( 'wm/a11y-contrast-switch', [
		{ key: 'showHighContrast', type: 'boolean', label: __( 'Kontrastschalter anzeigen', 'wm-suite-hub' ) },
		{ key: 'showDyslexicFont', type: 'boolean', label: __( 'Leseschrift-Schalter anzeigen', 'wm-suite-hub' ) },
		{ key: 'showTextScaler', type: 'boolean', label: __( 'Schriftgrößen-Schalter anzeigen', 'wm-suite-hub' ) }
	] ) );

	registerBlockType( 'wm/bfsg-statement-embed', createSuiteBlock( 'wm/bfsg-statement-embed', [] ) );

	registerBlockType( 'wm/newsticker-banner', createSuiteBlock( 'wm/newsticker-banner', [
		{ key: 'badgeText', type: 'string', label: __( 'Badge-Text', 'wm-suite-hub' ) },
		{ key: 'tickerText', type: 'string', label: __( 'Ticker-Meldungen', 'wm-suite-hub' ) }
	] ) );

	registerBlockType( 'wm/modular-type-container', createSuiteBlock( 'wm/modular-type-container', [
		{ key: 'scaleRatio', type: 'string', label: __( 'Skalierungsfaktor', 'wm-suite-hub' ) },
		{ key: 'baseSize', type: 'string', label: __( 'Basisschriftgröße', 'wm-suite-hub' ) }
	] ) );
} )( window.wp );
