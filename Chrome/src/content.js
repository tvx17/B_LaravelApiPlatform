(() => {
  const processor = LeseklarDOM.createProcessor(document, location.hostname);
  let settings;
  let interval;
  let debounce;
  let active = false;
  let changed = 0;
  const pending = new Set();
  const reveal = () =>
    document.documentElement?.setAttribute('data-leseklar-ready', 'true');
  const failOpen = setTimeout(reveal, 1400);

  const observer = new MutationObserver((records) => {
    if (
      records.some((record) =>
        ['contenteditable', 'translate', 'data-leseklar-ignore'].includes(
          record.attributeName,
        ),
      )
    ) {
      apply(settings);
      return;
    }
    for (const record of records) {
      const scope = processor.scope(record.target);
      if (scope) pending.add(scope);
    }
    if (pending.size && !debounce) debounce = setTimeout(flush, 40);
  });

  function observe() {
    if (active)
      observer.observe(document, {
        subtree: true,
        childList: true,
        characterData: true,
        attributes: true,
        attributeFilter: [
          'aria-label',
          'aria-description',
          'aria-valuetext',
          'alt',
          'title',
          'contenteditable',
          'translate',
          'data-leseklar-ignore',
        ],
      });
  }

  function scan(root = document) {
    if (!active) return;
    observer.disconnect();
    try {
      changed += processor.scan(root, settings);
    } catch (error) {
      console.warn('Leseklar: Verarbeitung fehlgeschlagen.', error);
    } finally {
      observe();
    }
  }

  function flush() {
    debounce = undefined;
    const roots = [...pending].filter((root) => root.isConnected);
    pending.clear();
    for (const root of roots) {
      if (!roots.some((other) => other !== root && other.contains(root)))
        scan(root);
    }
  }

  function apply(value) {
    settings = LeseklarSettings.validate(value);
    clearInterval(interval);
    clearTimeout(debounce);
    debounce = undefined;
    pending.clear();
    observer.disconnect();
    processor.restore();
    changed = 0;
    active =
      settings.enabled &&
      !settings.excludedDomains.some((domain) =>
        LeseklarSettings.matchesDomain(location.hostname, domain),
      );
    if (active) {
      scan();
      if (settings.intervalSeconds)
        interval = setInterval(() => scan(), settings.intervalSeconds * 1000);
    }
    if (!active || document.readyState !== 'loading') reveal();
  }

  document.addEventListener(
    'DOMContentLoaded',
    () => {
      scan();
      if (settings) {
        reveal();
        clearTimeout(failOpen);
      }
    },
    { once: true },
  );

  chrome.storage.local
    .get('settings')
    .then(({ settings: stored }) => apply(stored || LeseklarSettings.defaults))
    .catch((error) => {
      reveal();
      console.warn('Leseklar: Einstellungen nicht verfügbar.', error);
    });
  chrome.storage.onChanged.addListener((changes, area) => {
    if (area !== 'local' || !changes.settings) return;
    try {
      apply(changes.settings.newValue || LeseklarSettings.defaults);
    } catch (error) {
      reveal();
      console.warn('Leseklar: Ungültige Einstellungen.', error);
    }
  });
  chrome.runtime.onMessage.addListener((message, _sender, respond) => {
    if (message.type === 'scan') {
      scan();
      respond({ active, changed });
    }
    if (message.type === 'status') respond({ active, changed });
  });
})();
