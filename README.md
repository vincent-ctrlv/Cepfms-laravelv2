# CEPFMS — Laravel

**Citizen Engagement & Public Feedback Management System** for the Legislative Services Office
of San Jose del Monte, built on **Laravel 12 + PHP 8.2 + MySQL**.

Residents of San Jose del Monte send feedback, complaints and proposals to the city, and
follow them until they are resolved. Staff review every report, act on it and reply, and
the office measures what citizens are raising.

## Two portals

1. **Citizen portal** (`/citizen`): citizens sign in with their Google account and verify
   their identity once with a government ID. They then submit reports with photos and a
   location in San Jose del Monte, and follow each one from their own dashboard. Anyone can
   check a report's status by reference number, without signing in.
2. **Staff/admin portal** (`/admin`): staff sign in with a password and then a 6-digit code
   sent by email. They handle ID verification, moderation, case work, replies, analytics,
   user accounts and the audit log.

## How it works

1. **Verify.** A citizen signs in with Google and sends a photo of a government ID showing a
   San Jose del Monte address, plus a selfie holding it. An admin approves or rejects it,
   and the citizen is emailed either way. Only verified citizens can submit.
2. **Submit.** The citizen fills in the form. The server checks it again: readable text, a
   valid mobile number, at least one photo (JPG/PNG/WEBP/HEIC, up to 5 at 5 MB each), a
   barangay in SJDM, and a map pin inside the city boundary. Spam checks also run: a hidden
   honeypot field, very fast submissions, duplicates, links, spam keywords and gibberish.
3. **Classify.** Google Gemini (or the offline English/Filipino/Taglish keyword classifier if
   Gemini is unavailable) sets the type, category, priority, sentiment and a summary. A
   reference number is created: `FB-####`, `CM-####` or `PS-####`.
4. **Moderate.** Every new item enters the **Moderation & Validation** queue.
   - **Approve:** the status moves from Pending to Received, and the item shows up in its
     module and in the citizen's tracker.
   - **Reject:** an admin picks a reason. The item stays out of staff modules and analytics,
     and the citizen sees the reason.
5. **Work the case.** Staff update the status (Received → In Review → Assigned → Resolved),
   close complaints (SLA status) and set proposal outcomes (Under Review / Approved /
   Rejected / Implemented).
6. **Respond.** Staff draft and send a reply. The citizen receives it by email and in their
   portal.
7. **Measure.** The dashboard KPIs, the Needs Attention panel and Engagement Analytics count
   moderation-approved items only. Dashboards and analytics can be exported as
   password-protected PDFs, with the password emailed to whoever exports.

## Roles

| Role | Can do |
|---|---|
| Citizen | Submit reports (once verified), follow their progress and read replies, manage their profile |
| `admin` | All case work, moderation and citizen ID verification |
| `super_admin` | Everything an admin can do, plus user accounts, the audit log and system settings |

## Citizen portal

- **Dashboard:** counts of the citizen's reports (waiting for review, in progress,
  resolved), recent reports with a progress bar, and the latest replies from the office.
- **My Submissions:** every report, filterable by stage, each with its full progress
  timeline, photos and the office's replies.
- **ID Verification** and **Profile** (verified name, Google email, mobile number).
- Works on phones, with a bottom tab bar for the main pages.

Citizens only ever see their own reports, and only replies that were actually sent. Drafts,
internal notes and staff names are never shown to them.

## Identity verification and privacy

- Accepted IDs: any government ID showing a San Jose del Monte address (for example the
  PhilSys National ID, driver's license, UMID, voter's ID, postal ID, Bulacan Provincial ID
  or barangay ID).
- The ID photo and selfie are stored outside the public web folder and can be viewed only by
  signed-in admins. They are never cached by browsers or proxies.
- The ID number itself is never stored, only its last 4 characters and a keyed hash. The
  hash flags the same ID being used on more than one account.
- Approvals and rejections are recorded in the audit log.

## Tech stack

| Part | Technology |
|---|---|
| Backend | PHP 8.2, Laravel 12 |
| Database | MySQL 8 / MariaDB 10.6+ |
| Citizen sign-in | Google OAuth (Laravel Socialite) |
| Front end | Blade templates, plain JavaScript, Tailwind CSS |
| Maps | Leaflet with OpenStreetMap / CARTO tiles |
| PDF export | TCPDF (AES-256 password protection) |
| AI classification | Google Gemini, with an offline keyword fallback |
| Deployment | Docker (PHP 8.2 + Apache) |

## Where things live

| Purpose | Location |
|---|---|
| Routes (pages + JSON API) | `routes/web.php` |
| Citizen pages | `app/Http/Controllers/CitizenController.php` |
| Citizen accounts, portal and Google sign-in | `app/Http/Controllers/CitizenAccountController.php` |
| ID verification (citizen and admin) | `app/Http/Controllers/Api/VerificationController.php` |
| Staff sign-in pages | `app/Http/Controllers/AuthController.php` |
| Admin pages | `app/Http/Controllers/Admin/PageController.php` |
| JSON API (`/api/...`) | `app/Http/Controllers/Api/*` |
| Access control, idle timeout, headers | `app/Http/Middleware/` |
| AI, spam, uploads, email, analytics, PDFs | `app/Services/` |
| Shared helpers, report queries, service area, citizen session | `app/Support/` |
| Blade views | `resources/views/{citizen,auth,admin,layouts,partials}` |
| CSS / JS / images | `public/frontend/` |
| Uploaded photos and logo | `public/uploads/` (PHP execution disabled) |
| ID verification photos | `storage/app/private/verifications/` (not web-accessible) |
| Schema | `database/migrations/` |
| Product docs | `docs/prd.md`, `docs/erd.md` |

## Security

- **Database:** queries use parameterized PDO / the query builder.
- **Passwords:** stored with bcrypt.
- **Staff sign-in:** a password plus an emailed code, which is stored hashed and expires
  after 5 minutes. Both steps are limited to 5 attempts per 15 minutes per email address.
  Staff are signed out after a few minutes without activity.
- **Citizen sign-in:** Google accounts with a Google-verified email only.
- **Sessions:** the session ID is regenerated on every sign-in.
- **CSRF:** Laravel checks the token on every POST, including the JSON API.
- **Uploads:** checked by their real file type and saved under random file names.
- **Headers:** baseline security headers and a scoped Content-Security-Policy.
- **Separate databases** for development, automated tests and the live site. Tests refuse to
  run against any database whose name doesn't end in `_test`.
