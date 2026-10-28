import 'package:flutter_test/flutter_test.dart';
import 'package:utg_transport/domain/models/fuel_station.dart';
import 'package:utg_transport/domain/models/fuel_supplier.dart';
import 'package:utg_transport/domain/models/fuel_vehicle.dart';
import 'package:utg_transport/domain/models/quantity.dart';
import 'package:utg_transport/domain/models/shift_report.dart';
import 'package:utg_transport/ui/core/formatters.dart';

import '../helpers/fixtures.dart';

void main() {
  group('quantities', () {
    test('normalizeQuantity accepts a comma and trims', () {
      expect(normalizeQuantity('40,5'), '40.5');
      expect(normalizeQuantity(' 12 '), '12');
    });

    test('normalizeQuantity refuses what the server would reject', () {
      for (final text in ['40.125', '', 'abc', '-1', '1e3']) {
        expect(normalizeQuantity(text), isNull, reason: text);
      }
    });

    test('quantityError explains the problem', () {
      expect(quantityError('40.125'), "Ko'pi bilan 2 xona kasr");
      expect(quantityError(''), 'Miqdorni kiriting');
      expect(quantityError('x'), 'Son kiriting');
      expect(quantityError('40,5'), isNull);
    });

    test('differences are compared in hundredths', () {
      expect(toHundredths(4159.5) - toHundredths(4160.0), -50);
      expect(toHundredths(33.33) * 3, 9999);
    });

    test('formatQuantity shows two decimals with grouped thousands', () {
      expect(formatQuantity(4160, 'L'), '4 160.00 L');
      expect(formatQuantity(-5, 'L'), '-5.00 L');
      expect(formatQuantity(1234567.5), '1 234 567.50');
      expect(formatQuantity(null, 'L'), '—');
    });
  });

  group('fuel models', () {
    test('ShiftReport reads the shift, operations and totals', () {
      final report = ShiftReport.fromJson(
        shiftReportJson(
          operations: [fuelOperationJson(id: 3, cancelled: true)],
        ),
      );

      expect(report.shift!.isOpen, isTrue);
      expect(report.shift!.openedBy, 'Quyuvchi Test');
      expect(report.shift!.openingMeasured, 3200);
      expect(report.operations.single.isCancelled, isTrue);
      expect(report.operations.single.kind, FuelOperationKind.refuel);
      expect(report.operations.single.isIncoming, isFalse);
      expect(report.operations.single.subject, '01A001AA');
      expect(report.refuelsTotal, 40);
    });

    test('a closed shift keeps its difference and note', () {
      final shift = ShiftReport.fromJson(shiftReportJson(open: false)).shift!;

      expect(shift.isOpen, isFalse);
      expect(shift.discrepancy, -0.5);
      expect(shift.discrepancyNote, "O'lchovdagi xatolik");
      expect(shift.closedBy, 'Quyuvchi Test');
    });

    test('no open shift reads as an empty report', () {
      final report = ShiftReport.fromJson({
        'shift': null,
        'operations': [],
        'totals': null,
      });

      expect(report.shift, isNull);
      expect(report.operations, isEmpty);
      expect(report.receiptsTotal, 0);
    });

    test('a receipt is described by its supplier', () {
      final operation = FuelOperation.fromJson(
        fuelOperationJson(kind: 'receipt', amount: '1000.00'),
      );

      expect(operation.isIncoming, isTrue);
      expect(operation.subject, 'UzGazOil');
    });

    test('FuelStation reads balance and unit', () {
      final station = FuelStation.fromJson(fuelStationJson());

      expect(station.balance, 3200);
      expect(station.unit, 'L');
      expect(station.fuelType, 'benzin');
    });

    test('FuelVehicle reads the lookup result', () {
      final vehicle = FuelVehicle.fromJson(fuelVehicleJson(matches: false));

      expect(vehicle.fuelTypeMatches, isFalse);
      expect(vehicle.fuelType, 'dizel');
      expect(vehicle.waybillNumber, 'UTG-202610-0001');
      expect(vehicle.driverName, 'Aliyev Vali');
      expect(vehicle.tankCapacity, 60);
      expect(vehicle.odometer, 125000);
    });

    test('FuelSupplier reads its active flag', () {
      expect(
        FuelSupplier.fromJson(fuelSupplierJson(active: false)).isActive,
        isFalse,
      );
    });
  });
}
