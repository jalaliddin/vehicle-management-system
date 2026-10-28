import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:utg_transport/data/services/api_client.dart';
import 'package:utg_transport/data/services/api_exception.dart';

void main() {
  late http.Request lastRequest;

  ApiClient clientReturning(int status, Object? body) => ApiClient(
    baseUrl: 'https://vms.test/api/',
    httpClient: MockClient((request) async {
      lastRequest = request;
      return http.Response(body == null ? '' : jsonEncode(body), status);
    }),
  );

  test(
    'sends JSON headers, the bearer token and non-empty query params',
    () async {
      final client = clientReturning(200, {'ok': true})..token = 'abc';

      final result = await client.get(
        '/waybills',
        query: {'page': '2', 'status': null, 'search': ''},
      );

      expect(result, {'ok': true});
      expect(
        lastRequest.url.toString(),
        'https://vms.test/api/waybills?page=2',
      );
      expect(lastRequest.headers['Accept'], 'application/json');
      expect(lastRequest.headers['Authorization'], 'Bearer abc');
    },
  );

  test('encodes POST bodies and returns null for empty responses', () async {
    final client = clientReturning(204, null);

    final result = await client.post('/waybills/1/mechanic-check', {
      'passed': true,
    });

    expect(result, isNull);
    expect(lastRequest.method, 'POST');
    expect(jsonDecode(lastRequest.body), {'passed': true});
    expect(lastRequest.headers.containsKey('Authorization'), isFalse);
  });

  test('turns Laravel validation errors into field errors', () async {
    final client = clientReturning(422, {
      'message': 'The given data was invalid.',
      'errors': {
        'email': ["Kiritilgan ma'lumotlar noto'g'ri."],
        'password': ['Required', 'Too short'],
      },
    });

    await expectLater(
      client.post('/auth/login', {}),
      throwsA(
        isA<ApiException>()
            .having((e) => e.statusCode, 'statusCode', 422)
            .having(
              (e) => e.message,
              'message',
              "Kiritilgan ma'lumotlar noto'g'ri.",
            )
            .having((e) => e.fieldErrors, 'fieldErrors', {
              'email': "Kiritilgan ma'lumotlar noto'g'ri.",
              'password': 'Required',
            }),
      ),
    );
  });

  test('uses the server message for 403', () async {
    final client = clientReturning(403, {
      'message': 'Foydalanuvchi faol emas.',
    });

    await expectLater(
      client.post('/auth/login', {}),
      throwsA(
        isA<ApiException>().having(
          (e) => e.message,
          'message',
          'Foydalanuvchi faol emas.',
        ),
      ),
    );
  });

  test('reports an expired token once, but not anonymous 401s', () async {
    var calls = 0;
    final client = clientReturning(401, {'message': 'Unauthenticated.'})
      ..onUnauthorized = () => calls++;

    await expectLater(client.get('/auth/me'), throwsA(isA<ApiException>()));
    expect(calls, 0);

    client.token = 'expired';
    await expectLater(client.get('/auth/me'), throwsA(isA<ApiException>()));
    expect(calls, 1);
  });

  test('wraps network failures', () async {
    final client = ApiClient(
      baseUrl: 'https://vms.test/api',
      httpClient: MockClient((_) => throw http.ClientException('no route')),
    );

    await expectLater(
      client.get('/dashboard/stats'),
      throwsA(
        isA<ApiException>()
            .having((e) => e.statusCode, 'statusCode', isNull)
            .having((e) => e.message, 'message', contains('aloqa')),
      ),
    );
  });

  test('hides server error details', () async {
    final client = clientReturning(500, {
      'message': 'SQLSTATE[42S22]: Column not found',
    });

    await expectLater(
      client.get('/waybills'),
      throwsA(
        isA<ApiException>().having(
          (e) => e.message,
          'message',
          contains('Serverda xatolik'),
        ),
      ),
    );
  });
}
