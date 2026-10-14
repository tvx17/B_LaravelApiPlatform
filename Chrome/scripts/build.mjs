import { cp, mkdir, watch } from 'node:fs/promises';

async function build() {
  await mkdir('dist', { recursive: true });
  await cp('src', 'dist', { recursive: true });
  console.log('Erweiterung nach dist/ kopiert. Im Browser neu laden.');
}

await build();
if (process.argv.includes('--watch')) {
  for await (const event of watch('src', { recursive: true })) {
    if (event.filename) await build();
  }
}
