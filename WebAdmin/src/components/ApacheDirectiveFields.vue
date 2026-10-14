<script setup lang="ts">
import { ref, watch } from 'vue';
import {
  addDirective,
  directiveSpecs,
  displayValue,
  parseApacheConfig,
  quotePath,
  removeDirective,
  updateDirective,
} from '../lib/apache-config';
import type { DirectiveSpec } from '../lib/apache-config';

const props = defineProps<{ source: string; section: number | null; scope: string }>();
const emit = defineEmits<{ update: [source: string] }>();
const error = ref('');
const rows = ref<{ line: number; spec: DirectiveSpec; value: string; editable: boolean }[]>([]);
const newName = ref('');
const newValue = ref('');
watch(
  () => [props.source, props.section, props.scope],
  () => {
    rows.value = parseApacheConfig(props.source)
      .directives.filter((item) => item.section === props.section)
      .flatMap((item) => {
        const spec = directiveSpecs.find(
          (spec) =>
            spec.name.toLowerCase() === item.name.toLowerCase() &&
            spec.scopes.includes(props.scope),
        );
        return spec
          ? [{ line: item.line, spec, value: displayValue(item, spec), editable: item.editable }]
          : [];
      });
  },
  { immediate: true },
);
function action(callback: () => string) {
  try {
    emit('update', callback());
    error.value = '';
  } catch (reason) {
    error.value = reason instanceof Error ? reason.message : 'Änderung nicht möglich.';
  }
}
function apply(row: (typeof rows.value)[number]) {
  const current = parseApacheConfig(props.source).directives.find((item) => item.line === row.line);
  if (!current || displayValue(current, row.spec) === row.value) return;
  action(() =>
    updateDirective(props.source, row.line, row.spec.path ? quotePath(row.value) : row.value),
  );
}
function insert() {
  const spec = directiveSpecs.find((item) => item.name === newName.value);
  if (!spec) return;
  action(() =>
    addDirective(
      props.source,
      props.section,
      spec.name,
      spec.path ? quotePath(newValue.value) : newValue.value,
    ),
  );
  newValue.value = '';
}
</script>

<template>
  <div>
    <q-banner v-if="error" class="bg-red-1 text-negative q-mb-md" role="alert">{{
      error
    }}</q-banner>
    <div v-if="!rows.length" class="text-grey-7 q-mb-md">
      Keine bekannten Direktiven direkt in diesem Abschnitt. Du kannst eine Direktive hinzufügen.
    </div>
    <div
      v-for="row in rows"
      :key="`${section}:${row.line}`"
      class="row items-start q-col-gutter-sm q-mb-md"
    >
      <div class="col">
        <q-select
          v-if="row.spec.options"
          v-model="row.value"
          :options="[...new Set([...row.spec.options, row.value])]"
          use-input
          new-value-mode="add-unique"
          outlined
          dense
          :disable="!row.editable"
          :label="`${row.spec.name} — ${row.spec.label}`"
          @update:model-value="apply(row)"
          @blur="apply(row)"
        />
        <q-input
          v-else
          v-model="row.value"
          outlined
          dense
          :disable="!row.editable"
          :label="`${row.spec.name} — ${row.spec.label}`"
          @blur="apply(row)"
          @keydown.enter="apply(row)"
          @change="apply(row)"
        />
        <div class="text-caption text-grey-7">
          Zeile {{ row.line + 1
          }}<span v-if="!row.editable"> · Mehrzeiliger Wert: bitte im Quelltext bearbeiten.</span>
        </div>
      </div>
      <q-btn
        flat
        round
        icon="delete_outline"
        color="negative"
        :disable="!row.editable"
        :aria-label="`${row.spec.name} entfernen`"
        @click="action(() => removeDirective(source, row.line))"
        ><q-tooltip>Direktive entfernen</q-tooltip></q-btn
      >
    </div>
    <q-separator class="q-my-lg" />
    <div class="text-subtitle2 q-mb-sm">Direktive hinzufügen</div>
    <div class="row q-col-gutter-sm items-start">
      <div class="col-12 col-md-5">
        <q-select
          v-model="newName"
          outlined
          dense
          label="Direktive"
          :options="
            directiveSpecs.filter((spec) => spec.scopes.includes(scope)).map((spec) => spec.name)
          "
        />
      </div>
      <div class="col-12 col-md-5">
        <q-select
          v-if="directiveSpecs.find((spec) => spec.name === newName)?.options"
          v-model="newValue"
          outlined
          dense
          use-input
          new-value-mode="add-unique"
          label="Wert"
          :options="directiveSpecs.find((spec) => spec.name === newName)?.options"
        /><q-input v-else v-model="newValue" outlined dense label="Wert" />
      </div>
      <div class="col-12 col-md-2">
        <q-btn
          flat
          color="primary"
          icon="add"
          label="Hinzufügen"
          :disable="!newName || !newValue"
          @click="insert"
        />
      </div>
    </div>
    <p class="text-caption text-grey-7 q-mt-md">
      Vorschläge sind auswählbar; eigene Werte bleiben möglich. Nicht angezeigte Direktiven und
      Kommentare bleiben erhalten. Formularänderungen werden zunächst in den Entwurf übernommen.
    </p>
  </div>
</template>
