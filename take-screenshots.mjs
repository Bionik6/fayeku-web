/**
 * Screenshot capture script for Fayeku marketing pages.
 * Uses Playwright to log in to demo accounts and take cropped screenshots.
 * Rule #2: aggressive crop — hide sidebar, hide header, keep only the selling zone.
 * Uses viewport-height clipping: take a viewport screenshot (not element screenshot)
 * so we naturally get only the top portion visible in the viewport.
 */

import { chromium } from 'playwright';
import { mkdirSync } from 'fs';

const BASE = 'http://0.0.0.0:8000';
const OUT  = 'public/screenshots';
const OTP  = '123456';

mkdirSync(OUT, { recursive: true });

/** Hide sidebar, header, debug bars — keep only main content */
async function prepareForScreenshot(page) {
  await page.evaluate(() => {
    // Hide sidebar
    document.querySelectorAll('[role="complementary"], aside').forEach(el => {
      el.style.display = 'none';
    });

    // Hide header/banner (breadcrumb bar)
    document.querySelectorAll('[role="banner"], header').forEach(el => {
      el.style.display = 'none';
    });

    // Hide ALL fixed elements (debug bars, telescope)
    document.querySelectorAll('*').forEach(el => {
      const s = window.getComputedStyle(el);
      if (s.position === 'fixed') el.style.display = 'none';
    });

    // Make main content fill the entire viewport width
    const main = document.querySelector('main');
    if (main) {
      let el = main;
      while (el && el !== document.body) {
        el.style.marginLeft = '0';
        el.style.paddingLeft = '0';
        el.style.width = '100%';
        el.style.maxWidth = '100%';
        el.style.gridColumn = '1 / -1';
        const parent = el.parentElement;
        if (parent) {
          const s = window.getComputedStyle(parent);
          if (s.display === 'grid') {
            parent.style.gridTemplateColumns = '1fr';
          }
        }
        el = parent;
      }
      // Add padding for cleaner edges
      main.style.padding = '32px 48px';
    }

    // Scroll to top
    window.scrollTo(0, 0);
  });
}

/** Login with phone + OTP bypass */
async function login(page, phone, password) {
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
  await page.fill('input[placeholder="XX XXX XX XX"]', phone);
  await page.fill('input[placeholder="Entrez votre mot de passe..."]', password);
  await page.click('button[type="submit"]');
  await page.waitForTimeout(1500);

  const url = page.url();
  if (url.includes('/otp')) {
    const otpInputs = await page.locator('input[type="text"], input[type="number"], input[inputmode="numeric"]').all();
    if (otpInputs.length >= 6) {
      for (let i = 0; i < 6; i++) {
        await otpInputs[i].fill(OTP[i]);
      }
    } else if (otpInputs.length >= 1) {
      await otpInputs[0].fill(OTP);
    }
    const submitBtn = page.locator('button[type="submit"]');
    if (await submitBtn.count() > 0) {
      await submitBtn.click();
    }
    await page.waitForTimeout(2000);
  }
}

/**
 * Take a viewport-clipped screenshot.
 * This captures exactly what fits in the viewport (top of main content),
 * giving a natural "above-the-fold" crop.
 */
async function screenshotViewport(page, filename, viewportHeight = 900) {
  // Temporarily adjust viewport height for this screenshot
  const currentViewport = page.viewportSize();
  await page.setViewportSize({ width: currentViewport.width, height: viewportHeight });

  await prepareForScreenshot(page);
  await page.waitForTimeout(600);

  // Viewport screenshot = only what's visible, no scrolling
  await page.screenshot({
    path: `${OUT}/${filename}`,
    type: 'png',
    fullPage: false, // important: only viewport, not full scroll
  });

  // Restore viewport
  await page.setViewportSize(currentViewport);
  console.log(`  ✓ ${filename}`);
}

/**
 * Take a taller viewport screenshot for pages with more content to show.
 */
