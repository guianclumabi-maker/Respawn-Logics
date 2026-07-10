/**
 * dark-sweep3.cjs
 * 
 * Second pass — fixes remaining issues identified in the full audit:
 *   - More hardcoded dark hex backgrounds (070a12, 080b12, 0c1018, 0f1115, 1c1e26, 111625)
 *   - Standalone text-slate-200, text-slate-300, text-gray-200 (dark-only text colors)
 *   - text-slate-400/500, text-gray-500/600 → text-muted-foreground
 *   - text-slate-900 dark:text-white → text-foreground (redundant pattern)
 *   - bg-black/25 (table headers) → bg-muted/50
 *   - divide-white/[0.03] → divide-border
 *   - hover:text-white (standalone) → hover:text-foreground
 *   - hover:text-gray-300 → hover:text-foreground
 *   - border-white/20, border-white/[0.1], border-white/[0.07] → border-border
 *   - bg-white/[0.03], bg-white/5 (semi-transparent white panels) → bg-card/50
 *   - ThemeProvider defaultTheme="dark" → defaultTheme="system"
 *   - Recharts contentStyle inline dark colors — wrapped in dark: CSS var fallbacks
 *   - AdminUsers light-mode hardcoded classes (bg-white, bg-gray-50, divide-gray-200, hover:bg-gray-50)
 *     → bg-card, bg-muted/50, divide-border, hover:bg-accent
 *
 * Skips: onboarding, LoginPage (intentionally dark branded page), PayrollManager brand colors (#00e07a, #9b6dff)
 */

const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '../src/app');
const SKIP_DIRS = ['onboarding'];
// Files to skip entirely (intentionally dark)
const SKIP_FILES = ['LoginPage.tsx'];

