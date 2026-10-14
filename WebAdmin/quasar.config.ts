import { defineConfig } from '#q-app';
export default defineConfig(() => ({
  boot: [],
  css: ['app.css'],
  extras: ['roboto-font', 'material-icons'],
  build: { typescript: { strict: true, vueShim: true }, vueRouterMode: 'hash' },
  devServer: { host: '127.0.0.1', open: false },
  framework: {
    lang: 'de',
    config: { brand: { primary: '#256b60', secondary: '#52647b' } },
    plugins: ['Dialog'],
  },
  electron: {
    preloadScripts: ['electron-preload'],
    bundler: 'packager',
    packager: { appBundleId: 'de.webadmin.desktop' },
  },
}));
