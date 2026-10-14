import { spawn } from 'node:child_process';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';

const require = createRequire(new URL('../src-electron/package.json', import.meta.url));
const electron = require('electron');
const appPath = fileURLToPath(new URL('../dist/electron/UnPackaged/', import.meta.url));
const environment = { ...process.env };
delete environment.ELECTRON_RUN_AS_NODE;
const temporaryRoot = mkdtempSync(path.join(os.tmpdir(), 'webadmin-smoke-'));
const fixtureRoot = path.join(temporaryRoot, 'Apache24');
mkdirSync(path.join(fixtureRoot, 'conf', 'extra'), { recursive: true });
writeFileSync(path.join(fixtureRoot, 'conf', 'httpd.conf'), '# smoke test');
writeFileSync(
  path.join(fixtureRoot, 'conf', 'extra', 'httpd-vhosts.conf'),
  '# preserved smoke comment\nLogLevel warn\n<VirtualHost *:80>\n    ServerName original.local\n    DocumentRoot "C:/web/original"\n    <Directory "C:/web/original">\n        AllowOverride None\n        Require local\n    </Directory>\n</VirtualHost>\n',
);
environment.WEBADMIN_SMOKE_DIRECTORY = fixtureRoot;
environment.WEBADMIN_SMOKE_PROFILE = path.join(temporaryRoot, 'profile');
process.on('exit', () => rmSync(temporaryRoot, { recursive: true, force: true }));
const child = spawn(electron, [appPath, '--smoke-test'], {
  stdio: 'inherit',
  windowsHide: true,
  env: environment,
});
const timeout = setTimeout(() => {
  child.kill();
  process.exitCode = 1;
}, 30000);
child.on('error', (error) => {
  clearTimeout(timeout);
  console.error(error);
  process.exitCode = 1;
});
child.on('exit', (code) => {
  clearTimeout(timeout);
  process.exitCode = code ?? 1;
});
