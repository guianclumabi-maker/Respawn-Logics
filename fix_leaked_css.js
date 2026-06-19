const fs = require('fs');
const path = require('path');

function processDir(dir) {
    const files = fs.readdirSync(dir);
    files.forEach(f => {
        const filePath = path.join(dir, f);
        if (fs.statSync(filePath).isDirectory()) {
            if (f === 'views') processDir(filePath);
        } else if (f.endsWith('.php')) {
            let content = fs.readFileSync(filePath, 'utf8');
            
            // The exact orphaned block that was left behind
            const regex = /\.global-glow-purple\s*\{\s*position: fixed; bottom: -150px; right: -100px; width: 600px; height: 600px; border-radius: 50%; background: #9b6dff; filter: blur\(140px\); opacity: 0\.06; pointer-events: none; z-index: -1;\s*\}/;
            
            if (regex.test(content)) {
                content = content.replace(regex, '<style>');
                fs.writeFileSync(filePath, content);
                console.log("Restored <style> in " + filePath);
            }
        }
    });
}

processDir('./pages');
