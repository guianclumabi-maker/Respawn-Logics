#!/usr/bin/env node
/**
 * Theme sweep (Phase 2.5) — makes every hard-coded `text-white` theme-aware.
 *
 * Problem: standalone `text-white` is invisible on light backgrounds in light mode.
 * Fix: convert a STANDALONE `text-white` (preceded by whitespace/quote/backtick — i.e. NOT
 *      `dark:text-white`, `hover:text-white`, `group-hover:text-white`, etc.) into a proper
 *      pair `text-slate-900 dark:text-white`. This never makes text invisible in either mode:
 *        - light mode -> slate-900 (dark text, readable on light cards/buttons)
 *        - dark  mode -> white (identical to before)
 *      Opacity suffixes (`text-white/80`) are preserved on both halves.
 *
 * Safe + idempotent: files already converted to `text-foreground` have no `text-white` left,
 * so they're untouched. Modifier-prefixed variants are intentionally left alone.
 *
 * Skips: node_modules, dist, and the LOCKED onboarding sub-app.
 *
 * Run from frontend/:  node scripts/theme-sweep.cjs
 * Then verify:         npm run build   (must compile clean)
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..', 'src', 'app');
const SKIP_DIRS = new Set(['node_modules', 'dist']);
const SKIP_PATH_FRAGMENTS = ['/onboarding/', '\\onboarding\\']; // locked — do not touch

// Standalone text-white (not dark:/hover:/etc.), with optional /opacity.
const RE = /(?<=[\s"'`])text-white(\/\d+)?/g;

let filesChanged = 0;
let replacements = 0;

function walk(dir) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      if (SKIP_DIRS.has(entry.name)) continue;
      walk(full);
    } else if (/\.(tsx|ts|jsx|js)$/.test(entry.name)) {
      if (SKIP_PATH_FRAGMENTS.some((f) => full.includes(f))) continue;
      const src = fs.readFileSync(full, 'utf8');
      let count = 0;
      const out = src.replace(RE, (_m, op = '') => {
        count++;
        return `text-slate-900${op} dark:text-white${op}`;
      });
      if (count > 0) {
        fs.writeFileSync(full, out, 'utf8');
        filesChanged++;
        replacements += count;
        console.log(`  ${path.relative(ROOT, full)} — ${count}`);
      }
    }
  }
}

console.log('Theme sweep: converting standalone `text-white` -> light/dark pair...');
walk(ROOT);
console.log(`\nDone. ${replacements} replacements across ${filesChanged} files.`);
