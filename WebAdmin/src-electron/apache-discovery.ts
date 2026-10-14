import { readdir, realpath, stat } from 'node:fs/promises';
import path from 'node:path';
import type { ApacheConfigFile, ApacheScanResult } from '../src/types/apache';

const configDirectories = [
  'conf',
  'conf.d',
  'conf.modules.d',
  'extra',
  'sites-available',
  'sites-enabled',
  'mods-available',
  'mods-enabled',
  'conf-available',
  'conf-enabled',
];
const rootFiles = new Set([
  'httpd.conf',
  'apache2.conf',
  'ports.conf',
  'mime.types',
  'magic',
  'envvars',
]);
const mainFiles = new Set(['httpd.conf', 'apache2.conf']);
const maxEntries = 10000;
const maxDepth = 12;

function describeFile(relativePath: string): string {
  const name = path.basename(relativePath).toLowerCase();
  if (mainFiles.has(name)) return 'Hauptkonfiguration';
  if (name === 'mime.types' || name === 'magic') return 'Dateitypen';
  if (name === 'ports.conf') return 'Ports';
  if (name === 'envvars') return 'Umgebung';
  if (/ssl|tls/.test(name)) return 'TLS / SSL';
  if (/vhosts|sites-(available|enabled)/.test(relativePath)) return 'Virtual Hosts';
  if (/mods-|modules/.test(relativePath) || name.endsWith('.load')) return 'Module';
  return 'Zusätzliche Konfiguration';
}

export async function scanApacheDirectory(directory: unknown): Promise<ApacheScanResult> {
  if (
    typeof directory !== 'string' ||
    !directory.trim() ||
    !path.isAbsolute(directory) ||
    directory.length > 4096
  ) {
    return { ok: false, error: 'Bitte ein gültiges absolutes Apache-Verzeichnis auswählen.' };
  }
  try {
    const root = await realpath(directory);
    if (!(await stat(root)).isDirectory())
      return { ok: false, error: 'Der gespeicherte Pfad ist kein Verzeichnis.' };
    const files: ApacheConfigFile[] = [];
    const warnings = new Set<string>();
    let entries = 0;

    async function collectFile(filePath: string) {
      try {
        const details = await stat(filePath);
        if (!details.isFile()) return;
        const relativePath = path.relative(root, filePath).split(path.sep).join('/');
        files.push({
          path: filePath,
          relativePath,
          category: describeFile(relativePath),
          size: details.size,
        });
      } catch {
        warnings.add(`Datei nicht zugänglich: ${path.relative(root, filePath)}`);
      }
    }

    async function walk(folder: string, depth: number, ancestors: Set<string>) {
      if (depth > maxDepth) {
        warnings.add('Die maximale Suchtiefe wurde erreicht.');
        return;
      }
      let resolved: string;
      try {
        resolved = await realpath(folder);
      } catch {
        warnings.add(`Verzeichnis nicht zugänglich: ${path.relative(root, folder)}`);
        return;
      }
      if (ancestors.has(resolved)) {
        warnings.add(`Verzeichniszyklus übersprungen: ${path.relative(root, folder)}`);
        return;
      }
      const nextAncestors = new Set(ancestors).add(resolved);
      const children = await readdir(folder, { withFileTypes: true }).catch(() => {
        warnings.add(`Verzeichnis nicht lesbar: ${path.relative(root, folder)}`);
        return [];
      });
      for (const child of children) {
        if (++entries > maxEntries) {
          warnings.add('Die Suche wurde nach 10.000 Einträgen begrenzt.');
          return;
        }
        const location = path.join(folder, child.name);
        try {
          const details = child.isSymbolicLink() ? await stat(location) : null;
          if (child.isDirectory() || details?.isDirectory())
            await walk(location, depth + 1, nextAncestors);
          else if (/\.(conf|load)$/i.test(child.name) || rootFiles.has(child.name.toLowerCase()))
            await collectFile(location);
        } catch {
          warnings.add(`Eintrag nicht zugänglich: ${path.relative(root, location)}`);
        }
      }
    }

    const children = await readdir(root, { withFileTypes: true });
    for (const child of children) {
      if (rootFiles.has(child.name.toLowerCase()) || /\.conf$/i.test(child.name))
        await collectFile(path.join(root, child.name));
    }
    for (const name of configDirectories) {
      const folder = path.join(root, name);
      try {
        if ((await stat(folder)).isDirectory()) await walk(folder, 0, new Set([root]));
      } catch (error) {
        if ((error as NodeJS.ErrnoException).code !== 'ENOENT')
          warnings.add(`Verzeichnis nicht zugänglich: ${name}`);
      }
    }
    files.sort(
      (a, b) =>
        Number(b.category === 'Hauptkonfiguration') - Number(a.category === 'Hauptkonfiguration') ||
        a.relativePath.localeCompare(b.relativePath),
    );
    if (!files.some((file) => file.category === 'Hauptkonfiguration'))
      warnings.add(
        'Keine httpd.conf oder apache2.conf gefunden. Prüfe, ob du das Installations- oder Konfigurationsverzeichnis gewählt hast.',
      );
    return { ok: true, directory: root, files, warnings: [...warnings] };
  } catch (error) {
    const code = (error as NodeJS.ErrnoException).code;
    return {
      ok: false,
      error:
        code === 'ENOENT'
          ? 'Das Verzeichnis existiert nicht mehr. Bitte wähle es erneut aus.'
          : 'Das Verzeichnis konnte nicht gelesen werden. Prüfe den Pfad und deine Zugriffsrechte.',
    };
  }
}
