# Contributing to WM Suite Hub

Thank you for your interest in contributing to **WM Suite Hub**. As part of the **WM Enterprise Suite**, this repository adheres to high standards of security, accessibility, and legal compliance.

---

## Zero Support & Issue Scope

**Zero technical, configuration, or private support is provided.** See [SUPPORT.md](SUPPORT.md):

- Report reproducible code defects **exclusively** via public [GitHub Issues](https://github.com/arnoldwender/wm-wp-suite-hub/issues).
- **Do not** contact the author privately via email, `arnoldwender.com`, or direct messages — private inquiries are never answered.
- Issues and pull requests carry no response or maintenance SLA, and the software is provided strictly **"AS IS"** without warranty or liability.

---

## Core Development Standards

1. **PHP 8.2+ Strict Typing:** Every PHP file starts with `declare(strict_types=1);` (the header declares `Requires PHP: 8.2`; CI runs 8.2 and 8.3).
2. **Zero-CDN Architecture:** No external resources (CDNs, Google Fonts, external telemetry) may be loaded at runtime.
3. **Audit chain invariants:** every event is sealed with `HMAC-SHA256` over the previous hash and the payload; a change to what is sealed needs a migration and a test that verification still names the broken row.
4. **Measured documentation:** `README.md` and `DEVELOPERS.md` describe what the code registers. A command, route, table or hook that `grep` does not find does not go into them.
5. **Design family:** `assets/css/wm-admin-tokens.css` and `assets/css/wm-admin-ui.css` are canonical here; every change is propagated with `wp-plugin-fleet-ui-sync.py` and measured with `wp-plugin-fleet-token-contrast.py` (4.5:1 for body text, 44px targets, visible focus).

---

## Development Workflow

```bash
# 1. Clone the repository
git clone https://github.com/arnoldwender/wm-wp-suite-hub.git
cd wm-suite-hub

# 2. Run the standalone suites (no WordPress needed)
php tests/test-suite.php

# 3. Undo every correction and require the suite to notice
bash tests/run-mutations.sh
```

`composer.json` declares no scripts; a coding-standard run needs `phpcs` installed separately.

---

## Commit Conventions

- Use conventional format: `[Feature] ...`, `[Fix] ...`, `[Security] ...`, `[Docs] ...`.
- All pull requests must include updated assertions in `tests/test-suite.php`.

For support and liability rules, read [SUPPORT.md](SUPPORT.md).
