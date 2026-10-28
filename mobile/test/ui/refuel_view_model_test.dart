import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:utg_transport/data/services/api_exception.dart';
import 'package:utg_transport/domain/models/fuel_station.dart';
import 'package:utg_transport/domain/models/fuel_vehicle.dart';
import 'package:utg_transport/ui/features/fuel/view_models/refuel_view_model.dart';

import '../helpers/fixtures.dart';

void main() {
  late MockFuelRepository fuel;
  late RefuelViewModel viewModel;

  FuelVehicle vehicle({
    int id = 5,
    String stateNumber = '01A001AA',
    bool matches = true,
  }) => FuelVehicle.fromJson(
    fuelVehicleJson(id: id, stateNumber: stateNumber, matches: matches),
  );

  setUp(() {
    fuel = MockFuelRepository();
    viewModel = RefuelViewModel(
      fuelRepository: fuel,
      station: FuelStation.fromJson(fuelStationJson()),
    );
  });

  test('a late answer to an older search is ignored', () async {
    final older = Completer<List<FuelVehicle>>();
    final newer = Completer<List<FuelVehicle>>();
    when(() => fuel.vehicles(1, '01')).thenAnswer((_) => older.future);
    when(() => fuel.vehicles(1, '01A')).thenAnswer((_) => newer.future);

    final first = viewModel.search('01');
    final second = viewModel.search('01A');
    newer.complete([vehicle()]);
    await second;
    older.complete([vehicle(id: 6, stateNumber: '01B002BB')]);
    await first;

    expect(viewModel.results.map((v) => v.stateNumber), ['01A001AA']);
    expect(viewModel.searching, isFalse);
  });

  test('pickFromWaybill selects the vehicle', () async {
    when(() => fuel.vehicleFromWaybill(1, 10))
        .thenAnswer((_) async => vehicle());

    await viewModel.pickFromWaybill(10);

    expect(viewModel.vehicle?.id, 5);
    expect(viewModel.message, isNull);
  });

  test(
    'pickFromWaybill with no match keeps the selection empty and explains',
    () async {
      when(() => fuel.vehicleFromWaybill(1, 10)).thenAnswer((_) async => null);

      await viewModel.pickFromWaybill(10);

      expect(viewModel.vehicle, isNull);
      expect(
        viewModel.message,
        'Avtomobil bu shahobcha tashkilotida topilmadi',
      );
    },
  );

  test('pickFromWaybill reports a failed lookup', () async {
    when(() => fuel.vehicleFromWaybill(1, 10))
        .thenThrow(const ApiException("Ma'lumot topilmadi", statusCode: 404));

    await viewModel.pickFromWaybill(10);

    expect(viewModel.vehicle, isNull);
    expect(viewModel.message, "Ma'lumot topilmadi");
  });

  test('a wrong fuel type blocks saving', () {
    viewModel.select(vehicle(matches: false));

    expect(viewModel.canSave, isFalse);
    expect(
      viewModel.fuelTypeWarning,
      "Yoqilg'i turi mos emas: avtomobil dizel, shahobcha benzin",
    );
  });

  test('save normalizes the amount', () async {
    when(() => fuel.refuel(1, vehicleId: 5, odometer: 125200, amount: '40.5'))
        .thenAnswer((_) async {});
    viewModel.select(vehicle());

    expect(viewModel.fuelTypeWarning, isNull);
    expect(await viewModel.save(odometer: '125200', amount: '40,5'), isTrue);
    verify(() => fuel.refuel(1, vehicleId: 5, odometer: 125200, amount: '40.5'))
        .called(1);
  });

  test('save refuses three decimals and a non-integer odometer without calling the server', () async {
    viewModel.select(vehicle());

    expect(await viewModel.save(odometer: '12.5', amount: '40.125'), isFalse);
    expect(viewModel.fieldErrors, {
      'odometer': 'Butun son kiriting',
      'amount': "Ko'pi bilan 2 xona kasr",
    });
    verifyNever(
      () => fuel.refuel(
        any(),
        vehicleId: any(named: 'vehicleId'),
        odometer: any(named: 'odometer'),
        amount: any(named: 'amount'),
      ),
    );
  });

  test('server field errors land under their fields', () async {
    const message = "Spidometr ko'rsatkichi oldingisidan kam";
    when(() => fuel.refuel(1, vehicleId: 5, odometer: 100, amount: '40'))
        .thenThrow(
          const ApiException(
            message,
            statusCode: 422,
            fieldErrors: {'odometer': message},
          ),
        );
    viewModel.select(vehicle());

    expect(await viewModel.save(odometer: '100', amount: '40'), isFalse);
    expect(viewModel.fieldErrors['odometer'], message);
    expect(viewModel.message, isNull);
  });

  test('a ledger refusal without a field becomes the message', () async {
    const message = 'Smena ochilmagan';
    when(() => fuel.refuel(1, vehicleId: 5, odometer: 125200, amount: '40'))
        .thenThrow(
          const ApiException(
            message,
            statusCode: 422,
            fieldErrors: {'shift': message},
          ),
        );
    viewModel.select(vehicle());

    expect(await viewModel.save(odometer: '125200', amount: '40'), isFalse);
    expect(viewModel.message, message);
  });
}
