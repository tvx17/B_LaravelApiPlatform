import { mkdir, readdir, readFile, writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import { zipSync } from 'fflate';

const files = {};
async function collect(directory, prefix = '') {
  for (const entry of await readdir(directory, { withFileTypes: true })) {
    const name = prefix + entry.name;
    if (entry.isDirectory())
      await collect(join(directory, entry.name), name + '/');
    else if (entry.isFile())
      files[name] = new Uint8Array(await readFile(join(directory, entry.name)));
  }
}
await collect('dist');
await mkdir('release', { recursive: true });
await writeFile('release/leseklar.zip', zipSync(files, { level: 6 }));
console.log('Paket erstellt: release/leseklar.zip');
