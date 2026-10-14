<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import type { QTableProps } from 'quasar';
import type { ApacheConfigFile } from '../types/apache';

const storageKey = 'webadmin.apache.directory';
const desktop = window.webadmin;
const directory = ref('');
const files = ref<ApacheConfigFile[]>([]);
const warnings = ref<string[]>([]);
const error = ref('');
const storageError = ref('');
const busy = ref(false);
const scanned = ref(false);
const filter = ref('');
const columns: NonNullable<QTableProps['columns']> = [
  {
    name: 'relativePath',
    label: 'Datei / relativer Pfad',
    field: 'relativePath',
    align: 'left' as const,
    sortable: true,
  },
  { name: 'category', label: 'Bereich', field: 'category', align: 'left' as const, sortable: true },
  {
    name: 'size',
    label: 'Größe',
    field: 'size',
    align: 'right' as const,
    sortable: true,
    format: (size: number) =>
      `${new Intl.NumberFormat('de-DE', { maximumFractionDigits: 1 }).format(size / 1024)} KB`,
  },
];
columns.push({ name: 'actions', label: '', field: 'path', align: 'right' });
const hasMainConfig = computed(() =>
  files.value.some((file) => file.category === 'Hauptkonfiguration'),
);

function saveDirectory() {
  try {
    if (directory.value) localStorage.setItem(storageKey, directory.value);
    else localStorage.removeItem(storageKey);
    storageError.value = '';
  } catch {
    storageError.value =
      'Der Pfad konnte nicht gespeichert werden. Du kannst ihn für diese Sitzung verwenden.';
  }
}

async function scan() {
  if (!desktop || !directory.value) return;
  files.value = [];
  warnings.value = [];
  error.value = '';
  scanned.value = false;
  const result = await desktop.scanApacheDirectory(directory.value);
  if (!result.ok) {
    error.value = result.error;
    return;
  }
  directory.value = result.directory;
  saveDirectory();
  files.value = result.files;
  warnings.value = result.warnings;
  scanned.value = true;
}

async function refresh() {
  busy.value = true;
  try {
    await scan();
  } catch {
    error.value = 'Die Suche ist fehlgeschlagen. Bitte versuche es erneut.';
  } finally {
    busy.value = false;
  }
}

async function selectDirectory() {
  if (!desktop) return;
  busy.value = true;
  try {
    const selected = await desktop.selectApacheDirectory();
    if (selected === null) return;
    directory.value = selected;
    filter.value = '';
    saveDirectory();
    await scan();
  } catch {
    error.value = 'Die Verzeichnisauswahl oder Suche ist fehlgeschlagen. Bitte versuche es erneut.';
  } finally {
    busy.value = false;
  }
}

function forgetDirectory() {
  directory.value = '';
  files.value = [];
  warnings.value = [];
  error.value = '';
  scanned.value = false;
  filter.value = '';
  saveDirectory();
}

onMounted(async () => {
  try {
    directory.value = localStorage.getItem(storageKey) ?? '';
  } catch {
    storageError.value = 'Der gespeicherte Pfad konnte nicht gelesen werden.';
  }
  if (directory.value && desktop) await refresh();
});
</script>

