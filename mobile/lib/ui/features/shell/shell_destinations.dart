import 'package:flutter/material.dart';

import '../../../domain/models/permission.dart';
import '../../../domain/models/user.dart';
import '../../../routing/routes.dart';

/// The bottom-bar tabs. Which ones a user gets follows their permissions,
/// so nobody lands on a screen the server would answer with 403.
enum ShellTab {
  tasks('Vazifalar', Icons.assignment_outlined, Icons.assignment),
  waybills('Varaqalar', Icons.description_outlined, Icons.description),
  fuel("Yoqilg'i", Icons.local_gas_station_outlined, Icons.local_gas_station),
  scanner('Skaner', Icons.qr_code_scanner, Icons.qr_code_scanner),
  profile('Profil', Icons.person_outline, Icons.person);

  const ShellTab(this.label, this.icon, this.selectedIcon);

  final String label;
  final IconData icon;
  final IconData selectedIcon;

  /// The router branch the tab shows; null for the scanner, which opens
  /// full screen so the camera only runs while visible.
  int? get branch => switch (this) {
    tasks => 0,
    waybills => 1,
    fuel => 2,
    scanner => null,
    profile => 3,
  };
}

bool _canUseWaybills(User user) => user.can(Permission.waybillsView);

bool _canUseFuel(User user) => user.can(Permission.fuelTransactionsCreate);

List<ShellTab> shellTabs(User user) => [
  for (final tab in ShellTab.values)
    if (switch (tab) {
      ShellTab.tasks ||
      ShellTab.waybills ||
      ShellTab.scanner => _canUseWaybills(user),
      ShellTab.fuel => _canUseFuel(user),
      ShellTab.profile => true,
    })
      tab,
];

/// Where a user starts after login.
String homeLocation(User user) {
  if (_canUseWaybills(user)) return Routes.tasks;
  if (_canUseFuel(user)) return Routes.fuel;
  return Routes.profile;
}

/// Whether [user] may open [location]; the router sends them home if not.
bool canVisit(User user, String location) {
  bool under(String root) => location == root || location.startsWith('$root/');

  if (under(Routes.fuel)) return _canUseFuel(user);
  if (under(Routes.tasks) || under(Routes.waybills) || under(Routes.scanner)) {
    return _canUseWaybills(user);
  }
  return true;
}
