import 'package:flutter_test/flutter_test.dart';
import 'package:utg_transport/routing/routes.dart';
import 'package:utg_transport/ui/features/shell/shell_destinations.dart';

import '../helpers/fixtures.dart';

void main() {
  test('a fueler gets the fuel desk and the profile', () {
    expect(shellTabs(userWith('fueler')), [ShellTab.fuel, ShellTab.profile]);
  });

  test('a mechanic keeps the waybill tabs', () {
    expect(shellTabs(userWith('mechanic')), [
      ShellTab.tasks,
      ShellTab.waybills,
      ShellTab.scanner,
      ShellTab.profile,
    ]);
  });

  test('a dispatcher gets waybills and fuel', () {
    expect(shellTabs(userWith('dispatcher')), [
      ShellTab.tasks,
      ShellTab.waybills,
      ShellTab.fuel,
      ShellTab.scanner,
      ShellTab.profile,
    ]);
  });

  test('home follows the permissions', () {
    expect(homeLocation(userWith('fueler')), Routes.fuel);
    expect(homeLocation(userWith('dispatcher')), Routes.tasks);
    expect(homeLocation(userWith('hr', permissions: const [])), Routes.profile);
  });

  test('canVisit guards waybill and fuel routes', () {
    final fueler = userWith('fueler');
    final mechanic = userWith('mechanic');

    expect(canVisit(fueler, '/waybills/3'), isFalse);
    expect(canVisit(fueler, Routes.tasks), isFalse);
    expect(canVisit(fueler, Routes.scanner), isFalse);
    expect(canVisit(fueler, Routes.fuelRefuel), isTrue);
    expect(canVisit(fueler, Routes.profile), isTrue);
    expect(canVisit(mechanic, Routes.fuel), isFalse);
    expect(canVisit(mechanic, '/waybills/3'), isTrue);
  });
}
