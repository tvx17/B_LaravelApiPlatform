import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { environment } from './helpers.mjs';

const wait = (milliseconds = 100) =>
  new Promise((done) => setTimeout(done, milliseconds));

function contentEnvironment(t, html, overrides = {}) {
  const env = environment(html);
  const listeners = {};
  const stored = { ...env.settings, ...overrides };
  env.window.chrome = {
    storage: {
      local: { get: async () => ({ settings: stored }) },
      onChanged: {
        addListener: (callback) => {
          listeners.storage = callback;
        },
      },
    },
    runtime: {
      onMessage: {
        addListener: (callback) => {
          listeners.message = callback;
        },
      },
    },
  };
  env.window.eval(readFileSync('src/content.js', 'utf8'));
  t.after(() => env.dom.window.close());
  return { ...env, listeners };
}

test('Content Script verarbeitet initiale und nachgeladene Texte ohne Beobachterschleife', async (t) => {
  const { document, listeners } = contentEnvironment(
    t,
    '<p>eine <b>engagierte</b> Studierende</p>',
  );
  await wait();
  assert.equal(
    document.querySelector('p').textContent,
    'eine engagierte Studentin',
  );
  assert.equal(
    document.documentElement.getAttribute('data-leseklar-ready'),
    'true',
  );
  const paragraph = document.createElement('p');
  paragraph.textContent = 'Leser:innen';
  document.body.append(paragraph);
  await wait();
  assert.equal(paragraph.textContent, 'Leser');
  let initial;
  listeners.message({ type: 'status' }, null, (response) => {
    initial = response.changed;
  });
  await wait();
  listeners.message({ type: 'status' }, null, (response) =>
    assert.equal(response.changed, initial),
  );
});

test('Content Script übernimmt Änderungen und restauriert beim Ausschalten', async (t) => {
  const { document, settings, listeners } = contentEnvironment(
    t,
    '<p>Leser*innen</p>',
  );
  await wait();
  listeners.storage(
    { settings: { newValue: { ...settings, enabled: false } } },
    'local',
  );
  assert.equal(document.querySelector('p').textContent, 'Leser*innen');
  listeners.storage({ settings: { newValue: settings } }, 'local');
  assert.equal(document.querySelector('p').textContent, 'Leser');
  document.querySelector('p').setAttribute('contenteditable', 'true');
  await wait();
  assert.equal(document.querySelector('p').textContent, 'Leser*innen');
});

test('Intervall und manueller Scan vergrößern eigene Ersetzungen nicht', async (t) => {
  const { document, listeners } = contentEnvironment(t, '<p>A</p>', {
    intervalSeconds: 1,
    rules: [
      {
        name: 'Test',
        pattern: 'A',
        flags: 'g',
        replacement: 'AA',
        enabled: true,
      },
    ],
  });
  await wait(1200);
  listeners.message({ type: 'scan' }, null, () => {});
  assert.equal(document.querySelector('p').textContent, 'AA');
});

test('Ausgenommene Domain wird unverändert sichtbar', async (t) => {
  const { document } = contentEnvironment(t, '<p>Leser*innen</p>', {
    excludedDomains: ['beispiel.de'],
  });
  await wait();
  assert.equal(document.querySelector('p').textContent, 'Leser*innen');
  assert.equal(
    document.documentElement.getAttribute('data-leseklar-ready'),
    'true',
  );
});

test('Bei fehlendem Einstellungszugriff wird die Seite freigegeben', async (t) => {
  const env = environment('<p>Leser*innen</p>');
  t.after(() => env.dom.window.close());
  env.window.console.warn = () => {};
  env.window.chrome = {
    storage: {
      local: {
        get: async () => {
          throw new Error('Nicht verfügbar');
        },
      },
      onChanged: { addListener: () => {} },
    },
    runtime: { onMessage: { addListener: () => {} } },
  };
  env.window.eval(readFileSync('src/content.js', 'utf8'));
  await wait();
  assert.equal(
    env.document.documentElement.getAttribute('data-leseklar-ready'),
    'true',
  );
  assert.equal(env.document.querySelector('p').textContent, 'Leser*innen');
});
