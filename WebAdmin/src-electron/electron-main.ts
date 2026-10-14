import { app, BrowserWindow, dialog, ipcMain } from 'electron';
import path from 'node:path';
import { registerQuasarRuntime, resolveElectronAssetsPath } from '#q-app/electron/main';
import { scanApacheDirectory } from './apache-discovery';
import { ApacheFileStore } from './apache-files';

const smokeTest = process.argv.includes('--smoke-test');
if (smokeTest && process.env.WEBADMIN_SMOKE_PROFILE)
  app.setPath('userData', process.env.WEBADMIN_SMOKE_PROFILE);
let smokeDialogCalls = 0;
async function createWindow() {
  const mainWindow = new BrowserWindow({
    title: 'WebAdmin',
    width: 1200,
    height: 800,
    minWidth: 760,
    minHeight: 560,
    show: !smokeTest,
    icon: resolveElectronAssetsPath('icons/icon.png'),
    webPreferences: {
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: true,
      preload: path.join(import.meta.dirname, 'electron-preload.cjs'),
    },
  });
  ipcMain.removeHandler('apache:select-directory');
  ipcMain.removeHandler('apache:scan-directory');
  ipcMain.removeHandler('apache:open-file');
  ipcMain.removeHandler('apache:save-file');
  const fileStore = new ApacheFileStore();
  ipcMain.handle('apache:open-file', (event, directory: unknown, filePath: unknown) => {
    if (
      event.sender !== mainWindow.webContents ||
      event.senderFrame !== mainWindow.webContents.mainFrame
    )
      throw new Error('Ungültiger Aufrufer');
    return fileStore.open(directory, filePath);
  });
  ipcMain.handle('apache:save-file', (event, token: unknown, content: unknown) => {
    if (
      event.sender !== mainWindow.webContents ||
      event.senderFrame !== mainWindow.webContents.mainFrame
    )
      throw new Error('Ungültiger Aufrufer');
    return fileStore.save(token, content);
  });
  ipcMain.handle('apache:select-directory', async (event) => {
    if (
      event.sender !== mainWindow.webContents ||
      event.senderFrame !== mainWindow.webContents.mainFrame
    )
      throw new Error('Ungültiger Aufrufer');
    if (smokeTest)
      return smokeDialogCalls++ === 0 ? (process.env.WEBADMIN_SMOKE_DIRECTORY ?? null) : null;
    const result = await dialog.showOpenDialog(mainWindow, {
      title: 'Apache-Verzeichnis auswählen',
      properties: ['openDirectory'],
    });
    return result.canceled ? null : (result.filePaths[0] ?? null);
  });
  ipcMain.handle('apache:scan-directory', (event, directory: unknown) => {
    if (
      event.sender !== mainWindow.webContents ||
      event.senderFrame !== mainWindow.webContents.mainFrame
    )
      throw new Error('Ungültiger Aufrufer');
    return scanApacheDirectory(directory);
  });
  mainWindow.webContents.setWindowOpenHandler(() => ({ action: 'deny' }));
  mainWindow.webContents.on('will-prevent-unload', (event) => {
    const choice = dialog.showMessageBoxSync(mainWindow, {
      type: 'question',
      title: 'Ungespeicherte Änderungen',
      message: 'Ungespeicherte Änderungen verwerfen und das Fenster schließen oder neu laden?',
      buttons: ['Im Editor bleiben', 'Änderungen verwerfen'],
      defaultId: 0,
      cancelId: 0,
    });
    if (choice === 1) event.preventDefault();
  });
  mainWindow.webContents.on('will-navigate', (event, url) => {
    if (url.split('#')[0] !== mainWindow.webContents.getURL().split('#')[0]) event.preventDefault();
  });
  if (import.meta.env.QUASAR_DEV) await mainWindow.loadURL(import.meta.env.QUASAR_APP_URL);
  else await mainWindow.loadFile('index.html');

  if (smokeTest) {
    const result: unknown = await mainWindow.webContents.executeJavaScript(`(async () => {
      const waitFor = async (text) => {
        for (let attempt = 0; attempt < 100; attempt++) {
          if (document.body.textContent.includes(text)) return;
          await new Promise(resolve => setTimeout(resolve, 50));
        }
        throw new Error('Page did not render: ' + text + ' | ' + [...document.querySelectorAll('[role=alert], .q-dialog .q-banner')].map(item => item.textContent).join(' | '));
      };
      await waitFor('Ein klarer Blick');
      if (!window.webadmin?.electronVersion) throw new Error('Preload bridge missing');
      if (typeof window.require !== 'undefined') throw new Error('Node exposed to renderer');
      location.hash = '#/workspace';
      await waitFor('Noch kein Verzeichnis ausgewählt');
      const selectButton = [...document.querySelectorAll('button')].find(button => button.textContent.includes('Verzeichnis auswählen'));
      if (!selectButton) throw new Error('Directory selector missing');
      selectButton.click();
      await waitFor('2 Konfigurationsdateien gefunden');
      await waitFor('conf/httpd.conf');
      const savedDirectory = localStorage.getItem('webadmin.apache.directory');
      if (!savedDirectory) throw new Error('Directory not persisted');
      [...document.querySelectorAll('button')].find(button => button.textContent.includes('Verzeichnis ändern')).click();
      await new Promise(resolve => setTimeout(resolve, 150));
      if (localStorage.getItem('webadmin.apache.directory') !== savedDirectory) throw new Error('Cancel changed selection');
      const editRow = [...document.querySelectorAll('tbody tr')].find(row => row.textContent.includes('conf/extra/httpd-vhosts.conf'));
      editRow?.querySelector('a')?.click();
      await waitFor('Apache-Datei bearbeiten.');
      await waitFor('LogLevel');
      const logLevelField = [...document.querySelectorAll('label')].find(label => label.textContent.includes('LogLevel —'));
      logLevelField?.querySelector('input')?.click();
      for (let attempt = 0; attempt < 100 && !document.querySelector('.q-menu'); attempt++) await new Promise(resolve => setTimeout(resolve, 20));
      const logOption = [...document.querySelectorAll('.q-menu .q-item')].find(item => item.textContent.trim() === 'error');
      if (!logOption) throw new Error('LogLevel dropdown option missing');
      logOption.click();
      const clickTab = label => [...document.querySelectorAll('[role="tab"]')].find(tab => tab.textContent.trim().toLowerCase() === label.toLowerCase())?.click();
      const setInput = async (label, value) => {
        const scope = document.querySelector('.q-dialog') ?? document;
        const input = [...scope.querySelectorAll('input')].find(input => input.closest('label')?.textContent.includes(label));
        if (!input) throw new Error('Missing editor input: ' + label);
        input.focus();
        Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(input, value);
        input.dispatchEvent(new Event('input', { bubbles: true }));
        await new Promise(resolve => setTimeout(resolve, 30));
        input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
        input.blur();
      };
      clickTab('Virtual Hosts');
      await waitFor('ServerName —');
      await setInput('ServerName —', 'edited.local');
      await waitFor('Ungespeicherte Änderungen');
      [...document.querySelectorAll('button')].find(button => button.textContent.includes('Virtual Host anlegen')).click();
      await waitFor('In Entwurf übernehmen');
      await setInput('ServerName — Hostname', 'new.local');
      await setInput('DocumentRoot — Webverzeichnis', 'C:/web/new');
      [...document.querySelectorAll('button')].find(button => button.textContent.includes('In Entwurf übernehmen')).click();
      await waitFor('2 Virtual Hosts');
      clickTab('Quelltext');
      await waitFor('Konfigurations-Quelltext');
      const content = document.querySelector('textarea')?.value;
      if (!content?.includes('ServerName edited.local') || !content.includes('ServerName new.local') || !content.includes('# preserved smoke comment') || !content.includes('LogLevel error')) throw new Error('Virtual host / dropdown edits not preserved');
      [...document.querySelectorAll('button')].find(button => button.textContent.includes('Datei speichern')).click();
      await waitFor('Gespeichert. Sicherung:');
      [...document.querySelectorAll('button')].find(button => button.textContent.includes('Datei neu laden')).click();
      await waitFor('Keine offenen Änderungen');
      await new Promise(resolve => setTimeout(resolve, 200));
      if (!document.querySelector('textarea')?.value.includes('ServerName new.local')) throw new Error('Saved virtual host missing on reload');
      clickTab('Virtual Hosts');
      await waitFor('2 Virtual Hosts');
      [...document.querySelectorAll('button')].find(button => button.textContent.includes('Virtual Host löschen')).click();
      await waitFor('Änderung bestätigen');
      [...document.querySelectorAll('.q-dialog button')].find(button => button.textContent.includes('Bestätigen')).click();
      await waitFor('1 Virtual Hosts');
      [...document.querySelectorAll('button')].find(button => button.textContent.includes('Datei speichern')).click();
      await waitFor('Gespeichert. Sicherung:');
      location.hash = '#/settings';
      await waitFor('Deine Umgebung.');
      if (!document.body.textContent.includes(window.webadmin.electronVersion)) throw new Error('Runtime information missing');
      location.hash = '#/missing';
      await waitFor('Diese Seite wurde nicht gefunden.');
      location.hash = '#/workspace';
      await waitFor('2 Konfigurationsdateien gefunden');
      return { platform: window.webadmin.platform, electron: window.webadmin.electronVersion, pages: 5, editor: true };
    })()`);
    await new Promise<void>((resolve) => {
      mainWindow.webContents.once('did-finish-load', resolve);
      mainWindow.reload();
    });
    await mainWindow.webContents.executeJavaScript(`(async () => {
      for (let attempt = 0; attempt < 100; attempt++) {
        if (document.body.textContent.includes('2 Konfigurationsdateien gefunden')) break;
        await new Promise(resolve => setTimeout(resolve, 50));
      }
      if (!document.body.textContent.includes('conf/extra/httpd-vhosts.conf')) throw new Error('Restored directory not scanned');
      [...document.querySelectorAll('button')].find(button => button.textContent.includes('Auswahl entfernen')).click();
      await new Promise(resolve => setTimeout(resolve, 50));
      if (localStorage.getItem('webadmin.apache.directory') !== null) throw new Error('Directory not removed');
      if (!document.body.textContent.includes('Noch kein Verzeichnis ausgewählt')) throw new Error('Reset did not render');
    })()`);
    console.log('SMOKE_OK', JSON.stringify(result));
    app.exit(0);
  }
}

void app
  .whenReady()
  .then(async () => {
    registerQuasarRuntime();
    if (smokeTest)
      setTimeout(() => {
        console.error('SMOKE_TIMEOUT');
        app.exit(1);
      }, 20000).unref();
    await createWindow();
    app.on('activate', () => {
      if (BrowserWindow.getAllWindows().length === 0)
        void createWindow().catch((error: unknown) => {
          console.error(error);
          app.exit(1);
        });
    });
  })
  .catch((error: unknown) => {
    console.error(error);
    app.exit(1);
  });
app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') app.quit();
});
