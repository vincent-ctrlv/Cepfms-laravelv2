# Citizen Engagement & Public Feedback Management System (CEPFMS)

## Project Overview
The Citizen Engagement & Public Feedback Management System (CEPFMS) is a web-based platform for
the Legislative Services Office of San Jose del Monte. Residents send feedback, complaints and
proposals to the city and follow them until they are resolved, while staff review every report,
act on it, reply, and measure what citizens are raising.

---

## Portals

| Portal | Description |
|--------|-------------|
| Citizen Portal (`/citizen`) | Citizens sign in with Google, verify their identity once with a government ID, submit reports with photos and a map location, and follow each report from their dashboard |
| Staff / Admin Portal (`/admin`) | Staff sign in with a password and an emailed 6-digit code, then handle ID verification, moderation, case work, replies, analytics, user accounts and the audit log |
| Public Tracker | Anyone can check a report's status by its reference number, without signing in |

---

## Modules & Descriptions

| Module | Description |
|--------|-------------|
| ID Verification | Citizens upload a government ID with an SJDM address and a selfie; admins approve or reject, and the citizen is emailed either way |
| Feedback, Complaints & Proposals | Submission forms with photo uploads, barangay selection, a map pin inside the city boundary, and spam checks |
| AI Classification | Google Gemini (with an offline English/Filipino/Taglish fallback) sets the type, category, priority, sentiment and summary |
| Moderation & Validation | Every new report is approved or rejected with a reason before it reaches staff modules |
| Case Management | Status tracking (Received → In Review → Assigned → Resolved), complaint SLA and proposal outcomes |
| Responses | Staff draft and send replies, delivered by email and in the citizen portal |
| Engagement Analytics | Dashboard KPIs, a Needs Attention panel, and password-protected PDF exports |
| System Administration | User accounts, audit log and system settings |

---

## How It Works

1. **Verify** – the citizen signs in with Google and submits a government ID for approval.
2. **Submit** – the verified citizen sends feedback, a complaint or a proposal.
3. **Classify** – the report is categorized automatically and given a reference number (`FB-####`, `CM-####` or `PS-####`).
4. **Moderate** – an admin approves or rejects the report.
5. **Work the Case** – staff update the status until it is resolved.
6. **Respond** – staff reply to the citizen.
7. **Measure** – approved reports feed the dashboards and analytics.

---

## Technologies Used

- **Backend:** Laravel 12 (PHP 8.2)
- **Database:** MySQL 8 / MariaDB 10.6+
- **Frontend:** Blade Templates, JavaScript, TailwindCSS
- **Authentication:** Google OAuth (Laravel Socialite) for citizens, password + email code for staff
- **Maps:** Leaflet with OpenStreetMap / CARTO tiles
- **AI:** Google Gemini
- **PDF Export:** TCPDF
- **Deployment:** Docker (PHP 8.2 + Apache)
- **Other Tools:** Composer, NPM

---

## Author Notes

This project was created for academic purposes and is intended to support citizen engagement
and public feedback management for the City of San Jose del Monte.
