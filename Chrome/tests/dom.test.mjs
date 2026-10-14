import test from 'node:test';
import assert from 'node:assert/strict';
import { environment } from './helpers.mjs';

test('Inline-Formatierung, Elemente und bestehende Ereignisbindungen bleiben erhalten', () => {
  const { document, window, settings } = environment(
    '<p>eine <strong>engagierte</strong> Studierende <button>Leser:innen</button></p>',
  );
  const button = document.querySelector('button');
  const strong = document.querySelector('strong');
  let clicked = false;
  button.addEventListener('click', () => {
    clicked = true;
  });
  window.LeseklarDOM.createProcessor(document, 'beispiel.de').scan(
    document,
    settings,
  );
  assert.equal(
    document.querySelector('p').textContent,
    'eine engagierte Studentin Leser',
  );
  assert.equal(document.querySelector('strong'), strong);
  assert.equal(document.querySelector('button'), button);
  button.click();
  assert.equal(clicked, true);
});

test('Code, Formulare, editierbare Inhalte und explizite Ausnahmen bleiben erhalten', () => {
  const { document, window, settings } = environment(
    '<pre>Leser*innen</pre><textarea>Studierende</textarea><div contenteditable="true"><p>Leser:innen</p></div><p translate="no">LeserInnen</p><p data-leseklar-ignore>Leser*innen</p><input value="Studierende"><p>Leser*innen</p>',
  );
  window.LeseklarDOM.createProcessor(document, '').scan(document, settings);
  assert.equal(document.querySelector('pre').textContent, 'Leser*innen');
  assert.equal(document.querySelector('textarea').value, 'Studierende');
  assert.equal(
    document.querySelector('[contenteditable]').textContent,
    'Leser:innen',
  );
  assert.equal(document.querySelector('[translate]').textContent, 'LeserInnen');
  assert.equal(
    document.querySelector('[data-leseklar-ignore]').textContent,
    'Leser*innen',
  );
  assert.equal(document.querySelector('input').value, 'Studierende');
  assert.equal(
    document.querySelector('body > p:last-child').textContent,
    'Leser',
  );
});

test('Screenreader-Beschriftungen werden angepasst, Referenzen bleiben erhalten', () => {
  const { document, window, settings } = environment(
    '<img alt="Studierende" aria-label="Leser:innen" aria-labelledby="original" title="LeserInnen">',
  );
  const processor = window.LeseklarDOM.createProcessor(document, '');
  processor.scan(document, settings);
  const image = document.querySelector('img');
  assert.equal(image.alt, 'Studenten');
  assert.equal(image.getAttribute('aria-label'), 'Leser');
  assert.equal(image.getAttribute('aria-labelledby'), 'original');
  processor.scan(document, { ...settings, accessibleLabels: false });
  assert.equal(image.alt, 'Studierende');
});

test('Wiederholte Scans verwenden den Ursprungstext und verhindern Wachstum', () => {
  const { document, window, settings } = environment('<p>A</p>');
  const processor = window.LeseklarDOM.createProcessor(document, '');
  const custom = {
    ...settings,
    rules: [
      {
        name: 'Wachstum',
        pattern: 'A',
        flags: 'g',
        replacement: 'AA',
        enabled: true,
      },
    ],
  };
  for (let count = 0; count < 10; count++) processor.scan(document, custom);
  assert.equal(document.querySelector('p').textContent, 'AA');
  processor.restore();
  assert.equal(document.querySelector('p').textContent, 'A');
});

test('Neue Seitentexte werden als neuer Ursprung übernommen und wiederhergestellt', () => {
  const { document, window, settings } = environment('<p>Leser*innen</p>');
  const processor = window.LeseklarDOM.createProcessor(document, '');
  processor.scan(document, settings);
  document.querySelector('p').firstChild.data = 'Studierende gesucht';
  processor.scan(document, settings);
  assert.equal(document.querySelector('p').textContent, 'Studenten gesucht');
  processor.restore();
  assert.equal(document.querySelector('p').textContent, 'Studierende gesucht');
});

test('Blockgrenzen verhindern Kontextübernahme vom vorherigen Absatz', () => {
  const { document, window, settings } = environment(
    '<p>eine engagierte</p><p>Studierende gesucht</p>',
  );
  window.LeseklarDOM.createProcessor(document, '').scan(document, settings);
  assert.equal(
    document.querySelector('p:last-child').textContent,
    'Studenten gesucht',
  );
});

test('Regex über mehrere Textknoten lässt die Formatierung bestehen', () => {
  const { document, window, settings } = environment(
    '<p>Leser<span>:innen</span></p>',
  );
  const span = document.querySelector('span');
  window.LeseklarDOM.createProcessor(document, '').scan(document, settings);
  assert.equal(document.querySelector('p').textContent, 'Leser');
  assert.equal(document.querySelector('span'), span);
});
