/**
 * dark-sweep.cjs
 * Replaces hardcoded dark hex backgrounds / text colors in TSX files
 * with semantic Tailwind tokens so the app looks correct in both light and dark mode.
 *
 * Strategy:
 *   bg-[#0b0f1a]   → bg-background        (root background)
 *   bg-[#06070a]   → bg-background        (root background)
 *   bg-[#0d0f19]   → bg-card              (card/panel)
 *   bg-[#121625]   → bg-card              (table header / panel)
 *   bg-[#121827]   → bg-card
 *   bg-[#1a1d27]   → bg-card
 *   bg-[#0f121d]   → bg-card
 *   bg-[#161922]   → bg-card
 *   bg-[#141929]   → bg-card  (dark: hover)
 *
 *   When found as a standalone bg-[#xxx] (no light-mode prefix) add dark:bg-XXX instead if there is a preceding light-mode class.
 *   When found WITHOUT any light-mode companion, replace wholesale.
 *
 *   border-white/[0.04]  → border-border
 *   text-gray-300 (standalone, not dark:)  → text-muted-foreground
 *
 *   hover:bg-[#xxxxx] where xxx is a dark hex → prefix with dark:
 */

const fs = require('fs');
const path = require('path');
const glob = require('glob'); // must be available; older API

const ROOT = path.join(__dirname, '../src/app');

// Map of hex backgrounds to semantic tokens (without dark: prefix — we'll handle that per case)
const BG_MAP = {
  '#06070a': 'bg-background',
  '#0b0f1a': 'bg-background',
  '#0d0f19': 'bg-card',
  '#121625': 'bg-card',
  '#121827': 'bg-card',
  '#1a1d27': 'bg-card',
  '#0f121d': 'bg-card',
  '#161922': 'bg-card',
  '#141929': 'bg-card',
};

const REPLACEMENTS = [
  // Full-coverage hardcoded backgrounds: replace the bg-[#xxx] class entirely
  // For patterns that already have a light counterpart like bg-[#f4f6f8] dark:bg-[#xxx], leave them (they're already correct)
  
  // Standalone dark-only backgrounds — swap to dark: prefixed version
  // bg-[#06070a] alone → dark:bg-background (keep light mode as bg-background by default)
  { from: /\bbg-\[#06070a\]/g, to: 'bg-background dark:bg-[#06070a]', skipIfPrecededByBg: true },
  { from: /\bbg-\[#0b0f1a\]/g, to: 'bg-background dark:bg-[#0b0f1a]', skipIfPrecededByBg: true },
  { from: /\bbg-\[#0d0f19\]/g, to: 'bg-card dark:bg-[#0d0f19]', skipIfPrecededByBg: true },
  { from: /\bbg-\[#121625\]/g, to: 'bg-card dark:bg-[#121625]', skipIfPrecededByBg: true },
  { from: /\bbg-\[#121827\]/g, to: 'bg-card dark:bg-[#121827]', skipIfPrecededByBg: true },
  { from: /\bbg-\[#1a1d27\]/g, to: 'bg-card dark:bg-[#1a1d27]', skipIfPrecededByBg: true },
  { from: /\bbg-\[#0f121d\]/g, to: 'bg-card dark:bg-[#0f121d]', skipIfPrecededByBg: true },
  { from: /\bbg-\[#161922\]/g, to: 'bg-card dark:bg-[#161922]', skipIfPrecededByBg: true },

  // border-white/[0.04] → border-border
  { from: /\bborder-white\/\[0\.04\]/g, to: 'border-border' },

  // Permissions.tsx wraps the whole page in bg-[#0d0f19] text-foreground — fix the outer wrapper
  // Note: The text-foreground part is fine, just the background
  
  // hover: dark hex backgrounds — wrap in dark: only
  { from: /\bhover:bg-\[#(06070a|0b0f1a|0d0f19|121625|121827|1a1d27|0f121d|161922|141929)\]/g, to: 'dark:hover:bg-[$1]' },
];

function processFile(filePath) {
  let content = fs.readFileSync(filePath, 'utf8');
  const original = content;
  let changed = false;
  let count = 0;

  for (const rule of REPLACEMENTS) {
    if (rule.skipIfPrecededByBg) {
      // Only replace if there's no preceding bg- light variant (e.g. bg-[#f4f6f8] dark:bg-[#xxx] is already correct)
      const darkPreceded = new RegExp(`bg-\\[#[a-fA-F0-9]{6}\\]\\s+${rule.from.source.replace(/^\\b/, '').replace(/\\b$/, '')}`, '');
      // We do a simple check: if the token appears after another bg- class that starts with 'f' (light color) skip it
      // Actually: the safe approach is only replace bare bg-[#dark] that are NOT preceded by dark:
      const safeSub = (str) => {
        return str.replace(rule.from, (match, offset) => {
          // Look back in the string for `dark:bg-` right before this; if found, already handled
          const before = str.substring(Math.max(0, offset - 20), offset);
          if (before.includes('dark:')) return match; // already a dark: variant 
          // Also skip if this is INSIDE a dark: class already: dark:bg-[#xxx]
          const charBefore = str.substring(Math.max(0, offset - 5), offset);
          if (charBefore.endsWith('dark:')) return match;
          count++;
          return rule.to;
        });
      };
      const newContent = safeSub(content);
      if (newContent !== content) { content = newContent; changed = true; }
    } else {
      const newContent = content.replace(rule.from, (match, ...args) => {
        count++;
        // For hover: rule, reconstruct with the captured group
        if (rule.to.includes('$1')) {
          const hex = args[0];
          return rule.to.replace('$1', hex);
        }
        return rule.to;
      });
      if (newContent !== content) { content = newContent; changed = true; }
    }
  }

  // Special case: standalone bg-[#141929] that appears only in dark:hover:bg-[#141929] is already ok
  // But if it appears in a non-prefixed position we already caught it above.

  if (changed) {
    fs.writeFileSync(filePath, content, 'utf8');
    console.log(`[FIXED] ${path.relative(ROOT, filePath)} — ${count} replacements`);
  }
  return changed;
}

// Use node's built-in glob or simple recursive walk
function walk(dir, ext) {
  const results = [];
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  for (const e of entries) {
    const full = path.join(dir, e.name);
    if (e.isDirectory()) {
      // Skip onboarding as per project rules
      if (e.name === 'onboarding') continue;
      results.push(...walk(full, ext));
    } else if (e.name.endsWith(ext)) {
      results.push(full);
    }
  }
  return results;
}

const files = walk(ROOT, '.tsx');
let totalFixed = 0;
for (const f of files) {
  if (processFile(f)) totalFixed++;
}

console.log(`\nDark sweep complete. Fixed ${totalFixed} files.`);
