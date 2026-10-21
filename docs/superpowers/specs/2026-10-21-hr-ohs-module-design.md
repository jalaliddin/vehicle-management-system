# Stage 1 — HR and Occupational Safety (Kadrlar va mehnat muhofazasi)

Date: 2026-10-21
Status: approved design, ready for planning
Source: ТЗ «Единая электронная программа ООО «Ургенчтрансгаз»», section 5 (module 1) and section 4 (cross-cutting requirements that land in stage 1: operation log, backups, personal-data protection).

## Goal

An electronic employee base for all 14 organizations: hire, transfer and dismissal history, departments and positions, leave and absences, HR reports; deadline control for medical exams, safety briefings and PPE (special clothing, footwear, personal protective equipment) with warnings for anything overdue. Drivers become employees, so the waybill module can later check a driver's medical exam and briefing from the same records.

## Decisions

| Topic | Decision |
|---|---|
| Employee vs driver | New `employees` table is the person's master record. `drivers` gains a unique `employee_id`; the driver row stays as the "driver profile" (license, category, vehicle). Waybills, mobile app and reports keep using `driver_id`. `drivers.full_name/pinfl/phone/organization_id` stay as columns and are synced from the employee on every change. |
| Current assignment | `employees` stores the current organization, department, position, status, hire and dismissal dates. They change only through employment events; the card edit form cannot change them. |
| Warnings | Computed by queries on request, not stored. Threshold "due soon" = 30 days (config). In-app only: dashboard widget + alerts page. No Telegram/SMS/email in stage 1. |
| Driver clearance | A service returns `admitted` + reasons. Stage 1 shows it as a warning (driver card, driver picker in waybill creation). Hard blocking of departure is module 4. |
| Annual leave balance | Calendar-year model: entitlement (`employees.annual_leave_days`, default 21 from config) minus actual annual-leave days falling in that year. |
| Medical privacy | Without `ohs.medical.view` the API returns only exam type, date, next due date and a status (`valid`, `due_soon`, `overdue`, `unfit`); no institution, result text, restrictions or document. |
| Personal data | PINFL and passport are masked in list responses; full values on the card. Every card view is written to the audit log. |
| Audit log | One `audit_logs` table filled by an `Auditable` model trait (created/updated/deleted) plus explicit `viewed`/`cancelled` entries. Read-only: no update or delete path. |
| Backups | Own artisan command (`app:backup`), not a package: gzip'd `mysqldump` + tar of private files into `BACKUP_PATH`, retention 30 daily + 12 monthly. Scheduled daily; a `scheduler` container runs `schedule:work`. |
| Import | Employees from an `.xlsx` or `.csv` template; dry-run returns per-row errors, commit is all-or-nothing. |
| Lists | Employees, briefings and audit log are paginated server-side (`paginate(50)`); small catalogs return full lists like existing endpoints. |
| Language | UI labels in Uzbek (Latin), like the rest of the app. |

## Data model

All tables have `id` and timestamps unless noted. "FK" = `foreignId()->constrained()`.

### departments
`organization_id` FK, `parent_id` nullable FK → departments (restrict), `name`, `code` nullable, `is_active` bool default true.
A department can be deleted only when it has no employees and no child departments. Name unique among siblings (validated in the request).

### positions
`name` unique, `medical_exam_months` unsigned tinyint nullable (null = no periodic exam required), `briefing_months` unsigned tinyint nullable (null = no repeated briefing required), `is_active` bool default true. Global catalog, shared by all organizations. Deletable only when unused.

### ppe_items
`name`, `kind` enum `clothing|footwear|ppe`, `unit` string default `dona`, `default_wear_months` unsigned smallint nullable, `is_active`. Deletable only when unused.

### position_ppe_norms
`position_id` FK cascade, `ppe_item_id` FK restrict, `quantity` unsigned smallint, `wear_months` unsigned smallint. Unique (`position_id`, `ppe_item_id`). Edited as a whole set per position.

### employees (soft deletes)
`organization_id` FK, `department_id` nullable FK (null on delete), `position_id` nullable FK, `personnel_number` nullable, `last_name`, `first_name`, `middle_name` nullable, `pinfl` char 14 unique, `passport` nullable (series + number, e.g. `AA1234567`), `birth_date` nullable, `gender` enum `male|female` nullable, `phone` nullable, `address` nullable, `education` nullable, `photo_path` nullable, `clothing_size` nullable, `shoe_size` nullable, `employment_type` enum `staff|contract|part_time` default `staff`, `status` enum `active|dismissed` default `active`, `hire_date` nullable, `dismissal_date` nullable, `annual_leave_days` unsigned smallint, `notes` text nullable.
Accessor `full_name` = "last first middle". Soft delete only for records created by mistake (manage permission, no driver with waybills).

