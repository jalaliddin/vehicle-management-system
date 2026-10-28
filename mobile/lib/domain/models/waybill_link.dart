/// Extracts a waybill id from scanned QR code text.
///
/// Printed waybills encode `<web origin>/w/<id>`. Links to the web detail
/// page (`/waybills/<id>`) and a bare id are accepted too.
int? waybillIdFromQr(String raw) {
  final text = raw.trim();
  final bareId = int.tryParse(text);
  if (bareId != null) return bareId > 0 ? bareId : null;

  final uri = Uri.tryParse(text);
  if (uri == null || !uri.hasScheme) return null;

  final segments = uri.pathSegments.where((s) => s.isNotEmpty).toList();
  for (var i = segments.length - 2; i >= 0; i--) {
    if (segments[i] == 'w' || segments[i] == 'waybills') {
      final id = int.tryParse(segments[i + 1]);
      return id != null && id > 0 ? id : null;
    }
  }
  return null;
}
