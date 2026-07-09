# Antigravity prompt — commit & push Respawn-Logics to trigger the Railway deploy

Copy everything below the line into Antigravity when you're ready to ship.

---

# Task: Commit and push Respawn-Logics to trigger the Railway deployment

## Context
- Repo: C:\xampp\htdocs\respawn-logics (remote: https://github.com/guianclumabi-maker/Respawn-Logics.git)
- This is a PHP HRIS app deployed on Railway via the GitHub integration (nixpacks.toml + railway.json are already configured). Pushing to the branch Railway watches triggers a deploy.
- Current branch is `fix/elr-missing-case-tables` with a large number of uncommitted changes, plus a new `mobile/` folder (an Expo React Native app for iOS).

## What to do
1. Run `git status` and `git diff --stat` and give me a short summary of what changed before committing anything.
2. Stage and commit in logical groups (backend changes, docs, and the new `mobile/` app as separate commits) with clear conventional commit messages.
3. NEVER stage: `.env`, `*.log`, `*.sql` backups, `phpunit_*.txt`, `test_output.txt`, `debug_*.php`, `composer.phar`, `phpunit.phar`, `node_modules/`, `mobile/node_modules/`, `mobile/_source_archive.zip`, `fpdf.zip`, or anything in `scratch/`. If `.gitignore` doesn't already cover these, update it first in its own commit.
4. Push the branch: `git push -u origin fix/elr-missing-case-tables`.
5. Check which branch Railway deploys from (likely `main`). If it's `main`, open a pull request from `fix/elr-missing-case-tables` into `main` with a summary of the changes — do NOT merge it yourself; I'll review and merge.
6. After I merge, remind me to verify the deploy: check the Railway dashboard build logs, then hit `https://<railway-domain>/api/index.php?route=health&action=check` and the login page to confirm the app is up.

## Rules
- Never force-push.
- Never commit secrets or credentials; if you find any hardcoded in tracked files, stop and tell me instead of pushing.
- If a push is rejected, fetch and rebase onto the remote branch, resolve conflicts carefully, and show me the conflict resolutions before continuing.
- Do not run database migrations against production; Railway runs them via the deploy process.
