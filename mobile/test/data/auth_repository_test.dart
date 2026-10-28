import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:utg_transport/data/repositories/auth_repository.dart';
import 'package:utg_transport/data/services/api_client.dart';
import 'package:utg_transport/data/services/api_exception.dart';
import 'package:utg_transport/data/services/session_storage.dart';

import '../helpers/fixtures.dart';

void main() {
  late InMemorySessionStorage storage;
  late ApiClient api;
  late AuthRepository auth;
  late Map<String, http.Response Function(http.Request)> routes;
  late int notifications;

  setUp(() {
    storage = InMemorySessionStorage();
    routes = {};
    api = ApiClient(
      baseUrl: 'https://vms.test/api',
      httpClient: MockClient((request) async {
        final handler = routes['${request.method} ${request.url.path}'];
        return handler?.call(request) ?? http.Response('', 404);
      }),
    );
    auth = AuthRepository(apiClient: api, sessionStorage: storage);
    notifications = 0;
    auth.addListener(() => notifications++);
  });

  http.Response json(Object body, [int status = 200]) =>
      http.Response(jsonEncode(body), status);

  test('login saves the session and notifies', () async {
    routes['POST /api/auth/login'] = (_) =>
        json({'token': 'tok-1', 'user': userJson(role: 'mechanic')});

    await auth.login(email: ' mechanic@utg.uz ', password: 'password');

    expect(auth.isAuthenticated, isTrue);
    expect(auth.currentUser?.roleSlug, 'mechanic');
    expect(api.token, 'tok-1');
    expect(storage.session?.token, 'tok-1');
    expect(notifications, 1);
  });

  test('a failed login leaves the user signed out', () async {
    routes['POST /api/auth/login'] = (_) => json({
      'message': 'invalid',
      'errors': {
        'email': ["Kiritilgan ma'lumotlar noto'g'ri."],
      },
    }, 422);

    await expectLater(
      auth.login(email: 'x@utg.uz', password: 'bad'),
      throwsA(isA<ApiException>()),
    );
    expect(auth.isAuthenticated, isFalse);
    expect(storage.session, isNull);
  });

  test('restore opens with the cached profile, then refreshes it', () async {
    storage.session = StoredSession(
      token: 'tok-2',
      userJson: jsonEncode(userJson(role: 'dispatcher')),
    );
    routes['GET /api/auth/me'] = (_) => json(userJson(role: 'hq_dispatcher'));

    await auth.restore();
    expect(auth.currentUser?.roleSlug, 'dispatcher');
    expect(api.token, 'tok-2');

    await pumpEventQueue();
    expect(auth.currentUser?.roleSlug, 'hq_dispatcher');
    expect(jsonDecode(storage.session!.userJson)['role'], 'hq_dispatcher');
  });

  test('restore keeps the cached profile when offline', () async {
    storage.session = StoredSession(
      token: 'tok-3',
      userJson: jsonEncode(userJson()),
    );
    api = ApiClient(
      baseUrl: 'https://vms.test/api',
      httpClient: MockClient((_) => throw http.ClientException('offline')),
    );
    auth = AuthRepository(apiClient: api, sessionStorage: storage);

    await auth.restore();
    await pumpEventQueue();

    expect(auth.isAuthenticated, isTrue);
    expect(storage.session, isNotNull);
  });

  test('an expired token signs the user out', () async {
    storage.session = StoredSession(
      token: 'old',
      userJson: jsonEncode(userJson()),
    );
    routes['GET /api/auth/me'] = (_) =>
        json({'message': 'Unauthenticated.'}, 401);

    await auth.restore();
    await pumpEventQueue();

    expect(auth.isAuthenticated, isFalse);
    expect(api.token, isNull);
    expect(storage.session, isNull);
  });

  test('logout clears the session even if the server call fails', () async {
    routes['POST /api/auth/login'] = (_) =>
        json({'token': 'tok-4', 'user': userJson()});
    routes['POST /api/auth/logout'] = (_) => json({'message': 'boom'}, 500);
    await auth.login(email: 'd@utg.uz', password: 'password');

    await auth.logout();

    expect(auth.isAuthenticated, isFalse);
    expect(storage.session, isNull);
  });
}
