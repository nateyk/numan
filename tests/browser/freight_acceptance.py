"""Real Chromium acceptance for the local, synthetic Numan Freight flow.

Run only against the dedicated browser-acceptance database. This suite logs in
through the normal UI, changes freight state through visible controls and
checks that private contact data is absent until a reveal is confirmed.
"""

import json
import os
import re
import sys
from pathlib import Path

from playwright.sync_api import expect, sync_playwright

ROOT = Path(__file__).resolve().parents[2]
FIXTURES = ROOT / "storage/app/browser-acceptance/fixtures.json"
EVIDENCE = ROOT / "storage/app/browser-acceptance/evidence"
BASE_URL = os.environ.get("BROWSER_ACCEPTANCE_URL", "http://127.0.0.1:8000")
PASSWORD = os.environ.get("BROWSER_ACCEPTANCE_PASSWORD", "")
RESULTS = {}


def sign_in(page, email):
    page.goto(f"{BASE_URL}/login", wait_until="domcontentloaded")
    page.locator("#email").fill(email)
    page.locator("#password").fill(PASSWORD)
    page.locator('[data-test="login-button"]').click()
    page.wait_for_url(lambda url: "/login" not in str(url), timeout=20000)


def owner_row(page, ulid):
    return page.locator("tbody tr").filter(
        has=page.locator(f'a[href="/freight/{ulid}"]')
    )


def owner_card(page, ulid):
    return page.locator('[data-slot="card"]:visible').filter(
        has=page.locator(f'a[href="/freight/{ulid}"]')
    )


def menu_action(page, container, name):
    container.locator('button[aria-label^="More actions for"]').click()
    expect(page.get_by_role("menuitem", name=name)).to_be_visible()
    page.get_by_role("menuitem", name=name).click()


def confirm_dialog(page, label):
    dialog = page.get_by_role("dialog")
    expect(dialog).to_be_visible()
    dialog.get_by_role("button", name=label, exact=True).click()
    expect(dialog).to_be_hidden()


def desktop_owner_flow(browser, fixtures):
    context = browser.new_context(viewport={"width": 1366, "height": 900})
    page = context.new_page()
    try:
        sign_in(page, fixtures["consignor_email"])
        page.goto(f"{BASE_URL}/my-freight")
        expect(page).to_have_url(re.compile(r"/my-freight"))
        contact = owner_row(page, fixtures["contact_ulid"])
        expect(contact).to_be_visible()
        expect(contact).to_contain_text("Posted")
        expect(contact).to_contain_text("Expires")
        expect(page.get_by_text("Pickup / closes", exact=True)).to_be_visible()
        expect(contact).not_to_contain_text("Browser Test Transporter")
        page.screenshot(path=str(EVIDENCE / "desktop-owner-before.png"), full_page=True)

        cancel = owner_row(page, fixtures["cancel_ulid"])
        menu_action(page, cancel, "Close listing")
        dialog = page.get_by_role("dialog")
        expect(dialog).to_contain_text("This does not confirm delivery")
        dialog.get_by_role("combobox").click()
        page.get_by_role("option", name="Cancelled / no longer needed").click()
        confirm_dialog(page, "Close listing")
        expect(owner_row(page, fixtures["cancel_ulid"])).to_contain_text("Cancelled")

        arrived = owner_row(page, fixtures["arrive_ulid"])
        menu_action(page, arrived, "Mark arrived")
        confirm_dialog(page, "Mark arrived")
        expect(owner_row(page, fixtures["arrive_ulid"])).to_contain_text("Arrived")

        # A previously cancelled freight must not offer arrival.
        cancelled = owner_row(page, fixtures["cancelled_ulid"])
        cancelled.locator('button[aria-label^="More actions for"]').click()
        expect(page.get_by_role("menuitem", name="Mark arrived")).to_have_count(0)
        page.keyboard.press("Escape")
        page.screenshot(path=str(EVIDENCE / "desktop-owner-after.png"), full_page=True)
        RESULTS["desktop_owner"] = "passed"
    except Exception:
        page.screenshot(path=str(EVIDENCE / "desktop-owner-failed.png"), full_page=True)
        raise
    finally:
        context.close()


def mobile_owner_cards(browser, fixtures):
    context = browser.new_context(viewport={"width": 390, "height": 844}, is_mobile=True, has_touch=True)
    page = context.new_page()
    try:
        sign_in(page, fixtures["consignor_email"])
        page.goto(f"{BASE_URL}/my-freight")
        cancelled = owner_card(page, fixtures["cancel_ulid"])
        expect(cancelled).to_be_visible()
        expect(cancelled).to_contain_text("Cancelled")
        expect(cancelled).to_contain_text("Posted")
        expect(owner_card(page, fixtures["arrive_ulid"])).to_contain_text("Arrived")
        expect(owner_card(page, fixtures["contact_ulid"])).to_contain_text("Expires")
        assert page.evaluate(
            "document.documentElement.scrollWidth <= window.innerWidth + 2"
        ), "My Freight cards introduce horizontal overflow at 390px"

        page.screenshot(path=str(EVIDENCE / "mobile-owner-cards.png"), full_page=True)
        RESULTS["mobile_owner"] = "passed"
    except Exception:
        page.screenshot(path=str(EVIDENCE / "mobile-owner-failed.png"), full_page=True)
        raise
    finally:
        context.close()


