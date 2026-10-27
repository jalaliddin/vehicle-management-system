# Stage 2 — Fuel (GSM) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn fuel accounting into documented operations — supplier receipts, refuels with odometer readings, fueler shifts reconciled by measurement, inventory acts — with turnover, refuel, consumption and discrepancy reports and a phone-friendly fueler page.

**Architecture:** `fuel_transactions` stays the single ledger, extended with `kind` and document/vehicle fields; a `FuelLedger` service owns every balance-changing write under a station row lock. Shifts, suppliers and fueler assignments are new tables; vehicles gain tank capacity and odometer. Vue views follow the stage-1 patterns.

**Tech Stack:** Laravel 13 / PHP 8.3, MySQL 8.4 (prod) / SQLite (tests), Vue 3 + Vuetify 3.

**Spec:** `docs/superpowers/specs/2026-10-27-fuel-module-design.md`

## Global Constraints

- Branch `feature/fuel-gsm` (created from `main` at 1473933).
- Tests: `docker run --rm -u 1000:1000 -v $PWD/backend:/var/www/html -w /var/www/html utg-php-test:8.3 php artisan test --compact` (host PHP has no pdo_sqlite). No MySQL-only SQL in queries; index names ≤ 64 characters.
- Quantities are decimal(10,2). Balance arithmetic uses integer hundredths via `App\Support\Fuel\Quantity` — no float accumulation, no bcmath (not in the test image).
- Every balance-changing write goes through `App\Services\Fuel\FuelLedger` inside `DB::transaction` with `FuelStation::lockForUpdate()`.
- User-facing messages in Uzbek Latin, exactly as listed in the spec's Rules section; Laravel validation messages come from `lang/uz/validation.php` (extend its `attributes` for new fields).
- `config('fuel.units')` = `['benzin' => 'L', 'dizel' => 'L', 'gaz' => 'm³']`, `config('fuel.overconsumption_percent')` = 10 (env `FUEL_OVERCONSUMPTION_PERCENT`).
- Authorization through policies/gates like stage 1; station access via `User::canAccessStation()`.
- No new dependencies. `vendor/bin/pint --dirty --format agent` before each backend commit; commits end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. Decimal quantities entered as strings like `"33.33"` three times from a 100.00 balance — expect exactly `0.01` left, never a float residue or a false "not enough fuel" (test in Task 4).
2. A vehicle with no odometer yet (`null`) gets its first refuel — expect it accepted and the odometer set (test in Task 4).
3. Cancelling an older refuel after a newer refuel of the same vehicle — expect the odometer to stay at the newer reading (test in Task 4).
4. Assigning a fueler who belongs to another organization than the station — expect 422 (test in Task 2).
5. A consumption report period where a vehicle has one refuel, or two refuels at the same odometer — expect the vehicle left out, no division by zero (test in Task 6).

---

### Task 0: Preconditions

- [ ] **Step 1:** `git branch --show-current` → `feature/fuel-gsm`; `git status --short backend frontend` → no output.
- [ ] **Step 2:** Run the suite. Expected: 250 passed.

---

### Task 1: Schema, models, role, config, opening balances

**Files:**
- Create: `backend/database/migrations/2026_10_28_100001_create_fuel_operations_tables.php` (fuel_suppliers, fuel_station_user, fuel_shifts, fuel_transactions columns, vehicles columns — per spec Data model)
- Create: `backend/database/migrations/2026_10_28_100002_add_fueler_role.php` (role `fueler` "Yoqilg'i quyuvchi": `fuel.view`, `fuel.transactions.create`; down deletes)
- Create: `backend/database/migrations/2026_10_28_100003_record_opening_fuel_balances.php` (DML: one `adjustment` row per station with balance > 0: `type=in`, `amount=current_balance`, `fuel_type` of station, `document_number="Boshlang'ich qoldiq"`, `notes="Tizimga o'tishdagi qoldiq"`; down deletes rows with that document number)
- Create: `backend/config/fuel.php`; `backend/app/Support/Fuel/Quantity.php`
- Create: `backend/app/Models/{FuelSupplier,FuelShift}.php` (Auditable) + factories `{FuelSupplierFactory,FuelShiftFactory}`; `FuelTransactionFactory`
- Modify: `backend/app/Models/{FuelTransaction,FuelStation,Vehicle}.php`, `backend/app/Enums/Permission.php`, `backend/lang/uz/validation.php`
- Modify: `backend/tests/Feature/Rbac/AuthPermissionsTest.php` and `RoleManagementTest.php` role counts (8 → 9, 9 → 10)
- Test: `backend/tests/Feature/Fuel/FuelSchemaTest.php`, `backend/tests/Unit/Fuel/QuantityTest.php`

