# LuxeStay Hotel Booking System

## Important security notice

This is a student/demo project. The SQL seed currently includes demo administrator and receptionist accounts with the shared sample password `admin123`. Do not use these accounts on a public server or shared network. Change both passwords immediately; do not rely on the fact that passwords are hashed in the database. The login page no longer displays the demo credentials.

The SMTP App Password that was present in the original uploaded archive should be treated as compromised. Revoke it in Google Account security settings and use a new App Password only in `config/mail.local.php` or server environment variables. This hardened archive removes Git history so the old credential is not carried forward in `.git` objects, but removing it from this copy does not revoke it.

## XAMPP setup
1. Extract this folder to `C:\xampp\htdocs\hotel_booking_system`.
2. Start Apache and MySQL in XAMPP.
3. Open `http://localhost/phpmyadmin` and import `database.sql`.
4. Visit `http://localhost/hotel_booking_system/`.

> `database.sql` resets and recreates the project's tables. Back up existing data before importing it.

## Demo accounts
- Administrator: `admin@luxestay.test` / `admin123`
- Receptionist/Staff: `staff@luxestay.test` / `admin123`
- Customers can register through the public registration page.

## Roles and permissions
- **Customer:** browse rooms, create reservations, view own bookings, cancel eligible bookings.
- **Receptionist/Staff:** view reception overview and available room inventory; approve or decline pending reservations. Cannot edit rooms, manage users, change payment status, or access admin pages.
- **Administrator:** manage rooms, all bookings, users, role assignments, and account activation. Admin does not use the customer booking flow.

## Security notes
- Public registration always creates a `customer` account.
- Only administrators can assign the `customer`, `staff`, and `admin` roles.
- Role-specific pages check permissions on the server, not only in the navigation UI.
- Passwords are hashed; forms use CSRF tokens and database access uses PDO prepared statements.
- Demo admin and staff accounts share the demo password `admin123`; change both before deployment.

If you rename the project folder, update the base path in `includes/functions.php`. Sample room photos use external Unsplash URLs.


## Reservation availability and confirmations
- Approval is transactional and locks the room row while checking for overlapping confirmed reservations. If a room is no longer available for the requested dates, the approval is rejected with an in-page message.
- Pending and confirmed bookings are excluded from the dashboard's **available tonight** count when their stay overlaps today. The count is date-aware; approving a future booking will only reduce tonight's count if the dates overlap tonight.
- Customer cancellations release the dates for future bookings. Declined reservations do not block availability.
- Browser-native confirm dialogs have been replaced with an accessible in-page confirmation panel for approvals, declines, cancellations, deletes, and account/room/booking changes. Success and error messages appear in the site's status panels.


## Booking approval email notifications
- When a booking changes from a non-approved status to `confirmed`, the system creates an approval email containing the reference number, room, stay dates, guest count, nights, and total amount. The email is sent only on the status transition, so simply saving an already-approved booking does not send a duplicate.
- Set your real sender address in `config/mail.php` (`LUXESTAY_MAIL_FROM` and `LUXESTAY_MAIL_FROM_NAME`). Do not use the sample `no-reply@yourdomain.com` address in production.
- PHP `mail()` requires a working mail transport. A default XAMPP installation often does not have this configured; configure SMTP/mail transport in PHP/XAMPP or replace the mail sender with a configured SMTP library/provider. The application displays a warning if the mail function cannot hand off the message. A successful `mail()` result means it was handed to the configured transport, not that the recipient's inbox delivery is guaranteed.
- The customer dashboard, booking details, receptionist booking queue, all-bookings overview, and admin booking management display the date and time the reservation was created (`bookings.created_at`).

## Booking approval email (Gmail SMTP)

Booking approval from **Receptionist > Booking approvals** or **Admin > Bookings** sends an HTML and plain-text confirmation email to the customer's registered email address using PHPMailer via Gmail SMTP. Emails trigger only on a successful transition to `confirmed`, after the database transaction commits; updating a booking that is already confirmed does not trigger another message. A mail failure does not roll back the approval, and an on-screen warning is shown.

