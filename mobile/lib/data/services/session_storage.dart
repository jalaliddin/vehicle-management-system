import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class StoredSession {
  const StoredSession({required this.token, required this.userJson});

  final String token;

  /// The `/auth/me` payload as JSON text, so the app can open offline.
  final String userJson;
}

abstract interface class SessionStorage {
  Future<StoredSession?> read();
  Future<void> write(StoredSession session);
  Future<void> clear();
}

/// Keeps the session in the Android Keystore-backed secure storage.
class SecureSessionStorage implements SessionStorage {
  SecureSessionStorage([FlutterSecureStorage? storage])
    : _storage = storage ?? const FlutterSecureStorage();

  static const _tokenKey = 'auth_token';
  static const _userKey = 'auth_user';

  final FlutterSecureStorage _storage;

  @override
  Future<StoredSession?> read() async {
    try {
      final token = await _storage.read(key: _tokenKey);
      final userJson = await _storage.read(key: _userKey);
      if (token == null || userJson == null) return null;
      return StoredSession(token: token, userJson: userJson);
    } on Exception catch (error) {
      // A broken keystore (e.g. after a device restore) must not block
      // the app; the user just signs in again.
      debugPrint('Session read failed: $error');
      return null;
    }
  }

  @override
  Future<void> write(StoredSession session) async {
    await _storage.write(key: _tokenKey, value: session.token);
    await _storage.write(key: _userKey, value: session.userJson);
  }

  @override
  Future<void> clear() async {
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _userKey);
  }
}
