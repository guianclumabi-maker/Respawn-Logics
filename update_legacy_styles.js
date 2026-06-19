const fs = require('fs');
const path = require('path');

const dir = './pages';
const files = fs.readdirSync(dir).filter(f => f.endsWith('.php'));
files.forEach(f => {
    if (f === 'dashboard.php' || f === 'ai_companion.php' || f === 'login.php' || f === 'impersonate.php') return;
    const filePath = path.join(dir, f);
    let content = fs.readFileSync(filePath, 'utf8');

    let modified = false;

    // Replace glow divs
    if (content.includes('ambient-glow')) {
        content = content.replace(/<div class="ambient-glow glow-green".*?><\/div>/g, '<div class="global-glow-green"></div>');
        content = content.replace(/<div class="ambient-glow glow-cyan".*?><\/div>/g, '<div class="global-glow-purple"></div>');
        modified = true;
    }

    // Add CSS if not present
    const cssToAdd = "        body {\n            background-color: #f8fafc !important; /* slate-100 for separation */\n        }\n        .main-content {\n            background-color: #f8fafc !important; /* slate-100 for separation */\n            position: relative;\n            z-index: 0;\n        }\n        /* Global Background Glow Effects for Light Mode */\n        .global-glow-green {\n            position: fixed; top: -100px; left: -100px; width: 500px; height: 500px; border-radius: 50%; background: #00e07a; filter: blur(120px); opacity: 0.08; pointer-events: none; z-index: -1;\n        }\n        .global-glow-purple {\n            position: fixed; bottom: -150px; right: -100px; width: 600px; height: 600px; border-radius: 50%; background: #9b6dff; filter: blur(140px); opacity: 0.06; pointer-events: none; z-index: -1;\n        }\n";
    if (!content.includes('global-glow-green {') && content.includes('<style>')) {
        content = content.replace(/<style>/, "<style>\n" + cssToAdd);
        modified = true;
    }

    if (modified) {
        fs.writeFileSync(filePath, content);
        console.log("Updated " + f);
    }
});
