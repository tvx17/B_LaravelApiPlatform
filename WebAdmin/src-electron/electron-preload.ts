import { contextBridge, ipcRenderer } from 'electron';
import type { DesktopApi } from '../src/types/apache';
import { quasarRuntime } from '#q-app/electron/preload';
contextBridge.exposeInMainWorld('quasarRuntime', quasarRuntime);
contextBridge.exposeInMainWorld(
  'webadmin',
  Object.freeze({
    platform: process.platform,
    electronVersion: process.versions.electron,
    selectApacheDirectory: () =>
      ipcRenderer.invoke('apache:select-directory') as ReturnType<
        DesktopApi['selectApacheDirectory']
      >,
    scanApacheDirectory: (directory: string) =>
      ipcRenderer.invoke('apache:scan-directory', directory) as ReturnType<
        DesktopApi['scanApacheDirectory']
      >,
    openApacheFile: (directory: string, filePath: string) =>
      ipcRenderer.invoke('apache:open-file', directory, filePath) as ReturnType<
        DesktopApi['openApacheFile']
      >,
    saveApacheFile: (token: string, content: string) =>
      ipcRenderer.invoke('apache:save-file', token, content) as ReturnType<
        DesktopApi['saveApacheFile']
      >,
  }),
);