const RULES = [
  // ---- More dark hex page wrappers ----
  [/\bbg-\[#070a12\]/g, 'bg-background'],
  [/\bbg-\[#080b12\]/g, 'bg-background'],
  [/\bbg-\[#0c1018\]/g, 'bg-background'],
  [/\bbg-\[#0f1115\]/g, 'bg-background'],
  [/\bbg-\[#111625\]/g, 'bg-card'],
  [/\bbg-\[#1c1e26\]/g, 'bg-input'],
  // with opacity modifier
  [/\bbg-\[#0f1115\]\/80\b/g, 'bg-background/80'],
  [/\bbg-\[#0f121d\]\/40\b/g, 'bg-card/40'],

  // ---- Inline style backgroundColor that wasn't caught before ----
  [/style=\{\{ backgroundColor: ["']#0b0f19["'] \}\}/g, ''],
  [/style=\{\{ backgroundColor: ["']#0b0f1a["'] \}\}/g, ''],
  [/style=\{\{ backgroundColor: ["']#06070a["'] \}\}/g, ''],
  // AdminUsers: light-mode hardcoded inline style → remove (let bg-background take over)
  [/style=\{\{ backgroundColor: ["']#f9fafb["'] \}\}/g, ''],

  // ---- Text colors: standalone dark-biased ----
  [/\btext-slate-200\b/g, 'text-foreground'],
  [/\btext-gray-200\b/g, 'text-foreground'],
  // Combined text-slate-900 dark:text-white → text-foreground (cleaner)
  [/\btext-slate-900 dark:text-white\b/g, 'text-foreground'],
  [/\btext-gray-900 dark:text-foreground\b/g, 'text-foreground'],

  // Muted text colors
  [/\btext-slate-400\b/g, 'text-muted-foreground'],
  [/\btext-gray-400\b/g, 'text-muted-foreground'],
  [/\btext-gray-600\b/g, 'text-muted-foreground'],

  // ---- Borders ----
  [/\bborder-white\/20\b/g, 'border-border'],
  [/\bborder-white\/\[0\.1\]\b/g, 'border-border'],
  [/\bborder-white\/\[0\.07\]\b/g, 'border-border'],
  [/\bborder-white\/\[0\.08\]\b/g, 'border-border'],

  // ---- Semi-transparent white panel backgrounds ----
  [/\bbg-white\/\[0\.03\]\b/g, 'bg-card/50'],
  [/\bbg-white\/5\b/g, 'bg-card/50'],
  [/\bbg-white\/\[0\.05\]\b/g, 'bg-card/50'],
  [/\bhover:border-white\/\[0\.1\]\b/g, 'hover:border-border'],
  [/\bhover:border-white\/10\b/g, 'hover:border-border'],
  [/\bhover:border-white\/20\b/g, 'hover:border-border'],

  // ---- Table/list structure ----
  [/\bbg-black\/25\b/g, 'bg-muted/50'],
  [/\bbg-black\/10\b/g, 'bg-muted/30'],
  [/\bdivide-white\/\[0\.03\]\b/g, 'divide-border'],
  [/\bdivide-gray-200\b/g, 'divide-border'],

  // ---- Hover text ----
  [/\bhover:text-white\b/g, 'hover:text-foreground'],
  [/\bhover:text-gray-300\b/g, 'hover:text-foreground'],
  [/\bhover:text-slate-300\b/g, 'hover:text-foreground'],

  // ---- Disabled states ----
  [/\bdisabled:bg-slate-800\b/g, 'disabled:bg-muted'],
  [/\bdisabled:text-slate-600\b/g, 'disabled:text-muted-foreground'],

  // ---- ThemeProvider defaultTheme="dark" → "system" ----
  [/defaultTheme="dark"/g, 'defaultTheme="system"'],

  // ---- AdminUsers light-hardcoded patterns ----
  [/\bbg-white\b(?! \/)/g, 'bg-card'],           // bg-white but not bg-white/xx
  [/\bbg-gray-50\b/g, 'bg-muted/50'],
  [/\bhover:bg-gray-50\b/g, 'hover:bg-accent'],
  [/\bbg-blue-100\b/g, 'bg-primary/10'],
  [/\btext-gray-700\b/g, 'text-foreground'],
  [/\btext-gray-900\b(?! dark:)/g, 'text-foreground'],

  // ---- Recharts tooltip inline styles - wrap dark bg with CSS var fallback ----
  // contentStyle={{ backgroundColor: 'rgba(15,23,42,0.9)', color: '#fff' }}
  // → contentStyle={{ backgroundColor: 'var(--card)', color: 'var(--foreground)' }}
  [/backgroundColor: ['"]rgba\(15, ?23, ?42, ?0\.9\)['"]/g, "backgroundColor: 'var(--card)'"],
  [/backgroundColor: ['"]rgba\(15,23,42,0\.9\)['"]/g, "backgroundColor: 'var(--card)'"],
  [/color: ['"]#fff['"](,?\s*borderColor)/g, "color: 'var(--foreground)'$1"],
  [/borderColor: ['"]rgba\(255, ?255, ?255, ?0\.1\)['"]/g, "borderColor: 'var(--border)'"],
  
  // Chart grid stroke
  [/stroke: ?['"]rgba\(255, ?255, ?255, ?0\.05\)['"]/g, "stroke: 'var(--border)'"],
  [/stroke: ?['"]rgba\(255,255,255,0\.05\)['"]/g, "stroke: 'var(--border)'"],

  // ---- Avatar dark bg ----
  [/\bbg-\[#222\]\b/g, 'bg-muted'],
  [/\bbg-slate-700\b/g, 'bg-muted'],
];

function walk(dir) {
  const results = [];
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, e.name);
    if (e.isDirectory()) {
      if (SKIP_DIRS.includes(e.name)) continue;
      results.push(...walk(full));
    } else if ((e.name.endsWith('.tsx') || e.name.endsWith('.css')) && !SKIP_FILES.includes(e.name)) {
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
    const newContent = content.replace(pattern, (match, ...args) => {
      fileCount++;
      // Handle capture groups in replacement
      let r = replacement;
      args.forEach((a, i) => { if (typeof a === 'string') r = r.replace(`$${i+1}`, a); });
      return r;
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

console.log(`\n✅ Dark sweep 3 complete. ${totalReplacements} replacements across ${totalFixed} files.`);
