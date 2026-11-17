"""Chromium theme acceptance on disposable Numan Freight browser fixtures.

Uses only the CI fixture database: no production requests, payment calls, or
real user data. Exercise page rendering in both schemes and at phone/desktop
sizes; fail on brand-token regression or horizontal overflow.
"""

import json
import os
import re
from pathlib import Path

from playwright.sync_api import expect, sync_playwright

ROOT = Path(__file__).resolve().parents[2]
FIXTURES = ROOT / "storage/app/browser-acceptance/fixtures.json"
EVIDENCE = ROOT / "storage/app/browser-acceptance/evidence"
BASE_URL = os.environ.get("BROWSER_ACCEPTANCE_URL", "http://127.0.0.1:8000")
PASSWORD = os.environ.get("BROWSER_ACCEPTANCE_PASSWORD", "")


def luminance(rgb):
    channels = [
        (c / 255 / 12.92) if c / 255 <= 0.04045
        else (((c / 255 + 0.055) / 1.055) ** 2.4)
        for c in rgb
    ]
    return sum(a * b for a, b in zip(channels, (0.2126, 0.7152, 0.0722)))


def contrast(first, second):
    top, bottom = sorted((luminance(first), luminance(second)), reverse=True)
    return (top + 0.05) / (bottom + 0.05)


def rgb(value):
    components = re.findall(r"\d+(?:\.\d+)?", value)
    assert len(components) >= 3, f"Not a computed RGB value: {value}"
    return tuple(round(float(component)) for component in components[:3])


def assert_theme(page, mode, label):
    expected_dark = mode == "dark"
    expect(page.locator("html")).to_have_attribute("style", re.compile("color-scheme"))
    assert page.locator("html").evaluate(
        "(element) => element.classList.contains('dark')"
    ) == expected_dark, f"{label}: wrong theme class"

    actual = page.evaluate("""() => {
        const root = getComputedStyle(document.documentElement);
        const sample = document.createElement('button');
        sample.className = 'bg-primary text-primary-foreground';
        sample.style.position = 'absolute';
        sample.style.left = '-10000px';
        document.body.appendChild(sample);
        const button = getComputedStyle(sample);
        const values = {
            sky: root.getPropertyValue('--brand-sky').trim().toLowerCase(),
            navy: root.getPropertyValue('--brand-navy').trim().toLowerCase(),
            background: getComputedStyle(document.body).backgroundColor,
            primaryBackground: button.backgroundColor,
            primaryText: button.color
        };
        sample.remove();
        return values;
    }""")
    assert actual["sky"] == "#2d9cdb", f"{label}: logo sky token changed"
    assert actual["navy"] == "#0f294a", f"{label}: logo navy token changed"
    background = (8, 23, 36) if expected_dark else (247, 250, 252)
    primary = (45, 156, 219) if expected_dark else (15, 41, 74)
    primary_text = (8, 23, 36) if expected_dark else (255, 255, 255)
    assert rgb(actual["background"]) == background, (
        f"{label}: page background {actual['background']}"
    )
    assert rgb(actual["primaryBackground"]) == primary, (
        f"{label}: primary color {actual['primaryBackground']}"
    )
    assert rgb(actual["primaryText"]) == primary_text, (
        f"{label}: primary foreground {actual['primaryText']}"
    )
    assert contrast(primary, primary_text) >= 4.5, (
        f"{label}: primary button contrast below WCAG AA"
    )


def assert_fit(page, label):
    assert page.evaluate(
        "() => document.documentElement.scrollWidth <= window.innerWidth + 2"
    ), f"{label}: horizontal page overflow"


def login(page, email):
    page.goto(f"{BASE_URL}/login", wait_until="domcontentloaded")
    page.locator("#email").fill(email)
    page.locator("#password").fill(PASSWORD)
    page.locator('[data-test="login-button"]').click()
    page.wait_for_url(lambda url: "/login" not in str(url), timeout=20000)


