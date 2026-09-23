# Respawn Logics — Operations Runbook

The operational safety net for running the platform in production. If you do nothing else here, do §1 (key backup) and §2 (DB backup) **before** onboarding a real client — both are unrecoverable if skipped.

## 1. Encryption keys — BACK THESE UP OR LOSE DATA FOREVER

Two secrets encrypt all PII (government IDs, 201 documents, payslip PDFs):
- `APP_ENCRYPTION_KEY` — master AES-256 key (base64 of 32 bytes)
- the blind-index key (TIN equality search)

**If the key is lost, every encrypted record is permanently unreadable — there is no recovery.**

Procedure:
1. Generate (once): `php -r "echo base64_encode(random_bytes(32)),PHP_EOL;"`
2. Set in Railway → service → Variables. Never commit to git.
3. **Store a copy in a separate password manager / vault** (1Password, Bitwarden) under "Respawn Logics production keys." This is your only recovery path if Railway variables are wiped.
4. Also set `RESEND_API_KEY` and `MAIL_FROM` (password-reset + notification emails silently fail without them) and rotate the MySQL password if it was ever in code/history.

Key rotation: decrypt-with-old / re-encrypt-with-new across affected rows; the `enc:v1:` version tag lets a v2 coexist during migration. Plan downtime; test on a copy first.

## 2. Database backup & restore

**Backup (before every deploy that touches migrations, and daily):**
Railway → MySQL plugin → Backups (enable automated daily). Manual snapshot:
```
mysqldump -h <host> -P <port> -u root -p railway > backup_$(date +%Y%m%d_%H%M).sql
```
Store off-Railway (e.g. encrypted cloud storage). **These dumps contain PII — keep them encrypted and access-controlled; never commit them to git.**

**Restore drill (do this ONCE before beta so you know it works):**
1. Spin up a scratch MySQL.
2. `mysql -u root -p scratch_db < backup_YYYYMMDD.sql`
3. Point a local app instance at it, log in, confirm data present.
A backup you have never restored is not a backup.

## 3. Deploy & rollback

Deploy: push to the deploy branch → Railway auto-builds (nixpacks: npm install, composer, `npm run build`) → start command runs `migrate.php` (schema migrations, idempotent) then php-fpm + nginx.

Pre-deploy checklist:
- CI green (typecheck + PHPUnit).
- DB backup taken (§2).
- New migrations are idempotent and registered in `schema_migrations.php`.

Rollback: Railway → Deployments → redeploy the previous successful build. **Migrations do not auto-rollback** — if a deploy added a destructive migration, restore from the pre-deploy backup. (Current migrations are additive/idempotent, so this is rare.)

## 4. Monitoring — what to watch

- **Railway logs**: `error_log` output surfaces here. Grep for `[Auth]`, `[Payroll]`, `[CoreHR]`, `Mailer`, `Migration failed`.
- After each deploy: hit `/test_health.php`… **(removed — use `/api.php?action=current_user` while logged in, or add a lightweight health route)** and load the login page.
- First payroll run per client: watch logs live; a fail-loud error (missing config, no approved timesheets) returns a clear message rather than wrong pay.
- Weekly: skim `audit_logs` for anomalies (unexpected logins, bulk changes).

## 5. Incident response (one-page)

1. **Contain** — if data exposure suspected, take the app offline (Railway → pause) or rotate the leaked secret immediately.
2. **Assess** — use `audit_logs` + Railway logs to scope: what, whose data, when, how many records.
3. **Notify** — under RA 10173, the NPC and affected data subjects must be notified within **72 hours** of discovering a breach involving sensitive personal information. Use the templates in `DPA_RA10173_STARTER_KIT.md`.
4. **Remediate & record** — patch, document timeline, keep for the NPC file.
Name a responsible person (DPO) now, before you need them.

## 6. Standing config checklist (per environment)

| Variable | Purpose | If missing |
|---|---|---|
| APP_ENCRYPTION_KEY | PII encryption | Uploads stored UNENCRYPTED (logged warning) |
| (blind index key) | TIN search | TIN equality lookups fail |
| RESEND_API_KEY / MAIL_FROM | Email | Reset/notification emails silently skipped |
| DB_HOST/PORT/NAME/USER/PASS | Database | App down |
| APP_ENV | prod vs testing | Wrong DB targeted |

## 7. Health-endpoint

The old `/test_health.php` was removed in the security purge. Instead, use the authenticated health route `/api/index.php?route=health&action=check` which returns `{status: 'ok', time: ...}` for uptime monitoring.
