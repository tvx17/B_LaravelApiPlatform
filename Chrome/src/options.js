(() => {
  const $ = (id) => document.getElementById(id);
  const status = (text) => {
    $('status').textContent = text;
  };
  let previewWorker;

  function addRule(
    rule = {
      name: '',
      pattern: '',
      replacement: '',
      flags: 'gu',
      enabled: true,
      domain: '',
    },
  ) {
    const row = document.createElement('div');
    row.className = 'rule';
    for (const [key, label] of [
      ['name', 'Name'],
      ['pattern', 'Suchmuster'],
      ['replacement', 'Ersatztext'],
      ['flags', 'Flags (g, i, m, u)'],
      ['domain', 'Nur auf Domain (optional)'],
    ]) {
      const field = document.createElement('label');
      field.textContent = label;
      const input = document.createElement('input');
      input.dataset.key = key;
      input.value = rule[key];
      field.append(input);
      row.append(field);
    }
    const label = document.createElement('label');
    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.checked = rule.enabled;
    checkbox.dataset.key = 'enabled';
    label.append(checkbox, 'Regel aktiv');
    row.append(label);
    for (const [caption, action] of [
      [
        'Nach oben',
        () => {
          if (row.previousElementSibling)
            row.parentNode.insertBefore(row, row.previousElementSibling);
        },
      ],
      [
        'Nach unten',
        () => {
          if (row.nextElementSibling)
            row.parentNode.insertBefore(row.nextElementSibling, row);
        },
      ],
      ['Entfernen', () => row.remove()],
    ]) {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'secondary';
      button.textContent = caption;
      button.addEventListener('click', action);
      row.append(button);
    }
    $('rules').append(row);
  }

  function fill(settings) {
    for (const key of ['enabled', 'languageRules', 'accessibleLabels'])
      $(key).checked = settings[key];
    $('interval').value = settings.intervalSeconds;
    $('domains').value = settings.excludedDomains.join('\n');
    $('lexicon').value = JSON.stringify(settings.lexicon, null, 2);
    $('rules').replaceChildren();
    settings.rules.forEach(addRule);
  }

  function read() {
    return LeseklarSettings.validate({
      enabled: $('enabled').checked,
      languageRules: $('languageRules').checked,
      accessibleLabels: $('accessibleLabels').checked,
      intervalSeconds: Number($('interval').value),
      excludedDomains: $('domains').value.split(/\s+/).filter(Boolean),
      lexicon: JSON.parse($('lexicon').value),
      rules: [...$('rules').children].map((row) =>
        Object.fromEntries(
          [...row.querySelectorAll('[data-key]')].map((input) => [
            input.dataset.key,
            input.type === 'checkbox' ? input.checked : input.value,
          ]),
        ),
      ),
    });
  }

  $('settings-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
      await chrome.storage.local.set({ settings: read() });
      status('Einstellungen gespeichert.');
    } catch (error) {
      status(`Nicht gespeichert: ${error.message}`);
    }
  });
  $('add-rule').addEventListener('click', () => addRule());
  $('preview').addEventListener('click', () => {
    try {
      const settings = read();
      const host = $('preview-domain').value.trim();
      previewWorker?.terminate();
      if (
        !settings.enabled ||
        settings.excludedDomains.some((domain) =>
          LeseklarSettings.matchesDomain(host, domain),
        )
      ) {
        $('result').textContent = $('sample').value;
        return;
      }
      previewWorker = new Worker('preview-worker.js');
      const worker = previewWorker;
      const timer = setTimeout(() => {
        worker.terminate();
        status('Vorschau abgebrochen: Regel benötigt zu viel Zeit.');
      }, 1000);
      worker.onmessage = ({ data }) => {
        clearTimeout(timer);
        worker.terminate();
        $('result').textContent = data.result || '';
        status(data.error || 'Vorschau aktualisiert; noch nicht gespeichert.');
      };
      worker.onerror = () => {
        clearTimeout(timer);
        worker.terminate();
        status('Vorschau fehlgeschlagen.');
      };
      worker.postMessage({
        text: $('sample').value.slice(0, 30000),
        settings,
        host,
      });
    } catch (error) {
      status(error.message);
    }
  });
  $('export').addEventListener('click', () => {
    try {
      const blob = new Blob(
        [JSON.stringify({ version: 1, settings: read() }, null, 2)],
        { type: 'application/json' },
      );
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = 'leseklar-regeln.json';
      link.click();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
      status('Aktuelle Formulareinstellungen exportiert.');
    } catch (error) {
      status(error.message);
    }
  });
  $('import').addEventListener('change', async (event) => {
    try {
      const file = event.target.files[0];
      if (!file) return;
      if (file.size > 1000000) throw new Error('Datei größer als 1 MB.');
      const data = JSON.parse(await file.text());
      if (data.version !== 1) throw new Error('Unbekannte Dateiversion.');
      fill(LeseklarSettings.validate(data.settings));
      status('Import geprüft. Zum Übernehmen bitte speichern.');
    } catch (error) {
      status(`Import fehlgeschlagen: ${error.message}`);
    } finally {
      event.target.value = '';
    }
  });
  for (const family of LeseklarEngine.families) {
    const item = document.createElement('li');
    item.textContent = `${family.source}e → ${family.male} / ${family.female} / ${family.plural}`;
    $('families').append(item);
  }
  chrome.storage.local
    .get('settings')
    .then(({ settings }) =>
      fill(LeseklarSettings.validate(settings || LeseklarSettings.defaults)),
    )
    .catch((error) => {
      fill(LeseklarSettings.defaults);
      status(
        `Gespeicherte Einstellungen konnten nicht geladen werden: ${error.message}`,
      );
    });
})();
