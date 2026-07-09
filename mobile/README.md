# Respawn Logics — iOS Mobile App (Basic Access)

A stripped-down employee self-service app for the Respawn Logics HRIS, built with
**Expo / React Native (SDK 54)**. It talks to the same PHP API the web app uses
(session cookie + CSRF), so no backend changes are required.

## What's included (basic access)

| Tab | Features |
| --- | --- |
| Home | Greeting, clock in/out, weekly hours, pending leaves, personal task list |
| Time | Clock in/out, recent timesheet (last 30 punches) |
| Leaves | Balances, submit a leave request, request history |
| Pay | Payslip list, tap to download/share the PDF |
| More | Announcements, notifications, HR cases, manager approvals, profile, sign out |

Not included by design (use the web app): 2FA login, password changes, payroll
administration, ATS/recruiting, admin settings.

## 1. One-time setup on your PC

You need Node.js 20+ on Windows (https://nodejs.org). Then:

```bash
cd C:\xampp\htdocs\respawn-logics\mobile
npm install
```

If Expo warns about package versions on first start, run:

```bash
npx expo install --fix
```

## 2. Test on your iPhone (no Mac needed)

1. Install **Expo Go** from the App Store on your iPhone.
2. Make sure your iPhone and PC are on the **same Wi-Fi network**.
3. Start XAMPP (Apache + MySQL) as usual.
4. In the `mobile` folder run `npm start` (or double-click `start.bat`).
5. Scan the QR code shown in the terminal with the iPhone camera — it opens in Expo Go.
6. In the app's first screen, enter your server address using your PC's **LAN IP**,
   not localhost — e.g. `http://192.168.1.100/respawn-logics`.
   Find your IP by running `ipconfig` in a command prompt (IPv4 Address).
7. Log in with your normal Respawn Logics credentials.

### Troubleshooting

- **"Cannot reach server"** — check that Windows Firewall allows Apache
  (Control Panel → Windows Defender Firewall → Allow an app), and that you used
  the LAN IP. Test by opening `http://<your-ip>/respawn-logics/login.php` in the
  phone's Safari first.
- **QR won't connect** — some Wi-Fi networks block device-to-device traffic
  (AP isolation). Try `npm start -- --tunnel` instead (requires an Expo account).
- **Version warnings** — run `npx expo install --fix`.
- **Expo Go says the project isn't compatible** — this project targets SDK 54,
  which is what the App Store's Expo Go supports (as of mid-2026). If Expo Go has
  since moved on, run `npx expo install expo@latest` then `npx expo install --fix`.

## 3. Building a real installable iOS app (later)

When you want an actual `.ipa` / App Store build, no Mac is needed either — use
Expo's cloud build service:

```bash
npm install -g eas-cli
eas login          # free Expo account
eas build --platform ios
```

(Requires an Apple Developer account for device installs / TestFlight.)

## Notes for developers

- API client: `src/api.js` — wraps `/api/index.php?route=…&action=…`, handles the
  session cookie automatically and retries once on CSRF token mismatch.
- Auth/session state: `src/AuthContext.js` (restores the session on app launch via
  `GET /api.php?action=current_user`).
- Manager-only UI (Approvals) is gated by the `leave.manage` / `attendance.manage`
  permissions returned at login.
- The server URL is stored on-device with AsyncStorage; switch servers from the
  More tab.
