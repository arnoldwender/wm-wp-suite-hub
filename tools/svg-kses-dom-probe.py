#!/usr/bin/env python3
"""
svg-kses-dom-probe.py — does an inline SVG survive wp_kses() as a browser sees it?

wp_kses() lower-cases attribute names, so `viewBox` leaves PHP as `viewbox`. SVG attribute names
are case-sensitive in XML, which would make the icon lose its coordinate system and render at the
wrong scale. The HTML parsing algorithm, however, "adjusts SVG attributes" for elements in the SVG
namespace (viewbox -> viewBox) when the markup is parsed as text/html — which is how a WordPress
page is served. This probe measures that instead of asserting it: it loads both markups in a real
Chromium and compares what the DOM holds and the box the icon actually renders to.

Usage:  python3 tools/svg-kses-dom-probe.py
Exit:   0 when the kses output renders identically to the original, 1 otherwise.
"""
import sys
from playwright.sync_api import sync_playwright

ORIGINAL = ('<svg class="wm-block-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" '
            'stroke="currentColor" stroke-width="1.7" aria-hidden="true" focusable="false">'
            '<circle cx="12" cy="4.5" r="1.8"/><path d="M5 8.5h14"/></svg>')
# exactly what wp_kses() returned in a local WordPress 7.1 install, measured 2026-09-17
KSES = ('<svg class="wm-block-icon" width="48" height="48" viewbox="0 0 24 24" fill="none" '
        'stroke="currentColor" stroke-width="1.7" aria-hidden="true" focusable="false">'
        '<circle cx="12" cy="4.5" r="1.8" /><path d="M5 8.5h14" /></svg>')

# NEGATIVE CONTROL: the same icon with no viewBox at all. If the probe cannot tell this one apart,
# it measures nothing and its "identical" verdict is worthless.
BROKEN = ('<svg class="wm-block-icon" width="48" height="48" fill="none" '
          'stroke="currentColor" stroke-width="1.7" aria-hidden="true" focusable="false">'
          '<circle cx="12" cy="4.5" r="1.8"/><path d="M5 8.5h14"/></svg>')

JS = """(id) => {
  const svg = document.querySelector('#' + id + ' svg');
  const c = svg.querySelector('circle');
  const b = c.getBoundingClientRect();
  return {
    viewBoxAttr: svg.getAttribute('viewBox'),
    lowerAttr: svg.getAttribute('viewbox'),
    baseValWidth: svg.viewBox.baseVal ? svg.viewBox.baseVal.width : null,
    circle: [Math.round(b.width * 100) / 100, Math.round(b.height * 100) / 100],
  };
}"""

with sync_playwright() as p:
    browser = p.chromium.launch()
    page = browser.new_page()
    page.set_content(f'<!doctype html><html><body><div id="a">{ORIGINAL}</div><div id="b">{KSES}</div><div id="c">{BROKEN}</div></body></html>')
    a = page.evaluate(JS, "a")
    b = page.evaluate(JS, "b")
    c = page.evaluate(JS, "c")
    browser.close()

print("original:", a)
print("kses    :", b)
print("control :", c)
same = a["baseValWidth"] == b["baseValWidth"] == 24 and a["circle"] == b["circle"]
control_detected = c["circle"] != a["circle"]
print("rinde idéntico        :", "SÍ" if same else "NO")
print("control sin viewBox    :", "detectado (el probe mide)" if control_detected else "NO detectado — probe muerto")
sys.exit(0 if (same and control_detected) else 1)
