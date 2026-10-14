import assert from 'node:assert/strict';
import { test } from 'node:test';
import { mkdtemp, mkdir, readFile, writeFile, rm, readdir } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import {
  parseApacheConfig,
  updateDirective,
  addDirective,
  removeDirective,
  addVirtualHost,
  removeSection,
  updateSectionArgument,
  quotePath,
} from '../src/lib/apache-config.ts';
import { ApacheFileStore } from '../src-electron/apache-files.ts';

test('edits only the requested scope and preserves BOM, CRLF, comments, unknown directives and repeated Listen', () => {
  const original =
    '\uFEFF# comment\r\nListen 80\r\nListen 8080\r\nCustomUnknown abc\r\n<VirtualHost *:80>\r\n    ServerName old.local\r\n    <Directory "C:/web">\r\n        Require all denied\r\n    </Directory>\r\n</VirtualHost>\r\n';
  const document = parseApacheConfig(original);
  assert.equal(document.errors.length, 0);
  assert.equal(document.directives.filter((item) => item.name === 'Listen').length, 2);
  assert.equal(document.directives.find((item) => item.name === 'Require').section, 6);
  assert.equal(
    updateDirective(original, 5, 'new.local'),
    original.replace('old.local', 'new.local'),
  );
  assert.equal(
    updateSectionArgument(original, 4, '*:443'),
    original.replace('<VirtualHost *:80>', '<VirtualHost *:443>'),
  );
});

test('adds and removes vhosts without modifying nested or conditional original content', () => {
  const original =
    '# preserved\n<IfModule ssl_module>\n<VirtualHost *:443>\n    ServerName secure.local\n</VirtualHost>\n</IfModule>\n';
  const updated = addVirtualHost(original, {
    address: '*:80',
    serverName: 'new.local',
    aliases: 'www.new.local *.new.local',
    documentRoot: '${SRVROOT}/htdocs/new',
    allowOverride: 'None',
    require: 'local',
  });
  assert.ok(updated.startsWith(original));
  assert.match(updated, /<Directory "\$\{SRVROOT\}\/htdocs\/new">/);
  const document = parseApacheConfig(updated);
  assert.equal(document.errors.length, 0);
  const hosts = document.sections.filter((item) => item.name === 'VirtualHost');
  assert.equal(hosts.length, 2);
  assert.ok(removeSection(updated, hosts[1].start).startsWith(original));
});

test('handles custom dropdown values and blocks line injection, malformed blocks and continuation edits', () => {
  const source = 'LogLevel warn ssl:info\nKeepAlive On\n';
  assert.equal(
    updateDirective(source, 0, 'error ssl:debug'),
    'LogLevel error ssl:debug\nKeepAlive On\n',
  );
  assert.equal(removeDirective(source, 1), 'LogLevel warn ssl:info\n');
  assert.equal(addDirective('', null, 'Listen', '8080'), 'Listen 8080\n');
  assert.throws(() => updateDirective(source, 1, 'Off\nServerName injected'));
  assert.throws(() => quotePath('bad"path'));
  assert.equal(quotePath('C:\\web\\'), '"C:/web/"');
  const multiline = 'ServerAlias first.local \\\n  second.local\n';
  assert.equal(parseApacheConfig(multiline).directives[0].editable, false);
  assert.throws(() => updateDirective(multiline, 0, 'replacement'));
  assert.ok(parseApacheConfig('<VirtualHost *:80>\n').errors.length);
  assert.ok(parseApacheConfig('<Directory />\n</VirtualHost>\n').errors.length);
});

async function fileFixture(t, content = '# original\r\nServerName old.local\r\n') {
  const root = await mkdtemp(path.join(os.tmpdir(), 'webadmin-editor-'));
  t.after(() => rm(root, { recursive: true, force: true }));
  await mkdir(path.join(root, 'conf'));
  const file = path.join(root, 'conf', 'httpd.conf');
  await writeFile(file, content);
  return { root, file, store: new ApacheFileStore() };
}

test('saves atomically with original backup and supports a second save in the same session', async (t) => {
  const { root, file, store } = await fileFixture(t);
  const opened = await store.open(root, file);
  assert.equal(opened.ok, true);
  const content = opened.content.replace('old.local', 'new.local');
  const saved = await store.save(opened.token, content);
  assert.equal(saved.ok, true);
  assert.equal(await readFile(file, 'utf8'), content);
  assert.equal(await readFile(saved.backupPath, 'utf8'), opened.content);
  assert.equal(
    (await store.save(opened.token, content.replace('new.local', 'next.local'))).ok,
    true,
  );
  assert.equal(
    (await readdir(path.dirname(file))).filter((name) => name.endsWith('.tmp')).length,
    0,
  );
});

test('refuses external changes, unknown sessions, undiscovered paths and malformed section saves', async (t) => {
  const { root, file, store } = await fileFixture(t);
  assert.equal((await store.open(root, path.join(root, 'secret.txt'))).ok, false);
  assert.equal((await store.save('unknown', 'text')).ok, false);
  const opened = await store.open(root, file);
  assert.equal(opened.ok, true);
  assert.equal((await store.save(opened.token, '<VirtualHost *:80>\n')).ok, false);
  await writeFile(file, '# externally updated');
  const result = await store.save(opened.token, 'ServerName replace.local');
  assert.equal(result.ok, false);
  assert.match(result.error, /außerhalb/);
  assert.equal(await readFile(file, 'utf8'), '# externally updated');
});

test('rejects legacy encoded files and preserves UTF-8 BOM on save', async (t) => {
  const { root, file, store } = await fileFixture(t, Buffer.from([0x23, 0x20, 0xe4]));
  assert.equal((await store.open(root, file)).ok, false);
  await writeFile(file, '\uFEFF# ä\r\nServerName old.local\r\n');
  const opened = await store.open(root, file);
  assert.equal(opened.ok, true);
  assert.ok(opened.content.startsWith('\uFEFF'));
  assert.equal(
    (await store.save(opened.token, opened.content.replace('old.local', 'new.local'))).ok,
    true,
  );
  assert.deepEqual((await readFile(file)).subarray(0, 3), Buffer.from([0xef, 0xbb, 0xbf]));
});
