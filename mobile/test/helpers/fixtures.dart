import 'package:mocktail/mocktail.dart';
import 'package:utg_transport/data/repositories/auth_repository.dart';
import 'package:utg_transport/data/repositories/dashboard_repository.dart';
import 'package:utg_transport/data/repositories/fuel_repository.dart';
import 'package:utg_transport/data/repositories/reference_repository.dart';
import 'package:utg_transport/data/repositories/waybill_repository.dart';
import 'package:utg_transport/data/services/session_storage.dart';
import 'package:utg_transport/domain/models/dashboard_stats.dart';
import 'package:utg_transport/domain/models/new_waybill.dart';
import 'package:utg_transport/domain/models/paginated.dart';
import 'package:utg_transport/domain/models/permission.dart';
import 'package:utg_transport/domain/models/user.dart';
import 'package:utg_transport/domain/models/vehicle.dart';
import 'package:utg_transport/domain/models/waybill.dart';

class MockAuthRepository extends Mock implements AuthRepository {}

class MockWaybillRepository extends Mock implements WaybillRepository {}

class MockDashboardRepository extends Mock implements DashboardRepository {}

class MockReferenceRepository extends Mock implements ReferenceRepository {}

class MockFuelRepository extends Mock implements FuelRepository {}

void registerFallbacks() {
  registerFallbackValue(WaybillStatus.draft);
  registerFallbackValue(
    NewWaybill(
      organizationId: 1,
      vehicleId: 1,
      driverId: 1,
      vehicleType: VehicleType.car,
      createdDate: DateTime(2026),
      validUntil: DateTime(2026),
      destination: '',
    ),
  );
}

class InMemorySessionStorage implements SessionStorage {
  StoredSession? session;

  @override
  Future<StoredSession?> read() async => session;

  @override
  Future<void> write(StoredSession session) async => this.session = session;

  @override
  Future<void> clear() async => session = null;
}

/// The server's system roles and the permissions they ship with, as seeded
/// by the backend migration. Super Admin holds every permission.
final Map<String, List<String>> systemRolePermissions = {
  'superadmin': [for (final p in Permission.values) p.apiValue],
  'hq_dispatcher': [
    'data.all_organizations',
    'vehicles.view',
    'vehicles.create',
    'vehicles.update',
    'vehicles.delete',
    'drivers.view',
    'drivers.create',
    'drivers.update',
    'drivers.delete',
    'waybills.view',
    'waybills.create',
    'waybills.update',
    'waybills.delete',
    'waybills.submit',
    'waybills.approve',
    'waybills.depart',
    'waybills.complete',
    'fuel.view',
    'fuel.stations.manage',
    'fuel.transactions.create',
    'fuel_norms.view',
    'fuel_norms.manage',
    'reports.view',
    'organizations.manage',
  ],
  'dispatcher': [
    'vehicles.view',
    'vehicles.create',
    'vehicles.update',
    'drivers.view',
    'drivers.create',
    'drivers.update',
    'waybills.view',
    'waybills.create',
    'waybills.update',
    'waybills.delete',
    'waybills.submit',
    'waybills.depart',
    'waybills.complete',
    'fuel.view',
    'fuel.transactions.create',
    'reports.view',
  ],
  'mechanic': [
    'vehicles.view',
    'drivers.view',
    'waybills.view',
    'waybills.mechanic_check',
  ],
  'doctor': ['drivers.view', 'waybills.view', 'waybills.doctor_check'],
  'viewer': [
    'vehicles.view',
    'drivers.view',
    'waybills.view',
    'fuel.view',
    'fuel_norms.view',
    'reports.view',
  ],
};

/// The display names the backend gives its system roles.
const systemRoleNames = {
  'superadmin': 'Super Admin',
  'hq_dispatcher': 'Markaziy dispetcher',
  'dispatcher': 'Dispetcher',
  'mechanic': 'Mexanik',
  'doctor': 'Shifokor',
  'viewer': "Ko'ruvchi",
};

/// The shape of `/auth/login`'s `user` and of `/auth/me`. [role] is a system
/// role slug; pass [permissions] to model a custom role instead.
Map<String, dynamic> userJson({
  String role = 'dispatcher',
  List<String>? permissions,
  int id = 1,
  int? organizationId = 3,
}) => {
  'id': id,
  'name': 'Karimov Anvar',
  'email': '$role@utg.uz',
  'role': role,
  'role_id': 1,
  'role_name': systemRoleNames[role] ?? role,
  'permissions': permissions ?? systemRolePermissions[role] ?? const [],
  'is_active': true,
  'organization_id': organizationId,
  'organization': organizationId == null
      ? null
      : {'id': organizationId, 'name': 'Urganch filiali', 'short_name': 'UF'},
};

User userWith(String role, {List<String>? permissions}) =>
    User.fromJson(userJson(role: role, permissions: permissions));

