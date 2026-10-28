# Fuel Desk in the Mobile App Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fuelers run their whole shift (open, refuel with QR or search, receive, cancel, close, report) in the Flutter app against the existing stage-2 fuel API.

**Architecture:** A `fuel` feature in `mobile/lib/ui/features/fuel` following the app's MVVM (ViewModel + provider + go_router) over a new `FuelRepository`. The shell's tabs become permission-driven; the scanner gains a pick mode that returns a waybill id.

**Tech Stack:** Flutter (Dart 3), provider, go_router, http, mobile_scanner, flutter_test + mocktail + `http/testing.dart` MockClient.

**Spec:** `docs/superpowers/specs/2026-10-28-fuel-mobile-design.md`

## Global Constraints

- All paths below are relative to `mobile/` unless they start with `docs/` or `backend/`.
- UI copy is Uzbek Latin; server messages are shown as received.
- No new dependencies (`pubspec.yaml` unchanged) and no backend changes.
- MVVM as in the app: view models extend `ViewModel` (`lib/ui/core/view_model.dart`), are created with `ChangeNotifierProvider(create:)` inside their route, and talk to repositories over `ApiClient`; failures arrive as `ApiException` (`message`, `fieldErrors`).
- Quantities are sent as the typed text with `,` → `.`; more than two decimals is rejected in the form with "Ko'pi bilan 2 xona kasr". Client comparisons use integer hundredths.
- Baseline: `flutter test` 117 passing; `flutter analyze` has one existing info (`waybill_timeline.dart:34`) — add no new issues.
- Never run `flutter test` and a Gradle build at the same time (8 GB RAM); no emulator on this machine.
- Commit only on `feature/fuel-mobile`. Never commit `android/key.properties`, keystores, `frontend/public/privacy.html` or `.claude/`. Commit messages end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. An amount typed as `40,5` must be sent as `"40.5"`, and `40.125` must be refused in the form with "Ko'pi bilan 2 xona kasr", never rounded silently (tests in Tasks 1 and 5).
2. A station list that fails to load (offline) must show a retry, not "Sizga shahobcha biriktirilmagan" (test in Task 3).
3. A scanned waybill whose vehicle is not in the station's organization must leave the vehicle unselected with "Avtomobil bu shahobcha tashkilotida topilmadi" (tests in Tasks 2 and 5).
4. The balance changed on another device before closing: "Smenani yopish" reloads before computing the difference, and a server error on `discrepancy_note` reveals the note field (tests in Task 3).
5. A slow response to an earlier vehicle search must not replace the results of a newer one (test in Task 5).

---

### Task 0: Track the app and commit the plan

**Files:** all of `mobile/` that `.gitignore` allows; `docs/superpowers/specs/2026-10-28-fuel-mobile-design.md`; this plan.

- [ ] **Step 1:** `git add -n mobile/` — Expected: no `key.properties`, `*.jks`, `build/`, `.dart_tool/` in the list.
- [ ] **Step 2:** Commit `mobile/` alone: `chore(mobile): track the Flutter app`. Then commit the spec and plan: `docs: add fuel mobile spec and plan`.

### Task 1: Fuel models and quantity helpers

**Files:**
- Create: `lib/domain/models/quantity.dart`, `lib/domain/models/fuel_station.dart`, `lib/domain/models/shift_report.dart` (FuelShift, FuelOperation, ShiftReport), `lib/domain/models/fuel_vehicle.dart`, `lib/domain/models/fuel_supplier.dart`
- Modify: `lib/ui/core/formatters.dart` (add `formatQuantity`)
- Test: `test/domain/fuel_models_test.dart`, `test/helpers/fixtures.dart` (fuel JSON fixtures)

