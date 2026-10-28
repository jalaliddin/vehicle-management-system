// Helpers for reading the Laravel API's JSON, where decimal columns arrive
// as strings (e.g. "12.50") and eager-loaded relations may be missing.

double? parseDecimal(Object? value) => switch (value) {
  num number => number.toDouble(),
  String text => double.tryParse(text),
  _ => null,
};

DateTime? parseDateTime(Object? value) =>
    value is String ? DateTime.tryParse(value)?.toLocal() : null;

Map<String, dynamic>? asJsonMap(Object? value) =>
    value is Map<String, dynamic> ? value : null;

List<Map<String, dynamic>> asJsonList(Object? value) =>
    value is List ? value.whereType<Map<String, dynamic>>().toList() : const [];