Settings are in `config/mail.php`. SMTP uses Gmail `smtp.gmail.com` and port `587` with STARTTLS. The Gmail account requires an **App Password**, not the regular login password. Update the configured `SMTP_USERNAME`, `SMTP_PASSWORD`, and `LUXESTAY_MAIL_FROM` for your own account. `vendor/` includes PHPMailer so Composer installation is not required for the bundled project.

For production, move SMTP secrets to server-only environment variables, keep configuration out of public access/version control, and rotate any Gmail App Password that has been included in shared ZIP archives. A successful SMTP handoff does not guarantee inbox placement; check spam folders and server PHP error logs when troubleshooting.

## Email OTP password reset and status notifications (October 2026)
1. **Existing installation:** Back up the database and run `migration_password_reset_otps.sql` once in phpMyAdmin. **New installation:** `database.sql` includes the OTP table (note that importing `database.sql` deletes/recreates demo tables, so never import it over live data).
2. Open `auth/login.php` and choose **Forgot password?**. Enter your registered email, receive a six-digit OTP, verify it within **10 minutes**, then enter a new password (minimum 12 characters). Verification allows at most 5 incorrect attempts per issued code; resend requests are limited to one per minute and five per hour per email. Recovery responds generically whether or not an account exists.
3. Admin and receptionist booking status transitions send Gmail SMTP email to the registered customer: **pending**, **confirmed (approved)**, **cancelled (declined when receptionist decides)** and **completed**. The customer cancellation action also sends a notification. Unchanged booking status does not send duplicate email, and payment-only edits do not generate booking-status emails.
4. Mail is sent after committing the booking status change. If SMTP fails the status stays changed, and the UI shows a warning. The message indicates SMTP handoff, not guaranteed inbox delivery.
5. Configure Gmail SMTP in `config/mail.php`. Rotate the App Password if this project has been shared. For deployment, store secrets outside your public webroot and move them to environment variables, enforce HTTPS, implement login rate limiting, and restrict public access to vendor/configuration backups and SQL files. The OTP rate limits are per email; consider IP-level rate limiting and logging for larger deployments.


## IAS101 security hardening (rubric demonstration)

This version adds the following controls to the PHP application:

- **Authentication and access control:** public registration can create customer accounts only; admin/staff/customer pages check authorization on the server. The signed-in account's role and active status are re-read from MySQL on each request, so role changes and deactivation take effect immediately.
- **Password security:** passwords are stored with PHP `password_hash()` and checked with `password_verify()`. New customer accounts and admin-created accounts require at least 12 characters. The demo accounts below are only for local testing; change their passwords before showing the project on a shared network or deploying it.
- **Login brute-force mitigation:** five failed attempts for the same normalized email, or 20 failed attempts from the same IP hash, within 15 minutes trigger a temporary block. Login attempts are stored as hashes, not raw email/IP values, and old attempts are pruned.
- **CSRF protection:** state-changing forms require a session CSRF token. Logout and room deletion now use POST rather than GET.
- **Session hardening:** strict session mode, cookie-only sessions, HttpOnly, SameSite=Lax, session ID regeneration after login, and a Secure cookie when HTTPS is detected.
- **Browser security headers:** `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, a restrictive Content Security Policy, and no-store caching headers.
- **SQL injection and XSS defense:** PDO prepared statements are retained for queries; `e()` uses `htmlspecialchars()` with UTF-8 and substitution for invalid sequences. Room-management fields now have server-side validation.
- **Audit trail:** the administrator-only Security log records successful/failed logins, rate-limit blocks, logout, room deletion, account access changes, account deletion/deactivation, and booking status changes. Source IPs are stored as SHA-256 hashes.
- **Configuration protection:** database and SMTP values can be supplied through environment variables. SMTP secrets are no longer hard-coded in `config/mail.php`. The optional `config/mail.local.php` is ignored by Git and blocked from direct HTTP access.
- **Apache hardening:** directory listing is disabled, and direct HTTP access to SQL dumps, Composer metadata, hidden files, and configuration/vendor directories is denied.

### Apply the security migration to an existing database

1. Back up the `hotel_booking` database in phpMyAdmin.
2. Open phpMyAdmin, select `hotel_booking`, and import `migration_security_hardening.sql`.
3. For a new installation, import `database.sql`; the two security tables are included.
4. Restart Apache and MySQL in XAMPP, then test login, registration, booking, logout, admin room deletion, and the Security log.

### Configure email securely

1. Create a **new Gmail App Password** because the previous SMTP credential was included in the shared project archive. Revoke the old App Password in your Google Account.
2. Copy `config/mail.local.example.php` to `config/mail.local.php` and enter your own sender address and new App Password, or configure the equivalent `LUXESTAY_MAIL_FROM`, `LUXESTAY_MAIL_FROM_NAME`, `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, and `SMTP_PASSWORD` environment variables.
3. Never commit or share `config/mail.local.php`. For production, prefer environment variables or a secret manager outside the web root, enforce HTTPS, use a dedicated least-privilege database account, and remove/change demo accounts.
4. The application uses the folder URL `/hotel_booking_system/`; if you rename the project folder, update `url()` and the session cookie path in `includes/functions.php`.