**Interfaces:**
- Produces:
  - `String? normalizeQuantity(String text)` — `"40,5"` → `"40.5"`, `" 12 "` → `"12"`; null when blank, not a number, negative, or more than two decimals.
  - `String? quantityError(String text)` — `"Miqdorni kiriting"` (blank), `"Son kiriting"` (not a number), `"Ko'pi bilan 2 xona kasr"` (3+ decimals), else null.
  - `int toHundredths(double value)` — `(value * 100).round()`.
  - `String formatQuantity(double? value, [String unit = ''])` — two decimals, thousands grouped with a space: `4160.0, 'L'` → `"4 160.00 L"`; null → `"—"`.
  - `FuelStation {int id; String name; String fuelType; String unit; double capacity; double balance; double percentage}` + `fromJson` (`current_balance` → balance).
  - `enum FuelOperationKind { receipt, refuel, adjustment }`; `FuelOperation {int id; FuelOperationKind kind; bool isIncoming; double amount; int? odometer; DateTime? createdAt; DateTime? cancelledAt; String? cancelReason; String? documentNumber; String? vehicleNumber; String? driverName; String? supplierName; String? waybillNumber; bool get isCancelled; String get subject}` — `subject` = vehicle number, else supplier name, else document number, else `"—"`.
  - `FuelShift {int id; bool isOpen; String? stationName; DateTime? openedAt; String? openedBy; DateTime? closedAt; String? closedBy; double openingBalance; double openingMeasured; double? closingBalance; double? closingMeasured; double? discrepancy; String? discrepancyNote}`.
  - `ShiftReport {FuelShift? shift; List<FuelOperation> operations; double receiptsTotal; double refuelsTotal}` + `fromJson` (null `totals` → 0).
  - `FuelVehicle {int id; String stateNumber; String model; String fuelType; bool fuelTypeMatches; double? tankCapacity; int? odometer; String? driverName; String? waybillNumber}`.
  - `FuelSupplier {int id; String name; bool isActive}`.
  - Fixtures: `fuelStationJson({int id = 1, String fuelType = 'benzin', String balance = '3200.00'})`, `shiftReportJson({bool open = true, List<Map<String, dynamic>> operations = const []})`, `fuelOperationJson({int id, String kind = 'refuel', String amount = '40.00', bool cancelled = false})`, `fuelVehicleJson({int id = 5, String stateNumber = '01A001AA', bool matches = true, String? waybill = 'UTG-202610-0001'})`, `fuelSupplierJson({int id = 1, bool active = true})` — shapes as in the spec's API table.

- [ ] **Step 1: Write the failing tests** in `test/domain/fuel_models_test.dart`:
  - `normalizeQuantity`: `'40,5'` → `'40.5'`; `'40.125'`, `''`, `'abc'`, `'-1'` → null; `quantityError('40.125')` == `"Ko'pi bilan 2 xona kasr"`, `quantityError('')` == `'Miqdorni kiriting'`, `quantityError('x')` == `'Son kiriting'`.
  - `toHundredths(4159.5) - toHundredths(4160.0)` == `-50`; `formatQuantity(4160, 'L')` == `'4 160.00 L'`.
  - `ShiftReport.fromJson(shiftReportJson(operations: [fuelOperationJson(id: 3, cancelled: true)]))`: `shift!.isOpen`, `operations.single.isCancelled`, `operations.single.subject == '01A001AA'`, `refuelsTotal == 40`; `ShiftReport.fromJson({'shift': null, 'operations': [], 'totals': null}).shift` is null.
  - `FuelStation.fromJson(fuelStationJson())`: `balance == 3200`, `unit == 'L'`; `FuelVehicle.fromJson(fuelVehicleJson(matches: false))`: `fuelTypeMatches` false, `waybillNumber == 'UTG-202610-0001'`.
- [ ] **Step 2:** `flutter test test/domain/fuel_models_test.dart` — Expected: FAIL (missing files).
- [ ] **Step 3:** Implement the models and helpers with `parseDecimal`, `parseDateTime`, `asJsonMap`, `asJsonList` from `json_parsing.dart`.
- [ ] **Step 4:** Run the test — Expected: PASS.
- [ ] **Step 5:** Commit `feat(mobile-fuel): add fuel models and quantity helpers`.

