# CEPFMS — Entity Relationship Diagram

This reflects the actual schema in `database/migrations/` as of this writing — every
table, column, type, and foreign key below was pulled directly from that file, not written
from memory. If the schema changes, this document will drift out of date until it's
re-generated the same way.

## Diagram

```mermaid
erDiagram
    DEPARTMENTS ||--o{ STAFF : "staff belong to"
    DEPARTMENTS ||--o{ SUBMISSIONS : "routed to"

    CITIZENS ||--o{ SUBMISSIONS : "submits"

    SUBMISSIONS ||--o{ ATTACHMENTS : "has"
    SUBMISSIONS ||--o| COMPLAINTS : "extended by (if type=complaint)"
    SUBMISSIONS ||--o| PROPOSALS : "extended by (if type=proposal)"
    SUBMISSIONS ||--o{ RESPONSES : "has"
    SUBMISSIONS ||--o{ MODERATION_QUEUE : "queued in"

    STAFF ||--o{ RESPONSES : "drafts"
    STAFF ||--o{ MODERATION_QUEUE : "reviews"
    STAFF ||--o{ ACTIVITY_LOG : "performs"

    DEPARTMENTS {
        int id PK
        varchar name
        varchar slug UK
        varchar description
        int sla_hours
        timestamp created_at
    }

    STAFF {
        int id PK
        varchar full_name
        varchar email UK
        varchar password_hash
        varchar role "admin | staff | reviewer"
        int department_id FK
        varchar title
        tinyint is_active
        varchar otp_code "hashed, nullable"
        datetime otp_expires_at
        datetime last_login_at
        timestamp created_at
    }

    LOGIN_ATTEMPTS {
        int id PK
        varchar identifier "email being attempted"
        enum attempt_type "password | otp"
        timestamp created_at
    }

    CITIZENS {
        int id PK
        varchar full_name
        varchar email
        varchar phone
        timestamp created_at
    }

    SUBMISSIONS {
        int id PK
        varchar reference_number UK "FB-#### / CM-#### / PS-####"
        int citizen_id FK
        enum submission_type "feedback | complaint | proposal"
        varchar subject
        text description
        varchar category "AI- or keyword-classified"
        enum priority "Critical | High | Medium | Low"
        varchar summary "AI-generated"
        decimal ai_confidence
        varchar ai_reasoning
        varchar city
        varchar barangay
        varchar street
        decimal latitude "captured, not yet displayed anywhere"
        decimal longitude "captured, not yet displayed anywhere"
        int department_id FK
        enum status "Pending|Received|In Review|Assigned|Escalated|Resolved"
        enum moderation_status "Pending | Approved | Rejected"
        int support_count "column remains; no UI reads or writes it"
        datetime sla_due_at
        timestamp created_at
        timestamp updated_at
    }

    ATTACHMENTS {
        int id PK
        int submission_id FK
        varchar original_name "never trusted"
        varchar stored_name "randomized"
        varchar file_path
        varchar mime_type "server-verified"
        int file_size
        timestamp created_at
    }

    COMPLAINTS {
        int id PK
        int submission_id FK "UNIQUE — 1:1 with submissions"
        enum sla_status "On Track|At Risk|Breached|Met"
        tinyint escalated
        datetime closed_at
    }

    PROPOSALS {
        int id PK
        int submission_id FK "UNIQUE — 1:1 with submissions"
        enum proposal_status "Under Review|Approved|Rejected|Implemented"
    }

    MODERATION_QUEUE {
        int id PK
        enum content_type "Feedback | Complaint | Proposal"
        int submission_id FK
        int citizen_id FK "no formal constraint"
        text content_snapshot
        enum status "Pending|Approved|Rejected|Flagged"
        int reviewed_by FK "-> staff.id"
        datetime reviewed_at
        timestamp created_at
    }

    RESPONSES {
        int id PK
        int submission_id FK
        int staff_id FK
        varchar subject
        text body
        text internal_notes "staff-authored, not yet displayed anywhere after saving"
        enum status "Draft|Pending Approval|Sent|Overdue"
        datetime due_at
        datetime sent_at
        timestamp created_at
        timestamp updated_at
    }

    ACTIVITY_LOG {
        int id PK
        int staff_id FK
        varchar action
        varchar entity
        int entity_id
        json metadata
        timestamp created_at
    }

    SETTINGS {
        varchar setting_key PK
        varchar setting_value
    }
```

## Notes on relationships not shown above

- **`ACTIVITY_LOG`** and **`SETTINGS`** aren't drawn with relationship lines to keep the
  diagram readable: `activity_log.entity` / `entity_id` is a polymorphic reference (can point
  at a submission, a moderation item, etc. depending on `entity`'s value) rather than a real
  foreign key, and `settings` is a standalone key-value store with no relationships at all.
- **`moderation_queue.citizen_id`** has no `FOREIGN KEY` constraint in the schema even though
  it conceptually points to `citizens.id` — worth adding if this schema is revised.

## Columns that hold real data but aren't surfaced in any UI yet

Different category from "dead" — these are actively written with genuine data, just never
read back or displayed anywhere afterward. Worth wiring up a display for these rather than
removing them:

- **`submissions.latitude` / `longitude`** — captured from the citizen's browser geolocation
  on every submission that uses "Use My Location," but no admin view ever reads them back
  (e.g. no map view exists).
- **`responses.internal_notes`** — staff actively type into this field when drafting a
  response (see `frontend/js/responses.js`), but nothing displays it again after saving —
  once written, it's effectively invisible even to the staff who wrote it.
- **`departments.description`** — every seeded department has a real, human-written
  description (e.g. "Roads, drainage, and infrastructure maintenance"), but no view — citizen
  or admin — ever displays it.

## What was removed and why

As of this revision, three tables and a few columns were removed after confirming via
project-wide search that they had **zero references anywhere in the application code** —
not written to, not read from, not displayed:

- **`consultations`** and **`consultation_comments`** — a public-consultation feature that
  was fully scaffolded at the database level (tables, foreign keys, even a moderation
  pathway) but never built out on the application side. No controller, API, or view ever
  touched them.
- **`assignments`** — logged every department assignment, but nothing ever read it back; the
  same event was already visibly logged via `activity_log`, which the UI actually displays.
  Kept the visible one, dropped the redundant invisible one.
- **`moderation_queue.comment_id`** — an orphaned foreign key to `consultation_comments`.
- **`moderation_queue.flag`** — a 6-reason enum for flagging content, fully defined but never
  read or written by any moderation action in the code.
- **`'Comment'`** — removed from `moderation_queue.content_type`'s enum, since nothing ever
  created a moderation item with that type (nothing produces standalone "comments" now that
  consultations are gone).

If you're upgrading an existing database rather than doing a fresh import, see the migration
note in `README.md` §2.

## Where this comes from

Full source of truth: [`database/migrations/`](database/migrations/). Business rules
that aren't visible in the schema alone (e.g. which submissions count toward Analytics) are
documented in `app/models/Submissions.php` and section 8 of `README.md`.