/// The shape of a `/waybills` list item, plus [extra] fields.
Map<String, dynamic> waybillJson({
  int id = 10,
  WaybillStatus status = WaybillStatus.draft,
  Map<String, dynamic> extra = const {},
}) => {
  'id': id,
  'waybill_number': 'UTG-202610-${id.toString().padLeft(4, '0')}',
  'organization_id': 3,
  'vehicle_id': 5,
  'driver_id': 7,
  'vehicle_type': 'car',
  'tabel_number': null,
  'created_date': '2026-10-21T00:00:00.000000Z',
  'valid_until': '2026-10-22T00:00:00.000000Z',
  'trip_count': 1,
  'destination': 'Urganch - Xiva',
  'destination_organization': null,
  'planned_km': '120.00',
  'actual_km': null,
  'fuel_issued': '15.50',
  'fuel_consumed': null,
  'fuel_returned': null,
  'has_ac': false,
  'status': status.apiValue,
  'mechanic_passed': null,
  'doctor_passed': null,
  'organization': {'id': 3, 'name': 'Urganch filiali', 'short_name': 'UF'},
  'vehicle': {'id': 5, 'state_number': '90A123BC', 'model': 'Nexia'},
  'driver': {'id': 7, 'full_name': 'Aliyev Vali'},
  ...extra,
};

Waybill waybillWith(WaybillStatus status, {int id = 10}) =>
    Waybill.fromJson(waybillJson(id: id, status: status));

Paginated<Waybill> pageOf(
  List<Waybill> items, {
  int currentPage = 1,
  int lastPage = 1,
}) => Paginated(
  items: items,
  currentPage: currentPage,
  lastPage: lastPage,
  total: items.length,
);

DashboardStats statsWith({List<Waybill> activeTrips = const []}) =>
    DashboardStats(
      totalVehicles: 12,
      activeVehicles: 9,
      brokenVehicles: 2,
      maintenanceVehicles: 1,
      totalDrivers: 15,
      activeDrivers: 14,
      todayWaybills: 4,
      pendingWaybills: 3,
      activeWaybills: 2,
      fuelAlerts: const [],
      activeTrips: activeTrips,
    );

/// The shape of a `/fuel/stations` item.
Map<String, dynamic> fuelStationJson({
  int id = 1,
  String fuelType = 'benzin',
  String balance = '3200.00',
}) => {
  'id': id,
  'organization_id': 3,
  'name': 'QQP-MGQB ${fuelTypeLabel(fuelType)}',
  'fuel_type': fuelType,
  'capacity': '10000.00',
  'current_balance': balance,
  'min_threshold': '1000.00',
  'percentage': 32.0,
  'unit': fuelType == 'gaz' ? 'm³' : 'L',
  'fuelers': const [],
};

/// An operation inside a shift report.
Map<String, dynamic> fuelOperationJson({
  int id = 1,
  String kind = 'refuel',
  String amount = '40.00',
  bool cancelled = false,
}) => {
  'id': id,
  'fuel_station_id': 1,
  'kind': kind,
  'type': kind == 'refuel' ? 'out' : 'in',
  'amount': amount,
  'odometer': kind == 'refuel' ? 125200 : null,
  'document_number': kind == 'receipt' ? 'TTN-001' : null,
  'created_at': '2026-10-28T09:17:30.000000Z',
  'cancelled_at': cancelled ? '2026-10-28T09:20:00.000000Z' : null,
  'cancel_reason': cancelled ? "Noto'g'ri avtomobil" : null,
  'vehicle': kind == 'refuel'
      ? {'id': 5, 'state_number': '01A001AA', 'model': 'Toyota Camry'}
      : null,
  'driver': kind == 'refuel' ? {'id': 7, 'full_name': 'Aliyev Vali'} : null,
  'supplier': kind == 'receipt' ? {'id': 1, 'name': 'UzGazOil'} : null,
  'waybill': kind == 'refuel'
      ? {'id': 10, 'waybill_number': 'UTG-202610-0001'}
      : null,
};

/// The `{shift, operations, totals}` shape of current-shift and shift show.
Map<String, dynamic> shiftReportJson({
  bool open = true,
  List<Map<String, dynamic>> operations = const [],
}) => {
  'shift': {
    'id': 4,
    'fuel_station_id': 1,
    'status': open ? 'open' : 'closed',
    'opened_at': '2026-10-28T09:00:00.000000Z',
    'opening_balance': '3200.00',
    'opening_measured': '3200.00',
    'closed_at': open ? null : '2026-10-28T18:00:00.000000Z',
    'closing_balance': open ? null : '4160.00',
    'closing_measured': open ? null : '4159.50',
    'discrepancy': open ? null : '-0.50',
    'discrepancy_note': open ? null : "O'lchovdagi xatolik",
    'station': {'id': 1, 'name': 'QQP-MGQB Benzin', 'fuel_type': 'benzin'},
    'opened_by': {'id': 9, 'name': 'Quyuvchi Test'},
    'closed_by': open ? null : {'id': 9, 'name': 'Quyuvchi Test'},
  },
  'operations': operations,
  'totals': {'receipts': '0.00', 'refuels': '40.00'},
};

/// The shape of a `/fuel/stations/{id}/vehicles` item.
Map<String, dynamic> fuelVehicleJson({
  int id = 5,
  String stateNumber = '01A001AA',
  bool matches = true,
  String? waybill = 'UTG-202610-0001',
}) => {
  'id': id,
  'state_number': stateNumber,
  'model': 'Toyota Camry',
  'fuel_type': matches ? 'benzin' : 'dizel',
  'fuel_type_matches': matches,
  'tank_capacity': '60.00',
  'odometer': 125000,
  'driver': {'id': 7, 'full_name': 'Aliyev Vali'},
  'waybill': waybill == null ? null : {'id': 10, 'waybill_number': waybill},
};

Map<String, dynamic> fuelSupplierJson({int id = 1, bool active = true}) => {
  'id': id,
  'name': active ? 'UzGazOil' : 'Eski yetkazuvchi',
  'tin': '123456789',
  'phone': null,
  'is_active': active,
};
