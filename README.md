# CEPFMS — Laravel

**Citizen Engagement & Public Feedback Management System** for the Legislative Services Office
of San Jose del Monte, rebuilt on **Laravel 12 + PHP 8.2 + MySQL**.

It follows the same process and has the same UI as the original plain-PHP CEPFMS app.
The pages, CSS, JavaScript, images and business rules carry over unchanged. Routing,
sessions, CSRF, middleware, migrations, mail and artisan commands now come from Laravel.

Two portals:

1. **Citizen portal** (`/citizen`): citizens sign in with Google and verify their identity
   once with a government ID. Then they submit feedback, complaints and proposals with
   photos and a location in SJDM, and track them by reference number.
2. **Staff/admin portal** (`/admin`): sign-in with a password and then a 6-digit code sent by
   email. Staff handle moderation, case work, responses, analytics, user accounts and the
   audit log.

---

## Process (same as the original)

1. **Submit.** A signed-in citizen whose ID an admin has approved fills in the form. The server checks it again: readable text, a
   valid mobile number, at least one photo (JPG/PNG/WEBP/HEIC, up to 5 at 5 MB each), a
   barangay in SJDM, and a map pin inside the city boundary. Spam checks also run here:
   a hidden honeypot field, very fast submissions, duplicates, links, spam keywords and
   gibberish. Each IP can submit 5 times per 15 minutes and 20 times per 24 hours.
2. **Classify.** Google Gemini (or the offline English/Filipino/Taglish keyword
   classifier if Gemini is unavailable) sets the type, category, priority, sentiment and
   a summary. A reference number is created: `FB-####`, `CM-####` or `PS-####`.
3. **Moderate.** Every new item enters the **Moderation & Validation** queue with status
   `Pending`.
   - **Approve:** the status moves from Pending to Received, and the item shows up in its
     module and in the citizen's tracker.
   - **Reject:** an admin picks a reason. The item stays hidden from staff modules and
     analytics, and the citizen's tracker shows the reason.
4. **Work the case.** Staff update the status (Received → In Review → Assigned → Resolved).
   They close complaints (SLA status) and set proposal outcomes (Under Review / Approved /
   Rejected / Implemented).
5. **Respond.** A staff member drafts a reply, which can be marked Pending Approval, and
   then sends it. The citizen gets the reply by email.
6. **Measure.** The dashboard KPIs, the Needs Attention panel and Engagement Analytics
   count moderation-approved items only. Dashboards and analytics can be exported as
   password-protected PDFs, with the password emailed to whoever exports.

**Roles:**
- `admin`: all case work, plus moderation.
- `super_admin`: everything `admin` can do, plus user accounts, the audit log and system
  settings.

Super admins are created only from the command line.

---

## Requirements

- PHP 8.2+ (XAMPP 8.2 works) with `pdo_mysql`, `mbstring`, `fileinfo`, `gd`, `curl`
- Composer
- MySQL 8 or MariaDB 10.6+ (XAMPP/Laragon work fine)

## Installation

```bash
git clone https://github.com/vincent-ctrlv/Cepfms-laravel.git
cd Cepfms-laravel
composer install
cp .env.example .env
php artisan key:generate
```

Create an empty database (e.g. `cepfms`, utf8mb4) and set the connection in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cepfms
DB_USERNAME=root
DB_PASSWORD=
```

Then create the tables, default settings and the first super admin:

```bash
php artisan migrate --seed
php artisan cepfms:create-admin you@lgu.gov.ph 'A-strong-password' "Full Name"
php artisan serve
```

Open <http://localhost:8000> for the citizen portal or <http://localhost:8000/auth/login>
for staff. The first time you log in, you will be asked to change your password.

### Demo data (local only)

```bash
php artisan db:seed --class=DemoDataSeeder
```

This adds sample submissions and two accounts, both with the password `Demo-Password-123`:

- `superadmin@cepfms.test`
- `admin@cepfms.test`

### Login codes and email in development

When `APP_ENV=local` and no SMTP server is configured, emails are written to
`storage/logs/laravel.log` instead of being sent. The login screen also shows the 6-digit
code in an amber "Development mode" banner. To send real email, set:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=no-reply@lgu.gov.ph
MAIL_FROM_NAME="CEPFMS"
```

### Citizen sign-in and ID verification

Citizens must sign in with Google and have their identity verified before they can submit.
Browsing the portal and tracking a submission by reference number need no sign-in.

1. **Sign in** at `/citizen/login` with a Google account (the email must be verified by Google).
2. **Verify** at `/citizen/verify`: full name as on the ID, ID type, ID number, barangay, a
   photo of the ID and a selfie holding it. Accepted IDs (any government ID showing a San
   Jose del Monte address) are listed in `config/cepfms.php`.
3. **An admin approves or rejects** it on **ID Verification** in the admin portal. The
   citizen is emailed either way. Once approved, they can submit, and the name on their ID
   is used on their submissions.

What is stored: the ID and selfie photos go to `storage/app/private/verifications/` (not
web-accessible; admins view them through a signed-in route). The ID number is never
stored, only its last 4 characters and a keyed hash, used to flag one ID used on several
accounts.

**Setting up Google sign-in:**