### Task 2: FuelRepository

**Files:**
- Create: `lib/data/repositories/fuel_repository.dart`
- Test: `test/data/fuel_repository_test.dart`

**Interfaces:**
- Consumes: Task 1 models; `ApiClient.get/post`.
- Produces `class FuelRepository { FuelRepository({required ApiClient apiClient}); ... }`:
  - `Future<List<FuelStation>> stations()` — `GET /fuel/stations`
  - `Future<ShiftReport> currentShift(int stationId)` — `GET /fuel/stations/{id}/current-shift`
  - `Future<void> openShift(int stationId, String openingMeasured)` — body `{opening_measured}`
  - `Future<void> closeShift(int shiftId, {required String closingMeasured, String? note})` — body `{closing_measured, discrepancy_note}` (note omitted when null or blank)
  - `Future<ShiftReport> shiftReport(int shiftId)` — `GET /fuel/shifts/{id}`
  - `Future<List<FuelVehicle>> vehicles(int stationId, String search)` — `GET /fuel/stations/{id}/vehicles?search=`
  - `Future<FuelVehicle?> vehicleFromWaybill(int stationId, int waybillId)` — `GET /waybills/{id}/public` → `vehicle.state_number` → `vehicles(stationId, number)` → the result whose state number equals it (uppercase, spaces removed); null when the waybill has no vehicle or nothing matches.
  - `Future<void> refuel(int stationId, {required int vehicleId, required int odometer, required String amount})`
  - `Future<List<FuelSupplier>> activeSuppliers()` — `GET /fuel/suppliers`, keeps `isActive` only
  - `Future<void> receive(int stationId, {required int supplierId, required String documentNumber, required DateTime documentDate, required String documentAmount, required String amount, String? pricePerLiter})` — `document_date` as `yyyy-MM-dd`; `price_per_liter` omitted when null or blank
  - `Future<void> cancel(int operationId, String reason)` — `POST /fuel/transactions/{id}/cancel`

- [ ] **Step 1: Write the failing tests** (MockClient route map as in `auth_repository_test.dart`): `stations parses the list`; `closeShift sends note only when given` (decoded body has no `discrepancy_note` key for `note: null`); `refuel posts vehicle, odometer and amount` (body `{'vehicle_id': 5, 'odometer': 125200, 'amount': '40.5'}`); `receive formats the date and drops a blank price`; `activeSuppliers drops inactive ones`; `vehicleFromWaybill picks the exact state number` (lookup returns `01A001AB` and `01A001AA`, public waybill says `01a 001aa` → id of `01A001AA`); `vehicleFromWaybill returns null when the station has no such vehicle`; `a 422 surfaces field errors` (`refuel` → `ApiException.fieldErrors['odometer'] == "Spidometr ko'rsatkichi oldingisidan kam"`).
- [ ] **Step 2:** `flutter test test/data/fuel_repository_test.dart` — Expected: FAIL.
- [ ] **Step 3:** Implement `FuelRepository`.
- [ ] **Step 4:** Run the test — Expected: PASS.
- [ ] **Step 5:** Commit `feat(mobile-fuel): add fuel repository`.

### Task 3: Fuel desk view model and screen

**Files:**
- Create: `lib/ui/features/fuel/view_models/fuel_desk_view_model.dart`, `lib/ui/features/fuel/views/fuel_desk_screen.dart`
- Modify: `lib/routing/routes.dart` (constants only: `fuel = '/fuel'`, `fuelRefuel = '/fuel/refuel'`, `fuelReceipt = '/fuel/receipt'`, `fuelScan = '/fuel/scan'`, `static String fuelShift(int id) => '/fuel/shifts/$id'`)
- Test: `test/ui/fuel_desk_view_model_test.dart`, `test/helpers/fixtures.dart` (`class MockFuelRepository extends Mock implements FuelRepository {}`)

