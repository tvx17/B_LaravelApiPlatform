import { test, before, beforeEach, after } from 'node:test';
import { expect, chromium } from '@playwright/test';
import { resolve } from 'node:path';
import { readFile } from 'node:fs/promises';
import { createServer } from 'node:http';

let context;
let options;
let page;
let server;
let url;
let extensionId;
let browser;

before(async () => {
  const html = await readFile('tests/fixtures/demo.html');
  server = createServer((_request, response) => {
    response.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    response.end(html);
  });
  await new Promise((done) => server.listen(0, '127.0.0.1', done));
  url = `http://127.0.0.1:${server.address().port}`;
  const extension = resolve('dist');
  if (process.env.LESEKLAR_CDP_URL) {
    browser = await chromium.connectOverCDP(process.env.LESEKLAR_CDP_URL);
    context = browser.contexts()[0];
  } else
    context = await chromium.launchPersistentContext('', {
      channel: 'chromium',
      executablePath: process.env.LESEKLAR_CHROMIUM_EXECUTABLE || undefined,
      headless: true,
      args: [
        `--disable-extensions-except=${extension}`,
        `--load-extension=${extension}`,
      ],
    });
  const manager = await context.newPage();
  await manager.goto('chrome://extensions/');
  extensionId = await manager
    .locator('extensions-item')
    .first()
    .getAttribute('id');
  await manager.close();
  options = await context.newPage();
  await options.goto(`chrome-extension://${extensionId}/options.html`);
  page = await context.newPage();
});

beforeEach(async () => {
  await options.evaluate(async () => {
    await chrome.storage.local.set({ settings: LeseklarSettings.defaults });
  });
  await page.goto(url);
});

after(async () => {
  if (browser) await browser.close();
  else await context?.close();
  await new Promise((done) => (server ? server.close(done) : done()));
});

test('Installierte Erweiterung verarbeitet den ersten Seitenaufbau und dynamische Inhalte', async () => {
  await expect(page.locator('h1')).toHaveText('Leser, Leser und Leser');
  await expect(page.locator('p').first()).toHaveText(
    'eine engagierte Studentin',
  );
  await expect(page.locator('html')).toHaveAttribute(
    'data-leseklar-ready',
    'true',
  );
  await expect(page.locator('html')).toHaveCSS('visibility', 'visible');
  await expect(page.locator('img')).toHaveAttribute('alt', 'Leser');
  await expect(page.locator('pre')).toHaveText('Leser*innen');
  await expect(page.locator('textarea')).toHaveValue('Studierende');
  await page.locator('#listener').click();
  await expect(page.locator('#listener-result')).toHaveText(
    'Ereignisbindung funktioniert.',
  );
  await page.locator('#add').click();
  await expect(page.locator('#dynamic p')).toHaveText(
    'eine engagierte Studentin und Leser',
  );
});

test('Speichern, Vorschau und Ausschalten funktionieren über die echte Einstellungsseite', async () => {
  await options.reload();
  await options.locator('#preview').click();
  await expect(options.locator('#result')).toContainText(
    'Eine engagierte Studentin',
  );
  await options.locator('#enabled').uncheck();
  await options
    .getByRole('button', { name: 'Einstellungen speichern' })
    .click();
  await expect(options.locator('#status')).toHaveText(
    'Einstellungen gespeichert.',
  );
  await expect(page.locator('h1')).toHaveText(
    'Leser*innen, Leser:innen und LeserInnen',
  );
  await page.reload();
  await expect(page.locator('h1')).toHaveText(
    'Leser*innen, Leser:innen und LeserInnen',
  );
  await expect(page.locator('html')).toHaveCSS('visibility', 'visible');
});

test('Regex-Regeln wachsen bei wiederholten Scans nicht und Domain-Ausnahmen restaurieren Texte', async () => {
  await options.evaluate(async () => {
    await chrome.storage.local.set({
      settings: {
        ...LeseklarSettings.defaults,
        intervalSeconds: 1,
        rules: [
          {
            name: 'Test',
            pattern: 'Leser',
            flags: 'g',
            replacement: 'LeserLeser',
            enabled: true,
            domain: '',
          },
        ],
      },
    });
  });
  await expect(page.locator('h1')).toHaveText(
    'LeserLeser, LeserLeser und LeserLeser',
  );
  await page.waitForTimeout(2200);
  await expect(page.locator('h1')).toHaveText(
    'LeserLeser, LeserLeser und LeserLeser',
  );
  await options.evaluate(async () => {
    await chrome.storage.local.set({
      settings: {
        ...LeseklarSettings.defaults,
        excludedDomains: ['127.0.0.1'],
      },
    });
  });
  await expect(page.locator('h1')).toHaveText(
    'Leser*innen, Leser:innen und LeserInnen',
  );
});

test('Ausgeschlossene editierbare Bereiche erhalten ihren Ursprungstext zurück', async () => {
  await expect(page.locator('p').first()).toHaveText(
    'eine engagierte Studentin',
  );
  await page
    .locator('p')
    .first()
    .evaluate((element) => element.setAttribute('contenteditable', 'true'));
  await expect(page.locator('p').first()).toHaveText(
    'eine engagierte Studierende',
  );
});

test('Export und validierter Import erhalten die Einstellungen', async () => {
  await options.reload();
  const downloadPromise = options.waitForEvent('download');
  await options.locator('#export').click();
  const download = await downloadPromise;
  const data = JSON.parse(await readFile(await download.path(), 'utf8'));
  expect(data.version).toBe(1);
  await options.locator('#import').setInputFiles({
    name: 'regeln.json',
    mimeType: 'application/json',
    buffer: Buffer.from(
      JSON.stringify({
        ...data,
        settings: { ...data.settings, intervalSeconds: 7 },
      }),
    ),
  });
  await expect(options.locator('#interval')).toHaveValue('7');
  await options
    .getByRole('button', { name: 'Einstellungen speichern' })
    .click();
  const saved = await options.evaluate(
    async () =>
      (await chrome.storage.local.get('settings')).settings.intervalSeconds,
  );
  expect(saved).toBe(7);
});
