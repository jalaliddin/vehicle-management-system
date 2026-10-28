import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

import 'api_exception.dart';

/// Thin JSON client for the Laravel API with Sanctum bearer tokens.
///
/// Returns decoded JSON (`Map`, `List` or null) and throws [ApiException]
/// for every failure, including network errors.
class ApiClient {
  ApiClient({required String baseUrl, http.Client? httpClient})
    : _baseUrl = baseUrl.endsWith('/')
          ? baseUrl.substring(0, baseUrl.length - 1)
          : baseUrl,
      _http = httpClient ?? http.Client();

  static const _timeout = Duration(seconds: 20);

  final String _baseUrl;
  final http.Client _http;

  String? token;

  /// Called when the server rejects [token] with HTTP 401.
  void Function()? onUnauthorized;

  String get baseUrl => _baseUrl;

  Future<dynamic> get(String path, {Map<String, String?> query = const {}}) =>
      _send('GET', path, query: query);

  Future<dynamic> post(String path, [Map<String, dynamic>? body]) =>
      _send('POST', path, body: body);

  Future<dynamic> _send(
    String method,
    String path, {
    Map<String, String?> query = const {},
    Map<String, dynamic>? body,
  }) async {
    final params = {
      for (final MapEntry(:key, :value) in query.entries)
        if (value != null && value.isNotEmpty) key: value,
    };
    final uri = Uri.parse('$_baseUrl$path')
        .replace(queryParameters: params.isEmpty ? null : params);

    final sentToken = token;
    final request = http.Request(method, uri)
      ..headers.addAll({
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        if (sentToken != null) 'Authorization': 'Bearer $sentToken',
      });
    if (body != null) request.body = jsonEncode(body);

    final http.Response response;
    try {
      response = await http.Response.fromStream(
        await _http.send(request).timeout(_timeout),
      ).timeout(_timeout);
    } on TimeoutException {
      throw const ApiException("Server javob bermadi. Qayta urinib ko'ring.");
    } on http.ClientException {
      throw const ApiException(
        "Server bilan aloqa yo'q. Internetni tekshiring.",
      );
    }

    final decoded = _decode(response.body);
    final status = response.statusCode;
    if (status >= 200 && status < 300) return decoded;

    // Only a token we actually sent can have expired; a stale response must
    // not log out a session that started after it was sent.
    if (status == 401 && sentToken != null && sentToken == token) {
      onUnauthorized?.call();
    }
    throw _errorFrom(status, decoded);
  }

  static Object? _decode(String body) {
    if (body.isEmpty) return null;
    try {
      return jsonDecode(body);
    } on FormatException {
      return null;
    }
  }

  static ApiException _errorFrom(int status, Object? body) {
    final json = body is Map<String, dynamic>
        ? body
        : const <String, dynamic>{};
    final serverMessage = json['message'] is String
        ? json['message'] as String
        : null;

    final fieldErrors = <String, String>{};
    if (json['errors'] case final Map<String, dynamic> errors) {
      errors.forEach((field, messages) {
        if (messages case [final first, ...]) fieldErrors[field] = '$first';
      });
    }

    final message = switch (status) {
      401 => 'Sessiya tugagan. Qaytadan kiring.',
      403 => serverMessage ?? "Bu amal uchun ruxsat yo'q.",
      404 => "Ma'lumot topilmadi.",
      422 =>
        fieldErrors.values.firstOrNull ??
            serverMessage ??
            "Ma'lumotlar noto'g'ri.",
      >= 500 => "Serverda xatolik yuz berdi. Keyinroq urinib ko'ring.",
      _ => serverMessage ?? 'Xatolik yuz berdi ($status).',
    };
    return ApiException(message, statusCode: status, fieldErrors: fieldErrors);
  }
}