**Interfaces:**
- Consumes: Task 2 `FuelRepository`; Task 1 helpers.
- Produces `FuelDeskViewModel extends ViewModel`, ctor `({required FuelRepository fuelRepository})`:
  - state: `List<FuelStation> stations`, `FuelStation? station`, `ShiftReport? report`, `bool loading`, `String? loadError`, `bool busy`, `String? actionError`, `Map<String, String> fieldErrors`, `bool closing`
  - `bool get noStations` — true only after a successful load returned no stations
  - `Future<void> load()` — stations, keeps the selected station if still present else the first, then its current shift
  - `Future<void> selectStation(int id)`
  - `Future<bool> openShift(String measured)` — local `quantityError` → `fieldErrors['opening_measured']`
  - `Future<void> beginClosing()` — reloads stations and shift, then `closing = true`
  - `void cancelClosing()`
  - `int? discrepancyHundredths(String measured)` — measured − station balance in hundredths; null when invalid
  - `bool noteRequired(String measured)` — difference ≠ 0, or the server rejected `discrepancy_note`
  - `Future<int?> closeShift(String measured, String note)` — returns the closed shift id; null on failure (errors in `fieldErrors`/`actionError`)
  - `Future<bool> cancel(FuelOperation operation, String reason)` — reloads on success
- Screen `FuelDeskScreen` (title "Yoqilg'i quyish"): station dropdown when `stations.length > 1`; balance card with `formatQuantity` and a `LinearProgressIndicator` of `percentage / 100`; shift card per spec ("Smena yopiq" / "Smena ochiq", "Smenani ochish", "Hisobot", "Smenani yopish", note field labelled "Farq {formatQuantity} — izoh"); two `FilledButton`s "Yoqilg'i quyish" (`context.push<bool>(Routes.fuelRefuel, extra: station)`) and "Qabul qilish" (`Routes.fuelReceipt`), reloading when they return `true`; operations list (strike-through when cancelled, undo `IconButton` tooltip "Bekor qilish" opening a dialog with a required reason field "Bekor qilish sababi"); `RefreshIndicator`; `ErrorRetry` on `loadError`; `EmptyState` "Sizga shahobcha biriktirilmagan" on `noStations`. After a successful close: `await context.push(Routes.fuelShift(id))`, then `load()`.

- [ ] **Step 1: Write the failing tests:** `load selects the only station and its shift`; `a failed station load is not reported as no stations` (`stations()` throws `ApiException('Tarmoq xatosi')` → `loadError` set, `noStations` false); `an empty station list means no stations`; `openShift refuses a bad quantity without calling the server` (`'12.345'` → `fieldErrors['opening_measured'] == "Ko'pi bilan 2 xona kasr"`, `verifyNever(openShift)`); `beginClosing reloads the station before the difference is computed` (second `stations()` returns balance `4160.00` → `discrepancyHundredths('4159.50') == -50`); `noteRequired follows the difference` (`'4160'` false, `'4159.5'` true); `a server note error reveals the note field` (`closeShift` throws `ApiException('x', statusCode: 422, fieldErrors: {'discrepancy_note': 'Farq izohini yozing'})` with measured equal to book → `noteRequired` true); `closeShift returns the shift id`; `cancel sends the reason and reloads`.
- [ ] **Step 2:** `flutter test test/ui/fuel_desk_view_model_test.dart` — Expected: FAIL.
- [ ] **Step 3:** Implement the view model and the screen.
- [ ] **Step 4:** Run the test — Expected: PASS.
- [ ] **Step 5:** Commit `feat(mobile-fuel): add fuel desk`.

### Task 4: Permission-driven navigation

**Files:**
- Create: `lib/ui/features/shell/shell_destinations.dart`
- Modify: `lib/ui/features/shell/views/app_shell.dart`, `lib/routing/router.dart`, `lib/main.dart` (provide `FuelRepository`), `test/helpers/fixtures.dart` (`'fueler': ['fuel.view', 'fuel.transactions.create']` in `systemRolePermissions`, matching the server)
- Test: `test/ui/shell_destinations_test.dart`, `test/ui/app_flow_test.dart` (fueler flow)

