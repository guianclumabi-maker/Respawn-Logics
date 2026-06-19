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
            
            // Regex to remove the block
            const regex = /<style>\s*body {\s*background-color: #f8fafc !important; \/\* slate-100 for separation \*\/\s*}\s*\.main-content {\s*background-color: #f8fafc !important; \/\* slate-100 for separation \*\/\s*position: relative;\s*z-index: 0;\s*}\s*\/\* Global Background Glow Effects for Light Mode \*\/\s*\.global-glow-green {[\s\S]*?z-index: -1;\s*}\s*/;
            
            if (regex.test(content)) {
                content = content.replace(regex, "");
                fs.writeFileSync(filePath, content);
                console.log("Cleaned " + filePath);
            }
        }
    });
}

processDir('./pages');
