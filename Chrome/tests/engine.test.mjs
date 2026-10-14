import test from 'node:test';
import assert from 'node:assert/strict';
import { environment } from './helpers.mjs';

const { engine, settings, window } = environment();
for (const [source, expected] of [
  ['Leser*innen, Leser:innen und LeserInnen', 'Leser, Leser und Leser'],
  ['Ärzt*innen und Kolleg:innen', 'Ärzte und Kollegen'],
  [
    'Bürger:innen und Softwareentwickler*innen',
    'Bürger und Softwareentwickler',
  ],
  ['JournalistInnen und Expert*innen', 'Journalisten und Experten'],
  ['Leser/-innen und Lehrer/innen', 'Leser und Lehrer'],
  ['eine engagierte Studierende', 'eine engagierte Studentin'],
  ['Eine eingagierte Studierende', 'Eine eingagierte Studentin'],
  ['ein engagierter Studierender', 'ein engagierter Student'],
  ['Studierende gesucht', 'Studenten gesucht'],
  ['die Studierenden', 'die Studenten'],
  ['die Studierende', 'die Studentin'],
  ['mit einem Studierenden', 'mit einem Studenten'],
  ['einen engagierten Studierenden', 'einen engagierten Studenten'],
  ['eine Forschende und ein Lehrender', 'eine Forscherin und ein Lehrer'],
  ['die studierenden Kinder', 'die studierenden Kinder'],
  ['Studierende Kinder lernen.', 'Studierende Kinder lernen.'],
  ['Reisende und Vorsitzende', 'Reisende und Vorsitzende'],
  ['mit den Leser*innen', 'mit den Lesern'],
  ['eine engagierte Leser*in', 'eine engagierte Leserin'],
  ['eine Arzt:in', 'eine Ärztin'],
  ['Arzt*in', 'Arzt'],
  ['Bäuer*innen, Köch*innen und Anwält*innen', 'Bauern, Köche und Anwälte'],
  ['Das Wort LeserInnenbereich bleibt.', 'Das Wort LeserInnenbereich bleibt.'],
]) {
  test(`Sprachregel: ${source}`, () =>
    assert.equal(engine.transform(source, settings), expected));
}

test('Ersetzungen erhalten getrennte Formatierungssegmente', () => {
  const result = engine.transformParts(
    ['eine ', 'engagierte', ' Studierende'],
    settings,
  );
  assert.deepEqual([...result], ['eine ', 'engagierte', ' Studentin']);
  assert.deepEqual(
    [...engine.transformParts(['Leser', ':innen'], settings)],
    ['Leser', ''],
  );
});

test('Zusätzliche Wortfamilien erweitern und überschreiben die Standards', () => {
  const custom = {
    ...settings,
    lexicon: [
      {
        source: 'Testend',
        male: 'Tester',
        female: 'Testerin',
        plural: 'Tester',
        weak: false,
      },
    ],
  };
  assert.equal(
    engine.transform('eine Testende und Testende gesucht', custom),
    'eine Testerin und Tester gesucht',
  );
});

test('Regex-Regeln laufen in Reihenfolge und beachten Domains und Capture-Gruppen', () => {
  const custom = {
    ...settings,
    rules: [
      {
        name: 'Erste',
        pattern: '(Leser)',
        flags: 'g',
        replacement: '$1schaft',
        enabled: true,
        domain: 'beispiel.de',
      },
      {
        name: 'Zweite',
        pattern: 'Leserschaft',
        flags: 'g',
        replacement: 'Publikum',
        enabled: true,
        domain: '',
      },
    ],
  };
  assert.equal(
    engine.transform('Leser:innen', custom, 'www.beispiel.de'),
    'Publikum',
  );
  assert.equal(engine.transform('Leser:innen', custom, 'andere.de'), 'Leser');
});

test('Ungültige Einstellungen und Regex werden vor dem Speichern abgewiesen', () => {
  for (const input of [
    null,
    [],
    { intervalSeconds: -1 },
    { intervalSeconds: 0.5 },
    { excludedDomains: ['https://x.de'] },
    {
      rules: [
        { name: 'x', pattern: '[', flags: 'g', replacement: '', enabled: true },
      ],
    },
    { lexicon: [{ source: '.*' }] },
  ]) {
    assert.throws(() => window.LeseklarSettings.validate(input));
  }
});

test('Domains werden an Punktgrenzen geprüft', () => {
  assert.equal(
    window.LeseklarSettings.matchesDomain('www.beispiel.de', 'beispiel.de'),
    true,
  );
  assert.equal(
    window.LeseklarSettings.matchesDomain('keinbeispiel.de', 'beispiel.de'),
    false,
  );
});