async function screenshotTall(page, filename, viewportHeight = 1200) {
  return screenshotViewport(page, filename, viewportHeight);
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    deviceScaleFactor: 2, // Retina quality
  });

  const page = await context.newPage();

  // ──────────────────────────────────────
  //  PME SCREENSHOTS
  // ──────────────────────────────────────
  console.log('\n📸 PME Screenshots');
  console.log('  Logging in as PME...');
  await login(page, '77 620 00 01', 'passer1234');

  // P1 — Dashboard hero: show KPIs + trésorerie + activité (use tall viewport)
  console.log('  → Dashboard (hero)');
  await page.goto(`${BASE}/pme/dashboard`, { waitUntil: 'networkidle' });
  await screenshotTall(page, 'hero-pme-dashboard.png', 1050);

  // P2 — Invoices: KPIs + first rows of the table
  console.log('  → Factures');
  await page.goto(`${BASE}/pme/invoices`, { waitUntil: 'networkidle' });
  await screenshotTall(page, 'screen-pme-invoices.png', 900);

  // P3 — Collections: KPIs + mode relance + first rows
  console.log('  → Recouvrement');
  await page.goto(`${BASE}/pme/collections`, { waitUntil: 'networkidle' });
  await screenshotTall(page, 'screen-pme-collections.png', 1100);

  // P4 — Treasury: KPIs + forecast cards
  console.log('  → Trésorerie');
  await page.goto(`${BASE}/pme/treasury`, { waitUntil: 'networkidle' });
  await screenshotViewport(page, 'screen-pme-treasury.png', 900);

  // P5 — Detail: try clicking a collection row for slide-over
  console.log('  → Détail relance');
  await page.goto(`${BASE}/pme/collections`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);
  try {
    const row = page.locator('table tbody tr').first();
    if (await row.count() > 0) {
      await row.click({ timeout: 5000 });
      await page.waitForTimeout(2000);
      const modal = page.locator('[role="dialog"], dialog').first();
      if (await modal.count() > 0 && await modal.isVisible()) {
        await modal.screenshot({
          path: `${OUT}/detail-pme-reminder.png`,
          type: 'png',
        });
        console.log(`  ✓ detail-pme-reminder.png`);
        await page.keyboard.press('Escape');
        await page.waitForTimeout(500);
      } else {
        console.log('  → Fallback: collections viewport');
        await screenshotViewport(page, 'detail-pme-reminder.png', 900);
      }
    }
  } catch (e) {
    console.log(`  ⚠ Reminder detail skipped: ${e.message.substring(0, 80)}`);
  }

  // ──────────────────────────────────────
  //  COMPTABLE SCREENSHOTS
  // ──────────────────────────────────────
  console.log('\n📸 Comptable Screenshots');
  console.log('  Logging in as Comptable...');
  await context.clearCookies();
  await login(page, '77 610 00 01', 'passer1234');

  // C1 — Dashboard hero: portfolio + alerts + tiers
  console.log('  → Dashboard (hero)');
  await page.goto(`${BASE}/compta/dashboard`, { waitUntil: 'networkidle' });
  await screenshotTall(page, 'hero-compta-dashboard.png', 1200);

  // C2 — Clients list
  console.log('  → Clients');
  await page.goto(`${BASE}/compta/clients`, { waitUntil: 'networkidle' });
  await screenshotViewport(page, 'screen-compta-clients.png', 900);

  // C3 — Commissions
  console.log('  → Commissions');
  await page.goto(`${BASE}/compta/commissions`, { waitUntil: 'networkidle' });
  await screenshotTall(page, 'screen-compta-commissions.png', 1000);

  // C4 — Alerts
  console.log('  → Alertes');
  await page.goto(`${BASE}/compta/alertes`, { waitUntil: 'networkidle' });
  await screenshotViewport(page, 'screen-compta-alerts.png', 900);

  await browser.close();
  console.log('\n✅ All screenshots saved to public/screenshots/');
}

main().catch(err => {
  console.error('Error:', err);
  process.exit(1);
});
