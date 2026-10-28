import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../../../data/repositories/auth_repository.dart';
import '../../../../routing/routes.dart';
import '../shell_destinations.dart';

/// Bottom navigation around the tab branches, showing only the tabs the
/// user's permissions allow. The scanner entry is not a branch: it opens
/// the camera full screen so it only runs while visible.
class AppShell extends StatelessWidget {
  const AppShell({super.key, required this.navigationShell});

  final StatefulNavigationShell navigationShell;

  void _open(BuildContext context, ShellTab tab) {
    final branch = tab.branch;
    if (branch == null) {
      context.push(Routes.scanner);
      return;
    }
    navigationShell.goBranch(
      branch,
      initialLocation: branch == navigationShell.currentIndex,
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthRepository>().currentUser;
    final tabs = user == null ? const [ShellTab.profile] : shellTabs(user);
    final selected = tabs.indexWhere(
      (tab) => tab.branch == navigationShell.currentIndex,
    );
    return Scaffold(
      body: navigationShell,
      // A bar needs two destinations; a profile-only user gets none.
      bottomNavigationBar: tabs.length < 2
          ? null
          : NavigationBar(
              selectedIndex: selected < 0 ? 0 : selected,
              onDestinationSelected: (index) => _open(context, tabs[index]),
              destinations: [
                for (final tab in tabs)
                  NavigationDestination(
                    icon: Icon(tab.icon),
                    selectedIcon: Icon(tab.selectedIcon),
                    label: tab.label,
                  ),
              ],
            ),
    );
  }
}
