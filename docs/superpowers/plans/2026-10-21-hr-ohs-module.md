# Stage 1 — HR and Occupational Safety Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the HR and occupational-safety module (employees, employment events, absences, medical exams, briefings, PPE, warnings, reports, import) plus the stage-1 cross-cutting pieces (audit log, backups) to vms.utg.uz.

**Architecture:** New Laravel models, services and controllers under the existing `/api` + Sanctum + policy pattern; employees become the master person record and `drivers` link to them through `employee_id`. Warnings and clearance are computed by services on request. New Vue 3 + Vuetify views follow the existing views, routes and sidebar permission meta.

**Tech Stack:** PHP 8.3, Laravel 13, Sanctum 4, MySQL 8.4 (prod) / SQLite in-memory (tests), PHPUnit; Vue 3.5, Vuetify 3, Pinia, axios; new dependency `openspout/openspout` (xlsx/csv import).

**Spec:** `docs/superpowers/specs/2026-10-21-hr-ohs-module-design.md`

## Global Constraints

- Work on branch `feature/hr-ohs`, started only after the RBAC work currently uncommitted in the tree is committed (Task 0).
- Backend commands run from `backend/`; frontend commands from `frontend/`.
- Tests run on SQLite `:memory:`: no MySQL-only SQL (no `DATE_ADD`, `JSON_*`, `GREATEST`); do date math in PHP with Carbon, use `latestOfMany()`/`ofMany()` for "latest per employee".
- Every controller action calls `Gate::authorize(...)` with a policy in `app/Policies/`, like `DriverController`; organization scoping via `User::canAccessOrganization()` / `canAccessAllOrganizations()`.
- User-facing text (UI labels, validation and business-rule error messages) in Uzbek Latin. Forbidden responses keep the existing `"Ruxsat yo'q."`.
- `config('hr.alert_due_soon_days')` = 30, `config('hr.annual_leave_days')` = 21, `config('hr.backup.keep_daily')` = 30, `config('hr.backup.keep_monthly')` = 12, backup daily at `02:00`.
- PINFL is exactly 14 digits (`regex:/^\d{14}$/`). List masking: PINFL keeps first 3 and last 3 digits (`123********890`); passport keeps first 2 and last 2 characters (`AA*****67`).
- Paginated lists (`employees`, `safety-briefings`, `audit-logs`) use `paginate(50)`; catalogs return full lists.
- Event `effective_date`, absence dates of actual (non-planned) records, exam dates, briefing dates and PPE issue dates may not be in the future (`before_or_equal:today`); planned absences may be.
- No new frontend dependencies. Run `vendor/bin/pint --dirty` before each backend commit.
- Commit messages end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. A user with no organization and no `data.all_organizations` permission opens HR lists and alerts — expect empty results, never a 500 (test in Task 4 and Task 9).
2. A transfer or dismissal dated before the employee's latest event, or in the future — expect a 422 with an Uzbek message, state unchanged (tests in Task 4).
3. Dismissed employees — expect them absent from alerts, briefing participant validation, driver pickers, and clearance to report `employee_dismissed` (tests in Task 7 and Task 9).
4. Legacy driver names with one word or extra spaces (`"Karimov"`, `"  Karimov   Anvar  "`) — expect the backfill to succeed with sensible parts, not fail the migration (test in Task 4).
5. An import CSV saved by Excel in a Russian locale (BOM, `;` separator, `DD.MM.YYYY` dates) — expect it to parse like a comma/ISO file (test in Task 11).

---

### Task 0: Preconditions

- [ ] **Step 1: Confirm the RBAC work is committed**

Run: `git status --short backend/app/Enums backend/app/Policies backend/routes/api.php frontend/src/components/layout/AppSidebar.vue`
Expected: no output. If files are still modified or untracked, stop and ask the user; do not start.

- [ ] **Step 2: Branch and baseline**

Run: `git checkout -b feature/hr-ohs && cd backend && php artisan test`
Expected: all tests pass. Record the count; later tasks must keep these green.

---

### Task 1: Permissions, roles, config

**Files:**
- Modify: `backend/app/Enums/Permission.php`
- Create: `backend/config/hr.php`
- Create: `backend/database/migrations/2026_10_22_100001_add_hr_system_roles.php`
- Modify: `backend/tests/Feature/Rbac/AuthPermissionsTest.php` (role count 6 → 8)
- Test: `backend/tests/Feature/Hr/HrRolesTest.php`

**Interfaces:**
- Produces: enum cases `EmployeesView='employees.view'`, `EmployeesManage='employees.manage'`, `OrgStructureManage='org_structure.manage'`, `OhsView='ohs.view'`, `OhsMedicalView='ohs.medical.view'`, `OhsMedicalManage='ohs.medical.manage'`, `OhsBriefingsManage='ohs.briefings.manage'`, `OhsPpeManage='ohs.ppe.manage'`, `AuditView='audit.view'`; groups `hr` ("Kadrlar": the first three), `ohs` ("Mehnat muhofazasi": the five `ohs.*`), `AuditView` in `admin`. Role slugs `hr` ("Kadrlar bo'limi") and `ohs` ("Mehnat muhofazasi"), `is_system = true`.
- Produces: `config/hr.php` keys `alert_due_soon_days`, `annual_leave_days`, `dismissal_reasons` (code → Uzbek label: `own_wish` "Xodimning tashabbusi bilan", `agreement` "Tomonlarning kelishuviga ko'ra", `contract_end` "Mehnat shartnomasi muddati tugashi", `employer_initiative` "Ish beruvchining tashabbusi bilan", `retirement` "Pensiyaga chiqish", `other` "Boshqa"), `backup.path` (`env('BACKUP_PATH', storage_path('backups'))`), `backup.keep_daily`, `backup.keep_monthly`.

- [ ] **Step 1: Write the failing test**