def transporter_contact_privacy(browser, fixtures):
    # Guest and transporter must not receive contact details in the HTML/
    # Inertia page payload. The phone is intentionally synthetic.
    guest = browser.new_context(viewport={"width": 390, "height": 844}, is_mobile=True)
    guest_page = guest.new_page()
    try:
        guest_page.goto(f"{BASE_URL}/freight/{fixtures['contact_ulid']}")
        expect(guest_page.get_by_text("Consignor Poster")).to_be_visible()
        assert fixtures["consignor_phone"] not in guest_page.content()
        assert fixtures["consignor_secondary_phone"] not in guest_page.content()
        assert fixtures["consignor_whatsapp"] not in guest_page.content()
        assert fixtures["consignor_secondary_whatsapp"] not in guest_page.content()
        assert fixtures["consignor_email"] not in guest_page.content()
        RESULTS["guest_privacy"] = "passed"
    finally:
        guest.close()

    context = browser.new_context(viewport={"width": 390, "height": 844}, is_mobile=True, has_touch=True)
    page = context.new_page()
    try:
        sign_in(page, fixtures["transporter_email"])
        page.goto(f"{BASE_URL}/freight")
        expect(page.locator(f'a[href="/freight/{fixtures["contact_ulid"]}"]:visible').first).to_be_visible()
        assert page.evaluate(
            "document.documentElement.scrollWidth <= window.innerWidth + 2"
        ), "Freight discovery introduces horizontal overflow at 390px"
        page.screenshot(path=str(EVIDENCE / "mobile-transporter-discovery.png"), full_page=True)

        page.goto(f"{BASE_URL}/freight/{fixtures['contact_ulid']}")
        expect(page.get_by_text("Consignor Poster")).to_be_visible()
        assert fixtures["consignor_phone"] not in page.content()
        assert fixtures["consignor_secondary_phone"] not in page.content()
        assert fixtures["consignor_whatsapp"] not in page.content()
        assert fixtures["consignor_email"] not in page.content()
        expect(page.get_by_role("button", name="Reveal contact").first).to_be_visible()

        page.get_by_role("button", name="Reveal contact").first.click()
        dialog = page.get_by_role("dialog")
        expect(dialog).to_contain_text("consignor")
        dialog.get_by_role("button", name="Reveal contact").click()
        expect(page.locator('a[href^="tel:"]').first).to_be_visible(timeout=20000)
        assert fixtures["consignor_phone"] in page.content()
        expect(page.locator('a[href="tel:+251900111222"]')).to_have_count(1)
        expect(page.locator('a[href="tel:+251900222333"]')).to_have_count(1)
        expect(page.locator('a[href="https://wa.me/251900444555"]')).to_have_count(1)
        expect(page.locator('a[href="https://wa.me/251911777888"]')).to_have_count(1)
        expect(page.locator('a[href="mailto:consignor-browser@numan.invalid"]')).to_have_count(1)
        expect(page.get_by_role("button", name="Copy phone +251900111222")).to_be_visible()

        context.grant_permissions(["clipboard-read", "clipboard-write"])
        page.get_by_role("button", name="Copy phone +251900111222").click()
        assert page.evaluate("navigator.clipboard.readText()") == "+251900111222"
        assert page.evaluate(
            "document.documentElement.scrollWidth <= window.innerWidth + 2"
        ), "Revealed contact actions introduce horizontal overflow at 390px"
        page.screenshot(path=str(EVIDENCE / "mobile-after-reveal.png"), full_page=True)
        RESULTS["transporter_reveal"] = "passed"
    except Exception:
        page.screenshot(path=str(EVIDENCE / "mobile-transporter-failed.png"), full_page=True)
        raise
    finally:
        context.close()

    desktop = browser.new_context(viewport={"width": 1280, "height": 900})
    page = desktop.new_page()
    try:
        sign_in(page, fixtures["transporter_email"])
        page.goto(f"{BASE_URL}/freight/{fixtures['contact_ulid']}")
        expect(page.locator('a[href="https://wa.me/251911777888"]')).to_be_visible()
        expect(page.locator('a[href="tel:+251900111222"]')).to_be_visible()
        assert page.evaluate(
            "document.documentElement.scrollWidth <= window.innerWidth + 2"
        ), "Desktop contact actions introduce horizontal overflow"
        page.screenshot(path=str(EVIDENCE / "desktop-after-reveal.png"), full_page=True)
        RESULTS["desktop_contacts"] = "passed"
    except Exception:
        page.screenshot(path=str(EVIDENCE / "desktop-contacts-failed.png"), full_page=True)
        raise
    finally:
        desktop.close()


def main():
    if not PASSWORD or not FIXTURES.is_file():
        raise RuntimeError("Missing isolated browser fixture or synthetic password")

    EVIDENCE.mkdir(parents=True, exist_ok=True)
    fixtures = json.loads(FIXTURES.read_text(encoding="utf-8"))
    try:
        with sync_playwright() as p:
            browser = p.chromium.launch(headless=True)
            try:
                desktop_owner_flow(browser, fixtures)
                mobile_owner_cards(browser, fixtures)
                transporter_contact_privacy(browser, fixtures)
            finally:
                browser.close()
    except Exception as exc:
        RESULTS["failure"] = type(exc).__name__
        raise
    finally:
        (EVIDENCE / "report.json").write_text(
            json.dumps(RESULTS, indent=2) + "\n", encoding="utf-8"
        )
    print("Synthetic Chromium freight acceptance:", RESULTS)


if __name__ == "__main__":
    sys.exit(main() or 0)
