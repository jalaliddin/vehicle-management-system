// Fuel quantities as the server validates them (`decimal:0,2`): plain
// decimals with at most two places, sent as text so nothing is rounded.

final _plainDecimal = RegExp(r'^\d+(\.\d+)?$');

String _clean(String text) => text.trim().replaceAll(',', '.');

/// `40,5` → `40.5`; null when blank, not a plain number, or more precise
/// than hundredths.
String? normalizeQuantity(String text) {
  final normalized = _clean(text);
  if (!_plainDecimal.hasMatch(normalized)) return null;
  final decimals = normalized.contains('.')
      ? normalized.split('.').last.length
      : 0;
  return decimals <= 2 ? normalized : null;
}

/// Why [normalizeQuantity] refuses [text], or null when it is fine.
String? quantityError(String text) {
  final normalized = _clean(text);
  if (normalized.isEmpty) return 'Miqdorni kiriting';
  if (!_plainDecimal.hasMatch(normalized)) return 'Son kiriting';
  return normalizeQuantity(text) == null ? "Ko'pi bilan 2 xona kasr" : null;
}

/// Compare quantities in whole hundredths, never as doubles.
int toHundredths(double value) => (value * 100).round();
