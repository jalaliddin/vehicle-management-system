import 'package:flutter_test/flutter_test.dart';
import 'package:utg_transport/domain/models/dashboard_stats.dart';
import 'package:utg_transport/domain/models/new_waybill.dart';
import 'package:utg_transport/domain/models/paginated.dart';
import 'package:utg_transport/domain/models/permission.dart';
import 'package:utg_transport/domain/models/user.dart';
import 'package:utg_transport/domain/models/vehicle.dart';
import 'package:utg_transport/domain/models/waybill.dart';

import '../helpers/fixtures.dart';

void main() {
  group('Waybill.fromJson', () {
    test('reads a list item, including decimals sent as strings', () {
      final waybill = Waybill.fromJson(
        waybillJson(id: 42, status: WaybillStatus.mechanicCheck),
      );

      expect(waybill.id, 42);
      expect(waybill.number, 'UTG-202610-0042');
      expect(waybill.status, WaybillStatus.mechanicCheck);
      expect(waybill.plannedKm, 120);
      expect(waybill.fuelIssued, 15.5);
      expect(waybill.actualKm, isNull);
      expect(waybill.vehicle?.title, '90A123BC · Nexia');
      expect(waybill.driver?.fullName, 'Aliyev Vali');
      expect(waybill.organization?.displayName, 'UF');
      expect(waybill.vehicleType, VehicleType.car);
      expect(waybill.createdDate?.toUtc(), DateTime.utc(2026, 10, 21));
    });

    test('reads inspector and approver names from the detail payload', () {
      final waybill = Waybill.fromJson(
        waybillJson(
          status: WaybillStatus.approved,
          extra: {
            'mechanic': {'id': 4, 'name': 'Mexanik Ali'},
            'mechanic_checked_at': '2026-10-21T08:30:00.000000Z',
            'mechanic_passed': true,
            'mechanic_notes': 'Tormoz yaxshi',
            'doctor': {'id': 5, 'name': 'Doktor Lola'},
            'doctor_passed': true,
            'approved_by_user': {'id': 2, 'name': 'HQ Bobur'},
            'approved_at': '2026-10-21T09:00:00.000000Z',
          },
        ),
      );

      expect(waybill.mechanicName, 'Mexanik Ali');
      expect(waybill.mechanicPassed, isTrue);
      expect(waybill.mechanicNotes, 'Tormoz yaxshi');
      expect(
        waybill.mechanicCheckedAt?.toUtc(),
        DateTime.utc(2026, 10, 21, 8, 30),
      );
      expect(waybill.doctorName, 'Doktor Lola');
      expect(waybill.approvedByName, 'HQ Bobur');
    });

    test('tolerates missing relations and unknown statuses', () {
      final waybill = Waybill.fromJson({
        'id': 1,
        'status': 'something_new',
        'organization': null,
      });

      expect(waybill.status, WaybillStatus.draft);
      expect(waybill.vehicle, isNull);
      expect(waybill.driver, isNull);
      expect(waybill.organization, isNull);
      expect(waybill.number, '#1');
      expect(waybill.destination, '');
    });
  });

  group('User', () {
    test('reads the role and its permissions', () {
      final user = User.fromJson(
        userJson(
          role: 'fuel_operator',
          permissions: ['fuel.view', 'waybills.view', 'vehicles.fly'],
        ),
      );

      expect(user.roleSlug, 'fuel_operator');
      expect(user.roleName, 'fuel_operator');
      expect(user.permissions, {Permission.fuelView, Permission.waybillsView});
      expect(user.can(Permission.fuelView), isTrue);
      expect(user.can(Permission.waybillsApprove), isFalse);
    });

    test('has no permissions when the API sends none', () {
      final user = User.fromJson({...userJson(), 'permissions': null});
      expect(user.permissions, isEmpty);
      expect(user.canAccessAllOrganizations, isFalse);
    });

    test('falls back to the slug when the role has no display name', () {
      final user = User.fromJson({
        ...userJson(role: 'viewer'),
        'role_name': null,
      });
      expect(user.roleName, 'viewer');
    });

    test('only HQ roles see every organization', () {
      expect(userWith('superadmin').canAccessAllOrganizations, isTrue);
      expect(userWith('hq_dispatcher').canAccessAllOrganizations, isTrue);
      expect(userWith('dispatcher').canAccessAllOrganizations, isFalse);
    });

    test('derives initials and first name', () {
      final user = User.fromJson(userJson());
      expect(user.initials, 'KA');
      expect(user.firstName, 'Karimov');
      expect(user.organization?.name, 'Urganch filiali');
    });
  });

  group('Paginated.fromJson', () {
    test('knows when more pages exist', () {
      final page = Paginated.fromJson({
        'data': [waybillJson()],
        'current_page': 1,
        'last_page': 3,
        'total': 41,
      }, Waybill.fromJson);

      expect(page.items, hasLength(1));
      expect(page.hasMore, isTrue);
      expect(page.total, 41);
    });
  });

  group('DashboardStats.fromJson', () {
    test('reads counters, fuel alerts and active trips', () {
      final stats = DashboardStats.fromJson({
        'total_vehicles': 10,
        'active_vehicles': 8,
        'pending_waybills': 2,
        'fuel_alerts': [
          {
            'id': 1,
            'name': 'AYOQSH-1',
            'current_balance': '150.00',
            'min_threshold': '500.00',
            'capacity': '10000.00',
            'organization': {
              'id': 3,
              'name': 'Urganch filiali',
              'short_name': 'UF',
            },
          },
        ],
        'active_waybills_list': [waybillJson(status: WaybillStatus.inProgress)],
      });

      expect(stats.totalVehicles, 10);
      expect(stats.brokenVehicles, 0);
      expect(stats.fuelAlerts.single.currentBalance, 150);
      expect(stats.fuelAlerts.single.organization?.displayName, 'UF');
      expect(stats.activeTrips.single.status, WaybillStatus.inProgress);
    });
  });

  group('NewWaybill.toJson', () {
    test('formats dates and omits blank optional fields', () {
      final json = NewWaybill(
        organizationId: 3,
        vehicleId: 5,
        driverId: 7,
        vehicleType: VehicleType.truck,
        createdDate: DateTime(2026, 3, 4),
        validUntil: DateTime(2026, 3, 5),
        destination: 'Xiva',
        tabelNumber: '  ',
        plannedKm: 80,
        notes: '',
      ).toJson();

      expect(json, {
        'organization_id': 3,
        'vehicle_id': 5,
        'driver_id': 7,
        'vehicle_type': 'truck',
        'created_date': '2026-03-04',
        'valid_until': '2026-03-05',
        'destination': 'Xiva',
        'trip_count': 1,
        'has_ac': false,
        'planned_km': 80.0,
      });
    });
  });

  group('Vehicle', () {
    test('prefers the A/C norm only when asked and when set', () {
      final vehicle = Vehicle.fromJson({
        'id': 1,
        'state_number': '90A123BC',
        'model': 'Nexia',
        'fuel_norm': '9.50',
        'fuel_norm_ac': '10.80',
      });
      expect(vehicle.normFor(withAc: false), 9.5);
      expect(vehicle.normFor(withAc: true), 10.8);

      final noAcNorm = Vehicle.fromJson({'id': 2, 'fuel_norm': 12});
      expect(noAcNorm.normFor(withAc: true), 12);
    });
  });
}