```php
public function test_hr_and_ohs_roles_are_seeded_with_their_permissions(): void
{
    $this->assertEqualsCanonicalizing(
        ['employees.view', 'employees.manage', 'org_structure.manage', 'ohs.view', 'drivers.view'],
        Role::where('slug', 'hr')->firstOrFail()->permissionKeys());
    $this->assertEqualsCanonicalizing(
        ['employees.view', 'ohs.view', 'ohs.medical.view', 'ohs.medical.manage', 'ohs.briefings.manage', 'ohs.ppe.manage'],
        Role::where('slug', 'ohs')->firstOrFail()->permissionKeys());
}

public function test_permission_catalog_has_hr_and_ohs_groups(): void
{
    $groups = collect(Permission::grouped())->keyBy('key');
    $this->assertSame('Kadrlar', $groups['hr']['label']);
    $this->assertCount(5, $groups['ohs']['permissions']);
}
```

- [ ] **Step 2: Run** `php artisan test --filter=HrRolesTest` — Expected: FAIL (no role `hr`).
- [ ] **Step 3: Implement** the enum cases, labels, groups; the migration (insert both roles + `role_permission` rows; `down()` deletes them); `config/hr.php`; bump the count in `AuthPermissionsTest::test_system_roles_are_seeded_by_the_migration` to 8 and add `hr`, `ohs` to its slug list.
- [ ] **Step 4: Run** `php artisan test` — Expected: PASS, including Rbac tests.
- [ ] **Step 5: Commit** `feat(hr): add HR and OHS permissions, roles and config`

---

### Task 2: Audit log

**Files:**
- Create: `backend/database/migrations/2026_10_22_100002_create_audit_logs_table.php`
- Create: `backend/app/Models/AuditLog.php`, `backend/app/Models/Concerns/Auditable.php`
- Create: `backend/app/Http/Controllers/Api/AuditLogController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Hr/AuditLogTest.php`

**Interfaces:**
- Produces: `AuditLog::record(Model $subject, string $event, ?array $old = null, ?array $new = null): AuditLog` — fills `user_id` from `auth()->id()`, `ip_address` from `request()->ip()`, `employee_id` and `organization_id` from the subject's `auditEmployeeId()` / `auditOrganizationId()`.
- Produces: trait `Auditable` — on `created` records `new_values` = attributes; on `updated` records only `getChanges()` with matching originals; on `deleted` records `old_values`; skips `created_at`, `updated_at` and the model's `$hidden`. Overridable methods `auditEmployeeId(): ?int` (default: `employee_id` attribute) and `auditOrganizationId(): ?int` (default: `organization_id` attribute).
- Produces: `GET /api/audit-logs` (`audit.view`), filters `user_id`, `employee_id`, `event`, `from`, `to`; newest first; `paginate(50)`; with `user:id,name`; scoped to the user's organization unless all-organizations.
- `AuditLog` has `const UPDATED_AT = null`; `updating` and `deleting` model events throw `LogicException`.

- [ ] **Step 1: Write the failing tests** (the trait is exercised on a test-only model `AuditedThing` whose table the test creates in `setUp()`)

```php
test_creating_updating_deleting_an_auditable_model_writes_entries()   // 3 rows; updated row old_values == ['name' => 'A'], new_values == ['name' => 'B']
test_audit_entries_cannot_be_changed_or_deleted()                     // expectException(LogicException::class) on update() and delete()
test_audit_log_endpoint_requires_audit_view()                         // 403 for withRole('viewer')
test_audit_log_endpoint_is_scoped_to_users_organization()             // entry of another org not in data
test_audit_log_endpoint_filters_by_employee_id()
```

- [ ] **Step 2: Run** `php artisan test --filter=AuditLogTest` — Expected: FAIL.
- [ ] **Step 3: Implement** migration (columns and indexes per spec), model, trait, controller, route.
- [ ] **Step 4: Run** `php artisan test --filter=AuditLogTest` — Expected: PASS.
- [ ] **Step 5: Commit** `feat(audit): add immutable audit log with model trait`

---

### Task 3: HR schema and catalogs (departments, positions, PPE items, norms)

**Files:**
- Create: `backend/database/migrations/2026_10_22_100003_create_hr_tables.php` — every HR table from the spec (departments, positions, ppe_items, position_ppe_norms, employees, employment_events, employee_absences, medical_exams, safety_briefings, safety_briefing_participants, ppe_issues) and `drivers.employee_id`, in FK order.
- Create: `backend/app/Models/{Department,Position,PpeItem,PositionPpeNorm}.php` (all `use Auditable`)
- Create: `backend/app/Policies/{DepartmentPolicy,PositionPolicy,PpeItemPolicy}.php`
- Create: `backend/app/Http/Controllers/Api/{DepartmentController,PositionController,PpeItemController}.php`
- Create: `backend/database/factories/{DepartmentFactory,PositionFactory,PpeItemFactory}.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Hr/OrgStructureTest.php`

**Interfaces:**
- Produces: models with relations `Department::organization()`, `parent()`, `children()`, `employees()`; `Position::ppeNorms()`; `PositionPpeNorm::ppeItem()`.
- Produces: routes `apiResource departments` (index filter `organization_id`, scoped), `apiResource positions`, `GET/PUT positions/{position}/ppe-norms` (PUT body `norms: [{ppe_item_id, quantity, wear_months}]` replaces the set), `apiResource ppe-items`.
- Policies: view any of these = `employees.view` or `ohs.view` or `org_structure.manage`; manage departments/positions/norms = `org_structure.manage` (+ organization access for departments); manage PPE items = `ohs.ppe.manage`.
- Delete guards return 422: department with children → "Ichki bo'limlar mavjud"; with employees → "Bo'limda xodimlar bor"; position or PPE item in use (norms, employees, issues) → "Ma'lumot ishlatilmoqda, o'chirib bo'lmaydi".

- [ ] **Step 1: Write the failing tests**

```php
test_hr_user_creates_department_in_own_organization()
test_hr_user_cannot_create_department_in_other_organization()      // 403
test_department_name_unique_among_siblings()                       // 422 on 'name'
test_department_with_children_cannot_be_deleted()                  // 422
test_position_stores_periodicities()                               // medical_exam_months 12, briefing_months 6 persisted
test_ppe_norms_put_replaces_the_set()                              // 2 norms → PUT 1 norm → 1 row
test_ppe_item_in_a_norm_cannot_be_deleted()                        // 422
test_viewer_cannot_manage_catalogs()                               // 403
```

