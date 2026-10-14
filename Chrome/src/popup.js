(() => {
  const checkbox = document.getElementById('enabled');
  const status = document.getElementById('status');
  async function tabMessage(type) {
    const [tab] = await chrome.tabs.query({
      active: true,
      currentWindow: true,
    });
    if (!tab) throw new Error('Keine aktive Seite.');
    return chrome.tabs.sendMessage(tab.id, { type }, { frameId: 0 });
  }
  chrome.storage.local
    .get('settings')
    .then(async ({ settings }) => {
      checkbox.checked = LeseklarSettings.validate(
        settings || LeseklarSettings.defaults,
      ).enabled;
      checkbox.disabled = false;
      try {
        const result = await tabMessage('status');
        status.textContent = result.active
          ? `Aktiv. ${result.changed} Textbereiche aktualisiert.`
          : 'Auf dieser Seite deaktiviert.';
      } catch {
        status.textContent =
          'Auf Browserseiten nicht verfügbar. Bereits geöffnete Webseiten nach Installation neu laden.';
      }
    })
    .catch((error) => {
      status.textContent = error.message;
    });
  checkbox.addEventListener('change', async () => {
    try {
      const { settings } = await chrome.storage.local.get('settings');
      await chrome.storage.local.set({
        settings: {
          ...LeseklarSettings.validate(settings || LeseklarSettings.defaults),
          enabled: checkbox.checked,
        },
      });
      status.textContent = checkbox.checked
        ? 'Erweiterung aktiviert.'
        : 'Erweiterung deaktiviert.';
    } catch (error) {
      status.textContent = error.message;
    }
  });
  document.getElementById('scan').addEventListener('click', async () => {
    try {
      const result = await tabMessage('scan');
      status.textContent = result.active
        ? 'Seite erneut geprüft.'
        : 'Auf dieser Seite deaktiviert.';
    } catch {
      status.textContent =
        'Seite kann nicht geprüft werden. Webseite neu laden oder Berechtigungen prüfen.';
    }
  });
  document
    .getElementById('options')
    .addEventListener('click', () => chrome.runtime.openOptionsPage());
})();
