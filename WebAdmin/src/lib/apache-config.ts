export interface DirectiveSpec {
  name: string;
  label: string;
  scopes: string[];
  options?: string[];
  path?: boolean;
}

export const directiveSpecs: DirectiveSpec[] = [
  { name: 'ServerName', label: 'Servername', scopes: ['global', 'virtualhost'] },
  { name: 'ServerAlias', label: 'Weitere Hostnamen', scopes: ['virtualhost'] },
  { name: 'ServerAdmin', label: 'Administrator / E-Mail', scopes: ['global', 'virtualhost'] },
  { name: 'ServerRoot', label: 'Apache-Stammverzeichnis', scopes: ['global'], path: true },
  { name: 'DocumentRoot', label: 'Webverzeichnis', scopes: ['global', 'virtualhost'], path: true },
  { name: 'Listen', label: 'Adresse / Port', scopes: ['global'] },
  {
    name: 'Include',
    label: 'Zusätzliche Konfigurationsdatei / Muster',
    scopes: ['global', 'virtualhost', 'directory'],
    path: true,
  },
  {
    name: 'IncludeOptional',
    label: 'Optionale Konfigurationsdatei / Muster',
    scopes: ['global', 'virtualhost', 'directory'],
    path: true,
  },
  { name: 'DirectoryIndex', label: 'Startdateien', scopes: ['global', 'virtualhost', 'directory'] },
  { name: 'ErrorLog', label: 'Fehlerprotokoll', scopes: ['global', 'virtualhost'], path: true },
  {
    name: 'CustomLog',
    label: 'Zugriffsprotokoll (Pfad und Format)',
    scopes: ['global', 'virtualhost'],
  },
  {
    name: 'LogLevel',
    label: 'Protokollstufe',
    scopes: ['global', 'virtualhost'],
    options: ['emerg', 'alert', 'crit', 'error', 'warn', 'notice', 'info', 'debug', 'trace1'],
  },
  { name: 'Timeout', label: 'Timeout in Sekunden', scopes: ['global', 'virtualhost'] },
  {
    name: 'KeepAlive',
    label: 'Verbindungen offen halten',
    scopes: ['global', 'virtualhost'],
    options: ['On', 'Off'],
  },
  {
    name: 'KeepAliveTimeout',
    label: 'KeepAlive-Timeout in Sekunden',
    scopes: ['global', 'virtualhost'],
  },
  {
    name: 'MaxKeepAliveRequests',
    label: 'Maximale KeepAlive-Anfragen',
    scopes: ['global', 'virtualhost'],
  },
  {
    name: 'ServerTokens',
    label: 'Serverinformationen',
    scopes: ['global'],
    options: ['Prod', 'Major', 'Minor', 'Min', 'OS', 'Full'],
  },
  {
    name: 'ServerSignature',
    label: 'Signatur auf Fehlerseiten',
    scopes: ['global', 'virtualhost', 'directory'],
    options: ['Off', 'On', 'EMail'],
  },
  {
    name: 'SSLEngine',
    label: 'TLS aktivieren',
    scopes: ['global', 'virtualhost'],
    options: ['On', 'Off'],
  },
  {
    name: 'SSLCertificateFile',
    label: 'TLS-Zertifikat',
    scopes: ['global', 'virtualhost'],
    path: true,
  },
  {
    name: 'SSLCertificateKeyFile',
    label: 'TLS-Schlüsseldatei',
    scopes: ['global', 'virtualhost'],
    path: true,
  },
  {
    name: 'AllowOverride',
    label: '.htaccess-Berechtigungen',
    scopes: ['directory'],
    options: ['None', 'All', 'FileInfo', 'AuthConfig', 'Indexes', 'Limit', 'Options'],
  },
  {
    name: 'Options',
    label: 'Verzeichnisoptionen',
    scopes: ['directory'],
    options: ['None', 'FollowSymLinks', 'Indexes FollowSymLinks', '+FollowSymLinks', '-Indexes'],
  },
  {
    name: 'Require',
    label: 'Zugriffsregel',
    scopes: ['directory'],
    options: ['all granted', 'all denied', 'local'],
  },
];