- [ ] **Step 2: Run** `php artisan test --filter=OrgStructureTest` — Expected: FAIL.
- [ ] **Step 3: Implement** migration, models, factories, policies, controllers, routes.
- [ ] **Step 4: Run** `php artisan test` — Expected: PASS (whole suite: the migration must not break existing tests).
- [ ] **Step 5: Commit** `feat(hr): add HR schema and org-structure catalogs`

---

### Task 4: Employees, employment events, driver link, backfill

**Files:**
- Create: `backend/app/Models/{Employee,EmploymentEvent}.php`
- Modify: `backend/app/Models/Driver.php` (`employee()` relation, `employee_id` fillable)
- Create: `backend/app/Services/Hr/EmploymentService.php`
- Create: `backend/app/Support/Hr/DriverEmployeeBackfill.php`
- Create: `backend/database/migrations/2026_10_22_100004_backfill_employees_from_drivers.php` (calls the backfill)
- Create: `backend/app/Http/Resources/{EmployeeResource,EmployeeListResource}.php`
- Create: `backend/app/Policies/EmployeePolicy.php`
- Create: `backend/app/Http/Controllers/Api/{EmployeeController,EmploymentEventController}.php`
- Modify: `backend/app/Http/Controllers/Api/DriverController.php` (store requires `employee_id`)
- Create: `backend/database/factories/{EmployeeFactory,EmploymentEventFactory}.php`; modify `UserFactory` only if `withRole('hr')` needs nothing new (it should not)
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Hr/{EmployeeTest,EmploymentEventTest,DriverEmployeeLinkTest}.php`, `backend/tests/Unit/Hr/DriverEmployeeBackfillTest.php`

**Interfaces:**
- Produces: `Employee` (SoftDeletes, Auditable; `full_name` accessor; relations `organization`, `department`, `position`, `driver` (hasOne), `events`, `absences`, `medicalExams`, `latestMedicalExam` (`latestOfMany('exam_date')`), `briefings` (belongsToMany via `safety_briefing_participants`, pivot `next_due_date`), `ppeIssues`); `Employee::syncDriverProfile(): void` copies `full_name`, `pinfl`, `phone`, `organization_id` to the driver row if one exists; `auditEmployeeId()` returns `$this->id`.
- Produces: `EmploymentService` — `hire(Employee $e, array $data, User $by): EmploymentEvent`, `transfer(Employee $e, array $data, User $by): EmploymentEvent`, `dismiss(Employee $e, array $data, User $by): EmploymentEvent`, `cancel(EmploymentEvent $event, string $reason, User $by): void`, `recompute(Employee $e): void`. Rule violations throw `ValidationException::withMessages([...])` with Uzbek messages. Each runs in `DB::transaction`, writes `cancelled` audit entries for cancellations, applies the driver side effects from the spec.
- Produces: routes `apiResource employees` (store body = card fields + `hire: {effective_date, order_number, order_date, department_id?, position_id}`; update accepts card fields only and calls `syncDriverProfile()`), `POST employees/{employee}/photo` (image ≤ 2 MB, `local` disk `hr/photos/`), `GET employees/{employee}/history` (`audit.view`; audit entries with that `employee_id`), `GET/POST employees/{employee}/events` (store body `type` + type fields), `POST employment-events/{event}/cancel` (`reason` required).
- `GET employees/{id}` writes `AuditLog::record($employee, 'viewed')` and returns `EmployeeResource` (full PINFL/passport, organization, department, position, driver); index returns `EmployeeListResource` (masked) with pagination meta.
- `POST /drivers` validation: `employee_id` required, exists, employee active, same organization as request `organization_id`, no existing driver (`unique:drivers,employee_id`); `full_name`, `pinfl`, `phone` taken from the employee.
- `DriverEmployeeBackfill::run(): int` — creates position "Haydovchi" if missing, an employee per non-deleted driver without `employee_id`, links it, returns the count. Name split: `preg_split('/\s+/', trim($fullName))`; one token → `last_name` = token, `first_name` = `''`.

- [ ] **Step 1: Write the failing tests**

```php
// EmployeeTest
test_hr_user_creates_employee_with_hire_event()          // 201; employee status active, hire_date, position set; 1 event type hire
test_pinfl_must_be_14_digits()                            // '1234567890123A' → 422 on pinfl
test_list_masks_pinfl_and_passport()                      // data.0.pinfl == '123********890'
test_card_shows_full_pinfl_and_logs_view()                // pinfl full; audit_logs has event 'viewed'
test_update_cannot_change_department_or_position()       // department_id in body ignored
test_updating_name_syncs_driver_profile()                 // drivers.full_name changes
test_user_without_organization_sees_empty_list()          // organization_id null, no all-orgs → data == []
test_department_and_position_with_employees_cannot_be_deleted()  // 422 both
test_employee_whose_driver_has_waybills_cannot_be_deleted()      // 422; without waybills → soft-deleted
// EmploymentEventTest
test_transfer_updates_current_assignment()
test_transfer_dated_before_latest_event_is_rejected()     // 422, employee unchanged
test_future_dated_event_is_rejected()                     // effective_date tomorrow → 422
test_dismissal_sets_status_and_deactivates_driver()       // driver status inactive, vehicle_id null
test_transfer_to_other_org_clears_foreign_vehicle()
test_cancel_latest_event_replays_state()                  // transfer then cancel → back to old department
test_only_latest_event_can_be_cancelled()                 // 422
test_sole_hire_cannot_be_cancelled()                      // 422
test_rehire_after_dismissal()                             // status active again
// DriverEmployeeLinkTest
test_new_driver_requires_active_employee_of_same_org()
test_employee_can_have_only_one_driver_profile()
// DriverEmployeeBackfillTest (Unit, uses RefreshDatabase)
test_creates_employee_per_driver_and_links_it()
test_splits_messy_names()   // '  Karimov   Anvar  Olimovich ' → Karimov/Anvar/Olimovich; 'Karimov' → Karimov/''
test_is_idempotent()        // second run returns 0
```

- [ ] **Step 2: Run** `php artisan test --filter='EmployeeTest|EmploymentEventTest|DriverEmployeeLinkTest|DriverEmployeeBackfillTest'` — Expected: FAIL.
- [ ] **Step 3: Implement** models, service, backfill + migration, resources, policy (view/update scoped by `organization_id`; `manage` = `employees.manage`), controllers, routes, `DriverController::store` change.
- [ ] **Step 4: Run** `php artisan test` — Expected: PASS (existing driver tests updated where they create drivers without an employee: use `Driver::factory()` with an employee, i.e. `DriverFactory` gets `'employee_id' => Employee::factory()`).
- [ ] **Step 5: Commit** `feat(hr): add employees, employment events and driver link`

---

### Task 5: Due-date rules, absences, leave balance

**Files:**
- Create: `backend/app/Support/Hr/DueDates.php`
- Create: `backend/app/Models/EmployeeAbsence.php`
- Create: `backend/app/Services/Hr/LeaveBalance.php`
- Create: `backend/app/Http/Controllers/Api/AbsenceController.php`
- Create: `backend/database/factories/EmployeeAbsenceFactory.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Unit/Hr/DueDatesTest.php`, `backend/tests/Unit/Hr/LeaveBalanceTest.php`, `backend/tests/Feature/Hr/AbsenceTest.php`

**Interfaces:**
- Produces: `DueDates::medicalNextDue(CarbonInterface $examDate, ?int $months, string $result): ?CarbonImmutable` (null when `$months` null or result `unfit`); `DueDates::briefingNextDue(CarbonInterface $date, string $type, ?int $months): ?CarbonImmutable` (only `primary|repeated|unscheduled`); `DueDates::ppeDue(CarbonInterface $issuedOn, ?int $months): ?CarbonImmutable`; `DueDates::absenceDays(CarbonInterface $start, CarbonInterface $end): int` (inclusive). Month addition uses `addMonthsNoOverflow`.
- Produces: `LeaveBalance::forYear(Employee $employee, int $year): array{entitlement: int, used: int, remaining: int}` — counts actual `annual_leave` days clipped to the year.
- Produces: `Employee::isAbsentOn(CarbonInterface $date): bool` (actual records only).
- Produces: routes `GET/POST employees/{employee}/absences` (`?year=`), `PUT/DELETE absences/{absence}`, `GET employees/{employee}/leave-balance?year=`. Manage = `employees.manage`, view = `employees.view`.

- [ ] **Step 1: Write the failing tests**

```php
// DueDatesTest
test_medical_next_due_adds_months()            // 2026-01-31 + 1 → 2026-02-28
test_medical_next_due_is_null_for_unfit_or_no_periodicity()
test_briefing_next_due_only_for_resetting_types()   // 'targeted' → null
test_absence_days_inclusive()                  // 2026-03-01..2026-03-01 → 1
// LeaveBalanceTest
test_balance_clips_leave_crossing_new_year()   // Dec 25 – Jan 10: 7 days in 2026, 10 in 2027
test_planned_leave_does_not_reduce_balance()
// AbsenceTest
test_overlapping_actual_absences_are_rejected()      // 422 'start_date'
test_planned_and_actual_may_overlap()
test_absence_before_hire_or_after_dismissal_is_rejected()
test_days_are_computed_on_save()
test_viewer_cannot_create_absence()                  // 403
```

- [ ] **Step 2: Run** `php artisan test --filter='DueDatesTest|LeaveBalanceTest|AbsenceTest'` — Expected: FAIL.
- [ ] **Step 3: Implement** per Interfaces.
- [ ] **Step 4: Run** the same filter — Expected: PASS.
- [ ] **Step 5: Commit** `feat(hr): add absences, leave balance and due-date rules`

---

### Task 6: Medical exams

**Files:**
- Create: `backend/app/Models/MedicalExam.php`
- Create: `backend/app/Http/Resources/MedicalExamResource.php`
- Create: `backend/app/Policies/MedicalExamPolicy.php`
- Create: `backend/app/Http/Controllers/Api/MedicalExamController.php`
- Create: `backend/database/factories/MedicalExamFactory.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Hr/MedicalExamTest.php`

**Interfaces:**
- Consumes: `DueDates::medicalNextDue` (Task 5).
- Produces: `MedicalExam::status(?CarbonInterface $today = null): string` → `unfit` if result unfit; `overdue` if `next_due_date` < today; `due_soon` if ≤ today + `hr.alert_due_soon_days`; else `valid`.
- Produces: routes `GET/POST employees/{employee}/medical-exams`, `PUT/DELETE medical-exams/{exam}`, `GET medical-exams/{exam}/document`, `GET medical-exams?organization_id=&state=overdue|due_soon` (latest exam per active employee, scoped).
- Store/update: multipart, `document` optional `mimes:pdf,jpg,jpeg,png|max:10240`, stored on `local` disk under `hr/medical/{employee_id}/`; `next_due_date` defaults from the position, request value wins when present. Delete removes the file.
- `MedicalExamResource` always returns `id, employee_id, type, exam_date, next_due_date, status`; adds `institution, result, restrictions, notes, has_document` only when the user has `ohs.medical.view`. Document download requires `ohs.medical.view`.
- Policy: view = `ohs.view` or `ohs.medical.view` (+ org access via employee); manage = `ohs.medical.manage`.

- [ ] **Step 1: Write the failing tests**

```php
test_next_due_defaults_from_position_periodicity()     // position 12 months, exam 2026-01-10 → 2027-01-10
test_explicit_next_due_overrides_default()
test_user_without_medical_view_gets_no_medical_details() // ohs.view only: no 'result', no 'restrictions' keys; status present
test_document_download_requires_medical_view()          // 403 for ohs.view only; 200 with ohs.medical.view (Storage::fake('local'))
test_deleting_exam_removes_document()
test_future_exam_date_is_rejected()
test_org_list_returns_overdue_latest_exams_only()       // older overdue + newer valid exam → employee not listed
```

- [ ] **Step 2: Run** `php artisan test --filter=MedicalExamTest` — Expected: FAIL.
- [ ] **Step 3: Implement** per Interfaces.
- [ ] **Step 4: Run** the same filter — Expected: PASS.
- [ ] **Step 5: Commit** `feat(ohs): add medical exams with privacy-aware responses`

---

### Task 7: Safety briefings

**Files:**
- Create: `backend/app/Models/SafetyBriefing.php`
- Create: `backend/app/Policies/SafetyBriefingPolicy.php`
- Create: `backend/app/Http/Controllers/Api/SafetyBriefingController.php`
- Create: `backend/database/factories/SafetyBriefingFactory.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Hr/SafetyBriefingTest.php`

**Interfaces:**
- Consumes: `DueDates::briefingNextDue` (Task 5); `Employee::briefings()` (Task 4).
- Produces: `SafetyBriefing::participants()` (belongsToMany Employee, table `safety_briefing_participants`, pivot `next_due_date`); `SafetyBriefing::syncParticipants(array $employeeIds): void` — computes each participant's `next_due_date` from their position and records one `participants_synced` audit entry (`old_values`/`new_values` = sorted id lists).
- Produces: `apiResource safety-briefings` — body `organization_id, type, conducted_on, topic, instructor_employee_id?, instructor_name?, notes?, participant_ids[] (min 1)`; index filters `organization_id, type, from, to`, paginated, with participant count; show includes participants with `next_due_date`. Update re-syncs participants and recomputes due dates when `conducted_on` or `type` changes.
- Validation: participants must be active employees of `organization_id` → else 422 on `participant_ids`.
- Policy: view = `ohs.view`; manage = `ohs.briefings.manage`; scoped by `organization_id`.

- [ ] **Step 1: Write the failing tests**

```php
test_group_briefing_sets_next_due_per_participant_position()   // positions 6 and 12 months → two different dates
test_targeted_briefing_sets_no_next_due()
test_dismissed_or_foreign_employee_cannot_participate()         // 422 participant_ids
test_changing_date_recomputes_next_due()
test_participant_changes_are_audited()                          // audit event 'participants_synced'
test_ohs_view_cannot_create_briefing()                          // 403
```

- [ ] **Step 2: Run** `php artisan test --filter=SafetyBriefingTest` — Expected: FAIL.
- [ ] **Step 3: Implement** per Interfaces.
- [ ] **Step 4: Run** the same filter — Expected: PASS.
- [ ] **Step 5: Commit** `feat(ohs): add safety briefings with participant due dates`

---

### Task 8: PPE issues and the employee PPE card

**Files:**
- Create: `backend/app/Models/PpeIssue.php`
- Create: `backend/app/Policies/PpeIssuePolicy.php`
- Create: `backend/app/Http/Controllers/Api/EmployeePpeController.php`
- Create: `backend/database/factories/PpeIssueFactory.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Hr/PpeIssueTest.php`

**Interfaces:**
- Consumes: `DueDates::ppeDue` (Task 5); `PositionPpeNorm` (Task 3).
- Produces: routes `GET employees/{employee}/ppe` → `{issues: [...], norms: [{ppe_item, quantity, wear_months, current_issue: PpeIssue|null}]}`; `POST employees/{employee}/ppe-issues` (`ppe_item_id, quantity, size?, issued_on, notes?`); `POST ppe-issues/{issue}/close` (`status: returned|written_off`, `closed_on`).
- Rules: `due_on` from the employee position's norm for the item, else `ppe_items.default_wear_months`, else null; an existing `in_use` issue of the same item becomes `replaced` with `closed_on = issued_on`; closing a non-`in_use` issue → 422.
- Policy: view = `ohs.view`; manage = `ohs.ppe.manage`; scoped via employee.

- [ ] **Step 1: Write the failing tests**

```php
test_due_on_uses_position_norm_then_item_default()
test_reissue_replaces_previous_in_use_issue()        // old status 'replaced', closed_on == new issued_on
test_card_shows_uncovered_norms()                    // norm without issue → current_issue null
test_close_marks_returned()
test_closing_twice_is_rejected()                     // 422
```

- [ ] **Step 2: Run** `php artisan test --filter=PpeIssueTest` — Expected: FAIL.
- [ ] **Step 3: Implement** per Interfaces.
- [ ] **Step 4: Run** the same filter — Expected: PASS.
- [ ] **Step 5: Commit** `feat(ohs): add PPE issuing, replacement and employee PPE card`

---

### Task 9: Alerts and driver clearance

**Files:**
- Create: `backend/app/Services/Hr/ComplianceService.php`
- Create: `backend/app/Services/Hr/DriverClearance.php`
- Create: `backend/app/Http/Controllers/Api/HrAlertController.php`
- Modify: `backend/app/Http/Controllers/Api/DriverController.php` (`clearance` action; `show` embeds `clearance`)
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Hr/{HrAlertTest,DriverClearanceTest}.php`

