import type { RouteRecordRaw } from 'vue-router';
const routes: RouteRecordRaw[] = [
  {
    path: '/',
    component: () => import('@/layouts/MainLayout.vue'),
    children: [
      { path: '', component: () => import('@/pages/IndexPage.vue') },
      { path: 'workspace', component: () => import('@/pages/WorkspacePage.vue') },
      { path: 'settings', component: () => import('@/pages/SettingsPage.vue') },
      { path: 'apache/edit', component: () => import('@/pages/ApacheEditorPage.vue') },
    ],
  },
  { path: '/:catchAll(.*)*', component: () => import('@/pages/ErrorNotFound.vue') },
];
export default routes;
