# SSC Event Attendance

A separate event-based attendance module that follows the existing `system` Tailwind/violet admin style without modifying the `system` folder.

## Routes

- `/SSCAttendance/` - active event status and scanner entry point
- `/SSCAttendance/scan.php` - QR/barcode keyboard scanner
- `/SSCAttendance/admin/` - SSC dashboard
- `admin/events.php` - create, activate, register, and close events
- `admin/attendance.php` - filter attendance by event, status, or student
- `admin/payments.php` - record partial/full payments and view history
- `admin/clearance.php` - sign only when outstanding balance is zero

## Supabase setup

The application connects server-side to Supabase PostgreSQL using PDO. It needs a PHP host that supports `pdo_pgsql`; Supabase hosts the database, not the PHP pages.

1. Create a fresh Supabase project and run `supabase_schema.sql` in its SQL Editor. The script creates the app tables; it does not alter existing tables to migrate their structure.
2. Configure these environment variables on the PHP web host using the project's PostgreSQL connection details:
   - `SUPABASE_DB_HOST`
   - `SUPABASE_DB_NAME` (normally `postgres`)
   - `SUPABASE_DB_USER`
   - `SUPABASE_DB_PASSWORD`
   - `SUPABASE_DB_PORT` (normally `5432`)
   - `SUPABASE_DB_SSLMODE` (normally `require`)
   
   For XAMPP networks that use IPv4, choose **Session pooler** in Supabase's **Connect** dialog and copy its host and user values. The pooler username includes the project reference (for example, `postgres.<project-ref>`). Direct database connections may require IPv6; use the connection method that matches the PHP host's network.
3. Enable PHP's `pdo_pgsql` extension and deploy the PHP project to a PHP-capable web host. In XAMPP, enable `extension=pdo_pgsql` in the active `php.ini`, then restart Apache.
4. Open `/SSCAttendance/login.php`, then create an event, enable `Register all students`, and activate it.

Keep the database password only in server-side environment settings. Do not put it in browser JavaScript, commit it, or use a Supabase `anon`/`service_role` API key as the PostgreSQL password.

### Password reset email

The login page's password reset sends a six-digit, single-use code to the email address stored on an Admin or SSC user account. Configure these server-side environment variables to enable delivery through Gmail SMTP:

- `GMAIL_SMTP_USER` - the Gmail account used to send reset emails
- `GMAIL_SMTP_APP_PASSWORD` - an App Password for that Gmail account (enable 2-Step Verification first)
- `GMAIL_SMTP_HOST` - optional; defaults to `smtp.gmail.com`
- `GMAIL_SMTP_PORT` - optional; defaults to `587` (STARTTLS)
- `MAIL_FROM_NAME` - optional; defaults to `SSC Attendance`

Set them in Vercel Project Settings > Environment Variables for Production (and Preview if needed), then redeploy. For XAMPP, set them in the Apache/PHP environment and restart Apache. Never use your normal Gmail password, expose the App Password in client code, commit it, or send it in chat. Reset email is unavailable until SMTP settings are configured. Codes expire after 10 minutes and are limited to five verification attempts per request.

After a successful send, the reset page displays a six-digit code entry form. Invalid email addresses, emails without a matching Admin/SSC account, and delivery failures display an error instead.

### Vercel deployment

Vercel does not provide an official PHP runtime. This project uses the community-maintained `vercel-php` runtime; verify that runtime is available to your Vercel account before deploying. The `vercel.json` catch-all routes requests through `api/index.php`, which dispatches PHP pages and serves the app's static asset directories. On Vercel, PHP sessions are stored in the Supabase `app_sessions` table rather than a temporary function filesystem.

The PHP function is pinned to Vercel's Singapore region (`sin1`) to keep dynamic requests close to users in the Philippines and Southeast Asia. For lowest database latency, set the function region to the same or nearest region as the Supabase project.

If the Supabase `ssc_users` table is missing its `created_at` column, run [`supabase_restore_ssc_users_created_at.sql`](./supabase_restore_ssc_users_created_at.sql) in the Supabase SQL Editor. The migration is safe to rerun; existing rows receive the migration time because their original creation times cannot be reconstructed if the column was deleted.

Production URL: https://ssc-attendance-opal.vercel.app
GitHub repository: https://github.com/myboss2022/ssc-attendance. Pushes to `main` automatically deploy to Vercel Production; other branches create Preview deployments.

Before deploying:

1. Run the `app_sessions` table and index statements from `supabase_schema.sql` in the existing Supabase project's SQL Editor.
2. Link this project to a Vercel project, then set `SUPABASE_DB_HOST`, `SUPABASE_DB_NAME`, `SUPABASE_DB_USER`, `SUPABASE_DB_PASSWORD`, `SUPABASE_DB_PORT`, and `SUPABASE_DB_SSLMODE` for Production and any preview environments you use. Use the Supabase session-pooler host/user and keep the password as a server-side secret.
3. Deploy with the Vercel CLI or connect a Git repository. Test login, logout, scanning, redirects, and nested static assets on the deployed URL before sharing it.

### Moving existing MySQL records

`schema.sql` is the legacy XAMPP/MySQL schema. It is not the schema to import into Supabase. First create the PostgreSQL tables with `supabase_schema.sql`, then export the existing MySQL tables as CSV and import them into their matching Supabase tables. Import parent tables before dependent tables: `students`, `events`, `ssc_users`, and `admin_users`; then `event_students`, `event_attendance`, `fines`, `clearance`, `event_requests`, `payments`, `activity_logs`, `notifications`, `ssc_settings`, `attendance_corrections`, and `school_announcements`. Preserve each table's primary-key IDs so the foreign keys continue to match. If the old database uses `scc_users` or `scc_settings`, import those rows into `ssc_users` or `ssc_settings`; map a legacy `scc_user_id` column to `ssc_user_id` for `event_requests`. If both `announcements` and `school_announcements` exist, combine them into `school_announcements`; convert legacy priority labels `LOW`, `NORMAL`, `HIGH`, and `URGENT` to numeric priorities `1`, `5`, `8`, and `10`, and assign a new ID where the two tables' IDs collide. Map legacy activity-log actor type `SCC` (or blank when the entity is `ssc_user`) to `SSC`. Do not overwrite a Supabase project that already contains records.

After importing rows with explicit IDs, run `supabase_reset_sequences.sql` in the Supabase SQL Editor before the app creates records. Verify student, event, attendance, user, and payment counts in Supabase before switching the live site. Export files contain student information and password hashes; store them securely and delete temporary exports after the migration.

The PHP application uses the direct table names `events`, `event_students`, `event_attendance`, `fines`, `payments`, and `clearance`; student records remain in the same PostgreSQL database so attendance foreign keys stay connected. Scan decisions are validated in `api/scan.php`, including active event, registration, scan windows, one record per event, scan-in-before-scan-out, and duplicate scan-out rules.