**Interfaces:**
- Consumes: `MedicalExam::status()` (Task 6), briefing pivot `next_due_date` (Task 7), `PpeIssue.due_on` (Task 8), `Employee::isAbsentOn()` (Task 5), `drivers.license_expiry`.
- Produces: `ComplianceService::alerts(User $user, array $filters = []): Collection` of `{category: medical|briefing|ppe|license, state: overdue|due_soon, employee_id, employee_name, organization_id, department, position, due_date: ?string, days_left: ?int, detail: string}`; filters `organization_id, category, state`. Only active employees of accessible organizations. Missing required exam/briefing → `state = overdue`, `due_date = null`. Sorted by `days_left` ascending, nulls first.
- Produces: `ComplianceService::summary(User $user): array` — `['medical' => ['overdue' => int, 'due_soon' => int], 'briefing' => [...], 'ppe' => [...], 'license' => [...]]`.
- Produces: `DriverClearance::check(Driver $driver, ?CarbonInterface $on = null): array{admitted: bool, reasons: list<array{code: string, message: string}>}`; codes `no_employee`, `employee_dismissed`, `driver_inactive`, `license_expired`, `medical_missing`, `medical_overdue`, `medical_unfit`, `briefing_overdue`, `absent`.
- Produces: routes `GET hr/alerts`, `GET hr/alerts/summary` (`ohs.view` or `employees.view`), `GET drivers/{driver}/clearance` (`drivers.view`).

