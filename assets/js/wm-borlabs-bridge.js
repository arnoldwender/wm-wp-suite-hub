/**
 * WM Suite Hub — Borlabs Cookie bridge.
 *
 * Tells the hub each time the visitor saves a decision in Borlabs Cookie, so the route
 * wm-suite/v1/consent-sync can record it in the audit table. The decision itself is read on the
 * server from the borlabs-cookie cookie this same-origin request carries: Borlabs 3.4 writes that
 * cookie before it fires borlabs-cookie-consent-saved, and the event's detail is empty. The REST URL
 * and the nonce arrive through wp_localize_script as window.wmBorlabsBridge; without them the bridge
 * does nothing.
 */
(function () {
    'use strict';

    var cfg = window.wmBorlabsBridge || {};
    if (!cfg.restUrl || !cfg.nonce) {
        return;
    }

    // Storage throws where the browser blocks site data; the sync must still run.
    function readUuid() {
        try { return localStorage.getItem('wm_consent_uuid'); } catch (e) { return null; }
    }

    function storeUuid(uuid) {
        try { localStorage.setItem('wm_consent_uuid', uuid); } catch (e) { /* not kept, harmless */ }
    }

    function syncConsent() {
        if (!window.fetch) {
            return;
        }
        var payload = {
            uuid: readUuid(),
            borlabs_version: (window.BorlabsCookie && window.BorlabsCookie.version) ? String(window.BorlabsCookie.version) : '3.x'
        };

        fetch(cfg.restUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
            body: JSON.stringify(payload),
            keepalive: true
        }).then(function (res) { return res.json(); }).then(function (data) {
            if (data && data.session_uuid && !readUuid()) {
                storeUuid(data.session_uuid);
            }
        }).catch(function () {});
    }

    // Borlabs Cookie 3.x (measured against 3.4).
    document.addEventListener('borlabs-cookie-consent-saved', syncConsent);

    // Borlabs Cookie 2.x event name; not verified against a 2.x copy. The server writes nothing
    // when the cookie of that version carries no consents object.
    document.addEventListener('borlabs-cookie-unblock', syncConsent);
})();