**Interfaces:**
- Produces: `Permission::FuelAdjust = 'fuel.adjust'` (group `fuel`, label "Yoqilg'i inventarizatsiyasi").
- Produces: `Quantity::toHundredths(string|int|float $value): int` (rounds half up to 2 decimals), `Quantity::format(int $hundredths): string` (`"12.50"`).
- Produces: `FuelTransaction` fillable/casts for new columns, relations `shift()`, `supplier()`, `driver()`, `cancelledBy()`, scope `active()` (= `whereNull('cancelled_at')`); constants `KIND_RECEIPT='receipt'`, `KIND_REFUEL='refuel'`, `KIND_ADJUSTMENT='adjustment'`, `OPENING_DOCUMENT="Boshlang'ich qoldiq"`.
- Produces: `FuelStation::fuelers()` (belongsToMany User via `fuel_station_user`), `shifts()`, `openShift()` (hasOne where status open), `unit(): string` (from config); `User::fuelStations()`.
- Produces: `FuelShift` (`station()`, `transactions()`, `openedBy()`, `closedBy()`, casts decimals `decimal:2`, datetimes).
- Produces: `Vehicle` fillable `tank_capacity`, `odometer` (casts decimal:2 / integer).

- [ ] **Step 1: Write the failing tests**

```php
test_fueler_role_is_seeded()                                   // Role slug 'fueler' has exactly ['fuel.view','fuel.transactions.create']
test_opening_balance_rows_match_station_balances()             // run the DML migration class against two stations (3200.00, 0) → one adjustment row 3200.00, none for the empty one
test_station_unit_comes_from_config()                          // fuel_type 'gaz' → 'm³'
// QuantityTest
test_to_hundredths_rounds_half_up()                            // '33.335' → 3334, 12 → 1200, '0.1' → 10
test_format_pads_two_decimals()                                // 1 → '0.01', 125050 → '1250.50'
```

- [ ] **Step 2: Run** `--filter='FuelSchemaTest|QuantityTest'` — Expected: FAIL.
- [ ] **Step 3: Implement** per Interfaces and spec Data model.
- [ ] **Step 4: Run** the full suite — Expected: PASS (role-count tests updated).
- [ ] **Step 5: Commit** `feat(fuel): add fuel operations schema, fueler role and config`

---

### Task 2: Station access, fueler assignment, suppliers

**Files:**
- Modify: `backend/app/Models/User.php` (`canAccessStation`), `backend/app/Models/FuelStation.php` (`scopeAccessibleBy(Builder $q, User $user)`), `backend/app/Policies/FuelStationPolicy.php`
- Modify: `backend/app/Http/Controllers/Api/FuelController.php` (monitoring, stationIndex, transactions use `accessibleBy`; `assignFuelers`)
- Create: `backend/app/Policies/FuelSupplierPolicy.php`, `backend/app/Http/Controllers/Api/FuelSupplierController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Fuel/StationAccessTest.php`, `backend/tests/Feature/Fuel/FuelSupplierTest.php`

**Interfaces:**
- Produces: `User::canAccessStation(FuelStation $station): bool` — organization access AND (user has no station assignments OR station assigned).
- Produces: `FuelStationPolicy::operate(User, FuelStation): bool` = `fuel.transactions.create` + `canAccessStation`; `view` = `fuel.view` + `canAccessStation`; `adjust` = `fuel.adjust` + `canAccessStation`; `update` unchanged plus `canAccessStation`.
- Produces: `PUT fuel/stations/{station}/fuelers` `{user_ids: int[]}` (`fuel.stations.manage`); each user must belong to the station's organization and hold `fuel.transactions.create` → else 422 on `user_ids`. Station list rows include `fuelers: [{id, name}]`.
- Produces: `apiResource fuel/suppliers` except show (view `fuel.view`, manage `fuel.stations.manage`); delete refused 422 "Ma'lumot ishlatilmoqda, o'chirib bo'lmaydi" when used by a transaction.

- [ ] **Step 1: Write the failing tests**

