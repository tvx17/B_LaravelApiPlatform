import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

export function environment(html = '') {
  const dom = new JSDOM(html, {
    url: 'https://beispiel.de/',
    runScripts: 'outside-only',
  });
  for (const name of ['settings', 'engine', 'dom'])
    dom.window.eval(readFileSync(`src/${name}.js`, 'utf8'));
  const settings = dom.window.LeseklarSettings.validate(
    dom.window.LeseklarSettings.defaults,
  );
  return {
    dom,
    window: dom.window,
    document: dom.window.document,
    settings,
    engine: dom.window.LeseklarEngine,
  };
}
