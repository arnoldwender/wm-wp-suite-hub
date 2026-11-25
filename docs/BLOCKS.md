# The 16 Gutenberg blocks

Every block in this file is registered by [`src/Gutenberg/Block_Manager.php`](../src/Gutenberg/Block_Manager.php),
declares `"editorScript": "wm-suite-hub-blocks-editor"` (`build/index.js`, a byte copy of `blocks-src/index.js`)
with `InspectorControls` and a `ServerSideRender` preview, and renders server-side from
`src/Gutenberg/Blocks/<dir>/render.php`. They appear in the Block Inserter under the category
**WM Enterprise Suite** (`wm-suite`).

In addition, [`src/Gutenberg/Telemetry_Bridge.php`](../src/Gutenberg/Telemetry_Bridge.php) registers the
WordPress 6.5+ Block Bindings source `wm-suite-hub/telemetry` and the GenerateBlocks 2.x Dynamic Tag
`wm_hub_telemetry` (`health_score`, `global_status`, `active_spokes`, `version`), so core Paragraph, Heading and
Button blocks and GenerateBlocks elements can show the hub's aggregate status. A field the hub cannot compute is
empty, never a value that reads as healthy.

Measured 2026-11-21 (`v1.2.2`):

```bash
find src/Gutenberg/Blocks -name block.json | wc -l                                      # 16
grep -cE "^\s+'wm/[a-z0-9-]+'\s+=> '" src/Gutenberg/Block_Manager.php                   # the same 16 in the registry
grep -l "do_shortcode" src/Gutenberg/Blocks/*/render.php | wc -l                         # 4 show another plugin's output
grep -l "wm_suite_hub_[a-z_]*notice" src/Gutenberg/Blocks/*/render.php | wc -l           # 13 can be incomplete
```

---

## Read this before you publish one of these blocks

**No block states that a site complies with a law, and none computes or checks what it shows.** Each block
prints one of three things:

- **Another plugin's output.** Four blocks show what a WM Suite plugin renders through its shortcode: the
  reporting portal of WM HinSchG Whistleblower Desk, the erasure form of WM DSGVO Privacy Eraser, the
  accessibility statement of WM BFSG Compliance Dashboard and the consent button of WM Sovereign Consent Vault.
  The consent badge opens that vault's dialog.
- **Data a plugin reports.** `wm/event-grid` lists the active events of WM Cyber Events Core.
- **What you type.** The other ten print the values entered in the block inspector. Nothing in them is read
  live: a seat count, a percentage or a note is copy you assert, so set it from a source you can verify.

**13 blocks render nothing for visitors** until their plugin is active or their required setting is filled in.
Editors see a dashed notice that says what is missing instead
([`src/Gutenberg/block-notice.php`](../src/Gutenberg/block-notice.php)); the notice is shown to users who can
`edit_posts`, which includes the editor preview, since the REST block renderer runs as the editing user.

The plugin makes no legal assessment; [README.md](../README.md) says so and is the measured document.

### What 1.2.1 printed that 1.2.2 no longer does

Until 1.2.1 several blocks printed statements of their own on public pages. They are gone, together with the
attributes that fed them. A post that saved one of those attributes keeps it in its block comment; the render
ignores it.

