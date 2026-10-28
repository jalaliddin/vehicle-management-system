import 'package:flutter_test/flutter_test.dart';
import 'package:utg_transport/domain/models/waybill_link.dart';

void main() {
  test('reads the id from the printed QR link', () {
    expect(waybillIdFromQr('https://vms.utg.uz/w/42'), 42);
    expect(waybillIdFromQr('http://192.168.1.5/w/7?ref=print'), 7);
    expect(waybillIdFromQr('  https://vms.utg.uz/w/42/  '), 42);
  });

  test('accepts web detail links and bare ids', () {
    expect(waybillIdFromQr('https://vms.utg.uz/waybills/15'), 15);
    expect(waybillIdFromQr('99'), 99);
  });

  test('rejects anything else', () {
    expect(waybillIdFromQr(''), isNull);
    expect(waybillIdFromQr('0'), isNull);
    expect(waybillIdFromQr('-3'), isNull);
    expect(waybillIdFromQr('UTG-202610-0001'), isNull);
    expect(waybillIdFromQr('https://example.com/w/abc'), isNull);
    expect(waybillIdFromQr('https://example.com/vehicles/5'), isNull);
    expect(waybillIdFromQr('https://example.com/w'), isNull);
    expect(waybillIdFromQr('WIFI:S:office;T:WPA;P:secret;;'), isNull);
  });
}