**Interfaces:**
- Consumes: Task 3 `FuelDeskScreen`, `FuelDeskViewModel`, `Routes.fuel`.
- Produces:
  - `enum ShellTab { tasks, waybills, fuel, scanner, profile }` with `label` ("Vazifalar", "Varaqalar", "Yoqilg'i", "Skaner", "Profil") and icons; `List<ShellTab> shellTabs(User user)` — tasks/waybills/scanner need `Permission.waybillsView`, fuel needs `Permission.fuelTransactionsCreate`, profile always; order as in the enum.
  - `String homeLocation(User user)` — `/tasks` with `waybillsView`, else `/fuel` with `fuelTransactionsCreate`, else `/profile`.
  - `bool canVisit(User user, String location)` — `/tasks`, `/waybills…`, `/scan` need `waybillsView`; `/fuel…` needs `fuelTransactionsCreate`; anything else true.
  - Router: branches in order tasks(0), waybills(1), fuel(2), profile(3); redirect sends the login page and any location failing `canVisit` to `homeLocation(user)`. `AppShell` builds its `NavigationBar` from `shellTabs(user)` and maps tabs to branches (scanner pushes `Routes.scanner`).

- [ ] **Step 1: Write the failing tests:** `shellTabs`: fueler → `[fuel, profile]`; mechanic → `[tasks, waybills, scanner, profile]`; dispatcher → `[tasks, waybills, fuel, scanner, profile]`. `homeLocation`: fueler `/fuel`, dispatcher `/tasks`. `canVisit(fueler, '/waybills/3')` false. App flow `a fueler lands on the fuel desk without waybill tabs`: login returns `userJson(role: 'fueler')`, `/api/fuel/stations` returns one station, current-shift returns no shift → finds "Smena yopiq" and "Yoqilg'i", finds no "Vazifalar", and no request to `/api/waybills` or `/api/dashboard` was made.
- [ ] **Step 2:** Run `flutter test test/ui/shell_destinations_test.dart test/ui/app_flow_test.dart` — Expected: FAIL.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** Run `flutter test` (whole suite) — Expected: all pass, including the existing mechanic flow.
- [ ] **Step 5:** Commit `feat(mobile-fuel): show tabs by permission and route fuelers to the desk`.

### Task 5: Refuel with search and QR pick

**Files:**
- Create: `lib/ui/features/fuel/view_models/refuel_view_model.dart`, `lib/ui/features/fuel/views/refuel_screen.dart`
- Modify: `lib/ui/features/scanner/views/scanner_screen.dart` (`ScannerScreen({super.key, this.pickWaybill = false})`; pick mode pops with the waybill id instead of opening it), `lib/routing/router.dart` (`Routes.fuelRefuel` with `extra: FuelStation`, `Routes.fuelScan` → `ScannerScreen(pickWaybill: true)`; missing `extra` redirects to `Routes.fuel`)
- Test: `test/ui/refuel_view_model_test.dart`

**Interfaces:**
- Consumes: `FuelRepository.vehicles/vehicleFromWaybill/refuel`, `FuelStation`, `quantityError`, `normalizeQuantity`.
- Produces `RefuelViewModel extends ViewModel`, ctor `({required FuelRepository fuelRepository, required FuelStation station})`:
  - state: `List<FuelVehicle> results`, `FuelVehicle? vehicle`, `bool searching`, `bool saving`, `String? message`, `Map<String, String> fieldErrors`
  - `Future<void> search(String text)` — ignores a response when a newer search has started
  - `void select(FuelVehicle vehicle)`
  - `Future<void> pickFromWaybill(int waybillId)` — null result → `message = "Avtomobil bu shahobcha tashkilotida topilmadi"`, `vehicle` unchanged
  - `String? get fuelTypeWarning` — `"Yoqilg'i turi mos emas: avtomobil {vehicle.fuelType}, shahobcha {station.fuelType}"` when not matching
  - `bool get canSave` — vehicle selected, fuel type matches, not saving
  - `Future<bool> save({required String odometer, required String amount})` — odometer must parse as a non-negative int else `fieldErrors['odometer'] = 'Butun son kiriting'`; amount via `quantityError`; server `fieldErrors` and `message` kept
