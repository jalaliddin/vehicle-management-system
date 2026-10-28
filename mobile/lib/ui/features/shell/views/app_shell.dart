import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../../routing/routes.dart';

/// Bottom navigation around the tab branches. The scanner entry is not a
/// branch: it opens the camera full screen so it only runs while visible.
class AppShell extends StatelessWidget {
  const AppShell({super.key, required this.navigationShell});

  final StatefulNavigationShell navigationShell;

  static const _scannerDestination = 2;

  // Destination indexes 0, 1, 3 map to branches 0, 1, 2.
  int get _selectedDestination =>
      navigationShell.currentIndex >= _scannerDestination
      ? navigationShell.currentIndex + 1
      : navigationShell.currentIndex;

  void _onDestinationSelected(BuildContext context, int destination) {
    if (destination == _scannerDestination) {
      context.push(Routes.scanner);
      return;
    }
    final branch = destination > _scannerDestination
        ? destination - 1
        : destination;
    navigationShell.goBranch(
      branch,
      initialLocation: branch == navigationShell.currentIndex,
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    body: navigationShell,
    bottomNavigationBar: NavigationBar(
      selectedIndex: _selectedDestination,
      onDestinationSelected: (index) => _onDestinationSelected(context, index),
      destinations: const [
        NavigationDestination(
          icon: Icon(Icons.assignment_outlined),
          selectedIcon: Icon(Icons.assignment),
          label: 'Vazifalar',
        ),
        NavigationDestination(
          icon: Icon(Icons.description_outlined),
          selectedIcon: Icon(Icons.description),
          label: 'Varaqalar',
        ),
        NavigationDestination(
          icon: Icon(Icons.qr_code_scanner),
          label: 'Skaner',
        ),
        NavigationDestination(
          icon: Icon(Icons.person_outline),
          selectedIcon: Icon(Icons.person),
          label: 'Profil',
        ),
      ],
    ),
  );
}
