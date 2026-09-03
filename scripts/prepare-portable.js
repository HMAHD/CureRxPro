// scripts/prepare-portable.js - Portable Binary Verification Script
const fs = require('fs');
const path = require('path');

const rootDir = path.resolve(__dirname, '..');
const phpBin = path.join(rootDir, 'bin', 'php', 'php.exe');
const mysqlBin = path.join(rootDir, 'bin', 'mysql', 'bin', 'mysqld.exe');

console.log('=== CureRxPro Portable Environment Audit ===');

const phpExists = fs.existsSync(phpBin);
const mysqlExists = fs.existsSync(mysqlBin);

console.log(`- Portable PHP Server (${phpBin}): ${phpExists ? 'INSTALLED' : 'NOT FOUND (Will fallback to system PHP/XAMPP)'}`);
console.log(`- Portable MySQL Server (${mysqlBin}): ${mysqlExists ? 'INSTALLED' : 'NOT FOUND (Will fallback to remote/system MySQL)'}`);

if (phpExists && mysqlExists) {
  console.log('\n✅ Standalone portable runtimes verified. Ready for deployment.');
} else {
  console.log('\n⚠️ Note: Non-portable mode. Application will require system PHP/MySQL configuration via db_setup.php.');
}
