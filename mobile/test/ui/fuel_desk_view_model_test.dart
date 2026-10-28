import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:utg_transport/data/services/api_exception.dart';
import 'package:utg_transport/domain/models/fuel_station.dart';
import 'package:utg_transport/domain/models/shift_report.dart';
import 'package:utg_transport/ui/features/fuel/view_models/fuel_desk_view_model.dart';

import '../helpers/fixtures.dart';

void main() {
  late MockFuelRepository fuel;
  late FuelDeskViewModel viewModel;

  FuelStation station({String balance = '3200.00'}) =>
      FuelStation.fromJson(fuelStationJson(balance: balance));

  ShiftReport report({
    bool open = true,
    List<Map<String, dynamic>>? operations,
  }) => ShiftReport.fromJson(
    shiftReportJson(open: open, operations: operations ?? const []),
  );

  setUp(() {
    fuel = MockFuelRepository();
    viewModel = FuelDeskViewModel(fuelRepository: fuel);
    when(() => fuel.stations()).thenAnswer((_) async => [station()]);
    when(() => fuel.currentShift(1)).thenAnswer((_) async => report());
  });

  test('load selects the only station and its shift', () async {
    await viewModel.load();

    expect(viewModel.station?.id, 1);
    expect(viewModel.report?.shift?.isOpen, isTrue);
    expect(viewModel.loadError, isNull);
  });

  test('a failed station load is not reported as no stations', () async {
    when(() => fuel.stations()).thenThrow(const ApiException('Tarmoq xatosi'));

    await viewModel.load();

    expect(viewModel.loadError, 'Tarmoq xatosi');
    expect(viewModel.noStations, isFalse);
  });

  test('an empty station list means no stations', () async {
    when(() => fuel.stations()).thenAnswer((_) async => []);

    await viewModel.load();

    expect(viewModel.noStations, isTrue);
    verifyNever(() => fuel.currentShift(any()));
  });

  test('openShift refuses a bad quantity without calling the server', () async {
    await viewModel.load();

    expect(await viewModel.openShift('12.345'), isFalse);
    expect(
      viewModel.fieldErrors['opening_measured'],
      "Ko'pi bilan 2 xona kasr",
    );
    verifyNever(() => fuel.openShift(any(), any()));
  });

  test('openShift sends the normalized balance and reloads', () async {
    when(() => fuel.openShift(1, '3199.5')).thenAnswer((_) async {});
    await viewModel.load();

    expect(await viewModel.openShift('3199,5'), isTrue);
    verify(() => fuel.openShift(1, '3199.5')).called(1);
    verify(() => fuel.currentShift(1)).called(2);
  });

  test(
    'beginClosing reloads the station before the difference is computed',
    () async {
      await viewModel.load();
      when(() => fuel.stations())
          .thenAnswer((_) async => [station(balance: '4160.00')]);

      await viewModel.beginClosing();

      expect(viewModel.closing, isTrue);
      expect(viewModel.discrepancyHundredths('4159.50'), -50);
    },
  );

  test('noteRequired follows the difference', () async {
    await viewModel.load();

    expect(viewModel.noteRequired('3200'), isFalse);
    expect(viewModel.noteRequired('3199,5'), isTrue);
    expect(viewModel.discrepancyHundredths('abc'), isNull);
  });

  test('a server note error reveals the note field', () async {
    when(
      () => fuel.closeShift(
        4,
        closingMeasured: any(named: 'closingMeasured'),
        note: any(named: 'note'),
      ),
    ).thenThrow(
      const ApiException(
        'Farq izohini yozing',
        statusCode: 422,
        fieldErrors: {'discrepancy_note': 'Farq izohini yozing'},
      ),
    );
    await viewModel.load();

    expect(await viewModel.closeShift('3200', ''), isNull);
    expect(viewModel.noteRequired('3200'), isTrue);
    expect(viewModel.fieldErrors['discrepancy_note'], 'Farq izohini yozing');
  });

  test('closeShift returns the shift id', () async {
    when(() => fuel.closeShift(4, closingMeasured: '3199.5', note: 'Kam'))
        .thenAnswer((_) async {});
    await viewModel.load();
    await viewModel.beginClosing();

    expect(await viewModel.closeShift('3199,5', 'Kam'), 4);
    expect(viewModel.closing, isFalse);
  });

  test(
    'closeShift without a note is refused locally when there is a difference',
    () async {
      await viewModel.load();

      expect(await viewModel.closeShift('3100', ' '), isNull);
      expect(viewModel.fieldErrors['discrepancy_note'], 'Farq izohini yozing');
      verifyNever(
        () => fuel.closeShift(
          any(),
          closingMeasured: any(named: 'closingMeasured'),
          note: any(named: 'note'),
        ),
      );
    },
  );

  test('cancel sends the reason and reloads', () async {
    when(
      () => fuel.currentShift(1),
    ).thenAnswer((_) async => report(operations: [fuelOperationJson(id: 9)]));
    when(() => fuel.cancel(9, 'Xato')).thenAnswer((_) async {});
    await viewModel.load();

    expect(
      await viewModel.cancel(viewModel.report!.operations.single, 'Xato'),
      isTrue,
    );
    verify(() => fuel.cancel(9, 'Xato')).called(1);
    verify(() => fuel.currentShift(1)).called(2);
  });

  test('a refused cancel keeps the server message', () async {
    const message = "Smena yopilgan, bekor qilib bo'lmaydi";
    when(
      () => fuel.currentShift(1),
    ).thenAnswer((_) async => report(operations: [fuelOperationJson(id: 9)]));
    when(() => fuel.cancel(9, 'Xato')).thenThrow(
      const ApiException(
        message,
        statusCode: 422,
        fieldErrors: {'transaction': message},
      ),
    );
    await viewModel.load();

    expect(
      await viewModel.cancel(viewModel.report!.operations.single, 'Xato'),
      isFalse,
    );
    expect(viewModel.actionError, message);
  });

  test(
    'a ledger error on a field the form lacks becomes the message',
    () async {
      when(() => fuel.openShift(1, '3200')).thenThrow(
        const ApiException(
          'Bu shahobchada ochiq smena bor',
          statusCode: 422,
          fieldErrors: {'shift': 'Bu shahobchada ochiq smena bor'},
        ),
      );
      await viewModel.load();

      expect(await viewModel.openShift('3200'), isFalse);
      expect(viewModel.actionError, 'Bu shahobchada ochiq smena bor');
      expect(viewModel.fieldErrors, isEmpty);
    },
  );

  test(
    'a slow answer for the previous station does not land on the new one',
    () async {
      final stations = [
        FuelStation.fromJson(fuelStationJson()),
        FuelStation.fromJson(fuelStationJson(id: 2, fuelType: 'dizel')),
      ];
      when(() => fuel.stations()).thenAnswer((_) async => stations);
      final otherShift = shiftReportJson();
      (otherShift['shift'] as Map<String, dynamic>)['id'] = 7;
      when(() => fuel.currentShift(2))
          .thenAnswer((_) async => ShiftReport.fromJson(otherShift));
      await viewModel.load();

      final slow = Completer<ShiftReport>();
      when(() => fuel.currentShift(1)).thenAnswer((_) => slow.future);
      final refresh = viewModel.load();
      // Let the refresh get its stations and wait for station 1's shift.
      await Future<void>.delayed(Duration.zero);
      verify(() => fuel.currentShift(1)).called(2);
      await viewModel.selectStation(2);
      slow.complete(report());
      await refresh;

      expect(viewModel.station?.id, 2);
      expect(viewModel.report?.shift?.id, 7);
      expect(viewModel.loading, isFalse);
    },
  );
}
