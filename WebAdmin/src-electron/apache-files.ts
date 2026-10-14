import { readFile, realpath, stat, writeFile, rename, unlink } from 'node:fs/promises';
import { randomUUID, createHash } from 'node:crypto';
import path from 'node:path';
import { scanApacheDirectory } from './apache-discovery.ts';
import { parseApacheConfig } from '../src/lib/apache-config.ts';
import type { ApacheOpenResult, ApacheSaveResult } from '../src/types/apache';

const limit = 2 * 1024 * 1024;
const digest = (content: Buffer) => createHash('sha256').update(content).digest('hex');
interface OpenFile {
  logicalPath: string;
  realPath: string;
  original: Buffer;
  saving: boolean;
}

export class ApacheFileStore {
  private files = new Map<string, OpenFile>();
  private writingPaths = new Set<string>();

  async open(directory: unknown, filePath: unknown): Promise<ApacheOpenResult> {
    if (typeof filePath !== 'string') return { ok: false, error: 'Ungültiger Dateipfad.' };
    const scan = await scanApacheDirectory(directory);
    if (!scan.ok) return scan;
    if (!scan.files.some((file) => file.path === filePath))
      return { ok: false, error: 'Die Datei gehört nicht zur gefundenen Apache-Konfiguration.' };
    try {
      const resolved = await realpath(filePath);
      if ((await stat(resolved)).size > limit)
        return { ok: false, error: 'Dateien über 2 MB werden im Editor nicht unterstützt.' };
      const original = await readFile(resolved);
      if (original.length > limit || original.includes(0))
        return { ok: false, error: 'Die Datei ist zu groß oder enthält Binärdaten.' };
      // Fatal decoding avoids silently corrupting a legacy encoded configuration.
      const content = new TextDecoder('utf-8', { fatal: true, ignoreBOM: true }).decode(original);
      const token = randomUUID();
      if (this.files.size >= 20) {
        const oldest = [...this.files.entries()].find(([, item]) => !item.saving);
        if (oldest) this.files.delete(oldest[0]);
      }
      this.files.set(token, { logicalPath: filePath, realPath: resolved, original, saving: false });
      return { ok: true, token, content, path: filePath };
    } catch (error) {
      return {
        ok: false,
        error:
          error instanceof TypeError
            ? 'Diese Datei ist nicht UTF-8-kodiert. Sie wird zum Schutz vor Zeichenverlust nicht bearbeitet.'
            : 'Datei konnte nicht geöffnet werden. Prüfe ihre Existenz und Zugriffsrechte.',
      };
    }
  }

  async save(token: unknown, content: unknown): Promise<ApacheSaveResult> {
    if (typeof token !== 'string' || typeof content !== 'string')
      return { ok: false, error: 'Ungültige Speicheranfrage.' };
    const file = this.files.get(token);
    if (!file)
      return {
        ok: false,
        error: 'Die Editorsitzung ist abgelaufen. Bitte die Datei erneut öffnen.',
      };
    if (file.saving || this.writingPaths.has(file.realPath))
      return { ok: false, error: 'Diese Datei wird bereits gespeichert.' };
    if (Buffer.byteLength(content, 'utf8') > limit || content.includes('\0'))
      return { ok: false, error: 'Der Inhalt ist zu groß oder enthält ungültige Zeichen.' };
    if (/\.(conf|load)$/i.test(file.logicalPath)) {
      const errors = parseApacheConfig(content).errors;
      if (errors.length) return { ok: false, error: errors.join(' ') };
    }
    file.saving = true;
    this.writingPaths.add(file.realPath);
    let temporaryPath: string | undefined;
    try {
      if ((await realpath(file.logicalPath)) !== file.realPath)
        return {
          ok: false,
          error: 'Das Dateiziel hat sich geändert. Bitte die Datei erneut öffnen.',
        };
      const current = await readFile(file.realPath);
      if (digest(current) !== digest(file.original))
        return {
          ok: false,
          error:
            'Die Datei wurde außerhalb von WebAdmin geändert. Bitte Änderungen kopieren und die Datei neu laden.',
        };
      const metadata = await stat(file.realPath);
      if (metadata.nlink > 1)
        return {
          ok: false,
          error: 'Dateien mit mehreren Hardlinks werden nicht automatisch ersetzt.',
        };
      const suffix = randomUUID();
      const backupPath = `${file.realPath}.webadmin-${suffix}.bak`;
      await writeFile(backupPath, current, { flag: 'wx', mode: metadata.mode });
      temporaryPath = path.join(path.dirname(file.realPath), `.webadmin-${suffix}.tmp`);
      const updated = Buffer.from(content, 'utf8');
      await writeFile(temporaryPath, updated, { flag: 'wx', mode: metadata.mode });
      if (
        (await realpath(file.logicalPath)) !== file.realPath ||
        digest(await readFile(file.realPath)) !== digest(current)
      )
        return {
          ok: false,
          error: 'Die Datei wurde während des Speicherns geändert. Sie wurde nicht überschrieben.',
        };
      await rename(temporaryPath, file.realPath);
      temporaryPath = undefined;
      file.original = updated;
      return { ok: true, backupPath };
    } catch {
      return {
        ok: false,
        error:
          'Speichern fehlgeschlagen. Prüfe die Schreibrechte für Datei und Verzeichnis. Falls eine Sicherung angelegt wurde, bleibt sie erhalten.',
      };
    } finally {
      file.saving = false;
      this.writingPaths.delete(file.realPath);
      if (temporaryPath) await unlink(temporaryPath).catch(() => undefined);
    }
  }
}
