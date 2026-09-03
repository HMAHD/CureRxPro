const { app, BrowserWindow, ipcMain } = require('electron');
const path = require('path');
const { spawn } = require('child_process');
const http = require('http');

// Safely attempt electron-updater loading
let autoUpdater = null;
try {
  autoUpdater = require('electron-updater').autoUpdater;
} catch (err) {
  console.log('autoUpdater module optional load note:', err.message);
}

let mainWindow;
let phpProcess;
let mysqlProcess;
const PORT = 8989;

// Helper to recursively copy database files
function copyFolderRecursiveSync(source, target) {
  const fs = require('fs');
  const path = require('path');
  if (!fs.existsSync(target)) {
    fs.mkdirSync(target, { recursive: true });
  }
  if (fs.lstatSync(source).isDirectory()) {
    const files = fs.readdirSync(source);
    files.forEach((file) => {
      const curSource = path.join(source, file);
      const curTarget = path.join(target, file);
      if (fs.lstatSync(curSource).isDirectory()) {
        copyFolderRecursiveSync(curSource, curTarget);
      } else {
        fs.copyFileSync(curSource, curTarget);
      }
    });
  }
}

// 1. Start Portable Embedded MySQL Database Server
function startMysqlServer() {
  return new Promise((resolve) => {
    const fs = require('fs');
    const mysqlBin = path.join(__dirname, 'bin', 'mysql', 'bin', 'mysqld.exe');
    const baseDir = path.join(__dirname, 'bin', 'mysql');
    
    // Standard User-writable database directory inside AppData
    const dataDir = path.join(app.getPath('userData'), 'db-data');

    if (!fs.existsSync(mysqlBin)) {
      console.log('Portable MySQL database server binary not found. Skipping auto-start.');
      resolve();
      return;
    }

    // Copy template system database files to AppData if not present or dirty
    const srcDataDir = path.join(__dirname, 'bin', 'mysql', 'data');
    const initMarker = path.join(dataDir, 'initialized.txt');
    if (!fs.existsSync(initMarker)) {
      console.log(`Initializing user database storage in AppData: ${dataDir}`);
      try {
        if (fs.existsSync(dataDir)) {
          console.log('Cleaning old database directory before copying fresh template...');
          fs.rmSync(dataDir, { recursive: true, force: true });
        }
        copyFolderRecursiveSync(srcDataDir, dataDir);
        fs.writeFileSync(initMarker, '1');
      } catch (copyErr) {
        console.error('Failed to copy database files to AppData', copyErr);
      }
    }

    console.log(`Starting portable MySQL database server using datadir ${dataDir}...`);
    try {
      mysqlProcess = spawn(mysqlBin, [
        '--port=3307',
        `--basedir=${baseDir}`,
        `--datadir=${dataDir}`,
        '--console'
      ], {
        cwd: path.join(__dirname, 'bin', 'mysql', 'bin'),
        detached: false
      });

      mysqlProcess.stdout.on('data', (data) => console.log(`MySQL stdout: ${data}`));
      mysqlProcess.stderr.on('data', (data) => console.log(`MySQL stderr: ${data}`));

      mysqlProcess.on('error', (err) => {
        console.error('MySQL database process spawn error:', err);
      });

      // Give database server 2 seconds to initialize and listen on port 3307
      setTimeout(resolve, 2000);
    } catch (e) {
      console.error('Failed to spawn MySQL database server:', e);
      resolve();
    }
  });
}

// 2. Start Local Embedded PHP Web Server
function startPhpServer() {
  return new Promise((resolve, reject) => {
    const fs = require('fs');
    let phpBin = 'php';
    const localPhp = path.join(__dirname, 'bin', 'php', 'php.exe');

    if (fs.existsSync(localPhp)) {
      phpBin = localPhp;
    } else if (fs.existsSync('C:\\xampp\\php\\php.exe')) {
      phpBin = 'C:\\xampp\\php\\php.exe';
    }

    const appPath = __dirname;
    console.log(`Starting PHP server using ${phpBin} on port ${PORT} from ${appPath}`);

    try {
      phpProcess = spawn(phpBin, ['-S', `127.0.0.1:${PORT}`, '-t', appPath], {
        cwd: appPath,
        detached: false,
        env: {
          ...process.env,
          USER_DATA_PATH: app.getPath('userData')
        }
      });

      phpProcess.stdout.on('data', (data) => console.log(`PHP stdout: ${data}`));
      phpProcess.stderr.on('data', (data) => console.log(`PHP stderr: ${data}`));

      phpProcess.on('error', (err) => {
        console.error('PHP process spawn error:', err);
      });

      let attempts = 0;
      const checkServer = () => {
        attempts++;
        http.get(`http://127.0.0.1:${PORT}/index.php`, (res) => {
          resolve();
        }).on('error', () => {
          if (attempts > 40) {
            console.error('PHP server check timed out');
            resolve();
          } else {
            setTimeout(checkServer, 250);
          }
        });
      };

      checkServer();
    } catch (e) {
      console.error('Failed to spawn PHP server:', e);
      resolve();
    }
  });
}