| Block | Printed until 1.2.1 | Since 1.2.2 |
| --- | --- | --- |
| HinSchG portal (`whistleblower-wizard`) | its own three-step form that sent nothing, «Meldung erfolgreich verschlüsselt!» and a random key no office could look up | the portal of WM HinSchG Whistleblower Desk |
| `wm/whistleblower-card` | «HinSchG § 12 konform», client-side RSA-4096 and no IP logging, a «Status abfragen» link to an undefined anchor | one link to the reporting channel |
| `wm/bfsg-statement-embed` | its own statement: «erfüllt die Anforderungen nach EN 301 549 … (WCAG 2.2 AA)», «Automatisch verifiziert» | the statement WM BFSG Compliance Dashboard builds |
| `wm/privacy-center` | three switches no script read, «Konform nach TDDDG § 25 … revisionssicher» | the vault's button that opens its consent dialog |
| `wm/consent-badge` | «DSGVO & TDDDG Versiegelt» | «Datenschutz-Einstellungen», opening the vault's dialog |
| `wm/privacy-eraser-form` | its own form whose address field had no name, so it posted an empty request | the form of WM DSGVO Privacy Eraser |
| `wm/cra-security-status` | «EU Cyber Resilience Act (CRA)», a «BSI TR-03183» badge, «werden kryptografisch auditiert» | a download link for the SBOM file you publish |
| `wm/event-booking-cta` | a link to `/kasse/?wm_book_event=`, which no plugin provides, and «Preise zzgl. 19% MwSt. · Konforme E-Rechnung» | a link to the booking page you enter, with an optional note |
| `wm/peppol-id-verifier` | «Gültige Peppol / GEBA ID» for any well-formed ID, and XRechnung 4.0 delivery | «Format gültig (Schema:ID)»; it looks nothing up |
| `wm/script-placeholder` | «wurde blockiert … nach TDDDG § 25 vorab blockiert» while only un-hiding an element | a load button that inserts a `<template>` |
| `wm/newsticker-banner` | three invented headlines until somebody replaced them | your own text, or nothing |
| `wm/lksg-esg-scorecard` | «Transparenzbericht über Tier-1 und Tier-2 Auditprüfungen gemäß §§ 4-10 LkSG» | the three numbers typed in |
| `wm/event-card` / `wm/event-grid` | «Jetzt verbindlich buchen» to `#book-event` or `#booking`, a disabled «Warteliste» | a link to the booking or event page, or no button |
| `wm/modular-type-container` | a demo text on the public page | nothing, until content is written into it |

---

## Events and e-invoicing

### `wm/event-grid` — WM Event Grid Container

Lists the active events of WM Cyber Events Core (`CyberEvents_Database::get_active_events()`), each with a link
to its event page when the event has one. Without that plugin, or while it reports no active event, visitors
see nothing. The capacity line shows `remaining_capacity` when the events table provides it, otherwise
`max_capacity` as «Max. N Plätze».

| Attribute | Type | Default | Effect |
| --- | --- | --- | --- |
| `columns` | number | `3` | Columns at desktop width (the `--wm-grid-cols` custom property); narrow screens wrap to fewer |
| `limit` | number | `6` | Maximum number of events listed |

Until 1.2.2 a `categoryFilter` setting filtered on a `category` field the events table does not have; it is gone.

### `wm/event-card` — WM Atomic Event Card

One event tile with the values you type. Visitors see nothing while `title` is empty.

| Attribute | Type | Default |
| --- | --- | --- |
| `title` | string | `''` |
| `category` | string | `''` |
| `date` | string | `''` |
| `location` | string | `''` |
| `capacityRemaining` | number | `-1` |
| `price` | string | `''` |
| `bookingUrl` | string | `''` |

`capacityRemaining` is a number you type (`-1` hides the seat line, `0` reads «Ausgebucht»). The «Zur Buchung»
button appears only with a `bookingUrl` and while the card is not sold out. For seat counts read from the
booking tables, use the blocks of WM Cyber Events Core.

### `wm/event-booking-cta` — WM Event Booking CTA Button

A button to the booking page you enter, with an optional note under it. Visitors see nothing until
`bookingUrl` is set.

| Attribute | Type | Default |
| --- | --- | --- |
| `bookingUrl` | string | `''` |
| `buttonText` | string | `''` (renders «Zur Buchung») |
| `taxNote` | string | `''` (no note) |

A price statement in `taxNote` is a statement you make; PAngV decides what it has to say.

### `wm/peppol-id-verifier` — WM Peppol ID Format Check

Checks in the browser whether an ID has the form Scheme:ID (four digits, a colon, an identifier:
`^[0-9]{4}:[A-Za-z0-9\-_.]+$`) and answers «Format gültig (Schema:ID)» or «Ungültiges ID-Format».

