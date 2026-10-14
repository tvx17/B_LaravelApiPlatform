<template>
  <q-page padding class="page-content">
    <div class="text-overline text-primary">WEBADMIN</div>
    <h1 class="page-title">Ein klarer Blick auf deine Konfiguration.</h1>
    <p class="page-intro">
      Dein lokaler Arbeitsbereich beginnt hier. Die Oberfläche steht bereit — die
      Konfigurationsmodule ergänzen wir Schritt für Schritt.
    </p>
    <q-card flat bordered class="welcome-card q-mt-xl"
      ><q-card-section class="q-pa-lg"
        ><q-icon name="desktop_windows" size="36px" color="primary" />
        <h2 class="text-h6 q-mb-sm">Eine gemeinsame Basis</h2>
        <p class="text-grey-8">
          Wähle im Arbeitsbereich dein Apache-Verzeichnis. WebAdmin merkt sich den Pfad und zeigt
          die gefundenen Konfigurationsdateien übersichtlich an.
        </p>
        <q-btn
          color="primary"
          unelevated
          label="Zum Arbeitsbereich"
          to="/workspace"
          icon-right="arrow_forward" /></q-card-section
    ></q-card>
    <div class="row q-col-gutter-lg q-mt-md">
      <div v-for="item in milestones" :key="item.title" class="col-12 col-md-4">
        <q-card flat bordered class="full-height"
          ><q-card-section
            ><q-icon :name="item.icon" color="primary" size="28px" />
            <h2 class="text-subtitle1 text-weight-medium">{{ item.title }}</h2>
            <p class="text-grey-7 q-mb-none">{{ item.description }}</p></q-card-section
          ></q-card
        >
      </div>
    </div>
  </q-page>
</template>
<script setup lang="ts">
const milestones = [
  {
    icon: 'check_circle_outline',
    title: 'Oberfläche vorbereitet',
    description: 'Ein gemeinsames Layout mit übersichtlicher Navigation.',
  },
  {
    icon: 'folder_open',
    title: 'Apache-Dateien finden',
    description: 'Verzeichnis auswählen, lokal merken und Konfigurationsdateien auflisten.',
  },
  {
    icon: 'edit_note',
    title: 'Direktiven und Virtual Hosts',
    description: 'Einstellungen bearbeiten, Virtual Hosts verwalten und mit Sicherung speichern.',
  },
];
</script>
