// copy-backend-to-dist.js
const fs = require('fs');
const path = require('path');

// --- Paths for index.php ---
const indexPhpSourcePath = path.resolve(__dirname, 'public', 'index.php');
const indexPhpDestPath = path.resolve(__dirname, 'dist', 'index.php');

// --- Paths for public/app directory ---
const appSourcePath = path.resolve(__dirname, 'public', 'app');
const appDestPath = path.resolve(__dirname, 'dist', 'app');

console.log('--- Starting custom copy process ---');

try {
    // Ensure the destination 'dist' directory exists
    const distDir = path.resolve(__dirname, 'dist');
    if (!fs.existsSync(distDir)) {
        console.log(`Creating dist directory: ${distDir}`);
        fs.mkdirSync(distDir, { recursive: true });
    }

    // --- Copy public/index.php ---
    console.log(`Attempting to copy: ${indexPhpSourcePath} to ${indexPhpDestPath}`);
    fs.copyFileSync(indexPhpSourcePath, indexPhpDestPath);
    console.log('Successfully copied public/index.php to dist/index.php');

    // --- Copy public/app directory ---
    console.log(`Attempting to copy directory: ${appSourcePath} to ${appDestPath}`);
    // Ensure the destination 'dist/app' directory exists before copying contents
    if (fs.existsSync(appDestPath)) {
        // If it exists from a previous run, remove it for a clean copy
        console.log(`Removing existing ${appDestPath} for a clean copy.`);
        fs.rmSync(appDestPath, { recursive: true, force: true });
    }
    // Copy the entire directory recursively
    fs.cpSync(appSourcePath, appDestPath, { recursive: true });
    console.log('Successfully copied public/app to dist/app');

} catch (error) {
    console.error('An error occurred during the copy process:', error);
    process.exit(1); // Exit with an error code
}

console.log('--- Custom copy process completed ---');