- Screen `RefuelScreen` (title "Yoqilg'i quyish"): search `TextField` "Davlat raqami" with a `qr_code_scanner` `IconButton` (`context.push<int>(Routes.fuelScan)` → `pickFromWaybill`); results list; vehicle card (model, "Haydovchi: …", "Yo'l varaqasi: …" or warning "Ochiq yo'l varaqasi yo'q", "Oxirgi spidometr: … km · Bak: …"); `fuelTypeWarning` as an error container; fields "Spidometr (km)" and "Miqdor ({unit})" with `errorText` from `fieldErrors`; "Saqlash" enabled by `canSave`; on success `context.pop(true)`.

- [ ] **Step 1: Write the failing tests:** `a late answer to an older search is ignored` (first `vehicles` call completes after the second, via `Completer`s → `results` are the second call's); `pickFromWaybill selects the vehicle`; `pickFromWaybill with no match keeps the selection empty and explains` (message as above); `a wrong fuel type blocks saving` (`canSave` false, `fuelTypeWarning == "Yoqilg'i turi mos emas: avtomobil dizel, shahobcha benzin"`); `save normalizes the amount` (`'40,5'` → `refuel(... amount: '40.5')`); `save refuses three decimals and a non-integer odometer without calling the server`; `server field errors land under their fields` (`odometer` → "Spidometr ko'rsatkichi oldingisidan kam").
- [ ] **Step 2:** `flutter test test/ui/refuel_view_model_test.dart` — Expected: FAIL.
- [ ] **Step 3:** Implement the view model, screen, scanner pick mode and routes.
- [ ] **Step 4:** Run the test, then `flutter test test/domain/waybill_link_test.dart` — Expected: PASS.
- [ ] **Step 5:** Commit `feat(mobile-fuel): add refuel with search and waybill QR`.

### Task 6: Receipt

**Files:**
- Create: `lib/ui/features/fuel/view_models/receipt_view_model.dart`, `lib/ui/features/fuel/views/receipt_screen.dart`
- Modify: `lib/routing/router.dart` (`Routes.fuelReceipt` with `extra: FuelStation`)
- Test: `test/ui/receipt_view_model_test.dart`

**Interfaces:**
- Consumes: `FuelRepository.activeSuppliers/receive`.
- Produces `ReceiptViewModel extends ViewModel`, ctor `({required FuelRepository fuelRepository, required FuelStation station})`:
  - state: `List<FuelSupplier> suppliers`, `FuelSupplier? supplier`, `DateTime documentDate` (today, date only), `bool loading`, `String? loadError`, `bool saving`, `String? message`, `Map<String, String> fieldErrors`
  - `Future<void> load()`; `void selectSupplier(FuelSupplier s)`; `void setDocumentDate(DateTime d)`
  - `String? mismatchHint(String documentAmount, String amount)` — when both valid and different: `"Hujjatdagidan farq: {formatQuantity(received − document, unit)}"`
  - `Future<bool> save({required String documentNumber, required String documentAmount, required String amount, String pricePerLiter = ''})` — local errors: `fieldErrors['fuel_supplier_id'] = 'Yetkazib beruvchini tanlang'`, `fieldErrors['document_number'] = 'Hujjat raqamini kiriting'`, quantities via `quantityError` (price only when not blank)
- Screen `ReceiptScreen` (title "Yoqilg'i qabul qilish"): supplier via `SearchPickerField`, "Yo'l xati raqami", date via `showDatePicker` with `lastDate: today`, "Hujjatda ({unit})", "Qabul qilindi ({unit})", hint, "Narxi (so'm, ixtiyoriy)", "Saqlash" → `context.pop(true)`.

- [ ] **Step 1: Write the failing tests:** `load offers active suppliers only` (repository already filters; VM keeps order); `save requires supplier and document number` (both errors, no server call); `mismatchHint shows the difference` (`'1000'`, `'995'` → `"Hujjatdagidan farq: -5.00 L"`; equal → null); `save sends normalized amounts and the date` (`verify(receive(1, supplierId: 1, documentNumber: 'TTN-1', documentDate: today, documentAmount: '1000', amount: '995.5', pricePerLiter: null))` for input `'995,5'` and blank price).
- [ ] **Step 2:** `flutter test test/ui/receipt_view_model_test.dart` — Expected: FAIL.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** Run the test — Expected: PASS.
- [ ] **Step 5:** Commit `feat(mobile-fuel): add fuel receipt`.

### Task 7: Shift report screen

**Files:**
- Create: `lib/ui/features/fuel/view_models/shift_report_view_model.dart`, `lib/ui/features/fuel/views/shift_report_screen.dart`
- Modify: `lib/routing/router.dart` (`/fuel/shifts/:id`, non-numeric id → `Routes.fuel`)
- Test: `test/ui/shift_report_screen_test.dart`

**Interfaces:**
- Consumes: `FuelRepository.shiftReport`, `ShiftReport`, `formatQuantity`.
- Produces `ShiftReportViewModel extends ViewModel` (`({required FuelRepository fuelRepository, required int shiftId})`, `ShiftReport? report`, `bool loading`, `String? loadError`, `Future<void> load()`) and `ShiftReportScreen` (title "Smena hisoboti"): rows per spec ("Boshlang'ich qoldiq (hisob)", "Boshlang'ich o'lchov", "Qabul qilindi", "Quyildi", and when closed "Yakuniy qoldiq (hisob)", "Yakuniy o'lchov", "Farq", "Farq izohi"), then operations (time, kind label "Qabul"/"Quyish"/"Inventarizatsiya" with " (bekor)" when cancelled, subject, odometer, signed amount).

- [ ] **Step 1: Write the failing test:** `shows a closed shift with its difference and cancelled operations` — pump with a mocked repository returning a closed shift (closing balance 4160.00, measured 4159.50, discrepancy -0.50, note "O'lchovdagi xatolik") and one cancelled refuel → finds "-0.50 L", "O'lchovdagi xatolik", "Quyish (bekor)".
- [ ] **Step 2:** `flutter test test/ui/shift_report_screen_test.dart` — Expected: FAIL.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** Run the test — Expected: PASS.
- [ ] **Step 5:** Commit `feat(mobile-fuel): add shift report screen`.

### Task 8: Verification

- [ ] **Step 1:** `flutter analyze` — Expected: only the existing `waybill_timeline.dart:34` info. `flutter test` — Expected: all pass.
- [ ] **Step 2:** `flutter build apk` (alone, no tests running) — Expected: `✓ Built build/app/outputs/flutter-apk/app-release.apk`.
- [ ] **Step 3:** Data-layer run against the real API: throwaway MySQL in Docker (`docker compose up -d db`, `migrate:fresh --seed` with the cache env overrides), API via `php -S` on 8010 (see dev-machine notes), a fueler created and assigned to station 1, one approved waybill for vehicle 1. A temporary `test/live_fuel_test.dart` (plain `test()`, real `http.Client`, base URL from `--dart-define=LIVE_API=http://localhost:8010/api`) drives `FuelRepository`: stations → open shift → `vehicleFromWaybill` → refuel 40 → receipt from a new supplier → cancel → close with note → `shiftReport` totals (receipts as sent, refuels 40.00). Expected: all pass. Delete the temporary test, stop the API container, `docker compose down -v`.
- [ ] **Step 4:** README: add the fueler login (`fueler@…` after creating the user on the web) and the Yoqilg'i tab to "Run"/features. Commit `docs(mobile): describe the fuel desk`.
- [ ] **Step 5:** Final whole-branch review; then superpowers:finishing-a-development-branch.