### drivers (alter)
Add `employee_id` nullable unique FK → employees (null on delete). Nullable only for legacy rows; new drivers require it.

### employment_events
`employee_id` FK cascade, `type` enum `hire|transfer|dismissal`, `effective_date`, `order_number`, `order_date`, `organization_id` nullable FK, `department_id` nullable FK, `position_id` nullable FK, `dismissal_reason` nullable string (code from `config('hr.dismissal_reasons')`), `dismissal_article` nullable string (free text, the Labor Code article), `notes`, `created_by` FK users, `cancelled_at` nullable, `cancelled_by` nullable FK users, `cancel_reason` nullable.

Rules (enforced in `EmploymentService`):
- `hire` only when the employee is not active (creation, or rehire after dismissal). Requires organization, department optional, position required.
- `transfer` only when active; must change at least one of organization, department, position; `effective_date` ≥ the latest active event's date.
- `dismissal` only when active; `effective_date` ≥ the latest active event's date; requires `dismissal_reason`.
- Only the latest non-cancelled event can be cancelled; the sole hire cannot be cancelled (delete the employee instead).
- After every apply or cancel, the employee's current state is recomputed by replaying non-cancelled events ordered by (`effective_date`, `id`).
- Side effects on the driver profile: org change → `drivers.organization_id` follows and `vehicle_id` is cleared if the vehicle belongs to another org; dismissal → driver `status = inactive`, `vehicle_id = null`.

Dismissal reason codes (labels in Uzbek, no article numbers hard-coded): `own_wish`, `agreement`, `contract_end`, `employer_initiative`, `retirement`, `other`.

### employee_absences
`employee_id` FK cascade, `type` enum `annual_leave|unpaid_leave|study_leave|maternity_leave|sick_leave|business_trip|absenteeism|other`, `start_date`, `end_date`, `days` (calendar days inclusive, computed), `is_planned` bool default false, `document_number` nullable, `document_date` nullable, `notes`, `created_by`.
Rules: `end_date` ≥ `start_date`; no overlap with another record of the same `is_planned` value for the employee; the employee must be employed on `start_date` (hire date ≤ start, not dismissed before start). Planned records form the leave schedule; actual records drive availability and balance.

### medical_exams
`employee_id` FK cascade, `type` enum `preliminary|periodic|extraordinary`, `exam_date`, `institution` nullable, `result` enum `fit|fit_with_restrictions|unfit`, `restrictions` text nullable, `next_due_date` nullable, `document_path` nullable, `notes`, `created_by`.
`next_due_date` defaults to `exam_date + position.medical_exam_months` when the position has a periodicity and the result is not `unfit`; the user may override it. The latest exam (max `exam_date`, then `id`) defines the employee's medical status.

### safety_briefings
`organization_id` FK, `type` enum `introductory|primary|repeated|unscheduled|targeted`, `conducted_on`, `topic`, `instructor_employee_id` nullable FK employees, `instructor_name` nullable (external instructor), `notes`, `created_by`.

### safety_briefing_participants
`safety_briefing_id` FK cascade, `employee_id` FK cascade, `next_due_date` nullable. Unique pair. No timestamps.
Participants must be active employees of the briefing's organization. `next_due_date` = `conducted_on + position.briefing_months` for `primary|repeated|unscheduled` when the position has a periodicity; otherwise null. Recomputed when the briefing date or participants change.

### ppe_issues
`employee_id` FK cascade, `ppe_item_id` FK restrict, `quantity`, `size` nullable, `issued_on`, `due_on` nullable, `status` enum `in_use|replaced|returned|written_off` default `in_use`, `closed_on` nullable, `notes`, `created_by`.
`due_on` = `issued_on + wear_months` from the employee's position norm for that item, else the item's `default_wear_months`, else null. Issuing an item the employee already has `in_use` closes the previous issue as `replaced` with `closed_on = issued_on`. Returning or writing off sets the status and `closed_on`.

### audit_logs
`user_id` nullable FK, `event` string (`created|updated|deleted|viewed|cancelled|participants_synced`), `auditable_type`, `auditable_id`, `employee_id` nullable (filled when the record is an employee or belongs to one — makes the per-employee history one query), `organization_id` nullable, `old_values` json nullable, `new_values` json nullable, `ip_address` nullable, `created_at` only. Indexes: (`auditable_type`, `auditable_id`), (`employee_id`), (`created_at`).
The trait skips `created_at/updated_at` and logs only changed attributes on update. The model refuses updates and deletes.