- [ ] **Step 1: Write the failing tests**

```php
// HrAlertTest
test_overdue_and_due_soon_medical_exams_are_listed()
test_missing_required_exam_counts_as_overdue()        // position requires exam, none recorded
test_briefing_and_ppe_and_license_alerts()
test_dismissed_employees_are_excluded()
test_alerts_are_scoped_to_users_organization()
test_user_without_organization_gets_empty_alerts()
test_summary_counts_by_category()
// DriverClearanceTest
test_driver_with_valid_records_is_admitted()
test_each_reason_is_reported()                         // one case per code via data provider
test_driver_show_embeds_clearance()
```

- [ ] **Step 2: Run** `php artisan test --filter='HrAlertTest|DriverClearanceTest'` — Expected: FAIL.
- [ ] **Step 3: Implement** with eager loads (`latestMedicalExam`, latest briefing participation via `ofMany`, in-use PPE issues); no per-employee queries in loops.
- [ ] **Step 4: Run** the same filter — Expected: PASS.
- [ ] **Step 5: Commit** `feat(hr): add compliance alerts and driver clearance`

---

### Task 10: HR and OHS reports

**Files:**
- Create: `backend/app/Http/Controllers/Api/HrReportController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Hr/HrReportTest.php`

**Interfaces:**
- Produces: `GET hr/reports/headcount?date&organization_id` → rows `{organization, department, count}` (employees active on the date, from employment events); `movement?from&to` → `{hired: [...], transferred: [...], dismissed: [...], counts}`; `absent?date` → employees with actual absence covering the date + type; `leave-schedule?year` → per employee `{planned_days, actual_days, entitlement}`; `briefing-journal?from&to` → briefings with participants; `ppe-issues?from&to` → issues in period; `ppe-demand?months=3|6|12` → `{ppe_item, size, quantity}` summed from in-use issues with `due_on` within the window plus uncovered norms. All take `organization_id`, scoped; `headcount, movement, absent, leave-schedule` need `employees.view`; the rest `ohs.view`.

