// Records one segment of the Monitor demo tour. Usage: node seg.js <segment>
const { chromium } = require('playwright');
const SEG = process.argv[2];
const BASE = 'http://127.0.0.1:9400';
const OUT = `/tmp/vid/${SEG}`;

const CURSOR = `
(() => {
  const c = document.createElement('div');
  c.id = 'ax-cursor';
  c.style.cssText = 'position:fixed;z-index:999999;width:22px;height:22px;pointer-events:none;transform:translate(-3px,-3px);transition:none;';
  c.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22"><path d="M5 2l14 11-6.5 1L9 21z" fill="#1A2332" stroke="#fff" stroke-width="1.6"/></svg>';
  const add = () => { document.body.appendChild(c); };
  document.body ? add() : document.addEventListener('DOMContentLoaded', add);
  window.addEventListener('mousemove', (e) => { c.style.left = e.clientX + 'px'; c.style.top = e.clientY + 'px'; }, true);
  window.addEventListener('mousedown', () => { c.firstChild.style.transform = 'scale(0.8)'; }, true);
  window.addEventListener('mouseup', () => { c.firstChild.style.transform = 'scale(1)'; }, true);
})();`;

const CLEAN_CSS = `
  .notice, .update-nag, #wpfooter, #screen-meta-links { display: none !important; }
  html { scroll-behavior: auto !important; }
`;

async function glide(page, x, y, ms = 700) {
  await page.mouse.move(x, y, { steps: Math.max(8, Math.floor(ms / 16)) });
}
const pause = (page, ms) => page.waitForTimeout(ms);

async function smoothScroll(page, toY, ms = 1200) {
  await page.evaluate(([toY, ms]) => new Promise((res) => {
    const startY = window.scrollY, d = toY - startY, t0 = performance.now();
    const ease = (t) => t < 0.5 ? 2*t*t : 1 - Math.pow(-2*t+2, 2)/2;
    (function step(now) {
      const p = Math.min(1, (now - t0) / ms);
      window.scrollTo(0, startY + d * ease(p));
      p < 1 ? requestAnimationFrame(step) : res();
    })(performance.now());
  }), [toY, ms]);
}

const T0 = Date.now();
(async () => {
  const b = await chromium.launch();
  const ctx = await b.newContext({
    viewport: { width: 1280, height: 720 },
    recordVideo: { dir: OUT, size: { width: 1280, height: 720 } },
    deviceScaleFactor: 1,
  });
  const page = await ctx.newPage();
  await page.addInitScript(CURSOR);

  // Login (Playground default admin/password); tolerate auto-login.
  await page.goto(BASE + '/wp-login.php', { waitUntil: 'domcontentloaded' });
  if (await page.locator('#user_login').count()) {
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'password');
    await page.click('#wp-submit');
    await page.waitForURL('**/wp-admin/**', { timeout: 25000 }).catch(() => {});
  }
  await page.goto(BASE + '/wp-admin/admin.php?page=aismon', { waitUntil: 'networkidle' });
  await page.addStyleTag({ content: CLEAN_CSS });
  console.log('TOURSTART_MS', Date.now() - T0);
  await page.mouse.move(640, 200);
  await pause(page, 600);

  if (SEG === 's1-dashboard') {
    // Hero: summary cards + chart.
    await glide(page, 400, 260, 900); await pause(page, 900);
    await glide(page, 760, 260, 800); await pause(page, 900);
    await glide(page, 1040, 260, 800); await pause(page, 900);
    await smoothScroll(page, 380, 1400); await pause(page, 400);
    await glide(page, 700, 420, 900); await pause(page, 1800);
  } else if (SEG === 's2-sources') {
    const el = page.locator('text=Usage by source').first();
    await el.scrollIntoViewIfNeeded(); await page.evaluate(() => window.scrollBy(0, -80));
    await pause(page, 700);
    const box = await el.boundingBox();
    const y0 = box ? box.y + 60 : 300;
    await glide(page, 420, y0, 800); await pause(page, 700);
    await glide(page, 420, y0 + 40, 600); await pause(page, 600);
    await glide(page, 420, y0 + 80, 600); await pause(page, 600);
    await glide(page, 980, y0 + 40, 800); await pause(page, 1600);
  } else if (SEG === 's3-recent') {
    const el = page.locator('text=Recent AI calls').first();
    await el.scrollIntoViewIfNeeded(); await page.evaluate(() => window.scrollBy(0, -80));
    await pause(page, 700);
    const box = await el.boundingBox();
    const y0 = box ? box.y + 70 : 320;
    await glide(page, 350, y0, 800); await pause(page, 600);
    await glide(page, 700, y0 + 35, 700); await pause(page, 600);
    await glide(page, 950, y0 + 70, 700); await pause(page, 1400);
  } else if (SEG === 's4-notify') {
    const el = page.locator('text=Spend notification').first();
    await el.scrollIntoViewIfNeeded(); await page.evaluate(() => window.scrollBy(0, -120));
    await pause(page, 700);
    const amount = page.locator('input[name*="monthly"], input[type="number"]').first();
    if (await amount.count()) {
      const ab = await amount.boundingBox();
      if (ab) { await glide(page, ab.x + 30, ab.y + 15, 900); }
      await amount.click(); await pause(page, 300);
      await amount.fill(''); await amount.type('25', { delay: 220 });
      await pause(page, 800);
    }
    const exp = page.locator('text=Export CSV').first();
    if (await exp.count()) {
      const eb = await exp.boundingBox();
      if (eb) { await glide(page, eb.x + 40, eb.y + 12, 900); await pause(page, 1400); }
    } else { await pause(page, 1200); }
  }

  await ctx.close(); await b.close();
  console.log('segment', SEG, 'recorded');
})().catch((e) => { console.error('FAIL:', e.message.split('\n')[0]); process.exit(1); });