## Permissions and roles

New cases in `App\Enums\Permission` (labels in Uzbek, groups `hr` "Kadrlar" and `ohs` "Mehnat muhofazasi"; `audit.view` joins `admin`):

| Permission | Allows |
|---|---|
| `employees.view` | Employee list and card, employment history, absences, leave balance, HR reports |
| `employees.manage` | Create and edit cards, employment events and their cancellation, absences, import |
| `org_structure.manage` | Departments, positions (with periodicities), PPE norms per position |
| `ohs.view` | Alerts, medical due dates (no medical details), briefing journal, PPE cards and reports |
| `ohs.medical.view` | Medical exam institution, result, restrictions, document |
| `ohs.medical.manage` | Create, edit, delete medical exams |
| `ohs.briefings.manage` | Create, edit, delete briefings |
| `ohs.ppe.manage` | PPE item catalog, issuing, returning, writing off |
| `audit.view` | Audit log page and per-employee history |

All employee-bound records are scoped through `employees.organization_id` with `canAccessOrganization()`; departments and briefings through their own `organization_id`. Positions and PPE items are global catalogs.

A migration adds two system roles: `hr` "Kadrlar bo'limi" (`employees.view`, `employees.manage`, `org_structure.manage`, `ohs.view`, `drivers.view`) and `ohs` "Mehnat muhofazasi" (`employees.view`, `ohs.view`, `ohs.medical.view`, `ohs.medical.manage`, `ohs.briefings.manage`, `ohs.ppe.manage`). Existing roles are unchanged; administrators extend them in the role editor.

## Services

- `EmploymentService` — `hire`, `transfer`, `dismiss`, `cancel`, `recompute(Employee)`, driver side effects. All in DB transactions.
- `DueDates` — pure functions: exam next due, briefing next due, PPE due, `days` for an absence.
- `LeaveBalance` — `forYear(Employee, int $year)`: entitlement, used, remaining.
- `ComplianceService` — `alerts(User, filters)` and `summary(User)` across categories `medical`, `briefing`, `ppe`, `license`, states `overdue` and `due_soon`. "Missing" counts as overdue: an active employee whose position requires a periodic exam or repeated briefing and who has none.
- `DriverClearance` — `check(Driver, ?date)`: license valid, latest medical exam valid and not `unfit`, briefing not overdue, not absent on the date, employee active, driver status not `inactive`. Returns `admitted` and a list of reason codes with Uzbek messages.
- `EmployeeImporter` — parses `.xlsx`/`.csv` (OpenSpout), validates every row (required fields, PINFL format and uniqueness in file and DB, known org/department/position names), dry-run report or transactional insert with a hire event per row.
- `Backup` command + `BackupRetention` (pure: given backup file dates, which to delete).

## API

All under `auth:sanctum`, authorized with policies like the existing controllers.

| Method and path | Purpose |
|---|---|
| `GET/POST /departments`, `PUT/DELETE /departments/{id}` | Departments (`?organization_id=`) |
| `GET/POST /positions`, `PUT/DELETE /positions/{id}` | Positions |
| `GET/PUT /positions/{id}/ppe-norms` | PPE norm set of a position |
| `apiResource /ppe-items` | PPE catalog |
| `GET/POST /employees`, `GET/PUT/DELETE /employees/{id}` | Employees; POST carries the hire block; GET list paginated with filters `organization_id, department_id, position_id, status, search` |
| `POST /employees/{id}/photo` | Photo upload |
| `GET/POST /employees/{id}/events`, `POST /employment-events/{id}/cancel` | Employment events |
| `GET/POST /employees/{id}/absences`, `PUT/DELETE /absences/{id}`, `GET /employees/{id}/leave-balance?year=` | Absences |
| `GET/POST /employees/{id}/medical-exams`, `PUT/DELETE /medical-exams/{id}`, `GET /medical-exams/{id}/document`, `GET /medical-exams?organization_id&state=` | Medical exams |
| `apiResource /safety-briefings` | Briefings with `participant_ids` |
| `GET /employees/{id}/ppe`, `POST /employees/{id}/ppe-issues`, `POST /ppe-issues/{id}/close` | PPE card (issues + norm coverage), issue, close |
| `GET /hr/alerts`, `GET /hr/alerts/summary` | Warnings |
| `GET /drivers/{id}/clearance` | Driver clearance; also embedded in `GET /drivers/{id}` |
| `GET /employees/import-template`, `POST /employees/import` (`dry_run`) | Import |
| `GET /hr/reports/{headcount,movement,absent,leave-schedule,briefing-journal,ppe-issues,ppe-demand}` | HR and OHS reports |
| `GET /audit-logs`, `GET /employees/{id}/history` | Audit log |

