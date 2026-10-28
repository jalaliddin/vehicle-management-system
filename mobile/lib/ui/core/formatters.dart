import '../../domain/models/quantity.dart';

String _twoDigits(int value) => value.toString().padLeft(2, '0');

String formatDate(DateTime? date) => date == null
    ? '—'
    : '${_twoDigits(date.day)}.${_twoDigits(date.month)}.${date.year}';

String formatDateTime(DateTime? date) => date == null
    ? '—'
    : '${formatDate(date)} ${_twoDigits(date.hour)}:${_twoDigits(date.minute)}';

/// `12` or `12.5`, with an optional unit; `—` when missing.
String formatAmount(double? value, [String unit = '']) {
  if (value == null) return '—';
  final text = value == value.roundToDouble()
      ? value.toStringAsFixed(0)
      : value.toStringAsFixed(1);
  return unit.isEmpty ? text : '$text $unit';
}

/// Parses user input such as `12,5` or `12.5`; null when blank or invalid.
double? parseAmount(String text) {
  final normalized = text.trim().replaceAll(',', '.');
  return normalized.isEmpty ? null : double.tryParse(normalized);
}

/// Fuel quantities: two decimals, thousands grouped with a space.
String formatQuantity(double? value, [String unit = '']) {
  if (value == null) return '—';
  final [whole, fraction] = value.abs().toStringAsFixed(2).split('.');
  final grouped = whole.replaceAllMapped(
    RegExp(r'\B(?=(\d{3})+$)'),
    (_) => ' ',
  );
  final sign = toHundredths(value) < 0 ? '-' : '';
  final text = '$sign$grouped.$fraction';
  return unit.isEmpty ? text : '$text $unit';
}
