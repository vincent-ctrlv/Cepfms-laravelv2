# CEPFMS — Product Requirements Document

**This PRD describes the system as it actually exists in the codebase today**, not a future
roadmap. Where something is planned/scaffolded but not built, it's explicitly marked as such
in [§9](#9-known-gaps--not-yet-built) rather than described as working.

---

## 1. Overview

CEPFMS is a citizen engagement and public feedback management system for a Philippine
LGU (Local Government Unit) Legislative Services Office — currently configured for San Jose
del Monte, Bulacan. It gives citizens a single place to submit feedback, complaints, and
proposals without needing an account, and gives LGU staff a case-management portal to
triage, route, and resolve what comes in.

It's a traditional server-rendered PHP 8 + MySQL 8 + Apache application with no build step,
no frontend framework, and no JavaScript SPA — pages are generated per-request from the
database. Full technical layout is documented in `README.md` §4.

## 2. Problem Statement

Citizens reporting local issues (potholes, broken streetlights, waste collection, etc.) or
sharing feedback/proposals typically have no single, trackable channel to do so — reports go
through phone calls, walk-ins, or informal social media posts with no reference number, no
routing to the right department, and no visibility into whether anything happened.
CEPFMS gives every submission a trackable reference number, routes it to the
responsible department automatically (via AI or keyword classification), and gives staff a
shared queue instead of scattered channels.

## 3. Users

| User | Access | Needs |
|---|---|---|
| **Citizen** | Public portal, no account/login | Report an issue, share feedback, propose an idea, and check on it later without remembering a password |
| **LGU Staff** | Admin portal, password + email OTP login | Review incoming submissions, moderate content, assign to departments, respond, and see engagement trends |

There is currently **one staff access level in practice**: the `staff.role` column exists
(`admin` / `staff` / `reviewer`) but nothing in the application code checks its value —
every logged-in staff account currently has identical, full access to every admin module.
Role-based permission enforcement is not implemented (see §9).

## 4. Core User Flows

### 4.1 Citizen: Submit something
1. Land on the homepage (no login). See live stats (total submissions, resolution rate, avg.
   days to resolve) and three entry points: General Feedback, Complaint/Issue, Proposal/Idea
   — or a "Send a Quick Suggestion" inline box that hands off to the full form pre-filled.
2. Fill in subject, description (English, Filipino, or Taglish — no category picker; it's
   classified automatically), optional location (manual entry or "Use My Location" via
   browser geolocation + reverse geocoding), optional photos (up to 5, 5MB each), and
   required name + email (used to create/find a lightweight citizen record — no password).
3. On submit: server validates everything again (never trusts client-side checks alone),
   AI/keyword classification assigns type + category + priority + a short summary, a unique
   reference number is generated (`FB-####` / `CM-####` / `PS-####`), and the submission is
   placed in the moderation queue as `Pending` — **it is not yet visible to anyone but the
   citizen who submitted it.**
4. Citizen sees a success page with their reference number and can track status anytime at
   `/citizen/track.php` — no login required, just the reference number.

### 4.2 Staff: Moderate → Act → Respond
1. New submissions land in **Moderation & Validation** first, regardless of type. A staff
   member Approves, Rejects, or Escalates each one.
   - **Approve** → `moderation_status` becomes `Approved`, `status` advances from `Pending`
     to `Received` (only if it was still `Pending`), and it becomes visible in its module
     (Feedback / Complaints / Proposals) and to the citizen's tracker.
   - **Reject** → hidden permanently from staff modules and Analytics (data retained for
     audit, not deleted).
   - **Escalate** → stays `Pending`, needs another look.
2. Once approved, staff can assign it to a department, update its status, and (for
   complaints) track SLA status (On Track / At Risk / Breached / Met) or (for proposals)
   track proposal status (Under Review / Approved / Rejected / Implemented).
3. Staff draft a response (Draft → Pending Approval → Sent, or Overdue if past due), which
   the citizen would see attached to their tracked submission.
4. The Dashboard surfaces what needs attention today (breached SLAs, overdue responses, a
   24h+ moderation backlog) rather than just repeating the same KPI numbers twice.

## 5. Feature Inventory (what's actually built)

### Citizen Portal (`citizen/`, no login)
- Homepage with live stats, three submission entry points, a quick-suggestion widget, a
  Contact Information card, "how it works" explainer, recently-resolved list, and an FAQ
- Submission form (feedback / complaint / proposal) — auto-classified, geolocation-assisted,
  photo attachments, no account required
- Reference-number tracking with a visual progress tracker (Submitted → Received → In
  Review → Resolved)
- Success confirmation page after submitting

### Staff/Admin Portal (`admin/`, password + email OTP login)
- **Dashboard** — KPI cards with week-over-week trend, a weekly submissions chart, a
  "Needs Attention" panel (breached SLAs / overdue responses / stale moderation), recent
  staff activity feed, department workload
- **Feedback Submission** — table of approved feedback with search/filter
- **Complaints & Issues** — table with SLA status tracking, assign/update/escalate/log actions
- **Proposals & Suggestions** — table with proposal-specific status, assign/update actions
- **Moderation & Validation** — the queue every new submission passes through first;
  Approve / Reject / Escalate
- **Response Management** — draft, approve, and send responses to citizens
- **Engagement Analytics** — Status Distribution & Pipeline Health (donut), Submissions
  Volume by Municipal Category & Sector (bar chart) — both scoped to moderation-approved
  submissions only, both with custom hover tooltips
- Notification bell — polls for new citizen activity (anything landing in the moderation
  queue) every 60 seconds, shows a real dropdown with recent items, not just a static badge

### Cross-cutting
- **AI classification** (`services/AIClassifier.php`) — Google Gemini when an API key is
  configured (with a hand-tuned few-shot prompt covering Taglish phrasing, feedback-vs-
  complaint disambiguation, and realistic priority calibration), falling back automatically
  to an offline English/Filipino/Taglish keyword classifier when no key is set or Gemini
  fails — submission creation is never blocked by AI availability
- **Two-factor staff login** — password, then a one-time 6-digit email code (hashed at rest,
  5-minute expiry), both steps rate-limited (5 attempts / 15 min per email) against
  brute-force
- **Activity logging** — staff logins/logouts and case actions are logged to `activity_log`

## 6. Non-Functional Requirements (as implemented)

| Area | What's actually in place |
|---|---|
| **Security** | PDO prepared statements everywhere; bcrypt password hashing; CSRF tokens on all state-changing requests; `httponly`/`SameSite=Lax` sessions with 10-min idle timeout and fixation-resistant regeneration on login; server-verified (not extension-trusted) file MIME types; PHP execution disabled inside the uploads directory; baseline security headers + a scoped CSP via `.htaccess`; DB credentials read from environment variables with a gitignored local-override file |
| **Performance** | Composite/single-column indexes on the query patterns the Dashboard and Analytics actually run (`(moderation_status, status)`, `responses.status`, `complaints.sla_status`). No caching layer, no CDN, no queue — AI classification runs synchronously in the citizen's request (12s timeout, safe fallback) — acceptable at single-LGU-office scale, a real constraint at high volume (see `README.md` §7) |
| **Accessibility** | Skip-to-content link, `aria-live` regions on dynamic content (character counters, location detection), labeled form fields, focus-visible states |
| **Data integrity** | Foreign keys with appropriate `ON DELETE` behavior (`CASCADE` for genuinely dependent rows like attachments; `SET NULL` for optional associations like department reassignment) throughout, except two intentionally-loose references noted in `erd.md` |

## 7. Design Direction

Navy (`#0f1e3d`) + blue accent (`#2563eb`) civic/government visual identity, Tailwind
utility classes, no chart library (all charts are hand-built inline SVG with custom
tooltips). Status/semantic colors are fixed regardless of the brand accent: emerald = success
states, red = danger/escalated, amber = warning/pending. Full palette and component
conventions live in `frontend/css/styles.css` and `frontend/css/app.css`.

## 8. Success Metrics (what the app itself already measures)

The Dashboard and Analytics pages already track the metrics that matter for this product:

- **Resolution Rate** — resolved ÷ total, moderation-approved submissions only
- **Citizen Participation** *(where computed)* — approved ÷ everything ever submitted in the
  window, i.e. how much of what citizens send in actually makes it through moderation
- **Avg. Resolution Time** — in days, resolved + approved submissions only
- **SLA compliance** — per-department breach tracking on complaints
- **Category/sector volume** — where citizen engagement concentrates, to inform staffing

## 9. Known Gaps / Not Yet Built

Being direct about this rather than glossing over it:

- **Public Consultations was never built, and its scaffolding has since been removed.**
  `consultations` and `consultation_comments` tables were fully defined in the schema (with
  foreign keys and even a moderation pathway) but no controller, API endpoint, or view ever
  touched them. Since they had zero references anywhere in the application, they were dropped
  during a database cleanup pass rather than left half-built indefinitely. If this feature is
  wanted, it needs to be designed and built fresh — see `erd.md` for what existed before.
- **A few columns hold real data with no display built for it yet** — not dead, just
  incomplete: `submissions.latitude`/`longitude` (captured on every geolocation-assisted
  submission, never shown on any map/view), `responses.internal_notes` (staff actively type
  into this when drafting a response, but it's never displayed again after saving), and
  `departments.description` (real seeded content, no view shows it). These were deliberately
  kept rather than removed during the same cleanup pass — see `erd.md` for detail.
- **Role-based permissions are not enforced.** The `role` column exists on `staff` but
  nothing in the code branches on it — every staff account currently has identical access.
- **Real email delivery is not wired up.** OTP codes and responses run in a logged/on-screen
  dev mode by default; `services/EmailService.php` needs a real SMTP client for production.
- **No background job queue.** AI classification runs inline in the citizen's request.
  Acceptable today; a real constraint if submission volume grows significantly.
- **Announcements / Upcoming Events** (seen in reference UI mockups during development) were
  explicitly scoped out — would need new tables and an admin management UI, not built.

## 10. Out of Scope (by design, not oversight)

- Citizen accounts/login — deliberately not required, to minimize friction; reference-number
  tracking is the intended mechanism instead
- Native mobile app — this is a responsive web app only
- Multi-LGU / multi-tenant support — built for a single Legislative Services Office