def check_page(browser, spec, fixtures):
    name, route, email_key, mode, width, height = spec
    mobile = width < 768
    context = browser.new_context(
        viewport={"width": width, "height": height},
        is_mobile=mobile,
        has_touch=mobile,
    )
    page = context.new_page()
    page.add_init_script(f"localStorage.setItem('appearance', '{mode}')")
    try:
        if email_key:
            login(page, fixtures[email_key])
        path = route.format(**fixtures)
        response = page.goto(f"{BASE_URL}{path}", wait_until="domcontentloaded")
        assert response and response.status == 200, (
            f"{name}: request did not resolve ({response.status if response else 'none'})"
        )
        if path == "/login":
            expect(page.locator("#email")).to_be_visible()
        else:
            expect(page.locator("main").first).to_be_visible()
        page.wait_for_function(
            "() => getComputedStyle(document.body).backgroundColor !== 'rgba(0, 0, 0, 0)'"
        )
        assert_theme(page, mode, name)
        assert_fit(page, name)

        if path == "/login":
            expect(page.locator('img[src="/brand/numanfreight-wordmark-white.svg"]')).to_be_visible()
        elif path == "/dashboard":
            expect(page.get_by_role("heading", name=re.compile("Welcome back")).first).to_be_visible()
            expect(page.get_by_role("heading", name="Freight overview")).to_be_visible()
            expect(page.get_by_role("heading", name="Recent freight")).to_be_visible()
            expect(page.get_by_role("link", name=re.compile("View my freight", re.I)).first).to_be_visible()
            expect(page.locator('a[href="/my-freight?status=active"]')).to_contain_text(re.compile(r"Active posts\s*\d+"))
            expect(page.locator('a[href="/my-freight?status=draft"]')).to_contain_text(re.compile(r"Drafts\s*\d+"))
            expect(page.get_by_text("available now", exact=False).first).to_be_visible()
            expect(page.get_by_text("Recent freight", exact=True)).to_be_visible()
            expect(page.locator('a[href="/usage"]')).to_have_count(0)
        elif path == "/usage":
            expect(page).to_have_url(re.compile(r"/dashboard$"))
            expect(page.get_by_role("heading", name="Freight overview")).to_be_visible()
        elif path == "/admin/dashboard":
            expect(page.get_by_role("heading", name="System Overview")).to_be_visible()
            if mobile:
                page.get_by_role("button", name="Open admin menu").click()
                drawer = page.get_by_role("dialog", name="Admin navigation")
                expect(drawer).to_be_visible()
                expect(drawer.get_by_role("link", name="Overview")).to_be_visible()
                drawer.get_by_role("button", name="Close admin menu").click()
                expect(drawer).to_have_count(0)
            else:
                expect(page.locator('aside a[href="/admin/dashboard"]').first).to_be_visible()
        elif path.startswith("/freight/"):
            expect(page.get_by_role("heading", name="Route & pickup")).to_be_visible()
            expect(page.get_by_role("heading", name="Cargo & truck")).to_be_visible()
        elif path == "/freight":
            expect(page.locator('a[href^="/freight/"]:visible').first).to_be_visible()
        else:
            expect(page.get_by_role("heading", level=1)).to_be_visible()

        assert_fit(page, name)
        page.screenshot(path=str(EVIDENCE / f"theme-{name}.png"), full_page=True)
    except Exception:
        page.screenshot(path=str(EVIDENCE / f"theme-{name}-failed.png"), full_page=True)
        raise
    finally:
        context.close()


def main():
    if not PASSWORD or not FIXTURES.is_file():
        raise RuntimeError("Missing synthetic browser-acceptance password or fixture")
    fixtures = json.loads(FIXTURES.read_text(encoding="utf-8"))
    EVIDENCE.mkdir(parents=True, exist_ok=True)
    specs = [
        ("landing-light-desktop", "/", None, "light", 1280, 800),
        ("landing-dark-mobile", "/", None, "dark", 390, 844),
        ("login-dark-mobile", "/login", None, "dark", 390, 844),
        ("freight-list-light-mobile", "/freight", None, "light", 390, 844),
        ("freight-list-dark-desktop", "/freight", None, "dark", 1280, 800),
        ("freight-detail-dark-mobile", "/freight/{contact_ulid}", None, "dark", 390, 844),
        ("dashboard-light-desktop", "/dashboard", "consignor_email", "light", 1280, 800),
        ("dashboard-dark-mobile", "/dashboard", "consignor_email", "dark", 390, 844),
        ("usage-redirect-mobile", "/usage", "consignor_email", "light", 390, 844),
        ("admin-light-desktop", "/admin/dashboard", "admin_email", "light", 1280, 800),
        ("admin-dark-mobile", "/admin/dashboard", "admin_email", "dark", 390, 844),
    ]
    passed = []
    try:
        with sync_playwright() as p:
            browser = p.chromium.launch(headless=True)
            try:
                for spec in specs:
                    check_page(browser, spec, fixtures)
                    passed.append(spec[0])
            finally:
                browser.close()
    finally:
        (EVIDENCE / "brand-theme-report.json").write_text(
            json.dumps({"passed": passed, "total": len(specs)}, indent=2) + "\n",
            encoding="utf-8",
        )
    print("Numan Freight theme acceptance passed:", ", ".join(passed))


if __name__ == "__main__":
    main()