1. In [Google Cloud Console](https://console.cloud.google.com/), create a project, then go to
   **APIs & Services → OAuth consent screen** and set it up (type "External"; app name,
   support email and the LGU's logo).
2. Go to **APIs & Services → Credentials → Create credentials → OAuth client ID**, type
   **Web application**.
3. Under **Authorised redirect URIs**, add `<APP_URL>/citizen/auth/google/callback`,
   for example `http://localhost:8000/citizen/auth/google/callback` for local development and
   `https://your-domain/citizen/auth/google/callback` for the live site.
4. Put the client ID and secret in `.env` (or the host's environment variables):

```dotenv
GOOGLE_CLIENT_ID=1234567890-abc.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-...
```

`APP_URL` must match the address people use, because the redirect URI is built from it.

### AI classification (optional)

Set `GEMINI_API_KEY` in `.env`. Without a key, or whenever Gemini fails, the built-in
keyword classifier is used, so a submission never fails because of the AI.

To turn AI off, run this in SQL:

```sql
UPDATE settings SET setting_value='0' WHERE setting_key='ai_classification_enabled';
```

---

## Deploying with Docker

The `Dockerfile` builds a production image: PHP 8.2 + Apache serving `public/` only, with
the needed extensions, Composer dependencies and production PHP settings (uploads up to
5 × 5 MB, opcache). It works on any host that runs a Dockerfile from a Git repo, or locally:

```bash
docker build -t cepfms .
docker run -p 8080:80 --env-file .env.docker \
  -v cepfms-storage:/var/www/html/storage \
  -v cepfms-uploads:/var/www/html/public/uploads \
  cepfms
```

**Environment variables.** No `.env` file goes into the image; set these on the host:

| Variable | Value |
|---|---|
| `APP_KEY` | Required. Generate once with `php artisan key:generate --show` and keep it. |
| `APP_URL` | The public address, e.g. `https://cepfms.example.gov.ph` |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | The MySQL/MariaDB database |
| `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Required: staff can't sign in without the emailed code |
| `RUN_MIGRATIONS` | `true` to run `php artisan migrate --force` on every start |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | Required for citizens to sign in (see [Citizen sign-in](#citizen-sign-in-and-id-verification)) |
| `GEMINI_API_KEY` | Optional, for AI classification |
| `PORT` | Set by most platforms automatically; the container listens on it (default 80) |

`APP_ENV=production`, `APP_DEBUG=false` and logging to the container output are already
the defaults in the image.

**Persistent storage.** Mount volumes (or the host's "persistent disk") at
`/var/www/html/storage` and `/var/www/html/public/uploads`. Without them, citizens'
photos, their ID verification photos and the uploaded logo are lost every time the
container is rebuilt.

**First super admin.** After the first start, open a shell on the container (or the host's
"run command" option) and run:

```bash
php artisan cepfms:create-admin you@lgu.gov.ph 'A-strong-password' "Full Name"
```

---

## Where things live

| Purpose | Location |
|---|---|
| Routes (pages + JSON API) | `routes/web.php` |
| Citizen pages | `app/Http/Controllers/CitizenController.php` |
| Staff sign-in pages | `app/Http/Controllers/AuthController.php` |
| Admin pages | `app/Http/Controllers/Admin/PageController.php` |
| JSON API (`/api/...`) | `app/Http/Controllers/Api/*` |
| Access control / idle timeout / headers | `app/Http/Middleware/` (`RequireStaff`, `StaffIdleTimeout`, `SecurityHeaders`) |
| AI, spam, uploads, email, analytics, PDFs | `app/Services/` |
| Shared helpers, report queries, service area | `app/Support/` |
| Blade views (same markup as the original) | `resources/views/{citizen,auth,admin,partials}` |
| CSS / JS / images | `public/frontend/` |
| Uploaded photos and logo | `public/uploads/` (PHP execution disabled) |
| Schema | `database/migrations/` |
| Artisan commands (`cepfms:create-admin`, `cepfms:make-super-admin`) | `routes/console.php` |
| Product docs | `docs/prd.md`, `docs/erd.md` |

Old `.php` links, such as `/citizen/track.php?ref=CM-1234` in emails that were already
sent, redirect to the new URLs.

The citizen pages use a pre-built Tailwind file (`public/frontend/css/tailwind.css`).
If you change classes on those pages, rebuild it with `npm install && npm run build:css`.
The admin pages load Tailwind from its CDN, as before.

## Security

- **Database:** queries use parameterized PDO / the query builder.
- **Passwords:** stored with bcrypt.
- **Sign-in:**
  - Staff sign in with a password plus an emailed code. The code is stored hashed and
    expires after 5 minutes.
  - Both steps are limited to 5 attempts per 15 minutes per email address.
  - The session ID is regenerated when someone signs in.
  - Staff are signed out after `STAFF_IDLE_SECONDS` without activity (default 180).
- **CSRF:** Laravel checks the token on every POST, including the JSON API.
- **Uploads:** checked by their real MIME type and saved under random file names.
- **Headers:** baseline security headers and a scoped Content-Security-Policy.

## Databases

Each environment has its own database, so testing can never touch real data:

| Database | Used by | Configured in |
|---|---|---|
| `cepfms` | Local development (`php artisan serve`) | `.env` |
| `cepfms_test` | Automated tests. Wiped and rebuilt on every run. | `phpunit.xml` |
| Your host's database | The deployed site | The host's environment variables (see [Deploying with Docker](#deploying-with-docker)), never a file in the repo |

For the deployed site, create a dedicated database user with access to that database only
(not `root`), and give it a strong password.

## Tests

The tests run against a separate MySQL database, `cepfms_test`. Create it once:

```bash
mysql -u root -e "CREATE DATABASE cepfms_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

On XAMPP, the command is `C:\xampp\mysql\bin\mysql.exe`. Then run:

```bash
php artisan test
```

Tests refuse to run against any database whose name doesn't end in `_test`. If you see
that error, run `php artisan config:clear` (a cached config makes Laravel ignore
`phpunit.xml`).
