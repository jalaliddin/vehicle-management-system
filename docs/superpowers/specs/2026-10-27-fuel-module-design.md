# Stage 2 — Fuel (GSM) and fuelers (Yoqilg'i hisobi)

Date: 2026-10-27
Status: approved design, ready for planning
Source: ТЗ «Единая электронная программа ООО «Ургенчтрансгаз»», section 6 (module 2). Builds on stage 1 (`docs/superpowers/specs/2026-10-21-hr-ohs-module-design.md`).

## Goal

Every litre that enters or leaves a fuel point is a document in the system: receipts from suppliers, refuels per vehicle with the odometer reading, and inventory acts. Fuelers work in shifts whose closing balance is reconciled with a measurement. Management gets turnover, refuel and consumption-versus-norm reports.

## Decisions

| Topic | Decision |
|---|---|
| Ledger | `fuel_transactions` stays the single ledger. Each row has a `kind` (`receipt`, `refuel`, `adjustment`) and the existing direction `type` (`in`/`out`). The station's `current_balance` is updated in the same DB transaction as every ledger write. |
| Shifts | One open shift per station. Receipts and refuels require an open shift and belong to it. A shift records book and measured balances at opening and closing; the closing discrepancy (measured − book) needs an explanation when it is not zero. A closed shift is never edited. |
| Corrections | An operation can be cancelled only while its shift is open; the cancellation reverses the balance (and the odometer). After closing, only an inventory act (`adjustment`) changes the book balance; acts are entered between shifts and need the new `fuel.adjust` permission. |
| Negative balance | Rejected (422). The old silent `max(0, …)` clamp goes away with the old endpoint. |
| Opening balances | A migration writes one `adjustment` row per station with its current balance ("Boshlang'ich qoldiq"), so turnover statements add up from day one. |
| Refuel checks | Vehicle of the station's organization, active; same fuel type as the station; amount ≤ station balance and ≤ tank capacity (when set); odometer ≥ the vehicle's last reading. The vehicle's open waybill (`approved` or `in_progress`) is linked automatically; a refuel without one is allowed and flagged. |
| Odometer | `vehicles.odometer` holds the last known reading; a refuel raises it and stores the value it replaced, so a cancellation can restore it. |
| Units | `config('fuel.units')`: benzin → L, dizel → L, gaz → m³. Overconsumption tolerance `config('fuel.overconsumption_percent')` = 10. Both env-overridable. |
| Fueler access | New system role `fueler` "Yoqilg'i quyuvchi" (`fuel.view`, `fuel.transactions.create`). Users assigned to stations (`fuel_station_user`) see and operate only those stations; users with no assignment keep organization scoping. |
| Consumption estimate | Per vehicle in a period: litres refuelled after its first reading ÷ (last − first odometer) × 100, compared with `vehicles.fuel_norm`. Labelled as an estimate (assumes full tanks); module 4 replaces it with waybill data. |
| Waybill | Waybill fuel fields and the mobile app stay unchanged; the waybill card lists the refuels linked to it. |
| Flutter | Fueler screens in the mobile app are a separate follow-up step after stage 2. Stage 2 delivers a phone-friendly web page. |

## Data model

### fuel_suppliers
`name`, `tin` nullable (STIR), `phone` nullable, `is_active` bool default true. Auditable.

### fuel_station_user
`fuel_station_id` FK cascade, `user_id` FK cascade, unique pair. No timestamps.

### fuel_shifts
`fuel_station_id` FK, `status` enum `open|closed`, `opened_by` FK users, `opened_at`, `opening_balance` decimal(10,2) (book), `opening_measured` decimal(10,2), `closed_by` nullable FK users, `closed_at` nullable, `closing_balance` nullable (book at close), `closing_measured` nullable, `discrepancy` nullable (closing_measured − closing_balance), `discrepancy_note` nullable, timestamps. Index (`fuel_station_id`, `status`). Auditable.

### fuel_transactions (alter)
Add `kind` string(16) default `adjustment` (existing rows: none in production), `fuel_shift_id` nullable FK, `fuel_supplier_id` nullable FK, `document_number` nullable, `document_date` nullable, `document_amount` decimal(10,2) nullable (quantity per supplier document), `driver_id` nullable FK drivers, `odometer` unsigned int nullable, `previous_odometer` unsigned int nullable, `cancelled_at` nullable, `cancelled_by` nullable FK users, `cancel_reason` nullable. Indexes (`fuel_station_id`, `created_at`), (`vehicle_id`, `kind`).

### vehicles (alter)
Add `tank_capacity` decimal(8,2) nullable, `odometer` unsigned int nullable.

## Rules

- **Open shift** `POST fuel/stations/{station}/shifts` `{opening_measured}`: no open shift on the station; `opening_balance` = station balance.
- **Close shift** `POST fuel/shifts/{shift}/close` `{closing_measured, discrepancy_note}`: shift open; `closing_balance` = station balance; `discrepancy` = measured − book; `discrepancy_note` required when discrepancy ≠ 0.
- **Receipt** `POST fuel/stations/{station}/receipts` `{fuel_supplier_id, document_number, document_date (≤ today), document_amount, amount, price_per_liter?, notes?}`: open shift; balance + amount ≤ capacity; `type=in`.
- **Refuel** `POST fuel/stations/{station}/refuels` `{vehicle_id, odometer, amount, driver_id?, notes?}`: open shift; checks from Decisions; driver = request value, else the waybill's driver, else the driver assigned to the vehicle; `type=out`; vehicle odometer raised.
- **Cancel** `POST fuel/transactions/{transaction}/cancel` `{reason}`: receipt or refuel whose shift is still open and not cancelled; reverses the balance (cancelling a receipt may not make the balance negative); a refuel's vehicle odometer returns to `previous_odometer` when it still equals this refuel's reading.
- **Inventory act** `POST fuel/stations/{station}/adjustments` `{actual_balance, document_number, notes}`: no open shift; writes `|actual − book|` as `in` or `out`; zero difference → 422.
- All writes run in `DB::transaction` with the station row locked (`lockForUpdate`).

