import 'package:flutter_test/flutter_test.dart';
import 'package:utg_transport/domain/models/waybill.dart';
import 'package:utg_transport/domain/models/waybill_action.dart';

import '../helpers/fixtures.dart';

void main() {
  Set<WaybillAction> actionsOf(
    String role,
    WaybillStatus status, {
    List<String>? permissions,
  }) => WaybillAction.availableFor(
    userWith(role, permissions: permissions),
    waybillWith(status),
  );

  final all = systemRolePermissions.keys;

  // What each system role may do with its default permissions.
  final expected = <WaybillStatus, Map<WaybillAction, Set<String>>>{
    WaybillStatus.draft: {
      WaybillAction.submitToMechanic: {
        'dispatcher',
        'hq_dispatcher',
        'superadmin',
      },
    },
    WaybillStatus.mechanicCheck: {
      WaybillAction.mechanicCheck: {'mechanic', 'superadmin'},
    },
    WaybillStatus.mechanicOk: {
      WaybillAction.doctorCheck: {'doctor', 'superadmin'},
    },
    WaybillStatus.doctorCheck: {
      WaybillAction.doctorCheck: {'doctor', 'superadmin'},
    },
    WaybillStatus.doctorOk: {
      WaybillAction.approve: {'hq_dispatcher', 'superadmin'},
    },
    WaybillStatus.hqReview: {
      WaybillAction.approve: {'hq_dispatcher', 'superadmin'},
    },
    WaybillStatus.approved: {
      WaybillAction.depart: {'dispatcher', 'hq_dispatcher', 'superadmin'},
    },
    WaybillStatus.inProgress: {
      WaybillAction.complete: {'dispatcher', 'hq_dispatcher', 'superadmin'},
    },
    WaybillStatus.completed: {},
    WaybillStatus.cancelled: {},
  };

  for (final MapEntry(key: status, value: allowed) in expected.entries) {
    for (final role in all) {
      final want = {
        for (final MapEntry(key: action, value: roles) in allowed.entries)
          if (roles.contains(role)) action,
      };
      test('$role on ${status.name} may $want', () {
        expect(actionsOf(role, status), want);
      });
    }
  }

  test('viewers can never act', () {
    for (final status in WaybillStatus.values) {
      expect(actionsOf('viewer', status), isEmpty);
    }
  });

  test('custom roles act on exactly the permissions they hold', () {
    const inspector = ['waybills.mechanic_check', 'waybills.doctor_check'];

    expect(
      actionsOf(
        'inspector',
        WaybillStatus.mechanicCheck,
        permissions: inspector,
      ),
      {WaybillAction.mechanicCheck},
    );
    expect(
      actionsOf('inspector', WaybillStatus.mechanicOk, permissions: inspector),
      {WaybillAction.doctorCheck},
    );
    expect(
      actionsOf('inspector', WaybillStatus.doctorOk, permissions: inspector),
      isEmpty,
    );
  });

  test('a permission alone is not enough without the right status', () {
    for (final action in WaybillAction.values) {
      for (final status in WaybillStatus.values) {
        if (action.allowedFrom.contains(status)) continue;
        expect(
          actionsOf(
            'custom',
            status,
            permissions: [action.permission.apiValue],
          ),
          isNot(contains(action)),
        );
      }
    }
  });
}
