/**
 * dark-sweep2.cjs
 * 
 * Fixes the light/dark mode inconsistency by replacing hardcoded dark-only hex classes
 * with proper semantic token alternatives that work in BOTH light and dark mode.
 *
 * STRATEGY:
 *   Every hardcoded dark hex that appears WITHOUT a light-mode counterpart
 *   needs to be replaced by:
 *     - bg-background (for root-level page wrappers using #06070a or #0b0f1a)
 *     - bg-card (for panel/table backgrounds using #0d0f19, #121625, #141929, etc.)
 *     - bg-input (for input fields using #1a1d27, #121827 etc.)
 *
 *   The semantic tokens automatically adapt to light/dark via CSS variables.
 *   They are already defined in the project's index.css / globals.css.
 *
 *   Also fixes:
 *     - style={{ backgroundColor: "#0b0f19" }} inline styles
 *     - divide-white/5 → divide-border
 *     - text-gray-300 standalone → text-foreground (in table cells/content)
 */

const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '../src/app');

// Skip onboarding (locked)
const SKIP_DIRS = ['onboarding'];

// Simple string replacements (regex → replacement)
const RULES = [
  // ---- Page/section background roots ----
  // These are full-page wrappers — replace with bg-background
  [/\bbg-\[#06070a\]/g, 'bg-background'],
  [/\bbg-\[#0b0f1a\]/g, 'bg-background'],
  [/\bbg-\[#0b0f19\]/g, 'bg-background'],

  // ---- Inline styles ----
  [/style=\{\{ backgroundColor: ["']#0b0f1[9a]["'] \}\}/g, ''],
  [/style=\{\{ backgroundColor: ["']#06070a["'] \}\}/g, ''],

  // ---- Card/panel backgrounds ----
  [/\bbg-\[#0d0f19\]/g, 'bg-card'],
  [/\bbg-\[#121625\]/g, 'bg-card'],
  [/\bbg-\[#141929\]/g, 'bg-card'],
  [/\bbg-\[#161922\]/g, 'bg-card'],
  [/\bbg-\[#0f121d\]/g, 'bg-card'],

  // ---- Input/control backgrounds ----
  [/\bbg-\[#1a1d27\]/g, 'bg-input'],
  [/\bbg-\[#121827\]/g, 'bg-input'],

  // ---- Borders ----
  [/\bborder-white\/\[0\.04\]/g, 'border-border'],
  [/\bborder-white\/\[0\.06\]/g, 'border-border'],
  [/\bdivide-white\/5\b/g, 'divide-border'],
  [/\bdivide-white\/\[0\.05\]\b/g, 'divide-border'],

  // ---- Text colors ----
  // Standalone text-gray-300 in table cells (not used as light/dark pair)
  [/\btext-gray-300\b/g, 'text-foreground'],
  [/\btext-gray-500\b/g, 'text-muted-foreground'],

  // ---- Dark:hover that is now redundant because bg was replaced ----
  // dark:hover:bg-[#141929] → hover:bg-accent (since bg-card is now the base)
  [/\bdark:hover:bg-\[#141929\]/g, 'hover:bg-accent'],
  [/\bhover:text-gray-300\b/g, 'hover:text-foreground'],
];

function walk(dir) {
  const results = [];
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, e.name);
    if (e.isDirectory()) {
      if (SKIP_DIRS.includes(e.name)) continue;
      results.push(...walk(full));
    } else if (e.name.endsWith('.tsx') || e.name.endsWith('.css')) {
      results.push(full);
    }
  }
  return results;
}

const files = walk(ROOT);
let totalFixed = 0;
let totalReplacements = 0;

for (const filePath of files) {
  let content = fs.readFileSync(filePath, 'utf8');
  const original = content;
  let fileCount = 0;

  for (const [pattern, replacement] of RULES) {
    const newContent = content.replace(pattern, () => {
      fileCount++;
      return replacement;
    });
    content = newContent;
  }

  if (content !== original) {
    fs.writeFileSync(filePath, content, 'utf8');
    console.log(`[FIXED +${fileCount}]  ${path.relative(ROOT, filePath)}`);
    totalFixed++;
    totalReplacements += fileCount;
  }
}

console.log(`\n✅ Dark sweep 2 complete. ${totalReplacements} replacements across ${totalFixed} files.`);