export interface ApacheDirective {
  line: number;
  name: string;
  value: string;
  section: number | null;
  editable: boolean;
}
export interface ApacheSection {
  start: number;
  end: number;
  name: string;
  argument: string;
  parent: number | null;
}
export interface ApacheDocument {
  lines: string[];
  newline: string;
  bom: string;
  directives: ApacheDirective[];
  sections: ApacheSection[];
  errors: string[];
}

/** Line-based edits preserve unrelated lines; this is not an Apache execution parser. */
export function parseApacheConfig(source: string): ApacheDocument {
  const bom = source.startsWith('\uFEFF') ? '\uFEFF' : '';
  const lines = source.slice(bom.length).split(/\r?\n/);
  const document: ApacheDocument = {
    lines,
    newline: source.includes('\r\n') ? '\r\n' : '\n',
    bom,
    directives: [],
    sections: [],
    errors: [],
  };
  const stack: ApacheSection[] = [];
  let continuation = false;
  for (let line = 0; line < lines.length; line++) {
    const text = lines[line] ?? '';
    const trimmed = text.trim();
    if (continuation) {
      continuation = /\\\s*$/.test(text);
      continue;
    }
    if (!trimmed || trimmed.startsWith('#')) continue;
    const close = /^<\/([\w]+)\s*>\s*$/.exec(trimmed);
    if (close) {
      const opened = stack.pop();
      if (!opened || opened.name.toLowerCase() !== close[1]?.toLowerCase())
        document.errors.push(`Unpassender Abschnittsabschluss in Zeile ${line + 1}.`);
      else opened.end = line;
      continue;
    }
    const open = /^<([\w]+)(?:\s+(.*?))?>\s*$/.exec(trimmed);
    if (open) {
      const section = {
        start: line,
        end: lines.length - 1,
        name: open[1] ?? '',
        argument: open[2] ?? '',
        parent: stack.at(-1)?.start ?? null,
      };
      document.sections.push(section);
      stack.push(section);
      continue;
    }
    if (trimmed.startsWith('<'))
      document.errors.push(
        `Abschnitt in Zeile ${line + 1} ist nicht vollständig auf einer Zeile lesbar.`,
      );
    const directive = /^\s*([\w]+)\s+(.*?)\s*$/.exec(text);
    if (directive) {
      continuation = /\\\s*$/.test(text);
      document.directives.push({
        line,
        name: directive[1] ?? '',
        value: directive[2] ?? '',
        section: stack.at(-1)?.start ?? null,
        editable: !continuation,
      });
    }
  }
  for (const section of stack)
    document.errors.push(
      `Abschnitt ${section.name} in Zeile ${section.start + 1} ist nicht geschlossen.`,
    );
  return document;
}

