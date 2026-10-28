import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import 'config.dart';
import 'data/repositories/auth_repository.dart';
import 'data/repositories/dashboard_repository.dart';
import 'data/repositories/reference_repository.dart';
import 'data/repositories/waybill_repository.dart';
import 'data/services/api_client.dart';
import 'data/services/session_storage.dart';
import 'routing/router.dart';
import 'ui/core/theme.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  final apiClient = ApiClient(baseUrl: AppConfig.apiBaseUrl);
  final authRepository = AuthRepository(
    apiClient: apiClient,
    sessionStorage: SecureSessionStorage(),
  );
  await authRepository.restore();

  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider.value(value: authRepository),
        ChangeNotifierProvider(
          create: (_) => WaybillRepository(apiClient: apiClient),
        ),
        Provider(create: (_) => DashboardRepository(apiClient: apiClient)),
        Provider(create: (_) => ReferenceRepository(apiClient: apiClient)),
      ],
      child: UtgTransportApp(router: createRouter(authRepository)),
    ),
  );
}

class UtgTransportApp extends StatelessWidget {
  const UtgTransportApp({super.key, required this.router});

  final GoRouter router;

  @override
  Widget build(BuildContext context) => MaterialApp.router(
    title: 'UTG Transport',
    debugShowCheckedModeBanner: false,
    theme: buildAppTheme(),
    routerConfig: router,
    locale: const Locale('uz'),
    supportedLocales: const [Locale('uz')],
    localizationsDelegates: GlobalMaterialLocalizations.delegates,
  );
}
