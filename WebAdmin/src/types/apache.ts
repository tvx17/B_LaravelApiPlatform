export interface ApacheConfigFile {
  path: string;
  relativePath: string;
  category: string;
  size: number;
}

export type ApacheScanResult =
  | { ok: true; directory: string; files: ApacheConfigFile[]; warnings: string[] }
  | { ok: false; error: string };

export interface DesktopApi {
  readonly platform: string;
  readonly electronVersion: string;
  selectApacheDirectory: () => Promise<string | null>;
  scanApacheDirectory: (directory: string) => Promise<ApacheScanResult>;
  openApacheFile: (directory: string, filePath: string) => Promise<ApacheOpenResult>;
  saveApacheFile: (token: string, content: string) => Promise<ApacheSaveResult>;
}

export type ApacheOpenResult =
  { ok: true; token: string; content: string; path: string } | { ok: false; error: string };
export type ApacheSaveResult = { ok: true; backupPath: string } | { ok: false; error: string };
