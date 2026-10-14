import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { environment } from './helpers.mjs';

test('Einstellungsseite speichert Regeln, Intervall und Ausnahmen über das Formular', async (t) => {
  const { window, document, dom, settings } = environment(
    readFileSync('src/options.html', 'utf8'),
  );
  t.after(() => dom.window.close());
  let saved;
  window.chrome = {
    storage: {
      local: {
        get: async () => ({ settings }),
        set: async (value) => {
          saved = value.settings;
        },
      },
    },
  };
  window.eval(readFileSync('src/options.js', 'utf8'));
  await new Promise((done) => setTimeout(done, 0));
  document.querySelector('#interval').value = '5';
  document.querySelector('#domains').value = 'beispiel.de';
  document.querySelector('#add-rule').click();
  document.querySelector('[data-key="name"]').value = 'Test';
  document.querySelector('[data-key="pattern"]').value = 'Leser';
  document.querySelector('[data-key="replacement"]').value = 'Publikum';
  document
    .querySelector('#settings-form')
    .dispatchEvent(new window.Event('submit', { cancelable: true }));
  await new Promise((done) => setTimeout(done, 0));
  assert.equal(saved.intervalSeconds, 5);
  assert.equal(saved.excludedDomains[0], 'beispiel.de');
  assert.equal(saved.rules[0].replacement, 'Publikum');
  assert.equal(
    document.querySelector('#status').textContent,
    'Einstellungen gespeichert.',
  );
  document.querySelector('[data-key="pattern"]').value = '[';
  document
    .querySelector('#settings-form')
    .dispatchEvent(new window.Event('submit', { cancelable: true }));
  assert.match(
    document.querySelector('#status').textContent,
    /^Nicht gespeichert:/,
  );
});

test('Popup schaltet um, startet manuelle Prüfung und öffnet Einstellungen', async (t) => {
  const { window, document, dom, settings } = environment(
    readFileSync('src/popup.html', 'utf8'),
  );
  t.after(() => dom.window.close());
  let saved;
  let opened = false;
  let message;
  window.chrome = {
    storage: {
      local: {
        get: async () => ({ settings }),
        set: async (value) => {
          saved = value.settings;
        },
      },
    },
    tabs: {
      query: async () => [{ id: 1 }],
      sendMessage: async (_id, value) => {
        message = value;
        return { active: true, changed: 2 };
      },
    },
    runtime: {
      openOptionsPage: () => {
        opened = true;
      },
    },
  };
  window.eval(readFileSync('src/popup.js', 'utf8'));
  await new Promise((done) => setTimeout(done, 0));
  assert.equal(document.querySelector('#enabled').checked, true);
  document.querySelector('#enabled').click();
  await new Promise((done) => setTimeout(done, 0));
  assert.equal(saved.enabled, false);
  document.querySelector('#scan').click();
  await new Promise((done) => setTimeout(done, 0));
  assert.equal(message.type, 'scan');
  document.querySelector('#options').click();
  assert.equal(opened, true);
});
