import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:utg_transport/data/repositories/auth_repository.dart';
import 'package:utg_transport/data/repositories/dashboard_repository.dart';
import 'package:utg_transport/data/repositories/fuel_repository.dart';
import 'package:utg_transport/data/repositories/reference_repository.dart';
import 'package:utg_transport/data/repositories/waybill_repository.dart';
import 'package:utg_transport/data/services/api_client.dart';
import 'package:utg_transport/domain/models/waybill.dart';
import 'package:utg_transport/main.dart';
import 'package:utg_transport/routing/router.dart';

import '../helpers/fixtures.dart';

/// Drives the real router, repositories and API client against a fake
/// backend.
void main() {
  late List<String> requests;

  http.Response json(Object body, [int status = 200]) => http.Response(
    jsonEncode(body),
    status,
    headers: {'content-type': 'application/json'},
  );

  Map<String, dynamic> page(List<Map<String, dynamic>> items) => {
    'data': items,
    'current_page': 1,
    'last_page': 1,
    'total': items.length,
  };

  Future<void> pumpApp(WidgetTester tester, {String role = 'mechanic'}) async {
    requests = [];
    final api = ApiClient(
      baseUrl: 'https://vms.test/api',
      httpClient: MockClient((request) async {
        final url = request.url;
        requests.add(
          '${request.method} ${url.path}${url.hasQuery ? '?${url.query}' : ''}',
        );
        return switch ((request.method, url.path)) {
          ('POST', '/api/auth/login') =>
            jsonDecode(request.body)['password'] == 'password'
                ? json({'token': 't', 'user': userJson(role: role)})
                : json({
                    'message': 'invalid',
                    'errors': {
                      'email': ["Kiritilgan ma'lumotlar noto'g'ri."],
                    },
                  }, 422),
          ('POST', '/api/auth/logout') => json({'message': 'ok'}),
          ('GET', '/api/waybills') => json(
            page([
              if (url.queryParameters['status'] == 'mechanic_check')
                waybillJson(id: 10, status: WaybillStatus.mechanicCheck),
            ]),
          ),
          ('GET', '/api/waybills/10') => json(
            waybillJson(id: 10, status: WaybillStatus.mechanicCheck),
          ),
          ('GET', '/api/fuel/stations') => json([fuelStationJson()]),
          ('GET', '/api/fuel/stations/1/current-shift') => json({
            'shift': null,
            'operations': [],
            'totals': null,
          }),
          _ => json({'message': 'Not found'}, 404),
        };
      }),
    );
    final auth = AuthRepository(
      apiClient: api,
      sessionStorage: InMemorySessionStorage(),
    );

    await tester.pumpWidget(
      MultiProvider(
        providers: [
          ChangeNotifierProvider.value(value: auth),
          ChangeNotifierProvider(
            create: (_) => WaybillRepository(apiClient: api),
          ),
          Provider(create: (_) => DashboardRepository(apiClient: api)),
          Provider(create: (_) => ReferenceRepository(apiClient: api)),
          Provider(create: (_) => FuelRepository(apiClient: api)),
        ],
        child: UtgTransportApp(router: createRouter(auth)),
      ),
    );
    await tester.pumpAndSettle();
  }

  Future<void> login(WidgetTester tester, String password) async {
    await tester.enterText(
      find.widgetWithText(TextField, 'Email'),
      'mechanic@utg.uz',
    );
    await tester.enterText(find.widgetWithText(TextField, 'Parol'), password);
    await tester.tap(find.widgetWithText(FilledButton, 'Kirish'));
    await tester.pumpAndSettle();
  }

  testWidgets('signed-out users land on the login screen', (tester) async {
    await pumpApp(tester);

    expect(find.text('UTG Transport'), findsOneWidget);
    expect(find.widgetWithText(FilledButton, 'Kirish'), findsOneWidget);
  });

  testWidgets('wrong credentials show the server message', (tester) async {
    await pumpApp(tester);

    await login(tester, 'wrong');

    expect(find.text("Kiritilgan ma'lumotlar noto'g'ri."), findsOneWidget);
    expect(find.widgetWithText(FilledButton, 'Kirish'), findsOneWidget);
  });

  testWidgets('a mechanic logs in, opens a queued waybill and logs out', (
    tester,
  ) async {
    await pumpApp(tester);

    await login(tester, 'password');

    expect(find.text('Mexanik navbati'), findsOneWidget);
    expect(find.text('UTG-202610-0010'), findsOneWidget);
    expect(
      requests,
      contains('GET /api/waybills?page=1&per_page=100&status=mechanic_check'),
    );

    await tester.tap(find.text('UTG-202610-0010'));
    await tester.pumpAndSettle();
    expect(find.widgetWithText(OutlinedButton, 'Rad etish'), findsOneWidget);

    // pageBack() looks for the English tooltip; the app is in Uzbek.
    await tester.tap(find.byType(BackButton));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Profil'));
    await tester.pumpAndSettle();
    expect(find.text('Mexanik'), findsOneWidget);

    final logout = find.widgetWithText(OutlinedButton, 'Chiqish');
    await tester.ensureVisible(logout);
    await tester.pumpAndSettle();
    await tester.tap(logout);
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(FilledButton, 'Chiqish'));
    await tester.pumpAndSettle();

    expect(find.widgetWithText(FilledButton, 'Kirish'), findsOneWidget);
    expect(requests, contains('POST /api/auth/logout'));
  });

  testWidgets('a fueler lands on the fuel desk without waybill tabs', (
    tester,
  ) async {
    await pumpApp(tester, role: 'fueler');

    await login(tester, 'password');

    expect(find.text('Smena yopiq'), findsOneWidget);
    expect(find.text("Yoqilg'i"), findsOneWidget);
    expect(find.text('Profil'), findsOneWidget);
    expect(find.text('Vazifalar'), findsNothing);
    expect(find.text('Skaner'), findsNothing);
    expect(
      requests.where(
        (r) => r.contains('/api/waybills') || r.contains('/api/dashboard'),
      ),
      isEmpty,
    );
  });

  testWidgets('a user with neither waybills nor fuel only sees the profile', (
    tester,
  ) async {
    await pumpApp(tester, role: 'hr');

    await login(tester, 'password');

    expect(find.byType(NavigationBar), findsNothing);
    expect(find.widgetWithText(OutlinedButton, 'Chiqish'), findsOneWidget);
  });
}