// 3. Create Native BrowserWindow
function createWindow() {
  mainWindow = new BrowserWindow({
    width: 1366,
    height: 850,
    minWidth: 1024,
    minHeight: 700,
    title: 'CureRx Pro - Digital Prescription Suite',
    icon: path.join(__dirname, 'assets/icons/icon.ico'),
    webPreferences: {
      nodeIntegration: false,
      contextIsolation: true,
      preload: path.join(__dirname, 'preload.js')
    },
    autoHideMenuBar: true
  });

  mainWindow.loadURL(`http://127.0.0.1:${PORT}/index.php`);

  mainWindow.on('closed', () => {
    mainWindow = null;
  });
}

// 4. App Lifecycle
app.whenReady().then(async () => {
  try {
    await startMysqlServer();
    await startPhpServer();
    createWindow();
    
    // Check for software updates in background if updater present
    if (autoUpdater) {
      autoUpdater.checkForUpdatesAndNotify().catch(err => console.log('Auto update check note:', err.message));
    }
  } catch (err) {
    console.error('Failed to start PHP/MySQL servers', err);
  }
});

app.on('window-all-closed', () => {
  if (phpProcess) {
    phpProcess.kill();
  }
  if (mysqlProcess) {
    mysqlProcess.kill();
  }
  if (process.platform !== 'darwin') {
    app.quit();
  }
});

app.on('quit', () => {
  if (phpProcess) {
    phpProcess.kill();
  }
  if (mysqlProcess) {
    mysqlProcess.kill();
  }
});

// IPC Handler for Silent Print trigger (Prints via a hidden background worker window to prevent main window focus locks)
ipcMain.on('trigger-print-silent', (event, payload) => {
  const fs = require('fs');
  try {
    const printCssPath = path.join(__dirname, 'assets', 'css', 'print.css');
    let printCss = '';
    if (fs.existsSync(printCssPath)) {
      printCss = fs.readFileSync(printCssPath, 'utf8');
    }

    // Spawn a hidden worker window
    let workerWindow = new BrowserWindow({
      show: false,
      webPreferences: {
        nodeIntegration: false,
        contextIsolation: true
      }
    });

    const fullHtml = `
      <!DOCTYPE html>
      <html>
      <head>
        <meta charset="utf-8">
        <style>
          ${printCss}
          body {
            margin: 0 !important;
            padding: 0 !important;
            background: #ffffff !important;
            color: #000000 !important;
          }
        </style>
      </head>
      <body class="print-size-${payload.paperSize}">
        <div id="print-area">
          ${payload.html}
        </div>
      </body>
      </html>
    `;

    workerWindow.loadURL('data:text/html;charset=utf-8,' + encodeURIComponent(fullHtml));

    workerWindow.webContents.on('did-finish-load', () => {
      workerWindow.webContents.print({
        silent: true,
        printBackground: true,
        margins: { marginType: 'none' }
      }, (success, failureReason) => {
        console.log(`Silent print completed. Success: ${success}, Reason: ${failureReason}`);
        if (workerWindow) {
          workerWindow.destroy();
          workerWindow = null;
        }

        // Restore OS focus back to the parent window after background child window is destroyed
        setTimeout(() => {
          if (mainWindow) {
            mainWindow.focus();
            mainWindow.webContents.focus();
          }
        }, 50);
      });
    });
  } catch (err) {
    console.error('Failed to trigger background print:', err);
  }
});

// IPC Handler to explicitly restore window focus when requested by renderer
ipcMain.on('refocus-window', () => {
  if (mainWindow) {
    mainWindow.show();
    mainWindow.focus();
    mainWindow.webContents.focus();
  }
});

// IPC Handler to download and run the silent update installer
ipcMain.on('start-auto-update', (event, downloadUrl) => {
  const fs = require('fs');
  const https = require('https');
  const { exec } = require('child_process');

  const tempDir = app.getPath('temp');
  const installerPath = path.join(tempDir, 'CureRx_Pro_Setup_Update.exe');

  // Helper to safely delete file and download
  function startDownload(targetUrl) {
    const file = fs.createWriteStream(installerPath);
    
    https.get(targetUrl, (response) => {
      // Handle redirect
      if (response.statusCode >= 300 && response.statusCode < 400 && response.headers.location) {
        file.close();
        startDownload(response.headers.location);
        return;
      }

      if (response.statusCode !== 200) {
        file.close();
        fs.unlink(installerPath, () => {});
        event.reply('auto-update-error', `Server returned status: ${response.statusCode}`);
        return;
      }

      const totalBytes = parseInt(response.headers['content-length'], 10) || 0;
      let downloadedBytes = 0;

      response.on('data', (chunk) => {
        downloadedBytes += chunk.length;
        file.write(chunk);
        
        if (totalBytes > 0) {
          const percent = Math.round((downloadedBytes / totalBytes) * 100);
          event.reply('auto-update-progress', percent);
        }
      });

      response.on('end', () => {
        file.end(() => {
          event.reply('auto-update-progress', 100);
          
          // Execute the installer silently in the background
          // /S is the silent installer flag for NSIS installer
          setTimeout(() => {
            exec(`"${installerPath}" /S`, (err) => {
              if (err) {
                console.error('Failed to run silent installer:', err);
              }
            });
            // Quit the application so the installer can replace the executable files
            app.quit();
          }, 1000);
        });
      });
    }).on('error', (err) => {
      file.close();
      fs.unlink(installerPath, () => {});
      event.reply('auto-update-error', err.message);
    });
  }

  // Delete previous update installer if exists, then download
  fs.unlink(installerPath, () => {
    startDownload(downloadUrl);
  });
});
