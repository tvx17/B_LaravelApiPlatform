(() => {
  const families = [
    ['Studierend', 'Student', 'Studentin', 'Studenten', true],
    ['Dozierend', 'Dozent', 'Dozentin', 'Dozenten', true],
    ['Lehrend', 'Lehrer', 'Lehrerin', 'Lehrer'],
    ['Lernend', 'Schüler', 'Schülerin', 'Schüler'],
    ['Forschend', 'Forscher', 'Forscherin', 'Forscher'],
    ['Lesend', 'Leser', 'Leserin', 'Leser'],
    ['Teilnehmend', 'Teilnehmer', 'Teilnehmerin', 'Teilnehmer'],
    ['Mitarbeitend', 'Mitarbeiter', 'Mitarbeiterin', 'Mitarbeiter'],
    ['Bewerbend', 'Bewerber', 'Bewerberin', 'Bewerber'],
    ['Arbeitnehmend', 'Arbeitnehmer', 'Arbeitnehmerin', 'Arbeitnehmer'],
    ['Arbeitgebend', 'Arbeitgeber', 'Arbeitgeberin', 'Arbeitgeber'],
  ].map(([source, male, female, plural, weak = false]) => ({
    source,
    male,
    female,
    plural,
    weak,
  }));

  const irregular = {
    Ärzt: 'Ärzte',
    Arzt: 'Ärzte',
    Anwält: 'Anwälte',
    Anwalt: 'Anwälte',
    Kolleg: 'Kollegen',
    Expert: 'Experten',
    Kund: 'Kunden',
    Student: 'Studenten',
    Dozent: 'Dozenten',
    Assistent: 'Assistenten',
    Polizist: 'Polizisten',
    Journalist: 'Journalisten',
    Pädagog: 'Pädagogen',
    Psycholog: 'Psychologen',
    Biolog: 'Biologen',
    Architekt: 'Architekten',
    Pilot: 'Piloten',
    Soldat: 'Soldaten',
    Beamt: 'Beamte',
    Freund: 'Freunde',
    Köch: 'Köche',
    Koch: 'Köche',
    Bäuer: 'Bauern',
    Bauer: 'Bauern',
    Nachbar: 'Nachbarn',
    Autor: 'Autoren',
    Professor: 'Professoren',
    Direktor: 'Direktoren',
  };
  const singular = {
    Ärzt: 'Arzt',
    Anwält: 'Anwalt',
    Kolleg: 'Kollege',
    Expert: 'Experte',
    Kund: 'Kunde',
    Pädagog: 'Pädagoge',
    Psycholog: 'Psychologe',
    Biolog: 'Biologe',
    Beamt: 'Beamter',
    Köch: 'Koch',
    Bäuer: 'Bauer',
  };

  // Replace ranges without serializing HTML or deleting element objects.
  function replaceParts(parts, expression, replacement) {
    const text = parts.join('');
    const edits = [];
    text.replace(expression, (...args) => {
      const hasGroups = typeof args.at(-1) === 'object';
      const offset = args.at(hasGroups ? -3 : -2);
      const match = args[0];
      const value =
        typeof replacement === 'function'
          ? replacement(...args)
          : expandReplacement(replacement, args, text, offset, hasGroups);
      if (value !== match)
        edits.push({ start: offset, end: offset + match.length, value });
      return match;
    });
    const starts = [];
    let length = 0;
    for (const part of parts) {
      starts.push(length);
      length += part.length;
    }
    const output = [...parts];
    for (const edit of edits.reverse()) {
      let first = starts.findIndex(
        (start, index) =>
          edit.start >= start &&
          (edit.start < start + parts[index].length ||
            index === parts.length - 1),
      );
      if (first < 0) first = 0;
      for (let index = first; index < parts.length; index++) {
        const from = Math.max(0, edit.start - starts[index]);
        const to = Math.min(parts[index].length, edit.end - starts[index]);
        if (to < 0 || starts[index] > edit.end) break;
        output[index] =
          output[index].slice(0, from) +
          (index === first ? edit.value : '') +
          output[index].slice(Math.max(from, to));
        if (starts[index] + parts[index].length >= edit.end) break;
      }
    }
    return output;
  }

  function expandReplacement(template, args, text, offset, hasGroups) {
    const captures = args.slice(1, hasGroups ? -3 : -2);
    return template.replace(/\$(\$|&|`|'|\d{1,2}|<[^>]+>)/g, (token, key) => {
      if (key === '$') return '$';
      if (key === '&') return args[0];
      if (key === '`') return text.slice(0, offset);
      if (key === "'") return text.slice(offset + args[0].length);
      if (key.startsWith('<'))
        return hasGroups ? (args.at(-1)[key.slice(1, -1)] ?? '') : token;
      const number = Number(key);
      if (number > 0 && number <= captures.length)
        return captures[number - 1] ?? '';
      if (
        key.length === 2 &&
        Number(key[0]) > 0 &&
        Number(key[0]) <= captures.length
      )
        return (captures[Number(key[0]) - 1] ?? '') + key[1];
      return token;
    });
  }

  function nominal(entry, ending, prefix) {
    // A deliberately bounded heuristic, not a complete German parser.
    const words = prefix
      .split(/[.!?;:\n,]|\b(?:und|oder|sowie)\b/i)
      .at(-1)
      .trim()
      .split(/\s+/);
    const tail = words.slice(-4);
    const article = tail.findLast((word) =>
      /^(eine|einer|einem|einen|ein|die|der|den|das|dieser|diese|diesem|diesen|jede|jeder|jedem|jeden|meine|meiner|mein|meinem|meinen|unsere|unserer|unser|unserem|unseren)$/i.test(
        word,
      ),
    );
    const female =
      /^(eine|einer|meine|meiner|unsere|unserer|jede)$/i.test(article || '') ||
      (ending === 'e' && /^(die|diese)$/i.test(article || ''));
    if (female) return entry.female;
    const maleSingular =
      /^(ein|einem|einen|mein|meinem|meinen|unser|unserem|unseren|jedem|jeden)$/i.test(
        article || '',
      ) ||
      ['er', 'em', 'es'].includes(ending) ||
      (ending === 'e' && /^(der|dieser|jeder)$/i.test(article || ''));
    if (maleSingular) {
      const oblique =
        /^(einem|einen|meinem|meinen|unserem|unseren|jedem|jeden)$/i.test(
          article || '',
        ) || ['em', 'es'].includes(ending);
      return entry.male + (entry.weak && oblique ? 'en' : '');
    }
    return entry.plural;
  }

  function transformParts(input, settings, host = '') {
    let parts = [...input];
    if (settings.languageRules) {
      // Full words, including compounds. Unknown stems stay intact unless their
      // masculine form follows a supported productive suffix.
      const pattern =
        /(?<![\p{L}\p{N}_])([A-ZÄÖÜ][\p{L}]*?)(?:[:*·_]innen|\/-?innen|Innen|[:*·_]in|\/-?in|In)(?![\p{L}\p{N}_])/gu;
      parts = replaceParts(parts, pattern, (match, stem, offset, text) => {
        const plural = /(?:innen|Innen)$/.test(match);
        const context = text
          .slice(Math.max(0, offset - 100), offset)
          .split(/[.!?;:\n,]|\b(?:und|oder|sowie)\b/i)
          .at(-1);
        if (
          !plural &&
          /\b(?:eine|einer|meine|meiner|unsere|unserer|jede)\s+(?:[a-zäöüß]+\s+){0,2}$/iu.test(
            context,
          )
        ) {
          const femaleStem = stem.endsWith('Arzt')
            ? stem.slice(0, -4) + 'Ärzt'
            : stem.endsWith('Anwalt')
              ? stem.slice(0, -6) + 'Anwält'
              : stem;
          return femaleStem + 'in';
        }
        const knownKey = Object.keys(irregular).find((key) =>
          stem.endsWith(key),
        );
        let result;
        if (knownKey)
          result =
            stem.slice(0, -knownKey.length) +
            (plural ? irregular[knownKey] : singular[knownKey] || knownKey);
        else if (/(?:er|ler|ner)$/.test(stem)) result = stem;
        else if (/(?:ist|ant|ent|oge)$/.test(stem))
          result = stem + (plural ? 'en' : '');
        else return match;
        if (
          plural &&
          /(?:\bden|\bmit|\bvon|\bzu)\s+(?:[a-zäöüß]+\s+){0,2}$/iu.test(
            text.slice(Math.max(0, offset - 60), offset),
          ) &&
          result.endsWith('er')
        )
          result += 'n';
        return result;
      });
      const entries = [
        ...families.filter(
          (item) =>
            !settings.lexicon.some((custom) => custom.source === item.source),
        ),
        ...settings.lexicon,
      ];
      for (const entry of entries) {
        const expression = new RegExp(
          `(?<![\\p{L}\\p{N}_])${entry.source}(en|er|em|es|e)(?![\\p{L}\\p{N}_])`,
          'gu',
        );
        parts = replaceParts(
          parts,
          expression,
          (match, ending, offset, text) => {
            if (
              ending === 'e' &&
              /^\s+[A-ZÄÖÜ][a-zäöüß]+/u.test(
                text.slice(offset + match.length),
              ) &&
              !/\b(?:eine|einer|ein|die|der|meine|unsere|jede|diese)\s+(?:[a-zäöüß]+\s+){0,2}$/iu.test(
                text.slice(Math.max(0, offset - 100), offset),
              )
            )
              return match;
            return nominal(
              entry,
              ending,
              text.slice(Math.max(0, offset - 100), offset),
            );
          },
        );
      }
    }
    for (const rule of settings.rules) {
      if (
        !rule.enabled ||
        (rule.domain && !LeseklarSettings.matchesDomain(host, rule.domain))
      )
        continue;
      parts = replaceParts(
        parts,
        new RegExp(rule.pattern, rule.flags),
        rule.replacement,
      );
    }
    return parts;
  }

  globalThis.LeseklarEngine = {
    families,
    transformParts,
    transform: (text, settings, host = '') =>
      transformParts([text], settings, host)[0],
  };
})();