### Suggested live demonstration against the rubric

1. **Security concepts (25):** explain authentication, authorization, confidentiality, integrity, availability, least privilege, and defense in depth.
2. **Application (25):** show prepared statements, password hashing, CSRF tokens, role checks, secure sessions, and server-side validation.
3. **Threat identification (20):** explain SQL injection, password guessing, CSRF, XSS, session hijacking, privilege escalation, and leaked credentials.
4. **Implementation (20):** demonstrate a customer blocked from an admin URL, logout by POST, login rate limiting, and the admin Security log.
5. **Accuracy/compliance (10):** apply the SQL migration, use unique strong passwords, keep secrets out of Git, test the normal booking flow, and explain that local HTTP does not provide transport encryption.

**Limitations:** login rate limits are database-backed but should also include web-server/WAF controls in production. Rate limits currently use the direct client IP supplied by Apache; do not blindly trust forwarded-IP headers behind a proxy. HTTPS must be configured at Apache/hosting level. This is a student-project hardening baseline, not a formal penetration-test certification.

## Password reset OTP troubleshooting

The forgot-password flow sends the email before activating the OTP in the database. If SMTP delivery fails, it clears the pending code and shows a retry message instead of sending the user to a verification screen with a code that was never delivered. OTP expiry and resend cooldown comparisons use MySQL's clock to avoid PHP/MySQL timezone differences.

Before testing email, configure `config/mail.local.php` with a newly generated Gmail App Password:
- `SMTP_USERNAME`: the Gmail sender account
- `LUXESTAY_MAIL_FROM`: the same Gmail address
- `SMTP_PASSWORD`: a newly generated Google App Password (not the normal Gmail password)

SMTP errors are logged to the PHP error log. Do not expose those logs publicly.



## Email-based two-factor authentication (2FA)

For an existing installation, back up the database, then import `migration_two_factor_auth.sql` into the existing `hotel_booking` database using phpMyAdmin. Fresh installations using `database.sql` already include the 2FA tables.

After a successful password check, the system emails a six-digit sign-in code. The code expires after 10 minutes and is limited to five attempts. After the code is verified, the browser is remembered for 10 days using a random, HTTP-only cookie; the database stores only its SHA-256 hash. The user must verify by email again after the 10-day period, or if the remembered-device cookie is removed. A password reset revokes remembered devices.

New registration passwords and password-reset passwords must be 12–72 characters and contain at least one uppercase letter, lowercase letter, number, and special character. Existing accounts are not forced to change their password until they use password reset.
