(() => {
  const defaults = {
    enabled: true,
    languageRules: true,
    accessibleLabels: true,
    intervalSeconds: 0,
    excludedDomains: [],
    rules: [],
    lexicon: [],
  };

  function validate(input) {
    if (!input || typeof input !== 'object' || Array.isArray(input))
      throw new Error('Einstellungen müssen ein Objekt sein.');
    const result = { ...defaults, ...input };
    for (const key of ['enabled', 'languageRules', 'accessibleLabels']) {
      if (typeof result[key] !== 'boolean')
        throw new Error(`${key}: Wahrheitswert erwartet.`);
    }
    if (
      !Number.isFinite(result.intervalSeconds) ||
      result.intervalSeconds < 0 ||
      result.intervalSeconds > 86400 ||
      (result.intervalSeconds > 0 && result.intervalSeconds < 1)
    ) {
      throw new Error('Intervall: 0 oder 1 bis 86400 Sekunden.');
    }
    if (
      !Array.isArray(result.excludedDomains) ||
      result.excludedDomains.some(
        (value) =>
          typeof value !== 'string' || !/^(?:\*\.)?[a-z0-9.-]+$/i.test(value),
      )
    )
      throw new Error('Domain-Ausnahmen ohne Protokoll oder Pfad eintragen.');
    if (!Array.isArray(result.rules) || result.rules.length > 100)
      throw new Error('Maximal 100 Regeln erlaubt.');
    result.rules = result.rules.map((rule) => {
      if (
        !rule ||
        typeof rule.name !== 'string' ||
        typeof rule.pattern !== 'string' ||
        typeof rule.replacement !== 'string' ||
        typeof rule.flags !== 'string' ||
        typeof rule.enabled !== 'boolean'
      )
        throw new Error('Ungültige Regex-Regel.');
      if (rule.pattern.length > 500 || rule.replacement.length > 2000)
        throw new Error('Regel zu lang.');
      if (!/^[gimu]*$/.test(rule.flags))
        throw new Error('Erlaubte Regex-Flags: g, i, m, u.');
      new RegExp(rule.pattern, rule.flags);
      if (
        rule.domain !== undefined &&
        (typeof rule.domain !== 'string' ||
          (rule.domain && !/^(?:\*\.)?[a-z0-9.-]+$/i.test(rule.domain)))
      )
        throw new Error('Ungültige Regeldomain.');
      return {
        name: rule.name,
        pattern: rule.pattern,
        replacement: rule.replacement,
        flags: rule.flags,
        enabled: rule.enabled,
        domain: rule.domain || '',
      };
    });
    if (!Array.isArray(result.lexicon) || result.lexicon.length > 1000)
      throw new Error('Maximal 1000 Wortfamilien erlaubt.');
    result.lexicon = result.lexicon.map((entry) => {
      for (const key of ['source', 'male', 'female', 'plural']) {
        if (
          typeof entry?.[key] !== 'string' ||
          !/^[A-ZÄÖÜ][a-zäöüß]+$/.test(entry[key])
        )
          throw new Error(
            'Wortfamilien benötigen source, male, female und plural als deutsche Wörter.',
          );
      }
      return {
        source: entry.source,
        male: entry.male,
        female: entry.female,
        plural: entry.plural,
        weak: entry.weak === true,
      };
    });
    return Object.fromEntries(
      Object.keys(defaults).map((key) => [key, result[key]]),
    );
  }

  function matchesDomain(host, domain) {
    host = host.toLowerCase();
    domain = domain.toLowerCase();
    const base = domain.replace(/^\*\./, '');
    return host === base || host.endsWith(`.${base}`);
  }

  globalThis.LeseklarSettings = { defaults, validate, matchesDomain };
})();