```php
test_assigned_fueler_sees_only_assigned_stations()          // two stations in org; fueler assigned to one → monitoring and stations list return 1
test_unassigned_user_keeps_organization_scope()
test_fueler_from_another_organization_cannot_be_assigned()  // 422 user_ids
test_user_without_operate_permission_cannot_be_assigned()   // viewer role → 422 user_ids
test_transactions_list_is_limited_to_accessible_stations()
test_supplier_crud_and_in_use_delete_guard()
test_fueler_cannot_manage_suppliers()                       // 403
```

- [ ] **Step 2: Run** `--filter='StationAccessTest|FuelSupplierTest'` — Expected: FAIL.
- [ ] **Step 3: Implement** per Interfaces.
- [ ] **Step 4: Run** same filter + `Rbac` — Expected: PASS.
- [ ] **Step 5: Commit** `feat(fuel): scope stations to assigned fuelers and add suppliers`

---

### Task 3: Shifts

**Files:**
- Create: `backend/app/Services/Fuel/FuelLedger.php` (shift methods now; operations in Task 4, adjustments in Task 5)
- Create: `backend/app/Http/Controllers/Api/FuelShiftController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Fuel/FuelShiftTest.php`

**Interfaces:**
- Produces: `FuelLedger::openShift(FuelStation $station, string|float $measured, User $by): FuelShift`; `FuelLedger::closeShift(FuelShift $shift, string|float $measured, ?string $note, User $by): FuelShift`. Violations throw `ValidationException` with the spec messages ("Bu shahobchada ochiq smena bor"; "Farq izohini yozing" on `discrepancy_note`; closing a closed shift → "Smena yopilgan").
- Produces: routes `GET fuel/stations/{station}/current-shift` → `{shift|null, operations: [...]}`; `POST fuel/stations/{station}/shifts` `{opening_measured}`; `POST fuel/shifts/{shift}/close` `{closing_measured, discrepancy_note?}`; `GET fuel/shifts` (filters `station_id, from, to, with_discrepancy`, paginated 50, with station + openedBy/closedBy names); `GET fuel/shifts/{shift}` → shift + operations (all kinds incl. cancelled, newest first) + totals `{receipts, refuels}` (non-cancelled, formatted strings).
- Authorization: open/close need `operate`; list/show need `view` on the station (list scoped with `accessibleBy`).

- [ ] **Step 1: Write the failing tests**

```php
test_open_shift_records_book_and_measured_balance()        // station 3200.00, measured 3190 → opening_balance '3200.00', opening_measured '3190.00'
test_second_open_shift_is_rejected()                       // 422 "Bu shahobchada ochiq smena bor"
test_close_with_discrepancy_requires_note()                // measured ≠ book, no note → 422 discrepancy_note
test_close_records_discrepancy()                           // book 3200, measured 3195 → discrepancy '-5.00', status closed
test_closed_shift_cannot_be_closed_again()
test_shift_report_totals_exclude_cancelled_operations()    // seed via factories: receipt 100, refuel 40, cancelled refuel 10 → receipts '100.00', refuels '40.00'
test_shift_list_filters_discrepancies()
test_unassigned_fueler_cannot_open_shift_on_other_station() // 403
```

- [ ] **Step 2: Run** `--filter=FuelShiftTest` — Expected: FAIL.
- [ ] **Step 3: Implement** per Interfaces.
- [ ] **Step 4: Run** same filter — Expected: PASS.
- [ ] **Step 5: Commit** `feat(fuel): add fueler shifts with measured reconciliation`

---

### Task 4: Receipts, refuels, cancellation, vehicle lookup

**Files:**
- Modify: `backend/app/Services/Fuel/FuelLedger.php`
- Create: `backend/app/Http/Controllers/Api/FuelOperationController.php`
- Modify: `backend/app/Http/Controllers/Api/FuelController.php` (remove `addTransaction`; `transactions` gains `kind`, `station_id`, `vehicle_id` filters and loads supplier/driver/shift), `backend/app/Http/Controllers/Api/VehicleController.php` (`tank_capacity` nullable numeric 0–2000, `odometer` nullable integer ≥ 0)
- Modify: `backend/routes/api.php` (drop `fuel/stations/{station}/transaction`)
- Modify: `backend/tests/Feature/Rbac/ResourceAuthorizationTest.php` only if it references the removed route
- Test: `backend/tests/Feature/Fuel/FuelOperationTest.php`

