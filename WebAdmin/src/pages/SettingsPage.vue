<script setup lang="ts">
const desktop = window.webadmin;
</script>
<template>
  <q-page padding class="page-content"
    ><div class="text-overline text-primary">APP-EINSTELLUNGEN</div>
    <h1 class="page-title">Deine Umgebung.</h1>
    <p class="page-intro">
      Informationen zur aktuellen Anwendung. Weitere Optionen kommen mit den nächsten Funktionen
      hinzu.
    </p>
    <q-card flat bordered class="q-mt-xl"
      ><q-list separator
        ><q-item
          ><q-item-section
            ><q-item-label>Arbeitsmodus</q-item-label
            ><q-item-label caption>Lokale Konfigurationsdateien</q-item-label></q-item-section
          ><q-item-section side><q-icon name="computer" color="primary" /></q-item-section></q-item
        ><q-item
          ><q-item-section
            ><q-item-label>Oberfläche</q-item-label
            ><q-item-label caption>Deutsch</q-item-label></q-item-section
          ></q-item
        ><q-item
          ><q-item-section
            ><q-item-label>Laufzeit</q-item-label
            ><q-item-label caption>{{
              desktop
                ? `Electron ${desktop.electronVersion} · ${desktop.platform}`
                : 'Browser-Vorschau'
            }}</q-item-label></q-item-section
          ></q-item
        ></q-list
      ></q-card
    ></q-page
  >
</template>
