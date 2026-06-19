const fs = require('fs');
const path = require('path');

const dirs = [
    'frontend/src',
    'employee-relations/src',
    'payroll-frontend/src',
    'Dashboard/src',
    'ticket dashboard/src'
];

function walkDir(dir, callback) {
    if (!fs.existsSync(dir)) return;
    fs.readdirSync(dir).forEach(f => {
        let dirPath = path.join(dir, f);
        let isDirectory = fs.statSync(dirPath).isDirectory();
        isDirectory ? walkDir(dirPath, callback) : callback(path.join(dir, f));
    });
}

let modifiedFiles = 0;

dirs.forEach(dir => {
    walkDir(dir, function(filePath) {
        if (filePath.endsWith('.tsx') || filePath.endsWith('.ts')) {
            let content = fs.readFileSync(filePath, 'utf8');
            let originalContent = content;

            // Replace old purple and pink with new green and purple
            content = content.replace(/bg-\\[#8b5cf6\\]/g, 'bg-[#00e07a]');
            content = content.replace(/bg-\\[#ec4899\\]/g, 'bg-[#9b6dff]');
            content = content.replace(/bg-\\[#10b981\\]/g, 'bg-[#00e07a]');
            
            // Text colors
            content = content.replace(/text-\\[#8b5cf6\\]/g, 'text-[#00e07a]');
            content = content.replace(/text-\\[#ec4899\\]/g, 'text-[#9b6dff]');
            content = content.replace(/text-\\[#10b981\\]/g, 'text-[#00e07a]');
            
            // Border colors
            content = content.replace(/border-\\[#8b5cf6\\]/g, 'border-[#00e07a]');
            content = content.replace(/border-\\[#ec4899\\]/g, 'border-[#9b6dff]');
            content = content.replace(/border-\\[#10b981\\]/g, 'border-[#00e07a]');

            // Change App layout background from gray-50 to slate-100 for better separation
            content = content.replace(/bg-gray-50 dark:bg-\\[#06070a\\]/g, 'bg-slate-100 dark:bg-[#06070a]');
            content = content.replace(/bg-gray-50 dark:bg-\\[#0b0f1a\\]/g, 'bg-slate-100 dark:bg-[#0b0f1a]');
            
            if (content !== originalContent) {
                fs.writeFileSync(filePath, content, 'utf8');
                modifiedFiles++;
                console.log('Modified: ' + filePath);
            }
        }
    });
});

console.log('Total files updated: ' + modifiedFiles);
