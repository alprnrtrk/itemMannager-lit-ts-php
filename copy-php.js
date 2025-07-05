const fs = require('fs');
const path = require('path');

const sourcePath = path.resolve(__dirname, 'public', 'index.php');
const destPath = path.resolve(__dirname, 'dist', 'index.php');

console.log(`Attempting to copy: ${sourcePath} to ${destPath}`);

try {
    const destDir = path.dirname(destPath);
    if (!fs.existsSync(destDir)) {
        fs.mkdirSync(destDir, { recursive: true });
    }

    fs.copyFileSync(sourcePath, destPath);
    console.log('Successfully copied public/index.php to dist/index.php');
} catch (error) {
    console.error('Error copying index.php:', error);
    process.exit(1);
}