`POST /drivers` requires `employee_id` (an active employee of the same organization without a driver profile); `full_name`, `pinfl`, `phone`, `organization_id` are copied from the employee and no longer accepted from the request.

Employee and medical exam responses go through `JsonResource` classes so masking and medical hiding live in one place.

## Reports

| Report | Parameters | Content |
|---|---|---|
| headcount | date, organization | Active employees on the date by organization and department |
| movement | from, to, organization | Hired, transferred, dismissed in the period, with counts |
| absent | date, organization | Employees absent on the date and why |
| leave-schedule | year, organization | Planned vs actual annual leave per employee |
| briefing-journal | from, to, organization | Printable journal: date, type, topic, instructor, participants |
| ppe-issues | from, to, organization | PPE issue statement |
| ppe-demand | months (3/6/12), organization | Items whose issues fall due in the window plus uncovered norms, summed by item and size |

Overdue procedures are the alerts page itself. Export is CSV with BOM and print, matching `ReportsView.vue`.

## Frontend

New routes (permission in route meta, as today):
- `/employees`, `/employees/create`, `/employees/:id`, `/employees/:id/edit`, `/employees/import`
- `/org-structure` — departments tree per organization, positions table with periodicities, PPE norm dialog
- `/ohs/alerts`, `/ohs/medical`, `/ohs/briefings`, `/ohs/ppe` (catalog + issue statement)
- `/hr/reports`
- `/audit`

Employee card tabs: Ma'lumotlar, Kadrlar tarixi, Ta'tillar, Tibbiy ko'riklar, Yo'riqnomalar, Maxsus kiyim va SHHV, Tarix (audit, only with `audit.view`).

Sidebar sections: "Kadrlar" (Xodimlar, Tuzilma, Kadrlar hisobotlari) and "Mehnat muhofazasi" (Ogohlantirishlar, Tibbiy ko'riklar, Yo'riqnomalar, Maxsus kiyim va SHHV); "Amallar jurnali" under Boshqaruv. Dashboard gets an alerts-summary widget for users with `ohs.view` or `employees.view`.

Driver form: name, PINFL and phone inputs are replaced by an employee picker (active employees of the chosen organization without a driver profile). Driver detail and the waybill driver picker show the clearance status as a warning chip.

## Data migration

A migration creates a position "Haydovchi" (no periodicities — the customer sets them) and, for every non-deleted driver, an employee: organization, name split from `full_name` (first token → last name, second → first name, rest → middle name), PINFL, phone, employment type, status `active`, no hire date, position "Haydovchi"; then sets `drivers.employee_id`. Down drops the column and the tables.

## Backups

`php artisan app:backup` writes `{BACKUP_PATH}/db-YYYYmmdd-HHMMSS.sql.gz` (mysqldump via `Process`, piped through gzip) and `files-YYYYmmdd-HHMMSS.tar.gz` (storage/app/private), then applies retention: keep the newest 30 days of backups and the first backup of each of the last 12 months; delete the rest. Scheduled daily at 02:00 in `routes/console.php`. Docker: `mysql-client` in the backend image, a `scheduler` service running `php artisan schedule:work`, `BACKUP_PATH` on a mounted volume. The restore procedure is documented in the README.

## Audit trail coverage

The `Auditable` trait is applied to all new models. As the last step of stage 1 it is also applied to existing models (Vehicle, Driver, Waybill, FuelStation, FuelTransaction, FuelNorm, Organization, User, Role), once the RBAC work touching them is committed.

## Testing

- Feature tests per controller in `tests/Feature/Hr/`: 403 without the permission, organization scoping, validation, business rules (event order and cancellation replay, driver side effects, absence overlap and employment check, next-due computation, PPE replacement, PINFL masking, medical hiding, audit entries written, import dry-run errors and all-or-nothing commit, clearance reasons, alerts including "missing").
- Unit tests: `DueDates`, `LeaveBalance`, `BackupRetention`.
- Factories for every new model; `User::factory()->withRole('hr'|'ohs')`.
- Frontend: `npm run build` passes; the main flows are checked by hand against the running app.

## Out of scope for stage 1

Payroll, timesheets, the mobile app, integration with the national labor system, Telegram/SMS/email notifications, PPE stock write-off from a warehouse (module 3), hard blocking of waybill departure (module 4).

## Sequencing constraint

Another session is changing RBAC files in the working tree (`Permission` enum, policies, `api.php`, `AppSidebar.vue`, driver views). Implementation starts after that work is committed; the plan's first task checks this.
