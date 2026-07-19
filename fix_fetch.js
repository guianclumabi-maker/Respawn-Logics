const fs = require('fs');
const path = require('path');

const srcDir = path.join(__dirname, 'frontend/src');

function findRelativePath(fromFile, toFile) {
    let rel = path.relative(path.dirname(fromFile), toFile).replace(/\\/g, '/');
    if (!rel.startsWith('.')) rel = './' + rel;
    // remove extension
    rel = rel.replace(/\.ts$/, '');
    return rel;
}

const apiClientPath = path.join(srcDir, 'app/lib/apiClient.ts');

function processFile(filePath) {
    if (!filePath.endsWith('.tsx') && !filePath.endsWith('.ts')) return;
    
    let content = fs.readFileSync(filePath, 'utf8');
    
    // Check if it has a mutating raw fetch
    const fetchRegex = /\bfetch\s*\(/g;
    let modified = false;
    
    // We only care if there is a method: 'POST' or 'PUT' or 'DELETE' in the file, 
    // but to be safe, we replace ALL raw fetch calls with apiFetch in the frontend, 
    // EXCEPT inside apiClient.ts itself.
    if (filePath === apiClientPath) return;

    if (fetchRegex.test(content) && !content.includes('import { apiFetch }')) {
        // We will just replace ALL `fetch(` with `apiFetch(` where it seems it's hitting our API.
        // Actually, replacing all fetch is fine because apiFetch falls back to normal fetch anyway.
        content = content.replace(/\bfetch\s*\((.*?)\)/gs, (match, p1) => {
            if (p1.includes('/api/index.php') || p1.includes('API') || p1.includes('apiRoute') || p1.includes('api_base') || p1.includes('API_BASE')) {
                modified = true;
                return `apiFetch(${p1})`;
            }
            // Some use fetch(`../api/index.php...`)
            if (p1.includes('api/index.php')) {
                modified = true;
                return `apiFetch(${p1})`;
            }
            // If it has method POST
            if (p1.includes('POST') || p1.includes('PUT') || p1.includes('DELETE')) {
                modified = true;
                return `apiFetch(${p1})`;
            }
            return match; // keep original if not our API
        });

        if (modified) {
            const relPath = findRelativePath(filePath, apiClientPath);
            const importStmt = `import { apiFetch } from "${relPath}";\n`;
            
            // Insert after React imports or at the very top
            if (content.includes('import React')) {
                content = content.replace(/import React.*?;?\n/, match => match + importStmt);
            } else {
                content = importStmt + content;
            }
            fs.writeFileSync(filePath, content, 'utf8');
            console.log(`Updated: ${filePath}`);
        }
    }
}

function walk(dir) {
    const files = fs.readdirSync(dir);
    for (const file of files) {
        const fullPath = path.join(dir, file);
        if (fs.statSync(fullPath).isDirectory()) {
            walk(fullPath);
        } else {
            processFile(fullPath);
        }
    }
}

walk(srcDir);
console.log('Done.');
