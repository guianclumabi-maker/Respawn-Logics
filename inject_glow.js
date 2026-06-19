const fs = require('fs');
const path = require('path');

const files = [
    'frontend/src/app/App.tsx',
    'employee-relations/src/app/App.tsx',
    'ticket dashboard/src/app/App.tsx',
    'Dashboard/src/app/App.tsx',
    'payroll-frontend/src/App.tsx'
];

files.forEach(filePath => {
    if (fs.existsSync(filePath)) {
        let content = fs.readFileSync(filePath, 'utf8');
        
        // 1. Replace bg-gray-50 or bg-white with bg-slate-100
        content = content.replace(/bg-gray-50 dark:bg-\\[#06070a\\]/g, 'bg-slate-100 dark:bg-[#06070a]');
        content = content.replace(/bg-white dark:bg-\\[#06070a\\]/g, 'bg-slate-100 dark:bg-[#06070a]');
        content = content.replace(/bg-gray-50 dark:bg-\\[#0b0f1a\\]/g, 'bg-slate-100 dark:bg-[#0b0f1a]');
        content = content.replace(/bg-[#0b0f1a]/g, 'bg-slate-100 dark:bg-[#0b0f1a]');
        
        // 2. Add the glowing circles to the main layout wrapper
        // The main layout usually has className="flex h-screen w-full overflow-hidden..."
        // We will replace that whole line to inject the glows right after the <div
        
        const mainDivRegex = /<div\s+className="([^"]*h-screen[^"]*)"\s*>/g;
        
        content = content.replace(mainDivRegex, (match, classes) => {
            if (!classes.includes('relative')) {
                classes += ' relative z-0';
            }
            if (classes.includes('bg-gray-50')) {
                 classes = classes.replace('bg-gray-50', 'bg-slate-100');
            }
            return <div className="">
      <div className="absolute top-[-100px] left-[-100px] w-[500px] h-[500px] rounded-full bg-[#00e07a] blur-[120px] opacity-10 dark:opacity-[0.06] pointer-events-none z-[-1]" />
      <div className="absolute bottom-[-150px] right-[-100px] w-[600px] h-[600px] rounded-full bg-[#9b6dff] blur-[140px] opacity-10 dark:opacity-[0.05] pointer-events-none z-[-1]" />;
        });
        
        fs.writeFileSync(filePath, content, 'utf8');
        console.log('Modified: ' + filePath);
    } else {
        console.log('Not found: ' + filePath);
    }
});