**Interfaces:**
- Consumes: `Quantity` (Task 1), `FuelLedger` shifts (Task 3), `User::canAccessStation` (Task 2).
- Produces: `FuelLedger::receive(FuelStation $station, array $data, User $by): FuelTransaction`; `FuelLedger::refuel(FuelStation $station, array $data, User $by): FuelTransaction`; `FuelLedger::cancel(FuelTransaction $transaction, string $reason, User $by): void`. Rules and messages exactly per spec; open waybill = latest non-deleted waybill of the vehicle with status `approved|in_progress`.
- Produces: routes `POST fuel/stations/{station}/receipts`, `POST fuel/stations/{station}/refuels`, `POST fuel/transactions/{transaction}/cancel` (`operate` on the transaction's station), `GET fuel/stations/{station}/vehicles?search=` (`operate`; active vehicles of the station's organization whose state number contains the search, max 20) → `{id, state_number, model, fuel_type, fuel_type_matches, tank_capacity, odometer, driver: {id, full_name}|null, waybill: {id, waybill_number}|null}`.
- Produces: `FuelTransaction` JSON for refuels includes `vehicle`, `driver`, `waybill`, `odometer`.

- [ ] **Step 1: Write the failing tests**

```php
test_receipt_requires_open_shift()                          // 422 "Smena ochilmagan"
test_receipt_adds_to_balance_and_records_document()         // doc 1000, accepted 995 → balance +995.00, document_amount '1000.00'
test_receipt_over_capacity_is_rejected()                    // "Sig'imdan oshib ketadi"
test_refuel_links_open_waybill_and_driver()                 // in_progress waybill → waybill_id and driver_id from waybill
test_refuel_without_waybill_is_allowed()                    // waybill_id null
test_refuel_takes_vehicle_driver_when_no_waybill()          // driver with vehicle_id = vehicle → driver_id
test_refuel_checks()                                        // data provider: wrong fuel type, amount > balance, amount > tank, odometer < last, vehicle of other org, inactive vehicle → 422 with the spec message / field
test_refuel_raises_odometer_and_remembers_previous()        // odometer 15000 → 15250: vehicle 15250, previous_odometer 15000
test_first_refuel_sets_missing_odometer()                   // Review Focus 2
test_decimal_amounts_do_not_drift()                         // Review Focus 1: balance 100.00, three refuels '33.33' → '0.01'
test_cancel_reverses_balance_and_odometer()
test_cancel_older_refuel_keeps_newer_odometer()             // Review Focus 3
test_cancel_after_shift_close_is_rejected()                 // "Smena yopilgan, bekor qilib bo'lmaydi"
test_cancelling_a_receipt_cannot_make_balance_negative()
test_vehicle_lookup_reports_fuel_type_match_and_waybill()
test_old_transaction_endpoint_is_gone()                     // POST fuel/stations/{id}/transaction → 404 or 405
test_vehicle_form_accepts_tank_and_odometer()
```

- [ ] **Step 2: Run** `--filter=FuelOperationTest` — Expected: FAIL.
- [ ] **Step 3: Implement** per Interfaces; amounts compared and summed via `Quantity::toHundredths`.
- [ ] **Step 4: Run** the full suite — Expected: PASS.
- [ ] **Step 5: Commit** `feat(fuel): add receipts, refuels with odometer, and cancellation`

---

### Task 5: Inventory acts

**Files:**
- Modify: `backend/app/Services/Fuel/FuelLedger.php`, `backend/app/Http/Controllers/Api/FuelOperationController.php`, `backend/routes/api.php`
- Test: `backend/tests/Feature/Fuel/FuelAdjustmentTest.php`

**Interfaces:**
- Produces: `FuelLedger::adjust(FuelStation $station, array $data, User $by): FuelTransaction` and route `POST fuel/stations/{station}/adjustments` `{actual_balance, document_number, notes}` (`adjust` policy).

- [ ] **Step 1: Write the failing tests**

```php
test_adjustment_sets_book_balance_to_actual()               // book 3200.00, actual 3180.50 → out 19.50, balance '3180.50'
test_adjustment_during_open_shift_is_rejected()             // "Inventarizatsiya smenalar orasida o'tkaziladi"
test_zero_difference_is_rejected()
test_adjustment_needs_fuel_adjust_permission()              // fueler → 403
```

- [ ] **Step 2: Run** `--filter=FuelAdjustmentTest` — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** same filter — Expected: PASS.
- [ ] **Step 5: Commit** `feat(fuel): add inventory acts between shifts`

---

### Task 6: Reports

**Files:**
- Create: `backend/app/Support/Fuel/Consumption.php`, `backend/app/Http/Controllers/Api/FuelReportController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Unit/Fuel/ConsumptionTest.php`, `backend/tests/Feature/Fuel/FuelReportTest.php`

