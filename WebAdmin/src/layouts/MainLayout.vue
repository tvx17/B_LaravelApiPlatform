<script setup lang="ts">
import { ref } from 'vue';
const drawerOpen = ref(true);
const navigation = [
  { label: 'Übersicht', icon: 'space_dashboard', to: '/' },
  { label: 'Arbeitsbereich', icon: 'folder_open', to: '/workspace' },
  { label: 'Einstellungen', icon: 'tune', to: '/settings' },
];
</script>
<template>
  <q-layout view="hHh Lpr fFf">
    <q-header class="app-header"
      ><q-toolbar>
        <q-btn
          flat
          round
          icon="menu"
          aria-label="Navigation umschalten"
          @click="drawerOpen = !drawerOpen"
        />
        <q-toolbar-title class="text-weight-medium">WebAdmin</q-toolbar-title>
        <q-chip dense icon="computer" color="grey-2">Lokal</q-chip>
      </q-toolbar></q-header
    >
    <q-drawer v-model="drawerOpen" show-if-above bordered :width="250" class="app-drawer">
      <div class="q-pa-lg">
        <div class="text-overline text-primary">DEIN ARBEITSPLATZ</div>
        <div class="text-h6">Alles an einem Ort</div>
        <p class="text-grey-7 q-mt-sm">Die Basis für deine lokale Konfigurationsverwaltung.</p>
      </div>
      <q-list padding class="q-px-sm"
        ><q-item
          v-for="item in navigation"
          :key="item.to"
          :to="item.to"
          exact
          clickable
          active-class="navigation-active"
          class="rounded-borders q-mb-xs"
          ><q-item-section avatar><q-icon :name="item.icon" /></q-item-section
          ><q-item-section>{{ item.label }}</q-item-section></q-item
        ></q-list
      >
      <div class="q-pa-lg text-caption text-grey-7">Lokale Konfigurationsverwaltung</div>
    </q-drawer>
    <q-page-container><router-view /></q-page-container>
  </q-layout>
</template>
