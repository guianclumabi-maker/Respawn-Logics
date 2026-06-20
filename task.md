# Security Overhaul Task List

## Phase 1 — DB Migration
- [x] Create migrate_security.php (login_rate_limits, support_tickets, totp_secrets)
- [ ] Add migrate_security.php to migrate_all.php

## Phase 2 — Block Debug Files (Zero Risk)
- [ ] Update .htaccess with deny rules for debug/test/bak/txt files
- [ ] Update .railwayignore with file exclusions

## Phase 3 — CSRF Helpers (Additive Only)
- [x] Create helpers/csrf.php (csrf_token, csrf_field, csrf_verify)
- [ ] Load csrf.php from bootstrap/app.php
- [ ] Add csrf_field() to login form
- [ ] Add csrf_field() to ticket form

## Phase 4 — DB-Based Rate Limiting (login.php)
- [ ] Replace session-based rate limiting in login.php with DB-backed IP rate limiting

## Phase 5 — Real Ticket Submission
- [ ] Create submit_ticket.php backend endpoint
- [ ] Update ticket form frontend fetch() call in login.php

## Phase 6 — TOTP 2FA (Opt-In)
- [ ] Create services/TotpService.php (self-contained, no Composer)
- [ ] Create pages/setup_2fa.php (QR enroll page)
- [ ] Update login.php to check totp_enabled and prompt for code

## Phase 7 — Deploy
- [ ] Git commit + push all changes
- [ ] Trigger system_migrate.php on Railway
