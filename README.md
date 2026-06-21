# Respawn-Logics

Respawn-Logics is a comprehensive HR, Payroll, ATS, and Employee Services Management platform.

## File Storage & Security (CRITICAL)

Respawn-Logics employs a highly secure file storage mechanism designed to prevent any direct web access to sensitive employee data, payslips, receipts, resumes, and tickets. 

**All uploaded files must physically reside OUTSIDE the web server's document root.**

### Configuration Requirements
To enforce this security, the application uses a "fail-loud" storage guard mechanism. **If the storage paths are not configured correctly, the application will actively REJECT all file uploads with a 500 error.**

You MUST configure the following environment variables on your production server (e.g., Railway):
- `FILE_STORAGE_PATH`: The absolute path to a persistent storage volume (e.g., `/data/respawn_storage`).
- `RESUME_STORAGE_PATH`: The absolute path for ATS resumes (e.g., `/data/respawn_storage`).
- `APP_ENV`: Must be set to `production` (or anything other than `local`).

### Local Development (`APP_ENV=local`)
If you are developing locally on XAMPP/WAMP, you must set the environment variable:
`APP_ENV=local`

Only when `APP_ENV` is strictly `local` will the application allow files to be saved to the fallback `storage/` directory inside the repository tree. If you forget to set this, your local uploads will be rejected by the security guard.

### Migration of Legacy Uploads
If you are deploying this version over an older installation, you must migrate the legacy files from the old `uploads/` directory into the secure storage volume. After deploying and setting the environment variables above, run:
```bash
php backend/migrations/migrate_legacy_uploads.php
```
This script is idempotent and will safely move files and update database paths to the new secure schema.

## Running the Frontend

```bash
cd frontend
npm install
npm run dev
```