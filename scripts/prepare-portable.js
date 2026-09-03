const fs = require('fs');
const path = require('path');

const srcPhp = 'C:\\xampp\\php';
const srcMysql = 'C:\\xampp\\mysql';
const destPhp = path.join(__dirname, '..', 'bin', 'php');
const destMysql = path.join(__dirname, '..', 'bin', 'mysql');

// Helper to recursively copy directories
function copyRecursiveSync(src, dest, excludeFilter) {
  const exists = fs.existsSync(src);
  const stats = exists && fs.statSync(src);
  const isDirectory = exists && stats.isDirectory();

  if (isDirectory) {
    if (excludeFilter && excludeFilter(src)) {
      return;
    }
    if (!fs.existsSync(dest)) {
      fs.mkdirSync(dest, { recursive: true });
    }
    fs.readdirSync(src).forEach((childItemName) => {
      copyRecursiveSync(
        path.join(src, childItemName),
        path.join(dest, childItemName),
        excludeFilter
      );
    });
  } else {
    fs.copyFileSync(src, dest);
  }
}

console.log('Starting copy of portable runtimes from XAMPP...');

// 1. Copy PHP
if (fs.existsSync(srcPhp)) {
  console.log(`Copying PHP from ${srcPhp} to ${destPhp}...`);
  copyRecursiveSync(srcPhp, destPhp, (srcPath) => {
    // Exclude logs or temp php files if any
    return false;
  });
} else {
  console.error(`PHP source path ${srcPhp} not found!`);
}

// 2. Copy MySQL Binaries (excluding data folder entirely)
if (fs.existsSync(srcMysql)) {
  console.log(`Copying MySQL binaries from ${srcMysql} to ${destMysql}...`);
  copyRecursiveSync(srcMysql, destMysql, (srcPath) => {
    // Exclude data directory completely during copy (we will initialize a clean one)
    if (srcPath.startsWith(path.join(srcMysql, 'data'))) {
      return true;
    }
    // Exclude temporary backup/temp directories
    if (srcPath.includes('backup') || srcPath.endsWith('.log')) {
      return true;
    }
    return false;
  });

  // Run mysql_install_db to initialize a 100% clean, pristine system database template
  const { execSync } = require('child_process');
  const installDbExe = path.join(destMysql, 'bin', 'mysql_install_db.exe');
  const targetDataDir = path.join(destMysql, 'data');
  
  console.log('Initializing pristine system database template...');
  try {
    if (fs.existsSync(targetDataDir)) {
      fs.rmSync(targetDataDir, { recursive: true, force: true });
    }
    execSync(`"${installDbExe}" --datadir="${targetDataDir}"`, { stdio: 'inherit' });
    console.log('Pristine system database template successfully created.');
  } catch (err) {
    console.error('Failed to run mysql_install_db to create clean template', err);
  }
  
  // Create default configuration file bin/mysql/bin/my.ini if not copied
  const myIniPath = path.join(destMysql, 'bin', 'my.ini');
  if (!fs.existsSync(myIniPath)) {
    const defaultIni = `[mysqld]
port=3307
socket="mysql.sock"
basedir="../"
datadir="../data"
tmpdir="../tmp"
character-set-server=utf8mb4
collation-server=utf8mb4_unicode_ci
max_allowed_packet=64M
innodb_buffer_pool_size=128M
`;
    fs.writeFileSync(myIniPath, defaultIni);
    console.log('Created default my.ini configuration file for portable MySQL.');
  }
} else {
  console.error(`MySQL source path ${srcMysql} not found!`);
}

console.log('Portable runtimes copy completed successfully!');
