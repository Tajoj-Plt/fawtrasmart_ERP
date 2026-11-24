# Integration API — Clients — Detailed Reference

The field-sales part of the mobile app (Flutter) API: branches, clients, the daily itinerary, the visit workspace, forgotten visits, visit justifications, in-app notifications and location tracking. Everything here sits behind the same gate as [`DETAILED_REFERENCE.md`](./DETAILED_REFERENCE.md): Sanctum authentication, employee accounts only (`EnsureEmployeeUser`), active subscription (`EnsureSubscription` → `402`).

| | |
|---|---|
| **Base URL** | `{{base_url}}/api/integration/v1/` |
| **Format** | JSON — every response is JSON even if the request forgets `Accept: application/json` |
| **Uploads** | `multipart/form-data` on the endpoints that take files (add/update client, notes, visit note, competitors) |
| **Auth** | `Authorization: Bearer {token}` |
| **Language** | Arabic by default; `Accept-Language: en` for English validation messages |
| **Permissions** | Every route carries the **same** `permission.role:` gate as the matching web route — they all appear in `GET permissions` |

## Contents

1. [Implementation principle](#implementation-principle--a-standalone-copy)
2. [Response shape and error codes](#response-shape-and-error-codes)
3. [Gates](#gates)
4. [Data scope — who sees what](#data-scope--who-sees-what)
5. [Settings that change behaviour](#settings-that-change-behaviour)
6. [The visit sequence](#the-visit-sequence)
7. [Uploads](#uploads)
8. [Endpoint index](#endpoint-index)
9. [Branches](#1-branches)
10. [Clients](#2-clients)
11. [Contacts and notes](#3-contacts-and-notes)
12. [Daily itinerary](#4-daily-itinerary)
13. [Visit workspace](#5-visit-workspace)
14. [Forgotten visits](#6-forgotten-visits)
15. [Visit justifications](#7-visit-justifications)
16. [Notifications](#8-notifications)
17. [Location tracking](#9-location-tracking)
18. [Deliberate differences from the web](#deliberate-differences-from-the-web)
19. [Developer notes](#developer-notes)

---

## Implementation principle — a standalone copy

Nothing here calls a web controller. Each operation is **copied** from its web counterpart — same order of steps, same rules, same permissions, same Arabic messages — into API controllers that answer JSON only:

| API controller (`Modules\Integration\Http\Controllers\Api\…`) | Web source (`Modules\Client\Http\Controllers\…` unless noted) |
|---|---|
| `Clients\ClientController` | `ClientController` — `index`, `getMapData`, `show`, `store`, `update` |
| `Clients\ClientStatusController` | `ClientController` — `updateStatusClient`, `updateStageClient` |
| `Clients\ContactController` | `ClientNoteController` — `storeEmployeeNote`, `getEmployeeNotes` |
| `Clients\NoteController` | `ClientNoteController` — `addnotes`, `updateNote`, `destroyNote`, `deleteNoteAttachment` |
| `Clients\ItineraryController` | `DailyItineraryController`, `VisitWorkspaceController::returnToItinerary` |
| `Clients\VisitController` | `VisitWorkspaceController` |
| `Clients\ForgottenVisitController` | `VisitController` — forgotten visits |
| `Clients\JustificationController` | `IncompleteVisitsController` |
| `Clients\SettingsController` | reads `ClientSettingsHelper` / `ClientModuleSetting` |
| `Notifications\NotificationController` | `Modules\Sales\…\NotificationsController` |
| `Tracking\TrackingController` | `ItineraryController::updateLocation`, `getMapData` |
| `BranchController` | `DataScope::branchOptions()` |

Shared business services are reused, never duplicated: `ClientAccessService` (visibility cascade), `DailyItineraryService` (itinerary day, picker, add, postpone), `PointsService`, `OperationLog`, `next_document_number()`.

The API's own services live in `Modules/Integration/Services/Clients/`: `ClientVisibility`, `ClientForm`, `ClientWriter`, `ContactService`, `NoteService`, `VisitRules`, `MediaStore` — and `Services/TeamScope` for people-scoped screens.

---

## Response shape and error codes

```json
{ "success": true, "message": "…optional…", "data": { }, "meta": { "current_page": 1, "last_page": 3, "per_page": 20, "total": 55 } }
```

Errors always carry `success: false` and an Arabic `message`; validation errors add `errors { field: [messages] }`; business refusals add a machine `code`:

| HTTP | `code` | Meaning |
|---|---|---|
| 401 | `UNAUTHENTICATED` | Missing/expired token |
| 402 | `PLAN_…` | Subscription expired / feature not in plan (`plan:notifications`) |
| 403 | `FORBIDDEN` | Permission, scope or ownership refusal |
| 403 | `MUST_VISIT_FIRST` | The user must open a visit before seeing the client file — `data.open_visit_id` |
| 403 | `VISIT_FINISHED_TODAY` | The client's visit is done today and hidden (return it to the itinerary first) |
| 403 | `SECTION_DISABLED` | «خطوط السير» turned off in client module settings |
| 403 | `TRACKING_DISABLED` | «تتبّع خط السير» turned off |
| 404 | — | Record not found / unknown URL (JSON, never HTML) |
| 405 | `METHOD_NOT_ALLOWED` | Wrong verb — message lists the allowed ones |
| 422 | — | Validation (`errors`) |
| 422 | `VISIT_NOT_STARTED` | Step sent before «بدء الزيارة» |
| 422 | `VISIT_CLOSED` | Visit already completed or cancelled |
| 422 | `OUT_OF_RANGE` | Employee is farther than the proximity radius from the client |
| 422 | `STEPS_INCOMPLETE` | Complete sent before the mandatory steps — `data.steps` |
| 422 | `NOTE_REQUIRED` | Competitor step before the visit note |
| 422 | `STEP_DISABLED` | Competitor capture is off in settings |
| 429 | `TOO_MANY_ATTEMPTS` | Throttled (tracking: 30 requests/minute) |

---

## Gates

Applied in this order before any logic:

1. `auth:sanctum` + employee account.
2. Subscription active.
3. `module:customers` — the «العملاء» application toggle (all clients, itinerary, visits, notes, justifications and tracking routes). Off ⇒ `403`.
4. `plan:notifications` on notification routes (web parity).
5. `permission.role:` — same permission names as the web route.
6. Inside the controller: scope (which client / visit / employee), ownership, settings.

---

## Data scope — who sees what

**Clients** — the web index cascade, applied on the query (not on id lists):

1. `clients_view_all_clients` ⇒ all clients inside the user's current branch tree (`users.branch_id`, the branch picked in the web header; the main branch means no limit).
2. Otherwise the shared cascade (`ClientAccessService::applyPermissionCascade`), first match wins: daily itinerary ← employee groups ← assigned clients (`client_employee`, including the user's team).
3. Otherwise: an employee sees today's itinerary; a user with a branch sees the branch tree.

Then, for users without `clients_view_all_clients`: clients whose visit is finished today are hidden (setting «إخفاء العميل بعد إنهاء زيارته»), and clients hidden from the user's map are excluded.

`GET clients/map` follows the web map: a user with an itinerary today sees only that itinerary's clients, with `itinerary_source` (plan / self / other).

**Branch filter** — `branch_id` narrows lists to a tree node with its descendants (`include_descendants=0` for the node alone). A branch outside `GET branches` ⇒ `403`.

**Single client** (`show`, `update`, notes, contacts, visit open, status) — `ClientAccessService::canAccessClient()`.

**People** (itinerary of another rep, tracking path/team, forgotten visits `scope=all`) — `TeamScope`: the data-scope ladder (`DataScope::documentUserIds()`) **plus** the user and everyone under them in the management hierarchy. A supervisor always sees his team; `data_scope_all` / non-employees see everyone.

---

## Settings that change behaviour

Read with `GET clients/settings`; enforced server-side on every call.

| Setting (web screen) | Effect here |
|---|---|
| Required client fields (phone, mobile, email, tax number, CR, category, stage, location, image) | Validation of add/update client |
| Unique phone / client type scope | Validation |
| Default status / default registration stage | Applied on add |
| «تتبّع خط السير» | All `tracking/*` endpoints |
| Itinerary section on/off, max clients per day, lock after start, restrict to assigned clients | `itinerary/*` (via `DailyItineraryService`) |
| Visit proximity radius (per employee) | start / note / complete / cancel location check |
| «استبعاد الموقع» (client exemption) + `clients_visit_any_location` | Location becomes optional and unchecked |
| Competitor capture on/off | Step 4 exists or is skipped |
| Signature section on/off | `client_signature` / `employee_signature` required on complete |
| Note deposit count / note photo / min photos | Visit note & client note validation |
| Hide finished clients / allow return same day | Lists and `return-to-itinerary` |
| Status change requires approval | `POST clients/{id}/status` direct vs request |
| Max upload size | Every image field |

---

## The visit sequence

```
open ─► start (GPS in range) ─► contact ─► contact-shift ─► note ─► competitors ─► complete
  │                                                                              ▲
  └─────────────────────────────── cancel (reason + GPS, any time) ─────────────┘
```

- `open` returns today's open visit for this client or creates one (`visit_number` server-issued). Nothing else is allowed before `start`.
- `start` needs `current_latitude/longitude` within the proximity radius of the client location (unless exempt). No client location ⇒ refused with a clear message (cancel is the way out).
- `steps` in every visit response is the single source the app should read:

```json
"steps": { "has_contact": true, "has_shift": true, "has_note": true, "competitor_step": true,
           "has_competitors": true, "docs_unlocked": true, "has_sale": false,
           "can_complete": true, "can_return": true, "outcome": "failed" }
```

- The note step can carry `shift` to set (first time) or change (with `clients_change_contact_shift`) the contact's shift; the shift is written only after the note is saved.
- `docs_unlocked` tells the app it may now open invoice / receipt / credit note / exchange for this client (those are the Sales/Finance APIs with `client_id`).
- `complete` has no manual outcome: an invoice or a receipt created by the employee since `started_at` ⇒ `success`, otherwise `failed`. `return_later=true` + `return_time=HH:MM` needs only contact + shift + note, and puts the client back in today's itinerary.
- Every write on a completed/cancelled visit ⇒ `422 VISIT_CLOSED`; repeated `start` ⇒ `200` with the running timer.
- `toggle-pause` is meant to be called by the app on geofence exit/enter (the web does the same automatically).

---

## Uploads

| Field | Endpoint | Rule | Stored in |
|---|---|---|---|
| `attachments` | add/update client | image ≤ max upload | `public/assets/uploads/` |
| `attachments[]` | client note, visit note, update note | jpg/png ≤ max upload, min photos from settings, max 10 | `public/assets/uploads/notes/` |
| `image` | contact | image ≤ max upload | `public/uploads/client_notes/` |
| `competitors[i][photo]`, `no_competitors_photo` | competitors | jpg/png/webp ≤ max upload | `public/assets/uploads/visit_competitors/` |
| `client_signature`, `employee_signature` | complete | `data:image/…;base64,…` — must decode to a real image | `public/assets/uploads/visit_signatures/` |

Images are compressed server-side exactly like the web (max 1920 px, JPEG quality by size). File names are generated — never taken from the upload.

---

## Endpoint index

| # | Method | Path | Permission |
|---|---|---|---|
| 1.1 | GET | `branches` | — |
| 2.1 | GET | `clients/settings` | — |
| 2.2 | GET | `clients` | view clients |
| 2.3 | GET | `clients/map` | view clients |
| 2.4 | GET | `clients/options` | `clients_add_client` / `clients_edit_data` |
| 2.5 | POST | `clients` | `clients_add_client` |
| 2.6 | GET | `clients/{id}` | view clients |
| 2.7 | POST | `clients/{id}` | `clients_edit_data` |
| 2.8 | GET | `clients/{id}/status-options` | `clients_change_status` |
| 2.9 | POST | `clients/{id}/status` | `clients_change_status` |
| 2.10 | POST | `clients/{id}/stage` | `clients_change_status` |
| 3.1 | GET | `clients/{id}/contacts` | view notes |
| 3.2 | POST | `clients/{id}/contacts` | add notes |
| 3.3 | GET | `clients/{id}/notes` | view notes |
| 3.4 | POST | `clients/{id}/notes` | add notes |
| 3.5 | POST | `notes/{id}` | edit/delete notes |
| 3.6 | DELETE | `notes/{id}/attachments/{index}` | edit/delete notes |
| 3.7 | DELETE | `notes/{id}` | edit/delete notes |
| 4.1 | GET | `itinerary/day` | itinerary view |
| 4.2 | GET | `itinerary/employees` | itinerary view |
| 4.3 | GET | `itinerary/clients` | itinerary add |
| 4.4 | POST | `itinerary/clients` | itinerary add |
| 4.5 | POST | `itinerary/{rowId}/postpone` | `clients_itinerary_edit` |
| 4.6 | POST | `clients/{id}/return-to-itinerary` | workspace |
| 5.1 | POST | `clients/{id}/visits/open` | workspace |
| 5.2 | GET | `visits/{id}` | workspace |
| 5.3 | POST | `visits/{id}/start` | workspace |
| 5.4 | POST | `visits/{id}/toggle-pause` | workspace |
| 5.5 | POST | `visits/{id}/contact` | workspace + add notes |
| 5.6 | POST | `visits/{id}/contact-shift` | workspace |
| 5.7 | POST | `visits/{id}/notes` | workspace + add notes |
| 5.8 | POST | `visits/{id}/competitors/quick` | workspace + add notes |
| 5.9 | POST | `visits/{id}/competitors` | workspace + add notes |
| 5.10 | POST | `visits/{id}/notifications/{nid}/read` | workspace |
| 5.11 | POST | `visits/{id}/notifications/{nid}/reply` | workspace + `notifications_reply_to_notifications` |
| 5.12 | POST | `visits/{id}/complete` | workspace + `clients_visit_complete` |
| 5.13 | POST | `visits/{id}/cancel` | workspace + `clients_visit_cancel` |
| 6.1 | GET | `visits/forgotten` | forgotten own/all |
| 6.2 | POST | `visits/forgotten/{id}/close` | close own / force close |
| 6.3 | POST | `visits/forgotten/{id}/cancel` | `clients_cancel_forgotten_visits` |
| 7.1 | GET | `visit-justifications/pending` | — |
| 7.2 | POST | `visit-justifications` | — |
| 7.3 | GET | `visit-justifications` | justifications view own/all |
| 7.4 | GET | `visit-justifications/{id}` | justifications view own/all |
| 7.5 | PUT | `visit-justifications/{id}` | justifications view own/all |
| 8.1 | GET | `notifications` | `sales.notifications.view` + `notifications_view_all_notifications` |
| 8.2 | GET | `notifications/unread-count` | `sales.notifications.view` |
| 8.3 | GET | `notifications/{id}` | `sales.notifications.view` |
| 8.4 | POST | `notifications/{id}/read` | `sales.notifications.view` |
| 8.5 | POST | `notifications/read-all` | `sales.notifications.view` |
| 8.6 | POST | `notifications/{id}/reply` | `sales.notifications.reply` + `notifications_reply_to_notifications` |
| 9.1 | POST | `tracking/location` | `clients_itinerary_edit` |
| 9.2 | GET | `tracking/path` | `clients_itinerary_view` / `clients_view_by_daily_itinerary` |
| 9.3 | GET | `tracking/team` | `clients_itinerary_view` / `clients_view_by_daily_itinerary` |

*view clients* = `clients_view_all_clients | clients_view_own_clients | clients_view_by_employee_groups | clients_view_by_daily_itinerary` ·
*workspace* = `clients_visits_track | clients_itinerary_add | clients_visit_workspace_access | clients_must_visit_before_view` ·
*view/add/edit notes* = `client_follow_up_{view|add|edit_delete}_notes_attachments_appointments_{all|assigned|own}` (add also `clients_add_employee_note`) ·
*itinerary view* = `clients_daily_itinerary_view | clients_daily_itinerary_add_for_others` ·
*itinerary add* = `clients_daily_itinerary_add_client | clients_daily_itinerary_add_for_others`.

---

## 1) Branches

### 1.1 `GET branches`

Branch-tree nodes the user may filter by (`?search=` optional). `current_branch_id` is the branch selected in the web header — the default scope when no `branch_id` is sent. `restricted=false` means the whole organisation.

```json
{ "success": true, "data": { "current_branch_id": 4, "restricted": true,
  "branches": [ { "id": 4, "name": "منطقة الشرقية", "code": "B2", "parent_id": 1, "node_type": "region", "type_label": "منطقة", "is_main": false, "is_current": true } ] } }
```

---

## 2) Clients

### 2.1 `GET clients/settings`
See [Settings](#settings-that-change-behaviour). `shift.hours[]` feeds the «from/to» pickers; send `full` or `HH:00|HH:00`.

### 2.2 `GET clients`

Filters (all optional): `search` (name, code, phone, mobile), `branch_id` + `include_descendants`, `status` (id / `no_status`), `registration_stage`, `category_id`, `type` (1 individual / 2 commercial), `region`, `neighborhood`, `date_from`, `date_to`, `employee`, `over_credit_limit=1`, `customer_target_id` (id / `no_target`), `last_invoice_period` · `last_payment_period` · `last_activity_period` (`today, week, two_weeks, month, three_months, six_months, year, more_than_year`), `sort` (`newest` default / `name`), `latitude`+`longitude` (adds `distance_km`), `per_page` 1–100, `page`.

```json
{ "id": 11034, "code": "C-0412", "trade_name": "بقالة النور", "phone": "0500000001", "mobile": null,
  "status": { "id": 1, "name": "نشط", "color": "#28c76f" }, "branch": { "id": 4, "name": "منطقة الشرقية" },
  "neighborhood": "حي الاختبار", "location": { "latitude": 24.7136, "longitude": 46.6753 },
  "balance": 1250.5, "balance_formatted": "1,250.50 ر.س", "balance_direction": "debit",
  "needs_visit": true, "open_visit_id": null, "distance_km": 0.42 }
```

`needs_visit` / `open_visit_id` — for users bound by «الزيارة قبل العرض»: tap the row ⇒ open the visit, not the file. `balance > 0` = the client owes.

### 2.3 `GET clients/map`
Same filters; returns only clients with a location (max 2000) plus `branch_path` and `itinerary_source { tone: plan|self|other, label }`. `itinerary_only=true` when the user has an itinerary today.

### 2.4 `GET clients/options`
Everything the add/edit form needs. `branch_tree[]` is flat (`parent_id`), only `selectable=true` nodes may be sent as `client_tree_branch_id`. `employees[]` is filled only for users with `clients_assign_employees`. `next_code` is a preview; the real number is issued on save. `?client_id=` keeps an existing client's current node selectable.

### 2.5 `POST clients` (multipart)
Fields are exactly the web form: `client_tree_branch_id`*, `trade_name`, `first_name`, `last_name`, `phone`, `mobile`, `email`, `category_id`, `registration_stage_id`, `client_type`, `tax_number`, `commercial_registration`, `currency`, `printing_method`, `street1`, `street2`, `city`, `region`, `postal_code`, `country`, `notes`, `latitude`, `longitude`, `neighborhood_name_manual`, `attachments`, `employee_client_id[]`, `contacts[i][first_name|last_name|email|phone|mobile]`. Which ones are required comes from settings.

Side effects (same as web, in one transaction): client code, status and stage defaults, primary contact, assignment (chosen employees with `clients_assign_employees`, else the creator), location, neighbourhood, client login account when an email is given, ledger account under «حساب العملاء», points. With `clients_start_visit_on_create` the client enters today's itinerary and a visit is opened — its id is returned as `data.visit_id`.

```json
{ "success": true, "message": "تمت إضافة العميل وفتح زيارته — أضِف مسؤول العميل لتبدأ.", "data": { "id": 11034, "code": "C-0412", "…": "…", "visit_id": 15905 } }
```

### 2.6 `GET clients/{id}`
Full file + `assigned_employees[]`, `contacts[]` and `can { edit, change_status, add_note, view_notes, open_visit }`. May answer `403 MUST_VISIT_FIRST` (with `data.open_visit_id`) or `403 VISIT_FINISHED_TODAY` exactly where the web redirects.

### 2.7 `POST clients/{id}` (multipart)
Same fields as add + `item_quantity_limit`; `attachments` optional. The location is replaced only when `latitude/longitude` are sent. Assignments are replaced only when `employee_client_id` is sent (with `clients_assign_employees`).

### 2.8–2.10 Status and stage
`status-options` tells whether approval is needed and who can approve. Without approval rights the change becomes a request to `manager_id` (who must hold the approval permission) and a notification is sent; with rights it applies immediately and is logged as an approved request. `visit_id` (optional) tags the reason with the visit number.

```json
POST clients/11034/status   { "status_id": 2, "manager_id": 163, "notes": "المحل مغلق", "visit_id": 15905 }
→ { "success": true, "message": "رُفع طلب تغيير الحالة للمسؤول وأُشعِر به.", "data": { "client_id": 11034, "status_id": 1, "pending_approval": true } }
```

---

## 3) Contacts and notes

### 3.1–3.2 Contacts (client staff)
`shift` is one value: `full` or `HH:00|HH:00` (whole hours, start ≠ end). `invoice_id` must belong to the client.

### 3.3 `GET clients/{id}/notes`
Paginated (default 15). Users with only the `_own` view permission see their own notes. Each note has `attachments[{ index, url }]` — `index` is what `DELETE notes/{id}/attachments/{index}` expects — and `can_edit`.

### 3.4 `POST clients/{id}/notes` (multipart)
`client_employee_id` (a contact of this client), `description`, `deposit_count` (required when the setting is on), `attachments[]` (min photos from settings), `current_latitude/longitude` (required unless exempt), optional `shift` (else the contact's own shift), `process`, `site_type`.
The employee must be within the proximity radius of the client's registered location. The note notifies the author, stamps `last_note_at`, stores the employee position with the note, and awards points.

### 3.5–3.7 Update / delete
`_all` edits any note, `_own` only the user's notes — and only for clients in scope. Update keeps existing attachments and appends new ones.

---

## 4) Daily itinerary

`employee_id` here is a **user id** (the itinerary table stores `users.id`).

### 4.1 `GET itinerary/day?date=&employee_id=`
The service payload of the web «خط سيري اليومي»: ordered `stops[]` with `state` (`next`, `planned`, `late`, `active`, `done`, `missed`, `cancelled`), estimated `start/end`, legs, who added it, latest note/photos, and `stats`. `can.start` is true only for the user's own today.

### 4.2–4.4 Employees / picker / add
Adding respects the day cap, the lock after the first started visit, «assigned clients only», and department/branch restrictions — all from settings. Skipped clients are counted in the response (`already`, `out_of_scope`, `unassigned`, `full`).

### 4.5 Postpone
Only a planned visit of today or later, to the end of tomorrow's line (cap-checked).

### 4.6 Return to itinerary
For a client whose visit finished today and got hidden — adds an open itinerary row so it can be visited again (setting-controlled).

---

## 5) Visit workspace

See [The visit sequence](#the-visit-sequence). `GET visits/{id}` returns the whole screen in one call:

| Key | Content |
|---|---|
| `visit_id, visit_number, stage, started_at, paused, elapsed_seconds, departure_time, outcome` | Timer and state (`arrived → in_progress ⇄ paused → completed / cancelled`) |
| `client`, `client_location` | Client summary and the coordinates the distance is measured against |
| `steps` | Mandatory steps (source of truth) |
| `rules` | `proximity_meters`, `any_location`, `competitor_capture`, `signature_required`, note photo/deposit rules, `max_upload_kb` |
| `can` | complete / cancel / change_shift / reply_notifications / change_status (+ approval flags) |
| `contacts[]`, `notes[]` (last 20), `competitors_observed[]`, `competitor_options[]` | Step data |
| `client_notifications[]` | Management notes about this client addressed to the user (`client_popup`), unread first, with replies |
| `visit_documents[]` | Documents created in this visit window by the rep (or the viewer) |
| `client_documents{}` | Last 20 per group: `invoices, returns, receipts, expenses, credit_notes, exchanges` — `{ type, id, number, amount, at }` |

Competitors request (multipart):

```
competitors[0][competitor_id]=7
competitors[0][asset_count]=2
competitors[0][photo]=<file>
— or —
no_competitors=1
no_competitors_photo=<file>
```

Complete request:

```json
{ "current_latitude": 24.7136, "current_longitude": 46.6753, "outcome_notes": "",
  "return_later": true, "return_time": "18:30",
  "client_signature": "data:image/png;base64,…", "employee_signature": "data:image/png;base64,…" }
```

Only the visit owner, a holder of `clients_visits_track`, or an admin can open a visit (`403` otherwise).

---

## 6) Forgotten visits

A visit is forgotten when it started more than **4 hours** ago, or was opened on a previous day and never started. `scope=all` needs `clients_view_forgotten_visits_all` and returns the user's team (see [TeamScope](#data-scope--who-sees-what)). Closing someone else's visit needs `clients_force_close_forgotten_visits` and notifies the owner; the duration is written only when it is reasonable (≤ 4 h). Cancel needs a reason; cancelling another employee's visit is for admin/manager roles within the team.

---

## 7) Visit justifications

Yesterday-and-older itinerary visits that were not done need a written justification (≥ 5 chars) that management approves. Holders of `staff_visit_justification_exempt` get `exempt: true`. The web blocks the whole UI until justifications are sent; the API does not block — the app should read `pending_count` at start-up and prompt.

```json
POST visit-justifications   { "justifications": { "58753": "العميل كان مغلقاً طوال اليوم" } }
→ { "success": true, "message": "قُدّم 1 تبرير — ستراجعه الإدارة.", "data": { "submitted": [58753], "rejected": [] } }
```

Only visits currently waiting for the user's justification are accepted. A rejected justification can be edited and goes back to pending; an approved one cannot be edited.

---

## 8) Notifications

In-app notifications only — **there is no push (FCM)**. The app should poll `GET notifications/unread-count` (e.g. every 60 s while open) — it counts unread notifications of the last 24 hours, like the web bell.

- The list shows what the user sent or received; `unread=1` keeps unread received ones.
- Opening a notification marks it read (if the user is the receiver) and returns its replies and, for status requests, the approval `decision`.
- Replying goes to the other party and marks the original read.
- Client notifications (`client_popup`) are also delivered inside the visit (5.10 / 5.11) — reps who don't hold `sales.notifications.view` read and answer them there, exactly as on the web.

---

## 9) Location tracking

- **Send**: `POST tracking/location` with `{ latitude, longitude }` every ~15 s while working (the web header does the same), or `{ points: [ { latitude, longitude, recorded_at } ] }` (≤ 100) to upload what was collected offline. Points outside the last 24 h or in the future are rejected; a point closer than 2 m and within 60 s of the previous one is skipped (web rule). Throttle: 30 requests/minute.
- **Path**: `GET tracking/path?date=&employee_id=` — the day's points in order, total `distance_km`, and the current position (`is_live` = updated in the last 15 minutes).
- **Team**: `GET tracking/team` — latest position of every team member seen in the last 24 hours.

Permissions are the web ones: sending needs `clients_itinerary_edit` — the stock «مندوب مبيعات» role does not have it, so on the web the header GPS of reps is silently refused too. Grant it to the rep role to enable tracking.

---

## Deliberate differences from the web

Security and correctness fixes made in the API copy only (the web is unchanged unless stated):

| Area | Web | API |
|---|---|---|
| Add/update client | fills the model from the whole request (status, code, password… are fillable) | only the form's validated fields; code always server-issued |
| Credit limit / opening balance | accepted by the store action though absent from the form | not accepted (they have their own screens and permissions) |
| Update client | no scope check; wipes the location when not sent | scope checked; location kept unless sent |
| Contact / note contact id | any contact id of any client | must belong to the client |
| Visit steps after completion | still writable; cancel could overwrite a completed visit | `422 VISIT_CLOSED` |
| Signature | any string written to disk as PNG | must decode to an image |
| Cancel forgotten visit | no departure time — the visit could be re-opened as "today's open visit" | closed properly |
| Forgotten visits `scope=all` | whole organisation | the user's team / data scope |
| Justification submit | any visit id of the user, even completed ones | only visits waiting for justification; managers really notified |
| Notification detail | recomputes every reply thread in the system per request (~3 s) | same result, computed for this notification only |
| Unknown `Accept` header | validation errors redirect to an HTML page | always JSON |

Shared improvements (benefit the web too): indexes on hot lookup columns (`notifications.created_at/type/receiver_id+read/user_id`, `accounts.client_id`, `locations`, `neighborhoods.client_id`, `receipts.account_id/client_id`, `expenses.account_id`, `client_employee_notes.client_id`, `hidden_clients.user_id`) — creating any notification went from ~2 s to ~0.1 s; and a date filter in `PointsService` rewritten as a range (~2 s → 0.05 s per note).

---

## Developer notes

- Routes: `routes/api/clients.php`, `routes/api/notifications.php`, `routes/api/tracking.php`; `GET branches` in `routes/api.php`. All inside the authenticated group.
- `ForceJsonResponse` + `SetApiLocale` wrap every `/api/integration/v1` route.
- Route model binding: `{client}` → `Client`, `{visit}` → `Visit`, `{note}` → `ClientRelation`; missing ⇒ `404` Arabic JSON.
- Migration: `database/migrations/2026_09_26_000001_add_client_lookup_indexes.php` (guarded — skips existing indexes and missing columns).
- Old endpoints removed: `itinerary/today`, `PUT clients/{id}` (now `POST`), `visits/{id}/competitor-observations` (now `visits/{id}/competitors`).
- Postman: folders Branches, Clients, Itinerary, Visits, Forgotten Visits, Visit Justifications, Notifications, Tracking in `Integration.postman_collection.json` — run top to bottom; ids are saved automatically.
