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

const isWin = process.platform === 'win32';
const EXE = isWin ? '.exe' : '';

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
    const mysqlBin = path.join(__dirname, 'bin', 'mysql', 'bin', `mysqld${EXE}`);
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
    const localPhp = path.join(__dirname, 'bin', 'php', `php${EXE}`);

    if (fs.existsSync(localPhp)) {
      phpBin = localPhp;
    } else if (!isWin) {
      // macOS/Linux: check Homebrew and system paths
      const macPaths = ['/opt/homebrew/bin/php', '/usr/local/bin/php', '/usr/bin/php'];
      phpBin = macPaths.find(p => fs.existsSync(p)) || 'php';
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

function gracefulCleanup(callback) {
  if (phpProcess) {
    try { phpProcess.kill(); } catch (e) {}
    phpProcess = null;
  }

  const mysqlAdminPath = path.join(__dirname, 'bin', 'mysql', 'bin', `mysqladmin${EXE}`);
  const fs = require('fs');
  if (mysqlProcess && fs.existsSync(mysqlAdminPath)) {
    console.log('Initiating graceful MySQL shutdown via mysqladmin...');
    const { exec } = require('child_process');
    exec(`"${mysqlAdminPath}" -u root --port=3307 shutdown`, () => {
      if (mysqlProcess) {
        try { mysqlProcess.kill(); } catch (e) {}
        mysqlProcess = null;
      }
      if (callback) callback();
    });
  } else {
    if (mysqlProcess) {
      try { mysqlProcess.kill(); } catch (e) {}
      mysqlProcess = null;
    }
    if (callback) callback();
  }
}

app.on('window-all-closed', () => {
  gracefulCleanup(() => {
    if (process.platform !== 'darwin') {
      app.quit();
    }
  });
});

app.on('will-quit', (e) => {
  gracefulCleanup();
});

// IPC Handler for Silent Print trigger (Prints via a hidden background worker window to prevent main window focus locks)
ipcMain.on('trigger-print-silent', (event, payload) => {
  const fs = require('fs');
  try {
    if (!payload || typeof payload.html !== 'string') {
      console.error('Invalid print payload received');
      return;
    }

    // Sanitize paperSize parameter
    const allowedSizes = ['A5', 'A4', 'Thermal'];
    const paperSize = allowedSizes.includes(payload.paperSize) ? payload.paperSize : 'A5';

    // Strip harmful script tags and event handlers to prevent XSS (SEC-03)
    let sanitizedHtml = payload.html
      .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
      .replace(/on\w+\s*=\s*(['"])(.*?)\1/gi, '');

    const printCssPath = path.join(__dirname, 'assets', 'css', 'print.css');
    let printCss = '';
    if (fs.existsSync(printCssPath)) {
      printCss = fs.readFileSync(printCssPath, 'utf8');
    }

    // Spawn a sandboxed hidden worker window
    let workerWindow = new BrowserWindow({
      show: false,
      webPreferences: {
        nodeIntegration: false,
        contextIsolation: true,
        sandbox: true,
        webSecurity: true
      }
    });

    const fullHtml = `
      <!DOCTYPE html>
      <html>
      <head>
        <meta charset="utf-8">
        <meta http-equiv="Content-Security-Policy" content="default-src 'none'; style-src 'unsafe-inline'; font-src data:; img-src data:;">
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
      <body class="print-size-${paperSize}">
        <div id="print-area">
          ${sanitizedHtml}
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

// IPC Handler: renderer sends a version tag; main constructs and validates the URL
ipcMain.on('start-auto-update', (event, versionTag) => {
  const fs = require('fs');
  const https = require('https');
  const { exec } = require('child_process');

  // Validate version tag format (e.g. v1.4.2) — reject anything else
  if (typeof versionTag !== 'string' || !/^v\d+\.\d+\.\d+$/.test(versionTag)) {
    event.reply('auto-update-error', 'Invalid version tag');
    return;
  }

  // URL is constructed here — renderer cannot influence the host
  const downloadUrl = `https://github.com/HMAHD/CureRxPro/releases/download/${versionTag}/CureRx_Pro_Setup_Update.exe`;

  const tempDir = app.getPath('temp');
  const installerPath = path.join(tempDir, 'CureRx_Pro_Setup_Update.exe');

  function startDownload(targetUrl, depth = 0) {
    if (depth > 5) {
      event.reply('auto-update-error', 'Too many redirects');
      return;
    }
    // Only follow redirects to HTTPS within github.com / objects.githubusercontent.com
    const allowedHosts = ['github.com', 'objects.githubusercontent.com'];
    let parsedUrl;
    try { parsedUrl = new URL(targetUrl); } catch (_) { parsedUrl = null; }
    if (!parsedUrl || parsedUrl.protocol !== 'https:' || !allowedHosts.includes(parsedUrl.hostname)) {
      event.reply('auto-update-error', 'Redirect to untrusted or non-HTTPS host blocked');
      return;
    }

    const file = fs.createWriteStream(installerPath);
    https.get(targetUrl, (response) => {
      if (response.statusCode >= 300 && response.statusCode < 400 && response.headers.location) {
        file.close();
        startDownload(response.headers.location, depth + 1);
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
          event.reply('auto-update-progress', Math.round((downloadedBytes / totalBytes) * 100));
        }
      });
      response.on('end', () => {
        file.end(() => {
          event.reply('auto-update-progress', 100);
          setTimeout(() => {
            exec(`"${installerPath}" /S`, (err) => {
              if (err) console.error('Failed to run silent installer:', err);
            });
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

  fs.unlink(installerPath, () => { startDownload(downloadUrl); });
});
