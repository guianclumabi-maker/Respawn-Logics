const fs = require('fs');

const paths = [
    'Dashboard',
    'employee-relations',
    'frontend',
    'onboarding',
    'ticket dashboard'
];

paths.forEach(app => {
    // 1. Update theme.css
    const themePath = `C:/xampp/htdocs/respawn-logics/${app}/src/styles/theme.css`;
    if (fs.existsSync(themePath)) {
        let content = fs.readFileSync(themePath, 'utf8');
        // Light mode
        content = content.replace(/--card:\s*#ffffff;/g, '--card: rgba(255, 255, 255, 0.75);');
        // Dark mode
        content = content.replace(/--card:\s*oklch\(0\.145\s+0\s+0\);/g, '--card: rgba(11, 15, 26, 0.65);');
        fs.writeFileSync(themePath, content);
        console.log('Updated ' + themePath);
    }

    // 2. Update card.tsx
    const cardPath = `C:/xampp/htdocs/respawn-logics/${app}/src/app/components/ui/card.tsx`;
    if (fs.existsSync(cardPath)) {
        let content = fs.readFileSync(cardPath, 'utf8');
        if (!content.includes('backdrop-blur-[12px]')) {
            content = content.replace(/bg-card/g, 'bg-card backdrop-blur-[12px]');
            fs.writeFileSync(cardPath, content);
            console.log('Updated ' + cardPath);
        }
    }
});