<template>
  <q-page padding class="page-content">
    <div class="text-overline text-primary">LOKALER ARBEITSBEREICH</div>
    <h1 class="page-title">Apache-Konfigurationen.</h1>
    <p class="page-intro">
      Wähle dein Apache-Installationsverzeichnis oder den Konfigurationsordner. WebAdmin merkt sich
      den Pfad und sucht bei deinem nächsten Besuch erneut nach Dateien.
    </p>
    <q-banner v-if="!desktop" rounded class="bg-amber-1 q-mb-lg"
      >Die lokale Verzeichnisauswahl ist in der Desktop-App verfügbar. Starte sie mit npm run
      dev.</q-banner
    >
    <q-card flat bordered>
      <q-card-section>
        <h2 class="text-h6 q-mt-none">Apache-Verzeichnis</h2>
        <div v-if="directory" class="directory-path q-mb-md">{{ directory }}</div>
        <p v-else class="text-grey-7">
          Noch kein Verzeichnis ausgewählt. Zum Beispiel C:/Apps/Apache24 oder /etc/apache2.
        </p>
        <div class="row q-gutter-sm">
          <q-btn
            color="primary"
            unelevated
            icon="folder_open"
            :label="directory ? 'Verzeichnis ändern' : 'Verzeichnis auswählen'"
            :disable="!desktop || busy"
            @click="selectDirectory"
          />
          <q-btn
            v-if="directory"
            flat
            color="primary"
            icon="refresh"
            label="Erneut suchen"
            :disable="!desktop || busy"
            @click="refresh"
          />
          <q-btn
            v-if="directory"
            flat
            icon="close"
            label="Auswahl entfernen"
            :disable="busy"
            @click="forgetDirectory"
          />
        </div>
        <div class="text-caption text-grey-7 q-mt-md">
          Der Pfad wird lokal in dieser App gespeichert. Über „Bearbeiten“ öffnest du eine Datei im
          Editor.
        </div>
      </q-card-section>
      <q-linear-progress v-if="busy" indeterminate color="primary" />
    </q-card>
    <q-banner v-if="error" rounded class="bg-red-1 text-negative q-mt-md" role="alert">{{
      error
    }}</q-banner>
    <q-banner v-if="storageError" rounded class="bg-amber-1 q-mt-md" role="alert">{{
      storageError
    }}</q-banner>
    <q-banner v-for="warning in warnings" :key="warning" rounded class="bg-amber-1 q-mt-md">{{
      warning
    }}</q-banner>
    <q-card v-if="scanned" flat bordered class="q-mt-lg">
      <q-card-section>
        <div class="row items-center justify-between q-gutter-sm">
          <h2 class="text-h6 q-my-none">{{ files.length }} Konfigurationsdateien gefunden</h2>
          <q-chip v-if="hasMainConfig" outline color="primary" icon="check_circle"
            >Hauptkonfiguration gefunden</q-chip
          >
        </div>
        <p class="text-grey-7 q-mt-sm q-mb-none">
          Vorhandene Dateien in üblichen Konfigurationsordnern. Daraus lässt sich noch nicht
          ableiten, welche Dateien Apache tatsächlich lädt.
        </p>
        <q-input
          v-if="files.length"
          v-model="filter"
          outlined
          dense
          clearable
          label="Dateien filtern"
          class="q-mt-md"
          :disable="busy"
          ><template #prepend><q-icon name="search" /></template
        ></q-input>
      </q-card-section>
      <q-table
        flat
        :rows="files"
        :columns="columns"
        row-key="path"
        :filter="filter || ''"
        :pagination="{ rowsPerPage: 15 }"
        :rows-per-page-options="[15, 30, 0]"
        no-data-label="Keine Konfigurationsdateien gefunden. Wähle das Apache-Installations- oder Konfigurationsverzeichnis."
        no-results-label="Keine Dateien passen zum Filter."
        rows-per-page-label="Dateien pro Seite"
        wrap-cells
      >
        <template #body-cell-actions="props">
          <q-td :props="props"
            ><q-btn
              flat
              dense
              color="primary"
              icon="edit"
              label="Bearbeiten"
              :to="{ path: '/apache/edit', query: { directory, file: props.row.path } }"
          /></q-td>
        </template>
        <template #body-cell-relativePath="props"
          ><q-td :props="props"
            ><span class="config-file-path">{{ props.row.relativePath }}</span
            ><q-tooltip>{{ props.row.path }}</q-tooltip></q-td
          ></template
        >
      </q-table>
    </q-card>
  </q-page>
</template>

<style scoped>
.directory-path,
.config-file-path {
  overflow-wrap: anywhere;
  font-family: monospace;
}
</style>
