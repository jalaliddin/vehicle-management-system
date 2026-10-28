import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';

import '../../domain/models/user.dart';
import '../services/api_client.dart';
import '../services/api_exception.dart';
import '../services/session_storage.dart';

/// The signed-in user. Notifies on login and logout, which drives the
/// router's redirect.
class AuthRepository extends ChangeNotifier {
  AuthRepository({
    required ApiClient apiClient,
    required SessionStorage sessionStorage,
  }) : _api = apiClient,
       _storage = sessionStorage {
    _api.onUnauthorized = () => unawaited(_clearSession());
  }

  final ApiClient _api;
  final SessionStorage _storage;
  User? _user;

  User? get currentUser => _user;
  bool get isAuthenticated => _user != null;

  /// Restores the saved session, then refreshes the profile in the
  /// background so a role change on the server is picked up.
  Future<void> restore() async {
    final session = await _storage.read();
    if (session == null) return;
    try {
      _user = User.fromJson(
        jsonDecode(session.userJson) as Map<String, dynamic>,
      );
    } on Object {
      await _storage.clear();
      return;
    }
    _api.token = session.token;
    notifyListeners();
    unawaited(_refreshProfile(session.token));
  }

  Future<void> login({required String email, required String password}) async {
    final json = await _api.post('/auth/login', {
      'email': email.trim(),
      'password': password,
    }) as Map<String, dynamic>;
    await _saveSession(
      json['token'] as String,
      json['user'] as Map<String, dynamic>,
    );
  }

  Future<void> logout() async {
    try {
      await _api.post('/auth/logout');
    } on ApiException {
      // The token may already be revoked or the device offline; signing out
      // locally is what matters.
    }
    await _clearSession();
  }

  Future<void> _refreshProfile(String token) async {
    try {
      final json = await _api.get('/auth/me') as Map<String, dynamic>;
      if (_api.token == token) await _saveSession(token, json);
    } on ApiException {
      // Offline: keep the cached profile. A 401 has already signed out.
    }
  }

  Future<void> _saveSession(String token, Map<String, dynamic> userJson) async {
    await _storage.write(
      StoredSession(token: token, userJson: jsonEncode(userJson)),
    );
    _api.token = token;
    _user = User.fromJson(userJson);
    notifyListeners();
  }

  Future<void> _clearSession() async {
    final wasAuthenticated = isAuthenticated;
    _api.token = null;
    _user = null;
    if (wasAuthenticated) notifyListeners();
    await _storage.clear();
  }
}
