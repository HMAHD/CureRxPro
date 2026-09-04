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
  startAutoUpdate: (versionTag) => {
    if (typeof versionTag === 'string' && /^v\d+\.\d+\.\d+$/.test(versionTag)) {
      ipcRenderer.send('start-auto-update', versionTag);
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
  },
  onUpdateDownloaded: (callback) => {
    if (typeof callback === 'function') {
      ipcRenderer.on('auto-update-downloaded', (event) => callback());
    }
  }
});