| Attribute | Type | Default |
| --- | --- | --- |
| `heading` | string | `Peppol E-Rechnung Identifikator prüfen` |
| `placeholder` | string | `z.B. 0208:0843657437 oder 9958:DE...` |

It does not ask the Peppol network whether the participant exists. SMP discovery lives in
`wm-peppol-as4-gateway` (`wp peppol lookup`).

---

## Privacy and consent

### `wm/consent-badge` — WM Consent Settings Badge

A link that opens the consent dialog of WM Sovereign Consent Vault: the vault's script opens it for any element
with `data-wm-consent-open` and for links to `#wm-consent-settings`. Visitors see nothing without the vault.

| Attribute | Type | Default |
| --- | --- | --- |
| `label` | string | `''` (renders «Datenschutz-Einstellungen») |

### `wm/privacy-center` — WM Sovereign Privacy Center

A heading and the vault's button that opens its consent dialog (`[wm_consent_settings]`), where the visitor's
choice is stored. Visitors see nothing without the vault.

| Attribute | Type | Default |
| --- | --- | --- |
| `heading` | string | `''` (renders «Datenschutz-Einstellungen») |

### `wm/privacy-eraser-form` — WM DSGVO Art. 17 Privacy Eraser Form

The erasure request form of WM DSGVO Privacy Eraser (`[wm_dsgvo_erasure_portal]`), which posts the address to
`wm-dsgvo-eraser/v1/request` and mails a confirmation link. No attributes; visitors see nothing without that
plugin.

### `wm/script-placeholder` — WM External Content Placeholder

A placeholder with a load button for external content. Visitors see nothing until `targetElementId` is set.

| Attribute | Type | Default |
| --- | --- | --- |
| `serviceName` | string | `''` (no heading) |
| `targetElementId` | string | `''` |

Put the embed into a `<template>` element with that ID, for example in a Custom HTML block:

```html
<template id="video-1"><iframe src="https://www.youtube-nocookie.com/embed/…" title="…"></iframe></template>
```

Nothing inside a `<template>` is fetched before the button inserts it, so the provider receives nothing before
the click. If the ID names an ordinary element instead, the button only shows it again — an iframe in it has
loaded with the page already, and the placeholder protects nothing.

---

## Whistleblowing and ESG

### `wm/whistleblower-card` — WM HinSchG Whistleblower Portal Card

One link to the reporting channel: by default to the portal block on the same page (`#whistleblower-wizard`),
or to the address you enter.

| Attribute | Type | Default |
| --- | --- | --- |
| `companyName` | string | `''` (no heading) |
| `channelUrl` | string | `''` (links to `#whistleblower-wizard`) |

Without the portal block on the page and without a `channelUrl`, the link goes nowhere.

### `wm/whistleblower-wizard` — WM HinSchG Whistleblower Portal

The reporting portal of WM HinSchG Whistleblower Desk (`[wm_hinschg_portal]`), which stores the report and
issues the case ID and access key. It carries the id `whistleblower-wizard`, the default target of the card
above. No attributes; visitors see nothing without that plugin.

### `wm/lksg-esg-scorecard` — WM LkSG ESG Supply Chain Scorecard

Three percentages you type, under a supply chain heading; supplied values are clamped to `0..100`. The block
computes and checks nothing and never queries `wm-lksg-supply-auditor`. Visitors see nothing while all three
are `-1`.

| Attribute | Type | Default |
| --- | --- | --- |
| `humanRightsScore` | number | `-1` |
| `environmentScore` | number | `-1` |
| `governanceScore` | number | `-1` |

### `wm/cra-security-status` — WM SBOM Download

A download link for the CycloneDX SBOM. Visitors see nothing until `sbomUrl` is set.

| Attribute | Type | Default |
| --- | --- | --- |
| `version` | string | `''` (shown after the heading when set) |
| `sbomUrl` | string | `''` — a file you publish yourself |

**The SBOM is produced on the command line, not by a public route:**

```bash
wp wm-hub sbom --output=/var/www/html/sbom.json
```

