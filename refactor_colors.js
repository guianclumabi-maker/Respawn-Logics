const fs = require('fs');
const path = require('path');

const componentsDir = path.join(__dirname, 'frontend/src/app/components');
const filesToUpdate = [
  'ATSDashboard.tsx',
  'JobsPage.tsx',
  'PipelineBoard.tsx',
  'InterviewsPage.tsx',
  'Approvals.tsx',
  'InsightsPage.tsx',
  'RecruitingCopilot.tsx',
  'CandidatesList.tsx',
  'CandidateProfile.tsx',
  'TalentSearch.tsx',
  'TalentPools.tsx',
  'PoolDetail.tsx',
  'Sidebar.tsx' // Frontend sidebar
];

const replacements = [
  { regex: /bg-\[#0f1422\]/g, replacement: 'bg-background' },
  { regex: /bg-\[#161922\](\/[0-9]+)?/g, replacement: 'bg-card' },
  { regex: /bg-\[#13141f\]/g, replacement: 'bg-card' },
  { regex: /text-white/g, replacement: 'text-foreground' },
  { regex: /text-gray-[456]00/g, replacement: 'text-muted-foreground' },
  { regex: /border-white\/\[0\.[0-9]+\]/g, replacement: 'border-border' },
  { regex: /bg-white\/\[0\.02\]/g, replacement: 'bg-muted' },
  { regex: /bg-white\/\[0\.04\]/g, replacement: 'bg-secondary' },
  { regex: /bg-white\/\[0\.0[68]\]/g, replacement: 'bg-accent' },
  { regex: /hover:bg-white\/\[0\.[0-9]+\]/g, replacement: 'hover:bg-accent hover:text-accent-foreground' },
  { regex: /text-\[#00e07a\]/g, replacement: 'text-primary' },
  { regex: /bg-\[#00e07a\](\/[0-9]+)?/g, replacement: 'bg-primary' },
  { regex: /hover:bg-\[#00c9b1\]/g, replacement: 'hover:opacity-90' },
  { regex: /text-black/g, replacement: 'text-primary-foreground' }, // Assuming this was for primary buttons
  { regex: /style=\{\{\s*borderColor:\s*"rgba\(255,\s*255,\s*255,\s*0\.0[0-9]+\)"\s*\}\}/g, replacement: 'style={{ borderColor: "var(--border)" }}' },
  { regex: /style=\{\{\s*borderColor:\s*'rgba\(255,\s*255,\s*255,\s*0\.0[0-9]+\)'\s*\}\}/g, replacement: "style={{ borderColor: 'var(--border)' }}" },
  { regex: /backgroundColor:\s*"rgba\(17,\s*19,\s*28,\s*0\.5\)"/g, replacement: 'backgroundColor: "var(--card)"' },
  { regex: /backgroundColor:\s*'rgba\(17,\s*19,\s*28,\s*0\.5\)'/g, replacement: "backgroundColor: 'var(--card)'" }
];

filesToUpdate.forEach(file => {
  const filePath = path.join(componentsDir, file);
  if (fs.existsSync(filePath)) {
    let content = fs.readFileSync(filePath, 'utf-8');
    
    // Apply replacements
    replacements.forEach(({ regex, replacement }) => {
      content = content.replace(regex, replacement);
    });

    // Special case for inline conditional styles like:
    // borderColor: "rgba(255,255,255,0.08)" inside style={{ ... }}
    content = content.replace(/borderColor:\s*["']rgba\(255,255,255,0\.[0-9]+\)["']/g, 'borderColor: "var(--border)"');
    
    // Remove duplicate transition-all or cursor-pointer if they appear multiple times accidentally
    
    fs.writeFileSync(filePath, content, 'utf-8');
    console.log(`Updated ${file}`);
  } else {
    console.log(`File not found: ${file}`);
  }
});

// Also update global headers in PHP
const phpIncludes = [
  path.join(__dirname, 'includes/app-header.php'),
  path.join(__dirname, 'includes/sidebar.php'),
  path.join(__dirname, 'pages/dashboard.php')
];

phpIncludes.forEach(filePath => {
  if (fs.existsSync(filePath)) {
    let content = fs.readFileSync(filePath, 'utf-8');
    // PHP files might not use tailwind everywhere, but we can replace text-white and bg-[#...]
    replacements.forEach(({ regex, replacement }) => {
      content = content.replace(regex, replacement);
    });
    fs.writeFileSync(filePath, content, 'utf-8');
    console.log(`Updated ${path.basename(filePath)}`);
  }
});

console.log("Refactoring complete.");
