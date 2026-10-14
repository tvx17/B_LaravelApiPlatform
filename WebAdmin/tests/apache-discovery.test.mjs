import assert from 'node:assert/strict';
import { test } from 'node:test';
import { mkdtemp, mkdir, writeFile, rm } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { scanApacheDirectory } from '../src-electron/apache-discovery.ts';

async function fixture(t, entries) {
  const root = await mkdtemp(path.join(os.tmpdir(), 'webadmin-apache-'));
  t.after(() => rm(root, { recursive: true, force: true }));
  for (const file of entries) {
    await mkdir(path.dirname(path.join(root, file)), { recursive: true });
    await writeFile(path.join(root, file), '# test configuration');
  }
  return root;
}

test('finds Windows-style Apache configurations while excluding web content and backups', async (t) => {
  const root = await fixture(t, [
    'conf/httpd.conf',
    'conf/extra/httpd-vhosts.conf',
    'conf/extra/httpd-ssl.conf',
    'conf/mime.types',
    'conf/httpd.conf.bak',
    'htdocs/private.conf',
  ]);
  const result = await scanApacheDirectory(root);
  assert.equal(result.ok, true);
  assert.deepEqual(
    result.files.map((file) => file.relativePath),
    [
      'conf/httpd.conf',
      'conf/extra/httpd-ssl.conf',
      'conf/extra/httpd-vhosts.conf',
      'conf/mime.types',
    ],
  );
  assert.equal(result.warnings.length, 0);
  assert.equal(result.files[1].category, 'TLS / SSL');
});

test('accepts configuration folder itself and Debian-style split configuration', async (t) => {
  const root = await fixture(t, [
    'apache2.conf',
    'ports.conf',
    'envvars',
    'mods-enabled/rewrite.load',
    'sites-enabled/default.conf',
    'conf-available/security.conf',
  ]);
  const result = await scanApacheDirectory(root);
  assert.equal(result.ok, true);
  assert.equal(result.files.length, 6);
  assert.equal(result.files[0].relativePath, 'apache2.conf');
  assert.equal(
    result.files.find((file) => file.relativePath.endsWith('rewrite.load')).category,
    'Module',
  );
});

test('reports missing directory and rejects invalid IPC paths', async () => {
  for (const value of [
    null,
    42,
    '',
    'relative/path',
    path.join(os.tmpdir(), `webadmin-missing-${Date.now()}`),
  ]) {
    assert.equal((await scanApacheDirectory(value)).ok, false);
  }
});

test('reports missing main configuration without inventing files', async (t) => {
  const root = await fixture(t, ['notes.txt']);
  const result = await scanApacheDirectory(root);
  assert.equal(result.ok, true);
  assert.deepEqual(result.files, []);
  assert.match(result.warnings[0], /Keine httpd.conf/);
});

test('recognizes nested conf.d files in an installation directory', async (t) => {
  const root = await fixture(t, ['conf/httpd.conf', 'conf.d/nested/site.conf']);
  const result = await scanApacheDirectory(root);
  assert.equal(result.ok, true);
  assert.equal(result.files.length, 2);
});
