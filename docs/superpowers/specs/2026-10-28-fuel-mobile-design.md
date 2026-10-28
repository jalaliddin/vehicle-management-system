# Fuel desk in the mobile app (stage 2 follow-up)

Date: 2026-10-28
Status: approved design, ready for planning
Source: stage 2 spec (`2026-10-27-fuel-module-design.md`, decision "Flutter: fueler screens in the mobile app are a separate follow-up step"). Builds on the Flutter app in `mobile/` and the fuel API from stage 2.

## Goal

A fueler does the whole shift on the Android app: open the shift, refuel vehicles (picked by scanning the driver's waybill QR or by state number), receive fuel from suppliers, cancel mistakes, close the shift with a measured balance, and see the shift report. Everything the web fuel desk does, nothing more; the server stays unchanged.

## Decisions

| Topic | Decision |
|---|---|
| Placement | A `fuel` feature inside the existing app (`lib/ui/features/fuel`), same MVVM pattern: `ViewModel` + provider + go_router, repositories over `ApiClient`. No new dependencies. |
| Navigation | Tabs follow permissions. Vazifalar, Varaqalar and Skaner need `waybills.view`; **Yoqilg'i** needs `fuel.transactions.create`; Profil is always shown. Home after login: `/tasks` with `waybills.view`, else `/fuel` with `fuel.transactions.create`, else `/profile`. A route the user may not use redirects to their home. |
| Backend | No changes. Endpoints from stage 2 plus the public waybill endpoint. |
| Quantities | Sent as the typed text with `,` turned into `.`; input with more than two decimals is rejected in the form ("Ko'pi bilan 2 xona kasr"). Client-side comparisons (the closing discrepancy) use integer hundredths. Displayed with two decimals and the station's unit. |
| Vehicle pick | State-number search (station vehicle lookup) or the QR button. The scanner opens in *pick* mode and returns the waybill id; the app reads `GET /waybills/{id}/public` for the state number and selects the lookup result with that exact number. No match → "Avtomobil bu shahobcha tashkilotida topilmadi". |
| Shift report | On screen only; printing stays on the web. |
| Stale balance | Tapping "Smenani yopish" reloads the station and current shift before the close form computes the discrepancy. A server error on `discrepancy_note` reveals the note field. |
| Errors | `ApiException.message` and `fieldErrors` shown inline; load failures show retry (`ErrorRetry`). "Sizga shahobcha biriktirilmagan" appears only after a successful empty station list. |
| Offline | Not supported; every action needs the network. |
| Git | `mobile/` becomes tracked on branch `feature/fuel-mobile`: a baseline commit of the app as it is, then the fuel work. Signing files stay git-ignored. |

## API used

| Call | Use |
|---|---|
| `GET /fuel/stations` | Stations the user may operate (`id, name, fuel_type, unit, capacity, current_balance, percentage`) |
| `GET /fuel/stations/{id}/current-shift` | `{shift, operations, totals}`; `shift` null when none is open |
| `POST /fuel/stations/{id}/shifts` `{opening_measured}` | Open shift |
| `POST /fuel/shifts/{id}/close` `{closing_measured, discrepancy_note?}` | Close shift |
| `GET /fuel/shifts/{id}` | Shift report (same shape as current-shift) |
| `GET /fuel/stations/{id}/vehicles?search=` | Vehicle lookup: `id, state_number, model, fuel_type, fuel_type_matches, tank_capacity, odometer, driver{id, full_name}, waybill{id, waybill_number}` |
| `POST /fuel/stations/{id}/refuels` `{vehicle_id, odometer, amount}` | Refuel |
| `GET /fuel/suppliers` | Suppliers; only `is_active` ones are offered |
| `POST /fuel/stations/{id}/receipts` `{fuel_supplier_id, document_number, document_date, document_amount, amount, price_per_liter?}` | Receipt |
| `POST /fuel/transactions/{id}/cancel` `{reason}` | Cancel within the open shift |
| `GET /waybills/{id}/public` | `vehicle.state_number` for a scanned waybill |

Operations in a report carry `id, kind, type, amount, odometer, created_at, cancelled_at, cancel_reason, document_number, vehicle{state_number, model}, driver{full_name}, supplier{name}, waybill{waybill_number}`; totals are `{receipts, refuels}` as decimal strings.

## Screens

- **Yoqilg'i (desk):** station picker when there is more than one; balance card (balance, unit, fill bar); shift card — closed: measured balance field + "Smenani ochish"; open: opened at/by, receipts and refuels totals, "Hisobot", "Smenani yopish" (measured balance, live difference against the book balance, note required when the difference is not zero). While a shift is open: two large buttons "Yoqilg'i quyish" and "Qabul qilish", and the shift's operations newest first (cancelled ones struck through) with a cancel action that asks for a reason. Pull to refresh.
- **Yoqilg'i quyish:** vehicle field (search sheet) with a QR button; vehicle card — model, driver, waybill number or the warning "Ochiq yo'l varaqasi yo'q", last odometer, tank capacity; wrong fuel type shows "Yoqilg'i turi mos emas: avtomobil X, shahobcha Y" and disables saving. Fields: odometer (integer), amount. Server messages (e.g. "Spidometr ko'rsatkichi oldingisidan kam", "Bak hajmidan ko'p") appear under their fields.
- **Qabul qilish:** supplier (active), document number, document date (default today, not in the future), amount on the document, amount received (a hint when they differ), price per litre (optional).
- **Smena hisoboti:** station, opened/closed at and by, opening book and measured balances, receipts, refuels, closing book and measured balances, difference and note, then the operations. Opened from the shift card and automatically after closing; leaving it returns to the desk, which reloads.

## Testing

Unit tests for model parsing and quantity helpers; repository tests with `MockClient` covering every call above, including the QR lookup (found, no match, not a waybill); view-model tests for the desk (single station auto-selected, open, close needing a note, reload before closing, cancel, empty vs failed station list), refuel (search, QR pick, fuel-type block, field errors) and receipt (active suppliers, mismatch hint); widget tests for the tabs per permission set and the home redirect. Verification: `flutter analyze` with no new issues, `flutter test`, `flutter build apk`, and a data-layer run of the repository against the local API in Docker on a throwaway MySQL database.

## Out of scope

Offline queueing, printing from the phone, push notifications, inventory acts and admin pages (web only), backend changes.
