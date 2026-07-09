# Database Backup Runbook (Phase 0 · item 0.4)

The production database lives on **Railway** and holds real tenant data. Losing it = losing your pilot.
This runbook gives you a working backup today (free) and the durable option (paid).

---

## Option A — Railway automated backups (recommended, durable)
Railway offers scheduled DB backups on the **Pro plan**. This is the real answer:
1. Railway dashboard → your MySQL service → **Backups** tab.
2. Enable scheduled backups (daily) + set a retention window.
3. That's it — no scripts, no cron, restore is a click.

**Cost decision is yours.** Until then, use Option B.

---

## Option B — Manual `mysqldump` (free interim, run it yourself)
Works from any machine with the MySQL client (your XAMPP already has `mysqldump` at
`C:\xampp\mysql\bin\mysqldump.exe`).

### One-time setup
1. Railway dashboard → MySQL service → **Variables / Connect** → copy the public connection info:
   `MYSQLHOST`, `MYSQLPORT`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLDATABASE`.
2. Open `backup_db.bat` (in the repo root) and paste those five values at the top.

### Run a backup
Double-click `backup_db.bat` (or run it in a terminal). It writes a timestamped file to
`backups/respawn_YYYY-MM-DD_HHMM.sql`.

### Schedule it (Windows Task Scheduler)
1. Task Scheduler → **Create Basic Task** → name "Respawn DB Backup".
2. Trigger: **Daily**, pick a low-traffic hour.
3. Action: **Start a program** → browse to `backup_db.bat`.
4. Finish. Verify it produced a file the next day.

### Off-machine copy (important)
A backup on the same machine isn't a real backup. Point the `backups/` folder at a synced
location (OneDrive/Google Drive/Dropbox) or add a step to upload it, so a dead laptop doesn't
take the backups with it.

---

## Restore procedure (test this BEFORE you need it)
```
mysql -h <host> -P <port> -u <user> -p <database> < backups\respawn_YYYY-MM-DD_HHMM.sql
```
Do a **test restore into a scratch database** once, so you know the file is valid and the
command works. An untested backup is not a backup.

---

## Checklist to mark 0.4 done
- [ ] Either Railway Pro backups enabled, OR `backup_db.bat` scheduled daily
- [ ] At least one backup file verified to exist
- [ ] Backups copied off the machine (synced folder / upload)
- [ ] One test restore performed successfully
