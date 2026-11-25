# Security Policy — WM Suite Hub (Enterprise Cockpit)

## Supported Versions

| Version | Supported |
| --- | --- |
| `1.2.x` (`1.2.6`) | Current release branch |

---

## Reporting a Vulnerability

Please **do not** open a public GitHub issue for security vulnerabilities, and **do not** send private inquiries or support emails to `arnoldwender.com`, email addresses, or social/direct messages (private channels are unmonitored for open-source plugins).

Submit security reports **exclusively** via GitHub Private Vulnerability Reporting:

- **Security Advisories:** [github.com/arnoldwender/wm-wp-suite-hub/security/advisories/new](https://github.com/arnoldwender/wm-wp-suite-hub/security/advisories/new)

> **Best-Effort Only / Zero Warranty / Zero Support:** This open-source repository carries **zero technical or user support** ([SUPPORT.md](SUPPORT.md)) and **zero response-time SLA**. Advisory reports may be reviewed at the author's sole discretion without obligation, timeline, or liability ("AS IS" under [LICENSE](LICENSE)).

### What to include

1. Reproduction steps or a proof of concept.
2. The affected component: a `wm-hub/v1` route, the Borlabs consent bridge (`wm-suite/v1/consent-sync`), the cockpit screen or its script, the audit chain, the marketplace pre-flight and installer, the WP-CLI commands, or the spoke dispatcher.
3. The impact you observed (for example an event sealed into the chain from an unauthenticated request, a capability bypass on a cockpit route, or stored XSS in the cockpit).

### Attack surface

- **Public:** `POST /wm-suite/v1/consent-sync` (only with Borlabs Cookie active; REST nonce, 10 requests per minute per client), the server-rendered Gutenberg blocks.
- **`manage_wm_suite` only:** every `wm-hub/v1` route, the cockpit screen and the dashboard widget.
- **Stored data:** one table, `{prefix}wm_suite_audit_log`. Anyone with the site key (`wp_salt( 'auth' )`) can rewrite the chain consistently; the chain detects changes made without it.

Reports are acknowledged as soon as they are read; there is no contractual response time.
