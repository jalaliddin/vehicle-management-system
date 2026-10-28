import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../data/repositories/auth_repository.dart';
import '../ui/features/auth/view_models/login_view_model.dart';
import '../ui/features/auth/views/login_screen.dart';
import '../ui/features/profile/views/profile_screen.dart';
import '../ui/features/scanner/views/scanner_screen.dart';
import '../ui/features/shell/views/app_shell.dart';
import '../ui/features/tasks/view_models/tasks_view_model.dart';
import '../ui/features/tasks/views/tasks_screen.dart';
import '../ui/features/waybills/view_models/waybill_create_view_model.dart';
import '../ui/features/waybills/view_models/waybill_detail_view_model.dart';
import '../ui/features/waybills/view_models/waybill_list_view_model.dart';
import '../ui/features/waybills/views/waybill_create_screen.dart';
import '../ui/features/waybills/views/waybill_detail_screen.dart';
import '../ui/features/waybills/views/waybill_list_screen.dart';
import 'routes.dart';

/// View models are created with `ChangeNotifierProvider(create:)` inside
/// each route, so they survive router rebuilds and are disposed with the
/// route.
GoRouter createRouter(AuthRepository auth) => GoRouter(
  initialLocation: Routes.tasks,
  refreshListenable: auth,
  redirect: (context, state) {
    final atLogin = state.matchedLocation == Routes.login;
    if (!auth.isAuthenticated) return atLogin ? null : Routes.login;
    if (atLogin) return Routes.tasks;
    return null;
  },
  routes: [
    GoRoute(
      path: Routes.login,
      builder: (context, state) => ChangeNotifierProvider(
        create: (context) => LoginViewModel(authRepository: context.read()),
        child: const LoginScreen(),
      ),
    ),
    StatefulShellRoute.indexedStack(
      builder: (context, state, navigationShell) =>
          AppShell(navigationShell: navigationShell),
      branches: [
        StatefulShellBranch(
          routes: [
            GoRoute(
              path: Routes.tasks,
              builder: (context, state) => ChangeNotifierProvider(
                create: (context) => TasksViewModel(
                  authRepository: context.read(),
                  waybillRepository: context.read(),
                  dashboardRepository: context.read(),
                )..load(),
                child: const TasksScreen(),
              ),
            ),
          ],
        ),
        StatefulShellBranch(
          routes: [
            GoRoute(
              path: Routes.waybills,
              builder: (context, state) => ChangeNotifierProvider(
                create: (context) =>
                    WaybillListViewModel(waybillRepository: context.read())
                      ..refresh(),
                child: const WaybillListScreen(),
              ),
            ),
          ],
        ),
        StatefulShellBranch(
          routes: [
            GoRoute(
              path: Routes.profile,
              builder: (context, state) => const ProfileScreen(),
            ),
          ],
        ),
      ],
    ),
    // Full-screen routes above the tab bar. `new` must precede `:id`.
    GoRoute(
      path: Routes.newWaybill,
      builder: (context, state) => ChangeNotifierProvider(
        create: (context) => WaybillCreateViewModel(
          authRepository: context.read(),
          waybillRepository: context.read(),
          referenceRepository: context.read(),
        )..load(),
        child: const WaybillCreateScreen(),
      ),
    ),
    GoRoute(
      path: '${Routes.waybills}/:id',
      redirect: (context, state) =>
          int.tryParse(state.pathParameters['id']!) == null
          ? Routes.waybills
          : null,
      builder: (context, state) => ChangeNotifierProvider(
        create: (context) => WaybillDetailViewModel(
          waybillId: int.parse(state.pathParameters['id']!),
          authRepository: context.read(),
          waybillRepository: context.read(),
        )..load(),
        child: const WaybillDetailScreen(),
      ),
    ),
    GoRoute(
      path: Routes.scanner,
      builder: (context, state) => const ScannerScreen(),
    ),
  ],
);
