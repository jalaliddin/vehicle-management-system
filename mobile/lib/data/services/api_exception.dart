/// A failed API call, with a message ready to show the user.
class ApiException implements Exception {
  const ApiException(
    this.message, {
    this.statusCode,
    this.fieldErrors = const {},
  });

  final String message;

  /// Null when the request never got a response (offline, timeout).
  final int? statusCode;

  /// The first Laravel validation message per field, from a 422 response.
  final Map<String, String> fieldErrors;

  @override
  String toString() => 'ApiException($statusCode): $message';
}