**Interfaces:**
- Produces: `Consumption::estimate(array $refuels): ?array` — `$refuels` = list of `['odometer' => int, 'amount' => string]` ordered by odometer then time; returns `['first_odometer' => int, 'last_odometer' => int, 'km' => int, 'litres' => string, 'per_100km' => float (2 dp)]`, or null when fewer than 2 rows or km = 0. Litres = sum of all rows except the first.
- Produces: `GET fuel/reports/turnover` → rows `{station_id, station, unit, opening, receipts, refuels, adjustments_in, adjustments_out, closing}` (strings); `refuels` → `{rows: [...], by_vehicle: [{vehicle_id, state_number, amount}], by_driver: [{driver_id, full_name, amount}], total}`; `consumption` → rows `{vehicle_id, state_number, model, first_odometer, last_odometer, km, litres, per_100km, norm, deviation_percent, over_norm}`; `discrepancies` → closed shifts rows `{shift_id, station, closed_by, closed_at, closing_balance, closing_measured, discrepancy, note}`. Params `from`, `to` (default: start of month → today), `organization_id`, `station_id`; scoped by `accessibleBy`; permission `fuel.view`.

- [ ] **Step 1: Write the failing tests**

```php
// ConsumptionTest
test_estimate_uses_litres_after_first_reading()              // (10000,'40'), (10400,'38'), (10800,'42') → km 800, litres '80.00', per_100km 10.0
test_estimate_is_null_with_fewer_than_two_readings_or_zero_km()   // Review Focus 5
// FuelReportTest
test_turnover_opening_and_closing_balances()                 // opening adjustment 1000 before period; in period receipt 500, refuel 200, cancelled refuel 50, adjustment out 10 → opening 1000.00, receipts 500.00, refuels 200.00, adjustments_out 10.00, closing 1290.00
test_refuels_report_totals_by_vehicle_and_driver()
test_consumption_flags_over_norm()                           // norm 10, actual 11.5 (+15%) → over_norm true; 10.5 → false
test_consumption_skips_vehicle_with_single_refuel()
test_discrepancies_lists_only_nonzero_closed_shifts()
test_reports_are_scoped_to_accessible_stations()
```

- [ ] **Step 2: Run** `--filter='ConsumptionTest|FuelReportTest'` — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** same filter — Expected: PASS.
- [ ] **Step 5: Commit** `feat(fuel): add turnover, refuel, consumption and discrepancy reports`

---

### Task 7: Waybill refuels

**Files:**
- Modify: `backend/app/Models/Waybill.php` (`refuels(): HasMany` FuelTransaction kind refuel, active), `backend/app/Http/Controllers/Api/WaybillController.php` (`show` loads `refuels.fuelStation:id,name`)
- Test: `backend/tests/Feature/Fuel/WaybillRefuelTest.php`

- [ ] **Step 1: Write the failing test** `test_waybill_shows_its_active_refuels()` (two refuels, one cancelled → `refuels` has 1 with station name and odometer).
- [ ] **Step 2: Run** — Expected: FAIL. **Step 3: Implement.** **Step 4: Run** — Expected: PASS.
- [ ] **Step 5: Commit** `feat(fuel): show refuels on the waybill`

---

### Task 8: Frontend foundation

**Files:**
- Modify: `frontend/src/services/api.js` — `fuelApi`: drop `addTransaction`; add `assignFuelers`, `currentShift`, `openShift`, `closeShift`, `shifts`, `shift`, `receive`, `refuel`, `cancel`, `adjust`, `vehicles`; `fuelSupplierApi` (list/create/update/delete); `fuelReportApi` (turnover/refuels/consumption/discrepancies).
- Modify: `frontend/src/router/index.js` — `/fuel/station` (`fuel.transactions.create`), `/fuel/shifts` (`fuel.view`), `/fuel/suppliers` (`fuel.stations.manage`), `/fuel/reports` (`fuel.view`).
- Modify: `frontend/src/components/layout/AppSidebar.vue` — Yoqilg'i: Monitoring, Yoqilg'i quyish (`mdi-fuel`), Shahobchalar, Smenalar (`mdi-account-clock`), Harakatlar, Yetkazib beruvchilar (`mdi-truck-delivery`), Hisobotlar (`mdi-chart-line`).
- Create: `frontend/src/components/reports/ReportTabs.vue` — props `title: string`, `reports: Array<{key, title, params, fetch, columns, rows?, summary?}>`; renders the tabs, parameter fields (`date`, `from`/`to`, `year`, `months`, organization for all-org users), table, CSV and print — the body of today's `HrReportsView.vue`.
- Modify: `frontend/src/views/hr/HrReportsView.vue` — keeps only its report config and renders `<ReportTabs>`.
- Create: `frontend/src/utils/fuel.js` — labels for kinds, units helper `unitOf(fuelType)`.
- Create: placeholder views for the new routes.

