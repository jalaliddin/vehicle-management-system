import '../../../../data/services/api_exception.dart';

/// Splits a failed call into the errors a form shows under its [shownFields]
/// and one message for the rest. The fuel ledger reports rule violations
/// under keys such as `shift` or `transaction` that no form field shows.
({Map<String, String> fields, String? message}) splitErrors(
  ApiException error,
  Set<String> shownFields,
) {
  final fields = {
    for (final MapEntry(:key, :value) in error.fieldErrors.entries)
      if (shownFields.contains(key)) key: value,
  };
  final unshown = error.fieldErrors.entries
      .where((entry) => !shownFields.contains(entry.key))
      .map((entry) => entry.value)
      .firstOrNull;
  return (
    fields: fields,
    message: unshown ?? (fields.isEmpty ? error.message : null),
  );
}
