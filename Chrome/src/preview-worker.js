/* global importScripts */
importScripts('settings.js', 'engine.js');
self.onmessage = ({ data }) => {
  try {
    self.postMessage({
      result: LeseklarEngine.transform(data.text, data.settings, data.host),
    });
  } catch (error) {
    self.postMessage({ error: error.message });
  }
};
