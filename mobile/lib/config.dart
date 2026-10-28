/// Build-time settings, overridable with `--dart-define`.
abstract final class AppConfig {
  /// The Laravel API root, including the `/api` prefix. For a local backend
  /// on the Android emulator use `http://10.0.2.2:<port>/api`.
  static const apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://vms.utg.uz/api',
  );
}