- [ ] **Step 1: Implement.** **Step 2:** `npm run build` — Expected: success.
- [ ] **Step 3: Commit** `feat(fuel-ui): add fuel API client, routes, sidebar and shared report tabs`

---

### Task 9: Fueler desk

**Files:**
- Create: `frontend/src/views/fuel/FuelDeskView.vue`, `frontend/src/components/fuel/RefuelSheet.vue`, `frontend/src/components/fuel/ReceiptSheet.vue`, `frontend/src/components/fuel/ShiftReport.vue` (printable; reused in Task 10)

Phone-first (single column under 600 px, buttons ≥ 48 px tall): station picker (only when > 1 accessible station), balance with unit, shift card (open: measured input; open shift: opened at/by, "Smenani yopish" with measured + note shown when it differs), big "Yoqilg'i quyish" / "Qabul qilish" buttons (disabled without an open shift), refuel sheet (state-number autocomplete → vehicle card with driver, waybill or "Yo'l varaqasi yo'q", last odometer, red warning when `fuel_type_matches` is false → odometer, litres), receipt sheet (supplier, document number/date, document amount, accepted amount, price), operations of the current shift with cancel (reason prompt), "Smena hisoboti" print.

- [ ] **Step 1: Implement.** **Step 2:** `npm run build` — Expected: success.
- [ ] **Step 3: Commit** `feat(fuel-ui): add phone-friendly fueler desk`

---

### Task 10: Admin pages

**Files:**
- Modify: `frontend/src/views/fuel/FuelStationView.vue` — remove the add-transaction dialog; add "Quyuvchilar" dialog (multi-select of users of the station's organization with `fuel.transactions.create`, via `userApi.list`) and "Inventarizatsiya" dialog (`fuel.adjust`).
- Create: `frontend/src/views/fuel/FuelSupplierView.vue`, `frontend/src/views/fuel/FuelShiftListView.vue` (filters, discrepancy chip, `ShiftReport` dialog with print)
- Modify: `frontend/src/views/fuel/FuelTransactionView.vue` — kind, station, vehicle, driver, odometer, document, cancelled state; filters kind/station.

- [ ] **Step 1: Implement.** **Step 2:** `npm run build` — Expected: success.
- [ ] **Step 3: Commit** `feat(fuel-ui): add suppliers, shifts and fueler assignment pages`

---

### Task 11: Fuel reports page, vehicle and waybill integration

**Files:**
- Modify: `frontend/src/views/fuel/FuelReportsView.vue` (placeholder → `<ReportTabs>` with the four reports; consumption rows over norm highlighted; refuels without waybill marked)
- Modify: `frontend/src/views/vehicles/VehicleFormView.vue` (tank capacity, odometer), `frontend/src/views/vehicles/VehicleDetailView.vue` (show both), `frontend/src/views/waybills/WaybillDetailView.vue` (refuels table: time, station, odometer, litres)

- [ ] **Step 1: Implement.** **Step 2:** `npm run build` — Expected: success.
- [ ] **Step 3: Commit** `feat(fuel-ui): add fuel reports and vehicle/waybill fuel details`

---

### Task 12: Verification

- [ ] **Step 1:** Full suite — Expected: all pass.
- [ ] **Step 2:** MySQL 8.4 in Docker (throwaway volume): `migrate:fresh --seed`, backfill, `migrate:rollback --step=3`, `migrate` — Expected: all DONE; opening-balance rows equal seeded station balances.
- [ ] **Step 3:** Playwright smoke against API in Docker + Vite on 5179 (stage-1 setup): fueler user assigned to one station → desk shows one station; open shift; receipt; refuel with odometer (waybill linked); wrong-fuel-type warning visible; cancel; close shift with discrepancy note; shift report printable; admin: suppliers CRUD, shifts list shows discrepancy, turnover report figures match the operations; vehicle form saves tank/odometer; waybill card lists the refuel; no console errors.
- [ ] **Step 4:** Tear down Docker volumes, temp Vite config. Commit any fixes found.
- [ ] **Step 5:** Final whole-branch review; then superpowers:finishing-a-development-branch.