function join(document: ApacheDocument): string {
  return document.bom + document.lines.join(document.newline);
}
export function singleLine(value: string): string {
  if (!value.trim() || /[\r\n\0]/.test(value))
    throw new Error('Bitte einen nicht leeren, einzeiligen Wert eingeben.');
  return value.trim();
}
export function quotePath(value: string): string {
  const input = singleLine(value);
  const safe = /^(?:[a-zA-Z]:\\|\\\\)/.test(input) ? input.replace(/\\/g, '/') : input;
  if (safe.includes('"'))
    throw new Error('Ein Pfad darf keine doppelten Anführungszeichen enthalten.');
  return `"${safe}"`;
}
export function displayValue(directive: ApacheDirective, spec: DirectiveSpec): string {
  if (spec.path && /^("[^"]*"|'[^']*')$/.test(directive.value)) return directive.value.slice(1, -1);
  return directive.value;
}
export function updateDirective(source: string, line: number, value: string): string {
  const document = parseApacheConfig(source);
  const directive = document.directives.find((item) => item.line === line);
  if (!directive?.editable || document.errors.length)
    throw new Error('Diese Direktive kann nur im Quelltext bearbeitet werden.');
  const indent = /^\s*/.exec(document.lines[line] ?? '')?.[0] ?? '';
  document.lines[line] = `${indent}${directive.name} ${singleLine(value)}`;
  return join(document);
}
export function addDirective(
  source: string,
  sectionStart: number | null,
  name: string,
  value: string,
): string {
  const document = parseApacheConfig(source);
  if (document.errors.length || !directiveSpecs.some((spec) => spec.name === name))
    throw new Error('Direktive kann hier nicht eingefügt werden.');
  const section = document.sections.find((item) => item.start === sectionStart);
  if (sectionStart !== null && !section) throw new Error('Abschnitt nicht gefunden.');
  const index = section?.end ?? 0;
  const indent = section
    ? `${/^\s*/.exec(document.lines[section.start] ?? '')?.[0] ?? ''}    `
    : '';
  document.lines.splice(index, 0, `${indent}${name} ${singleLine(value)}`);
  return join(document);
}
export function removeDirective(source: string, line: number): string {
  const document = parseApacheConfig(source);
  if (
    document.errors.length ||
    !document.directives.some((item) => item.line === line && item.editable)
  )
    throw new Error('Direktive kann nicht entfernt werden.');
  document.lines.splice(line, 1);
  return join(document);
}
export function updateSectionArgument(source: string, start: number, argument: string): string {
  const document = parseApacheConfig(source);
  const section = document.sections.find((item) => item.start === start);
  if (!section || document.errors.length)
    throw new Error('Abschnitt kann nicht bearbeitet werden.');
  const safe = singleLine(argument);
  if (/[<>]/.test(safe))
    throw new Error('Adresse oder Pfad darf keine spitzen Klammern enthalten.');
  const indent = /^\s*/.exec(document.lines[start] ?? '')?.[0] ?? '';
  document.lines[start] = `${indent}<${section.name} ${safe}>`;
  return join(document);
}
export function removeSection(source: string, start: number): string {
  const document = parseApacheConfig(source);
  const section = document.sections.find((item) => item.start === start);
  if (!section || document.errors.length) throw new Error('Abschnitt kann nicht entfernt werden.');
  document.lines.splice(section.start, section.end - section.start + 1);
  return join(document);
}
export interface NewVirtualHost {
  address: string;
  serverName: string;
  aliases: string;
  documentRoot: string;
  allowOverride: string;
  require: string;
}
export function addVirtualHost(source: string, host: NewVirtualHost): string {
  const document = parseApacheConfig(source);
  if (document.errors.length)
    throw new Error('Bitte zuerst die Abschnittsfehler im Quelltext beheben.');
  const address = singleLine(host.address);
  if (
    !address
      .split(/\s+/)
      .every(
        (item) =>
          /^(\*|_default_|[\w.-]+|\[[\da-fA-F:]+\]):\d{1,5}$/.test(item) &&
          Number(item.slice(item.lastIndexOf(':') + 1)) >= 1 &&
          Number(item.slice(item.lastIndexOf(':') + 1)) <= 65535,
      )
  )
    throw new Error('Bitte gültige Adressen mit Port angeben, z. B. *:80 oder [::1]:443.');
  const serverName = singleLine(host.serverName);
  if (!/^[\w.-]+(?::\d{1,5})?$/.test(serverName))
    throw new Error('Bitte einen Servernamen ohne Leerzeichen angeben.');
  const root = quotePath(host.documentRoot);
  const aliases = host.aliases.trim();
  if (aliases && !aliases.split(/\s+/).every((item) => /^[\w.*?-]+$/.test(item)))
    throw new Error('Weitere Hostnamen bitte mit Leerzeichen trennen.');
  if (
    !['None', 'All', 'FileInfo'].includes(host.allowOverride) ||
    !['all granted', 'all denied', 'local'].includes(host.require)
  )
    throw new Error('Ungültige Verzeichnisregel.');
  const block = [
    `<VirtualHost ${address}>`,
    `    ServerName ${serverName}`,
    ...(aliases ? [`    ServerAlias ${aliases}`] : []),
    `    DocumentRoot ${root}`,
    '',
    `    <Directory ${root}>`,
    `        AllowOverride ${host.allowOverride}`,
    `        Require ${host.require}`,
    '    </Directory>',
    '</VirtualHost>',
  ];
  const separator = source.endsWith(document.newline)
    ? document.newline
    : document.newline + document.newline;
  return source + (source ? separator : '') + block.join(document.newline) + document.newline;
}