- [ ] **Step 1: Write the failing tests** — one per report: build a small scenario with factories and assert the exact rows (e.g. headcount on a date before a transfer counts the employee in the old department; ppe-demand for 3 months includes an issue due in 2 months and an uncovered norm, excludes one due in 5 months).
- [ ] **Step 2: Run** `php artisan test --filter=HrReportTest` — Expected: FAIL.
- [ ] **Step 3: Implement** per Interfaces; headcount on a date replays non-cancelled events up to that date per employee.
- [ ] **Step 4: Run** the same filter — Expected: PASS.
- [ ] **Step 5: Commit** `feat(hr): add HR and OHS report endpoints`

---

### Task 11: Employee import

**Files:**
- Modify: `backend/composer.json` (`composer require openspout/openspout`; pick the newest release supporting PHP 8.3)
- Create: `backend/app/Services/Hr/EmployeeImporter.php`
- Create: `backend/app/Http/Controllers/Api/EmployeeImportController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Hr/EmployeeImportTest.php`

**Interfaces:**
- Consumes: `EmploymentService::hire` (Task 4).
- Produces: `EmployeeImporter::validateFile(UploadedFile $file): array{rows: list<array<string, mixed>>, errors: list<array{row: int, field: string, message: string}>}` and `EmployeeImporter::import(array $rows, User $by): int`.
- Template columns (header row, in this order): `tashkilot_kodi, bolim, lavozim, familiya, ism, otasining_ismi, jshshir, pasport, tugilgan_sana, jinsi, telefon, tabel_raqami, bandlik_turi, ishga_qabul_sanasi, buyruq_raqami, buyruq_sanasi`. `tashkilot_kodi` = `organizations.code`; `bolim`/`lavozim` match existing names exactly (case-insensitive), unknown → error; `jinsi` `erkak|ayol`; `bandlik_turi` `shtat|shartnoma|yarim_stavka` → `staff|contract|part_time`; dates `YYYY-MM-DD` or `DD.MM.YYYY` or xlsx date cells; CSV delimiter detected from the header line (`;` or `,`), BOM stripped.
- Produces: routes `GET employees/import-template` (xlsx download) and `POST employees/import` (`file` xlsx/csv ≤ 5 MB, `dry_run` bool): dry run → `{valid_rows, errors}`; commit with any error → 422 `{errors}` and nothing written; else `{imported}`. Needs `employees.manage` and access to every organization in the file.

- [ ] **Step 1: Write the failing tests**

```php
test_dry_run_reports_row_errors()                 // bad PINFL on row 3 → errors[0] == ['row' => 3, 'field' => 'jshshir', ...]
test_duplicate_pinfl_in_file_and_db_is_reported()
test_commit_with_errors_writes_nothing()          // 422, employees count unchanged
test_commit_creates_employees_with_hire_events()
test_semicolon_csv_with_bom_and_dotted_dates_parses()
test_rows_for_inaccessible_organization_are_rejected()
```

- [ ] **Step 2: Run** `php artisan test --filter=EmployeeImportTest` — Expected: FAIL.
- [ ] **Step 3: Implement** per Interfaces.
- [ ] **Step 4: Run** the same filter — Expected: PASS.
- [ ] **Step 5: Commit** `feat(hr): add employee import from xlsx/csv`

---

### Task 12: Backups and scheduler

**Files:**
- Create: `backend/app/Support/BackupRetention.php`
- Create: `backend/app/Console/Commands/BackupCommand.php`
- Modify: `backend/routes/console.php` (`Schedule::command('app:backup')->dailyAt('02:00')`)
- Modify: `backend/Dockerfile` (add the MySQL client package), `docker-compose.yml` (service `scheduler`: same image/env/volumes as `queue`, `command: php artisan schedule:work`; named volume `backups` mounted at `/backups`; `BACKUP_PATH=/backups` in `.env.docker` example)
- Modify: `README.md` (backup location, retention, restore commands)
- Test: `backend/tests/Unit/BackupRetentionTest.php`, `backend/tests/Feature/BackupCommandTest.php`

