// preload.js - Electron Preload Bridge Script
const { contextBridge, ipcRenderer, shell } = require('electron');

contextBridge.exposeInMainWorld('electronAPI', {
  silentPrint: (payload) => ipcRenderer.send('trigger-print-silent', payload),
  refocusWindow: () => ipcRenderer.send('refocus-window'),
  openBrowser: (url) => shell.openExternal(url),
  startAutoUpdate: (url) => ipcRenderer.send('start-auto-update', url),
  onUpdateProgress: (callback) => ipcRenderer.on('auto-update-progress', (event, percent) => callback(percent)),
  onUpdateError: (callback) => ipcRenderer.on('auto-update-error', (event, errMsg) => callback(errMsg))
});
