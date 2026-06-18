const fs = require('fs');
 // using simple file traversal instead

function getFiles(dir, fileList = []) {
  const files = fs.readdirSync(dir);
  for (const file of files) {
    const stat = fs.statSync(dir + '/' + file);
    if (stat.isDirectory()) {
      getFiles(dir + '/' + file, fileList);
    } else {
      if (file.endsWith('.tsx')) {
        fileList.push(dir + '/' + file);
      }
    }
  }
  return fileList;
}

const files = getFiles('C:/xampp/htdocs/respawn-logics/frontend/src/app/components');
let modifiedCount = 0;

files.forEach(file => {
  let content = fs.readFileSync(file, 'utf8');
  let originalContent = content;
  
  // Replace the exact style injection
  content = content.replace(/style=\{\{\s*backgroundColor:\s*["']#0b0f1a["']\s*\}\}/g, '');
  
  // Also remove it if it was part of a larger style tag but we'll try to just remove the backgroundColor part
  content = content.replace(/,\s*backgroundColor:\s*["']#0b0f1a["']/g, '');
  content = content.replace(/backgroundColor:\s*["']#0b0f1a["'],?\s*/g, '');
  
  if (content !== originalContent) {
    // If the style object became empty style={{ }} or style={{  }}, let's just remove the empty style prop entirely
    content = content.replace(/style=\{\{\s*\}\}/g, '');
    
    fs.writeFileSync(file, content);
    modifiedCount++;
    console.log('Fixed background in: ' + file);
  }
});

console.log('Total files modified: ' + modifiedCount);