Uzbek error messages: "Smena ochilmagan", "Bu shahobchada ochiq smena bor", "Shahobchada yetarli yoqilg'i yo'q", "Sig'imdan oshib ketadi", "Yoqilg'i turi mos emas", "Spidometr ko'rsatkichi oldingisidan kam", "Bak hajmidan ko'p", "Smena yopilgan, bekor qilib bo'lmaydi", "Farq izohini yozing", "Inventarizatsiya smenalar orasida o'tkaziladi".

## Permissions and access

- New enum case `FuelAdjust = 'fuel.adjust'` (group `fuel`, label "Yoqilg'i inventarizatsiyasi").
- Migration adds the system role `fueler` "Yoqilg'i quyuvchi" with `fuel.view`, `fuel.transactions.create`.
- `User::canAccessStation(FuelStation $station): bool` = organization access and (no station assignments or the station is assigned). Station listings, monitoring, shifts, operations and reports apply it.
- Shift and operation endpoints need `fuel.transactions.create` + station access; suppliers, fueler assignment and station edits need `fuel.stations.manage`; inventory acts need `fuel.adjust`; reads need `fuel.view`.

## API

| Method and path | Purpose |
|---|---|
| `apiResource fuel/suppliers` (no show) | Suppliers |
| `PUT fuel/stations/{station}/fuelers` `{user_ids}` | Assign fuelers |
| `GET fuel/stations/{station}/vehicles?search=` | Vehicle lookup for the refuel form: state number, model, fuel type, tank capacity, odometer, driver, open waybill, `fuel_type_matches` |
| `GET fuel/stations/{station}/current-shift` | The open shift with its operations, or null |
| `POST fuel/stations/{station}/shifts`, `POST fuel/shifts/{shift}/close` | Open / close |
| `GET fuel/shifts` (`station_id, from, to, with_discrepancy`, paginated 50), `GET fuel/shifts/{shift}` | Shift list and shift report (operations + totals) |
| `POST fuel/stations/{station}/receipts`, `POST fuel/stations/{station}/refuels`, `POST fuel/transactions/{transaction}/cancel` | Operations |
| `POST fuel/stations/{station}/adjustments` | Inventory act |
| `GET fuel/reports/{turnover,refuels,consumption,discrepancies}` | Reports (`from`, `to`, `organization_id`, `station_id`) |

Removed: `POST fuel/stations/{station}/transaction` (bypassed shifts). `GET fuel/transactions` stays and gains the new fields and `kind`/`station_id` filters. `GET waybills/{id}` gains `refuels`. Vehicle create/update accept `tank_capacity` and `odometer`.

## Reports

| Report | Content |
|---|---|
| turnover | Per station: opening balance (signed sum of non-cancelled rows before `from`), receipts, refuels, adjustments in, adjustments out, closing balance |
| refuels | Rows (date, station, vehicle, driver, waybill or "yo'q", odometer, amount) + totals per vehicle and per driver |
| consumption | Per vehicle with ≥ 2 refuels in the period: first/last odometer, km, litres, L/100 km, norm, deviation %, `over_norm` (> tolerance) |
| discrepancies | Closed shifts in the period with discrepancy ≠ 0: station, fueler, closed at, book, measured, discrepancy, note |

## Frontend

- **Fueler desk** `/fuel/station` (phone-first): station picker when more than one; shift card (open with measured balance / close with measured balance and note); large "Yoqilg'i quyish" and "Qabul qilish" buttons opening bottom-sheet forms (refuel: state-number search → vehicle card with driver, waybill, last odometer, fuel-type warning → odometer and litres; receipt: supplier, document, amounts); the shift's operations with cancel; shift report print.
- **Admin:** stations page (fueler assignment, inventory act; old add-transaction dialog removed); suppliers page; shifts page (filters, discrepancy highlight, shift report dialog with print); operations page updated (kind, document, odometer, cancelled); fuel reports page sharing a config-driven report component with `HrReportsView`.
- Vehicle form: tank capacity, odometer. Waybill card: its refuels.
- Sidebar "Yoqilg'i": Monitoring, Yoqilg'i quyish, Shahobchalar, Smenalar, Harakatlar, Yetkazib beruvchilar, Hisobotlar.

## Testing

Feature tests per endpoint group in `tests/Feature/Fuel/` (authorization, station assignment scoping, every rule and message, balance and odometer effects, cancellation, inventory act, waybill auto-link, reports with exact figures); unit tests for the consumption estimate and turnover arithmetic. Frontend: build + a Playwright smoke run of the fueler desk and admin pages against the API in Docker on a throwaway MySQL DB. Migrations verified on MySQL 8.4 (up, rollback, up).

## Out of scope

Flutter fueler screens (next step), waybill fuel field changes (module 4), cost accounting beyond price per litre, fuel transfers between stations, card/limit systems.
