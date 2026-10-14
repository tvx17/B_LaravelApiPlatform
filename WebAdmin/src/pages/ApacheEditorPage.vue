<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { onBeforeRouteLeave, useRoute } from 'vue-router';
import { useQuasar } from 'quasar';
import ApacheDirectiveFields from '../components/ApacheDirectiveFields.vue';
import {
  addVirtualHost,
  parseApacheConfig,
  quotePath,
  removeSection,
  updateSectionArgument,
} from '../lib/apache-config';

const route = useRoute();
const quasar = useQuasar();
const filePath = typeof route.query.file === 'string' ? route.query.file : '';
const directory = typeof route.query.directory === 'string' ? route.query.directory : '';
const source = ref('');
const original = ref('');
const token = ref('');
const busy = ref(false);
const error = ref('');
const success = ref('');
const tab = ref('general');
const generalSection = ref<number | null>(null);
const selectedHost = ref<number | null>(null);
const selectedDirectory = ref<number | null>(null);
const hostAddress = ref('');
const directoryPath = ref('');
const showNewHost = ref(false);
const newHost = ref({
  address: '*:80',
  serverName: '',
  aliases: '',
  documentRoot: '',
  allowOverride: 'None',
  require: 'all granted',
});
const dirty = computed(() => source.value !== original.value);
const document = computed(() => parseApacheConfig(source.value));
const apacheSyntax = computed(() => /\.(conf|load)$/i.test(filePath));
const formsEnabled = computed(() => apacheSyntax.value && !document.value.errors.length);
const hosts = computed(() =>
  document.value.sections.filter((item) => item.name.toLowerCase() === 'virtualhost'),
);
const host = computed(() => hosts.value.find((item) => item.start === selectedHost.value));
const conditionalSections = computed(() =>
  document.value.sections.filter((item) =>
    ['ifmodule', 'ifdefine'].includes(item.name.toLowerCase()),
  ),
);
const generalScope = computed(() => {
  let selected = document.value.sections.find((item) => item.start === generalSection.value);
  while (selected) {
    if (selected.name.toLowerCase() === 'directory') return 'directory';
    if (selected.name.toLowerCase() === 'virtualhost') return 'virtualhost';
    const parent = selected.parent;
    selected = document.value.sections.find((item) => item.start === parent);
  }
  return 'global';
});
const directories = computed(() =>
  document.value.sections.filter(
    (item) =>
      item.name.toLowerCase() === 'directory' &&
      item.parent === (tab.value === 'hosts' ? selectedHost.value : generalSection.value),
  ),
);
const sectionDirectory = computed(() =>
  directories.value.find((item) => item.start === selectedDirectory.value),
);
const changes = computed(() => {
  const before = original.value.split(/\r?\n/);
  const after = source.value.split(/\r?\n/);
  const changed = [];
  for (let index = 0; index < Math.max(before.length, after.length); index++) {
    if (before[index] !== after[index])
      changed.push({ line: index + 1, before: before[index] ?? '', after: after[index] ?? '' });
  }
  return changed;
});
function confirm(message: string): Promise<boolean> {
  return new Promise((resolve) => {
    quasar
      .dialog({
        title: 'Änderung bestätigen',
        message,
        cancel: true,
        persistent: true,
        ok: 'Bestätigen',
      })
      .onOk(() => resolve(true))
      .onCancel(() => resolve(false));
  });
}
onBeforeRouteLeave(
  async () =>
    !dirty.value ||
    (await confirm('Ungespeicherte Änderungen verwerfen und den Editor verlassen?')),
);
function preventUnload(event: BeforeUnloadEvent) {
  if (dirty.value) {
    event.preventDefault();
    event.returnValue = '';
  }
}
onBeforeUnmount(() => window.removeEventListener('beforeunload', preventUnload));
function selectHost(value: number | null) {
  selectedHost.value = value;
  hostAddress.value = hosts.value.find((item) => item.start === value)?.argument ?? '';
  selectedDirectory.value = null;
}
function selectDirectory(value: number | null) {
  selectedDirectory.value = value;
  const argument = directories.value.find((item) => item.start === value)?.argument ?? '';
  directoryPath.value = argument.replace(/^["'](.*)["']$/, '$1');
}
function applyHostAddress() {
  if (host.value && hostAddress.value !== host.value.argument)
    action(() => updateSectionArgument(source.value, host.value!.start, hostAddress.value));
}
function applyDirectoryPath() {
  if (
    sectionDirectory.value &&
    directoryPath.value !== sectionDirectory.value.argument.replace(/^["'](.*)["']$/, '$1')
  )
    action(() =>
      updateSectionArgument(
        source.value,
        sectionDirectory.value!.start,
        quotePath(directoryPath.value),
      ),
    );
}
watch(source, () => {
  if (
    generalSection.value !== null &&
    !conditionalSections.value.some((item) => item.start === generalSection.value)
  )
    generalSection.value = null;
  if (selectedHost.value !== null && !hosts.value.some((item) => item.start === selectedHost.value))
    selectHost(hosts.value[0]?.start ?? null);
  if (host.value) hostAddress.value = host.value.argument;
  if (
    selectedDirectory.value !== null &&
    !directories.value.some((item) => item.start === selectedDirectory.value)
  )
    selectedDirectory.value = null;
});
watch(tab, () => {
  selectedDirectory.value = null;
  if (host.value) hostAddress.value = host.value.argument;
});
function update(next: string) {
  source.value = next;
  success.value = '';
}
function updateRaw(next: string | number | null) {
  const newline = original.value.includes('\r\n') ? '\r\n' : '\n';
  update(String(next ?? '').replace(/\r?\n/g, newline));
}
function action(callback: () => string) {
  try {
    update(callback());
    error.value = '';
  } catch (reason) {
    error.value = reason instanceof Error ? reason.message : 'Änderung nicht möglich.';
  }
}
async function load() {
  if (dirty.value && !(await confirm('Entwurf verwerfen und die Datei neu laden?'))) return;
  busy.value = true;
  error.value = '';
  success.value = '';
  try {
    const result = await window.webadmin?.openApacheFile(directory, filePath);
    if (!result?.ok) {
      error.value = result?.error ?? 'Bitte den Editor in der Desktop-App öffnen.';
      return;
    }
    source.value = original.value = result.content;
    token.value = result.token;
    selectHost(hosts.value[0]?.start ?? null);
    selectedDirectory.value = null;
    if (!formsEnabled.value) tab.value = 'source';
  } catch {
    error.value = 'Die Datei konnte nicht geöffnet werden.';
  } finally {
    busy.value = false;
  }
}
async function save() {
  const savedContent = source.value;
  busy.value = true;
  error.value = '';
  try {
    const result = await window.webadmin?.saveApacheFile(token.value, savedContent);
    if (!result?.ok) {
      error.value = result?.error ?? 'Desktop-Schnittstelle nicht verfügbar.';
      return;
    }
    original.value = savedContent;
    success.value = `Gespeichert. Sicherung: ${result.backupPath}`;
  } catch {
    error.value = 'Speichern fehlgeschlagen.';
  } finally {
    busy.value = false;
  }
}
function createHost() {
  try {
    update(addVirtualHost(source.value, newHost.value));
    selectHost(hosts.value.at(-1)?.start ?? null);
    showNewHost.value = false;
    error.value = '';
  } catch (reason) {
    error.value =
      reason instanceof Error ? reason.message : 'Virtual Host konnte nicht erstellt werden.';
  }
}
async function deleteHost() {
  if (
    !host.value ||
    !(await confirm(
      'Diesen Virtual Host mit allen enthaltenen Direktiven und Verzeichnisregeln aus dem Entwurf entfernen?',
    ))
  )
    return;
  const start = host.value.start;
  action(() => removeSection(source.value, start));
  selectHost(hosts.value[0]?.start ?? null);
}
onMounted(async () => {
  window.addEventListener('beforeunload', preventUnload);
  await load();
});
</script>

<template>
  <q-page padding class="page-content">
    <q-btn flat dense icon="arrow_back" label="Zur Dateiliste" to="/workspace" />
    <h1 class="page-title">Apache-Datei bearbeiten.</h1>
    <p class="editor-path">{{ filePath }}</p>
    <div class="row q-gutter-sm items-center q-mb-lg">
      <q-btn
        color="primary"
        unelevated
        icon="save"
        label="Datei speichern"
        :disable="!token || !dirty || busy || (apacheSyntax && document.errors.length > 0)"
        @click="save"
      />
      <q-btn flat icon="refresh" label="Datei neu laden" :disable="busy" @click="load" />
      <q-chip :color="dirty ? 'amber-2' : 'grey-2'">{{
        dirty ? 'Ungespeicherte Änderungen' : 'Keine offenen Änderungen'
      }}</q-chip>
    </div>
    <q-banner v-if="error" class="bg-red-1 text-negative q-mb-md" role="alert">{{
      error
    }}</q-banner>
    <q-banner v-if="success" class="bg-green-1 q-mb-md editor-path" role="status">{{
      success
    }}</q-banner>
    <q-banner
      v-for="message in apacheSyntax ? document.errors : []"
      :key="message"
      class="bg-amber-1 q-mb-md"
      >{{ message }} Bitte den Quelltext prüfen.</q-banner
    >
    <q-card v-if="token" flat bordered :inert="busy">
      <q-tabs v-model="tab" align="left" active-color="primary"
        ><q-tab name="general" label="Allgemeine Direktiven" :disable="!formsEnabled" /><q-tab
          name="hosts"
          label="Virtual Hosts"
          :disable="!formsEnabled" /><q-tab name="source" label="Quelltext" /><q-tab
          name="preview"
          label="Änderungsvorschau"
      /></q-tabs>
      <q-separator />
      <q-tab-panels v-model="tab">
        <q-tab-panel name="general">
          <h2 class="text-h6">Allgemeine Direktiven</h2>
          <q-select
            v-if="conditionalSections.length"
            v-model="generalSection"
            outlined
            class="q-mb-lg"
            label="Geltungsbereich"
            emit-value
            map-options
            :options="[
              { value: null, label: 'Global — außerhalb von Abschnitten' },
              ...conditionalSections.map((item) => ({
                value: item.start,
                label: `${item.name} ${item.argument} · Zeile ${item.start + 1}`,
              })),
            ]"
            @update:model-value="selectedDirectory = null"
          />
          <ApacheDirectiveFields
            :source="source"
            :section="generalSection"
            :scope="generalScope"
            @update="update"
          />
        </q-tab-panel>
        <q-tab-panel name="hosts">
          <div class="row items-center q-gutter-sm q-mb-lg">
            <h2 class="text-h6 q-my-none">{{ hosts.length }} Virtual Hosts</h2>
            <q-btn
              color="primary"
              flat
              icon="add"
              label="Virtual Host anlegen"
              @click="showNewHost = true"
            />
          </div>
          <p class="text-grey-7">
            Die Reihenfolge bleibt erhalten. Der erste passende Virtual Host ist Apaches
            Standard-Host. Hier bearbeitest du nur die aktuell geöffnete Datei.
          </p>
          <q-select
            v-if="hosts.length"
            :model-value="selectedHost"
            :options="
              hosts.map((item) => ({
                value: item.start,
                label: `${document.directives.find((directive) => directive.section === item.start && directive.name.toLowerCase() === 'servername')?.value ?? '(ohne ServerName)'} · ${item.argument} · Zeile ${item.start + 1}`,
              }))
            "
            emit-value
            map-options
            outlined
            label="Virtual Host auswählen"
            @update:model-value="selectHost"
          />
          <div v-if="host" class="q-mt-lg">
            <div class="row q-gutter-sm items-start q-mb-lg">
              <q-input
                v-model="hostAddress"
                outlined
                dense
                class="col"
                label="VirtualHost — Adressen und Ports"
                hint="Zum Beispiel *:80 oder *:443; mehrere Adressen mit Leerzeichen"
                @blur="applyHostAddress"
                @change="applyHostAddress"
              /><q-btn
                flat
                color="negative"
                icon="delete_outline"
                label="Virtual Host löschen"
                @click="deleteHost"
              />
            </div>
            <ApacheDirectiveFields
              :source="source"
              :section="host.start"
              scope="virtualhost"
              @update="update"
            />
          </div>
          <p v-else class="text-grey-7">
            Noch kein Virtual Host in dieser Datei. Lege einen neuen Eintrag an.
          </p>
        </q-tab-panel>
        <q-tab-panel name="source"
          ><p class="text-grey-7">
            Vollständiger Dateiinhalt einschließlich unbekannter Direktiven und Kommentare.
            Änderungen fließen auch in die Formulare ein.
          </p>
          <q-input
            :model-value="source"
            type="textarea"
            outlined
            autogrow
            input-class="config-source"
            label="Konfigurations-Quelltext"
            :disable="busy"
            @update:model-value="updateRaw"
        /></q-tab-panel>
        <q-tab-panel name="preview"
          ><p class="text-grey-7">
            Vergleich je Zeilenposition; eingefügte Zeilen können nachfolgende Zeilen verschieben.
          </p>
          <p v-if="!changes.length">Keine Änderungen.</p>
          <div v-for="change in changes" :key="change.line" class="q-mb-sm">
            <div class="text-caption">Zeile {{ change.line }}</div>
            <pre class="diff-before">− {{ change.before }}</pre>
            <pre class="diff-after">+ {{ change.after }}</pre>
          </div></q-tab-panel
        >
      </q-tab-panels>
      <q-card-section v-if="formsEnabled && (tab === 'general' || (tab === 'hosts' && host))">
        <q-separator class="q-mb-lg" />
        <h2 class="text-h6">Verzeichnisregeln (Directory)</h2>
        <q-select
          v-if="directories.length"
          :model-value="selectedDirectory"
          :options="
            directories.map((item) => ({
              value: item.start,
              label: `${item.argument} · Zeile ${item.start + 1}`,
            }))
          "
          emit-value
          map-options
          outlined
          label="Verzeichnisabschnitt auswählen"
          @update:model-value="selectDirectory"
        />
        <p v-else class="text-grey-7">
          Keine direkten Directory-Abschnitte hier. Im Quelltext kannst du zusätzliche Abschnitte
          ergänzen.
        </p>
        <div v-if="sectionDirectory" class="q-mt-lg">
          <q-input
            v-model="directoryPath"
            outlined
            dense
            label="Directory — Pfad"
            class="q-mb-lg"
            @blur="applyDirectoryPath"
            @change="applyDirectoryPath"
          /><ApacheDirectiveFields
            :source="source"
            :section="sectionDirectory.start"
            scope="directory"
            @update="update"
          />
        </div>
      </q-card-section>
    </q-card>
    <p class="text-caption text-grey-7 q-mt-lg">
      Beim Speichern entsteht eine Sicherung neben der Originaldatei. Abschnittsfehler werden
      geprüft; eine vollständige Apache-Syntaxprüfung ist noch nicht integriert. Includes,
      Zertifikate und Listen-Ports müssen zur Installation passen. Apache wird nicht automatisch neu
      gestartet.
    </p>
    <q-dialog v-model="showNewHost" persistent
      ><q-card style="width: 640px; max-width: 95vw"
        ><q-card-section
          ><h2 class="text-h6 q-mt-none">Virtual Host anlegen</h2>
          <p>
            Der neue Eintrag wird am Dateiende ergänzt und erhält einen passenden
            Directory-Abschnitt.
          </p>
          <q-banner v-if="error" class="bg-red-1 q-mb-md">{{ error }}</q-banner>
          <div class="q-gutter-md">
            <q-select
              v-model="newHost.address"
              outlined
              label="Adresse und Port"
              :options="['*:80', '*:443', '127.0.0.1:80', '[::1]:80']"
              use-input
              new-value-mode="add-unique"
            /><q-input
              v-model="newHost.serverName"
              outlined
              label="ServerName — Hostname"
              hint="Zum Beispiel meinprojekt.local"
            /><q-input
              v-model="newHost.aliases"
              outlined
              label="ServerAlias — weitere Namen (optional)"
            /><q-input
              v-model="newHost.documentRoot"
              outlined
              label="DocumentRoot — Webverzeichnis"
            /><q-select
              v-model="newHost.allowOverride"
              outlined
              label="AllowOverride — .htaccess"
              :options="['None', 'All', 'FileInfo']"
            /><q-select
              v-model="newHost.require"
              outlined
              label="Require — Zugriff"
              :options="['all granted', 'all denied', 'local']"
            /></div></q-card-section
        ><q-card-actions align="right"
          ><q-btn flat label="Abbrechen" v-close-popup /><q-btn
            unelevated
            color="primary"
            label="In Entwurf übernehmen"
            @click="createHost" /></q-card-actions></q-card
    ></q-dialog>
  </q-page>
</template>

<style>
.config-source {
  font-family: monospace;
  min-height: 400px !important;
}
.editor-path {
  overflow-wrap: anywhere;
  font-family: monospace;
}
.diff-before,
.diff-after {
  margin: 0;
  padding: 6px;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.diff-before {
  background: #ffeded;
}
.diff-after {
  background: #eaf6ee;
}
</style>
