# WM Suite Compliance SDK

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777bb4.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL%20v2%20or%20later-green.svg)](LICENSE)
[![Zero-Dependencies](https://img.shields.io/badge/Dependencies-0%20External-blue.svg)](#)

> **Zero-dependency facade** for WordPress plugins and themes that use WM Suite Hub: write an event to its audit table (an append-only HMAC-SHA256 chain), ask for consent, render a click-to-load placeholder and mask personal data in a prompt. Every call does nothing when the hub is inactive. It makes no legal assessment.

---

## Quick Start

### Installation

```bash
composer require arnoldwender/compliance-sdk
```

### 1. Writing an event to the hub's audit table

```php
use ArnoldWender\ComplianceSDK\Compliance;
use ArnoldWender\ComplianceSDK\Enums\EventSeverity;

// Appends one entry to the hub's HMAC-SHA256 chain when WM Suite Hub is active;
// does nothing when it is not.
Compliance::audit(
    eventType: 'order.completed',
    context: [
        'order_id' => 1042,
        'total'    => 149.99,
        'currency' => 'EUR',
    ],
    severity: EventSeverity::INFO,
    actorId: (string) get_current_user_id(),
    originModule: 'my-custom-store'
);
```

### 2. Checking consent

The hub answers from WM Sovereign Consent Vault when it is active, otherwise from the Borlabs Cookie cookie (the category is a Borlabs service group; `external_media` also matches `external-media`).

```php
use ArnoldWender\ComplianceSDK\Compliance;
use ArnoldWender\ComplianceSDK\Enums\ConsentCategory;

if (Compliance::hasConsent(ConsentCategory::MARKETING)) {
    // Load tracking pixel or marketing asset
}
```

### 3. Rendering 2-Click Blocked Embeds

```php
use ArnoldWender\ComplianceSDK\Compliance;
use ArnoldWender\ComplianceSDK\Enums\ConsentCategory;

echo Compliance::renderBlockedPlaceholder(
    serviceName: 'Google Maps',
    category: ConsentCategory::EXTERNAL_MEDIA,
    previewImageUrl: 'https://example.com/assets/map-preview.jpg',
    fallbackHtml: '<p>Bitte Cookies aktivieren.</p>'
);
```

### 4. Sanitizing PII before LLM / AI Dispatches

```php
use ArnoldWender\ComplianceSDK\Compliance;

$userPrompt = "Kunde Max Mustermann hat IBAN DE89370400440532013000 und Telefon 015112345678.";
$safePrompt = Compliance::sanitizePrompt($userPrompt);

// $safePrompt -> "Kunde Max Mustermann hat IBAN [ANONYMIZED_IBAN_DE] und Telefon [ANONYMIZED_PHONE]."
```

---

## License

GPL v2 or later. Copyright © 2026 Arnold Wender.
