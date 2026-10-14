import type { DesktopApi } from './apache';
declare global {
  interface Window {
    readonly webadmin?: DesktopApi;
  }
}