There is no public SBOM endpoint in this plugin, and `GET /wp-json/wm-hub/v1/*` requires `manage_wm_suite`.
Whether the fleet's exact component versions should be public at all is a decision for the site owner — it is a
precise inventory for anyone looking for a known vulnerability. The SBOM is an inventory; it does not establish
conformity with the Cyber Resilience Act.

---

## Accessibility and typography

### `wm/a11y-contrast-switch` — WM Accessibility Toolbar

Up to three groups of buttons, each switchable off. The choice is kept in the visitor's browser
(`localStorage`: `wm_a11y_contrast`, `wm_a11y_dyslexic`, `wm_a11y_scale`).

| Attribute | Type | Default | What the button changes |
| --- | --- | --- | --- |
| `showHighContrast` | boolean | `true` | the `--wm-*` colour tokens, so only the WM Suite blocks switch to black, white, yellow and cyan |
| `showDyslexicFont` | boolean | `true` | the root font family to OpenDyslexic if the visitor has it installed, else Comic Sans MS; elements with a font of their own keep it |
| `showTextScaler` | boolean | `true` | the root font size to 120 % or 140 % (A+, A++), and back |

The plugin ships no font file. Accessibility is a property of the whole site, not of a toolbar placed on it.

### `wm/bfsg-statement-embed` — WM Accessibility Statement

The accessibility statement WM BFSG Compliance Dashboard builds from the operator's settings
(`[wm_bfsg_statement]`). No attributes; visitors see nothing without that plugin. Its content — which
requirements the site meets and which barriers are known — comes from those settings; neither plugin audits
the site.

### `wm/newsticker-banner` — WM Newsticker

One line of your own text scrolling past. It stops on hover and with its pause button, and for visitors who ask
for reduced motion it stands still and wraps. Visitors see nothing while `tickerText` is empty.

| Attribute | Type | Default |
| --- | --- | --- |
| `badgeText` | string | `EILMELDUNG` (an empty value hides the badge) |
| `tickerText` | string | `''` |

For headlines pulled from posts, use `wm-newsticker`, which has its own block with a dynamic source.

### `wm/modular-type-container` — WM Modular Scale Fluid Type Container

A container meant for a modular type scale. In this version its editor takes no inner blocks and no
stylesheet reads the two custom properties it sets (`--wm-scale-ratio`, `--wm-font-size-root`), so it has no
effect on a page. Visitors see nothing unless content is written into the block in the code editor, which it
prints.

| Attribute | Type | Default |
| --- | --- | --- |
| `scaleRatio` | string | `1.25` |
| `baseSize` | string | `1rem` |

The full type system, with the blocks that consume it, is `wm-modularscale`.

---

## How a block is added

1. `src/Gutenberg/Blocks/<slug>/block.json` — `apiVersion` 3, `category: wm-suite`, `editorScript`
   `wm-suite-hub-blocks-editor`, the plugin version.
2. `src/Gutenberg/Blocks/<slug>/render.php` — receives `$attributes` and `$content`; echoes escaped HTML. A block
   that needs a plugin or a setting returns `wm_suite_hub_missing_plugin_notice()` or
   `wm_suite_hub_unconfigured_notice()` from `src/Gutenberg/block-notice.php` while it is incomplete: editors
   read what is missing, visitors get nothing.
3. One line in the `$blocks` array of
   [`src/Gutenberg/Block_Manager.php`](../src/Gutenberg/Block_Manager.php#L35-L59). **A block directory that is
   not in that array is never registered**, even with a valid `block.json`.
4. Its controls in `blocks-src/index.js`, copied byte for byte to `build/index.js`, and the first 20 hex digits
   of that file's MD5 as `version` in `build/index.asset.php`. Every control must be an attribute of `block.json`
   that `render.php` reads; `tests/test-suite.php` checks both directions.
5. An assertion in `tests/test-suite.php` and an entry in `tests/mutations.php`.

A missing `render.php` does not fatal: `render_block_ssr()` returns an HTML comment
(`<!-- WM Block [wm/x] Render template missing -->`), so the block silently renders nothing on the page. Check
the page source if a block looks absent.
