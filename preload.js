// preload.js - Secure Electron Preload Context Bridge
const { contextBridge, ipcRenderer, shell } = require('electron');

contextBridge.exposeInMainWorld('electronAPI', {
  silentPrint: (payload) => {
    if (!payload || typeof payload !== 'object') return;
    ipcRenderer.send('trigger-print-silent', payload);
  },
  refocusWindow: () => ipcRenderer.send('refocus-window'),
  openBrowser: (url) => {
    if (typeof url === 'string' && (url.startsWith('http://') || url.startsWith('https://'))) {
      shell.openExternal(url);
    }
  },
  startAutoUpdate: (url) => {
    if (typeof url === 'string' && url.startsWith('https://')) {
      ipcRenderer.send('start-auto-update', url);
    }
  },
  onUpdateProgress: (callback) => {
    if (typeof callback === 'function') {
      ipcRenderer.on('auto-update-progress', (event, percent) => callback(percent));
    }
  },
  onUpdateError: (callback) => {
    if (typeof callback === 'function') {
      ipcRenderer.on('auto-update-error', (event, errMsg) => callback(errMsg));
    }
  }
});