**Interfaces:**
- Produces: `BackupRetention::toDelete(array $backups, CarbonInterface $now, int $keepDays, int $keepMonths): list<string>` — `$backups` maps a set name (e.g. `20261021-020000`) to its timestamp; keep every set newer than `$keepDays` days and the earliest set of each of the last `$keepMonths` calendar months; return the rest.
- Produces: command `app:backup` — refuses (exit 1, message) unless the default connection is `mysql`; runs `mysqldump --single-transaction --routines --no-tablespaces` for the configured DB via `Process`, gzip to `db-{stamp}.sql.gz`; tars `storage/app/private` to `files-{stamp}.tar.gz`; deletes both files of every set returned by `toDelete`; prints what was written and removed.

- [ ] **Step 1: Write the failing tests**

```php
// BackupRetentionTest
test_keeps_last_30_days_and_first_of_each_of_12_months()
test_deletes_extra_sets_within_a_month()
// BackupCommandTest
test_command_runs_dump_and_archive_and_prunes_old_sets()   // config mysql connection, Process::fake(), temp BACKUP_PATH with old files → old pair removed
test_command_refuses_non_mysql_connection()                // sqlite → exit code 1
```

- [ ] **Step 2: Run** `php artisan test --filter='BackupRetentionTest|BackupCommandTest'` — Expected: FAIL.
- [ ] **Step 3: Implement** per Interfaces.
- [ ] **Step 4: Run** the same filter — Expected: PASS.
- [ ] **Step 5: Verify in Docker** — `docker compose build backend && docker compose run --rm backend php artisan app:backup`, then restore the dump into a scratch database: `gunzip -c <file> | docker compose exec -T db mysql -uroot -p$DB_ROOT_PASSWORD -e 'CREATE DATABASE restore_check' && ... mysql restore_check`. Expected: restore succeeds and `SELECT COUNT(*) FROM restore_check.users` matches. Drop `restore_check` afterwards. If the Alpine client (MariaDB's `mysqldump`) fails against MySQL 8.4, stop and report the error to the user instead of improvising another dump path.
- [ ] **Step 6: Commit** `feat(ops): add scheduled database and file backups with retention`

---

### Task 13: Frontend foundation (API client, routes, sidebar, dashboard widget)

**Files:**
- Modify: `frontend/src/services/api.js` — add `departmentApi`, `positionApi`, `ppeItemApi`, `employeeApi` (list, create, show, update, delete, photo, history, events, addEvent, cancelEvent, absences, addAbsence, updateAbsence, deleteAbsence, leaveBalance, medicalExams, addMedicalExam (FormData), updateMedicalExam, deleteMedicalExam, medicalDocumentUrl, ppe, issuePpe, closePpeIssue, importTemplate, import), `medicalExamApi.list`, `briefingApi`, `hrAlertApi` (list, summary), `hrReportApi` (one method per report), `auditLogApi.list`; `driverApi.clearance`.
- Modify: `frontend/src/router/index.js` — routes from the spec with `meta.permission`: employees.* → `employees.view` / `employees.manage`; `/org-structure` → `org_structure.manage`; `/ohs/*` → `ohs.view`; `/hr/reports` → `['employees.view', 'ohs.view']`; `/audit` → `audit.view`.
- Modify: `frontend/src/components/layout/AppSidebar.vue` — sections "Kadrlar" (Xodimlar `mdi-account-multiple`, Tuzilma `mdi-sitemap`, Kadrlar hisobotlari `mdi-chart-box`) and "Mehnat muhofazasi" (Ogohlantirishlar `mdi-alert`, Tibbiy ko'riklar `mdi-heart-pulse`, Yo'riqnomalar `mdi-clipboard-check`, Maxsus kiyim va SHHV `mdi-hard-hat`); "Amallar jurnali" `mdi-history` under Boshqaruv.
- Create: `frontend/src/components/hr/AlertsSummaryCard.vue` (calls `hrAlertApi.summary`, four counters linking to `/ohs/alerts?category=`), placed in `DashboardView.vue` when `auth.canAny(['ohs.view','employees.view'])`.
- Create: placeholder views for every new route (title only) so the build resolves imports; later tasks replace them.

- [ ] **Step 1: Implement** the files above.
- [ ] **Step 2: Build** `npm run build` — Expected: success, no unresolved imports.
- [ ] **Step 3: Check by hand** — run backend (`php artisan serve --port=8010`) and `npm run dev` (Vite proxy to 8010 if needed); log in as a user with role `hr`: the Kadrlar section shows, Mehnat muhofazasi partly (`ohs.view`), dashboard shows the alerts card; a `viewer` sees neither.
- [ ] **Step 4: Commit** `feat(hr-ui): add HR API client, routes, sidebar and dashboard alerts`

---

### Task 14: Frontend — org structure and PPE catalog

**Files:**
- Create: `frontend/src/views/hr/OrgStructureView.vue` — organization select (all-org users) → department tree (`v-treeview` or nested list) with add/rename/delete dialogs; positions table (name, medical months, briefing months) with edit dialog and "SHHV me'yorlari" dialog editing the norm set (`PUT positions/{id}/ppe-norms`).
- Create: `frontend/src/views/ohs/PpeView.vue` — tab "Katalog" (PPE items CRUD, `ohs.ppe.manage`) and tab "Berilganlar" (issue statement from `hrReportApi.ppeIssues`, CSV export).

- [ ] **Step 1: Implement**; 422 messages shown with the existing snackbar/alert pattern used in `DriverFormView.vue`.
- [ ] **Step 2: Build** `npm run build` — Expected: success.
- [ ] **Step 3: Check by hand** as `hr`: create a department, a child department, a position with 12/6 months and two norms; delete guard message appears for a department with children.
- [ ] **Step 4: Commit** `feat(hr-ui): add org structure and PPE catalog views`

---

### Task 15: Frontend — employee list, form, import

**Files:**
- Create: `frontend/src/views/hr/EmployeeListView.vue` — `v-data-table-server` (page size 50) with filters organization, department, position, status, search; masked PINFL column; "Import" and "Yangi xodim" buttons for `employees.manage`.
- Create: `frontend/src/views/hr/EmployeeFormView.vue` — card fields; on create also the hire block (date, order number/date, department, position); on edit no assignment fields.
- Create: `frontend/src/views/hr/EmployeeImportView.vue` — template download, file upload, dry-run table of errors (row, field, message), commit button enabled only with zero errors.

- [ ] **Step 1: Implement.**
- [ ] **Step 2: Build** `npm run build` — Expected: success.
- [ ] **Step 3: Check by hand**: create an employee; list shows masked PINFL; import a 3-row CSV with one bad row → error shown, fix → imported.
- [ ] **Step 4: Commit** `feat(hr-ui): add employee list, form and import`

---

### Task 16: Frontend — employee card

**Files:**
- Create: `frontend/src/views/hr/EmployeeDetailView.vue` (header: photo, name, organization/department/position, status, driver clearance chip if a driver) with tab components in `frontend/src/components/hr/`: `EmploymentEventsTab.vue` (timeline + transfer/dismiss/rehire dialogs, cancel latest), `AbsencesTab.vue` (year select, balance, list, add/edit), `MedicalExamsTab.vue` (list with status chips, add with document upload, details only when `ohs.medical.view`), `BriefingsTab.vue` (employee's participations, read-only), `PpeTab.vue` (norm coverage + issues, issue/close dialogs), `HistoryTab.vue` (audit entries, only with `audit.view`).

- [ ] **Step 1: Implement**; each tab loads lazily on first open; buttons hidden without the manage permission.
- [ ] **Step 2: Build** `npm run build` — Expected: success.
- [ ] **Step 3: Check by hand**: transfer then cancel; add overlapping absence → error; as `ohs` add a medical exam with a PDF; as `hr` the result text is not visible; issue the same PPE item twice → first shows "Almashtirilgan".
- [ ] **Step 4: Commit** `feat(hr-ui): add employee card with HR and OHS tabs`

---

### Task 17: Frontend — OHS pages, HR reports, audit log

**Files:**
- Create: `frontend/src/views/ohs/AlertsView.vue` (filters category/state/organization from query string, table with days left, link to employee card)
- Create: `frontend/src/views/ohs/MedicalExamListView.vue` (`medicalExamApi.list` with state filter)
- Create: `frontend/src/views/ohs/BriefingListView.vue` + `frontend/src/components/hr/BriefingDialog.vue` (create/edit: org, type, date, topic, instructor employee or name, participant multi-select of active employees with "select department" shortcut)
- Create: `frontend/src/views/hr/HrReportsView.vue` (tabs per report, parameters, table, CSV export with BOM and print — same helpers as `ReportsView.vue`; briefing journal print layout lists participants)
- Create: `frontend/src/views/AuditLogView.vue` (filters user, employee, event, dates; server pagination; expandable old/new values)

- [ ] **Step 1: Implement.**
- [ ] **Step 2: Build** `npm run build` — Expected: success.
- [ ] **Step 3: Check by hand**: an overdue exam appears on Alerts and in the dashboard counter; a group briefing for 3 employees appears in the journal print; CSV opens in Excel with correct Cyrillic/Uzbek characters.
- [ ] **Step 4: Commit** `feat(hr-ui): add OHS pages, HR reports and audit log view`

---

### Task 18: Frontend — driver integration

**Files:**
- Modify: `frontend/src/views/drivers/DriverFormView.vue` — on create: organization → employee autocomplete (`employeeApi.list({organization_id, status: 'active', without_driver: 1})`); name/PINFL/phone shown read-only from the employee. Edit keeps license fields editable, identity read-only.
- Modify: `backend/app/Http/Controllers/Api/EmployeeController.php` — `without_driver` filter (with a test added to `EmployeeTest`: `test_without_driver_filter_excludes_employees_with_driver_profile`).
- Modify: `frontend/src/views/drivers/DriverDetailView.vue` — clearance chip + reasons list; link to employee card.
- Modify: `frontend/src/views/waybills/WaybillCreateView.vue` — after a driver is chosen, call `driverApi.clearance` and show a warning alert listing reasons (not blocking).

- [ ] **Step 1: Write the failing backend test** for `without_driver`; run `php artisan test --filter=test_without_driver_filter` — Expected: FAIL; implement; run — Expected: PASS.
- [ ] **Step 2: Implement** the three views.
- [ ] **Step 3: Build** `npm run build` — Expected: success.
- [ ] **Step 4: Check by hand**: create a driver from an employee; a driver with no medical exam shows a warning on waybill creation but the waybill can still be saved.
- [ ] **Step 5: Commit** `feat(hr): link driver form to employees and show clearance warnings`

---

### Task 19: Audit existing models, final verification

**Files:**
- Modify: `backend/app/Models/{Vehicle,Driver,Waybill,FuelStation,FuelTransaction,FuelNorm,Organization,User,Role}.php` — `use Auditable` (User: `password`, `remember_token` already hidden, so skipped).
- Test: `backend/tests/Feature/Hr/AuditLogTest.php` — add `test_existing_models_are_audited` (update a waybill and a vehicle → entries exist).

- [ ] **Step 1: Write the failing test**; run `php artisan test --filter=test_existing_models_are_audited` — Expected: FAIL.
- [ ] **Step 2: Implement**; run the filter — Expected: PASS.
- [ ] **Step 3: Full verification** — `php artisan test` (all green, count ≥ baseline + new), `vendor/bin/pint --test` (clean), `npm run build` (success).
- [ ] **Step 4: Local database** — ask the user before migrating the local MySQL `utg_transport`; on approval run `php artisan migrate` (never `migrate:fresh`) and confirm the backfill linked every driver: `php artisan tinker --execute="echo App\Models\Driver::whereNull('employee_id')->count();"` → `0`.
- [ ] **Step 5: Commit** `feat(audit): audit existing models`
- [ ] **Step 6:** Use superpowers:finishing-a-development-branch.
