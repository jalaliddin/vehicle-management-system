import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:utg_transport/data/services/api_exception.dart';
import 'package:utg_transport/domain/models/driver.dart';
import 'package:utg_transport/domain/models/new_waybill.dart';
import 'package:utg_transport/domain/models/organization.dart';
import 'package:utg_transport/domain/models/vehicle.dart';
import 'package:utg_transport/domain/models/waybill.dart';
import 'package:utg_transport/ui/features/waybills/view_models/waybill_create_view_model.dart';

import '../helpers/fixtures.dart';

void main() {
  late MockAuthRepository auth;
  late MockWaybillRepository waybills;
  late MockReferenceRepository references;

  const truck = Vehicle(
    id: 5,
    stateNumber: '90A123BC',
    model: 'KamAZ',
    organizationId: 3,
    vehicleType: VehicleType.truck,
    fuelNorm: 30,
    fuelNormAc: 33,
  );
  const otherOrgCar = Vehicle(
    id: 6,
    stateNumber: '90B',
    model: 'Cobalt',
    organizationId: 4,
  );
  const driver = Driver(id: 7, fullName: 'Aliyev Vali', organizationId: 3);

  setUpAll(registerFallbacks);

  setUp(() {
    auth = MockAuthRepository();
    waybills = MockWaybillRepository();
    references = MockReferenceRepository();
    when(() => references.organizations()).thenAnswer(
      (_) async => const [
        Organization(id: 3, name: 'Urganch'),
        Organization(id: 4, name: 'Xiva'),
      ],
    );
    when(() => references.activeVehicles())
        .thenAnswer((_) async => const [truck, otherOrgCar]);
    when(() => references.activeDrivers())
        .thenAnswer((_) async => const [driver]);
  });

  Future<WaybillCreateViewModel> loadedFor(String role) async {
    when(() => auth.currentUser).thenReturn(userWith(role));
    final viewModel = WaybillCreateViewModel(
      authRepository: auth,
      waybillRepository: waybills,
      referenceRepository: references,
      today: DateTime(2026, 10, 21, 15, 30),
    );
    await viewModel.load();
    return viewModel;
  }

  test("a dispatcher creates for their own organization's vehicles", () async {
    final viewModel = await loadedFor('dispatcher');

    expect(viewModel.canChooseOrganization, isFalse);
    expect(viewModel.organizationId, 3);
    expect(viewModel.vehicles, [truck]);
    expect(viewModel.createdDate, DateTime(2026, 10, 21));
    expect(viewModel.validUntil, DateTime(2026, 10, 22));
    verifyNever(() => references.organizations());
  });

  test(
    'picking a vehicle sets its type and drives the fuel estimate',
    () async {
      final viewModel = await loadedFor('dispatcher');

      viewModel.selectVehicle(truck.id);
      expect(viewModel.vehicleType, VehicleType.truck);
      expect(viewModel.estimateFuel('200'), 60);

      viewModel.setHasAc(true);
      expect(viewModel.estimateFuel('200,0'), 66);
      expect(viewModel.estimateFuel(''), isNull);
    },
  );

  test('HQ changing organization clears vehicle and driver', () async {
    final viewModel = await loadedFor('hq_dispatcher');
    viewModel
      ..selectOrganization(3)
      ..selectVehicle(truck.id)
      ..selectDriver(driver.id)
      ..selectOrganization(4);

    expect(viewModel.vehicle, isNull);
    expect(viewModel.driver, isNull);
    expect(viewModel.vehicles, [otherOrgCar]);
  });

  test('validates before calling the API', () async {
    final viewModel = await loadedFor('dispatcher');

    final result = await viewModel.save(
      const WaybillFormText(destination: ' ', tripCount: '0', plannedKm: 'abc'),
      submitToMechanic: false,
    );

    expect(result, isNull);
    expect(
      viewModel.fieldErrors.keys,
      containsAll([
        'vehicle_id',
        'driver_id',
        'destination',
        'trip_count',
        'planned_km',
      ]),
    );
    verifyNever(() => waybills.create(any()));
  });

  test('creates and sends to the mechanic', () async {
    when(() => waybills.create(any()))
        .thenAnswer((_) async => waybillWith(WaybillStatus.draft, id: 55));
    when(() => waybills.submitToMechanic(55)).thenAnswer((_) async {});
    final viewModel = await loadedFor('dispatcher');
    viewModel
      ..selectVehicle(truck.id)
      ..selectDriver(driver.id);

    final result = await viewModel.save(
      const WaybillFormText(
        destination: 'Xiva',
        plannedKm: '120',
        fuelIssued: '36,5',
      ),
      submitToMechanic: true,
    );

    expect(result?.waybill.id, 55);
    expect(result?.submitError, isNull);
    final sent =
        verify(() => waybills.create(captureAny())).captured.single
            as NewWaybill;
    expect(sent.toJson(), containsPair('fuel_issued', 36.5));
    expect(sent.toJson(), containsPair('vehicle_type', 'truck'));
    expect(sent.toJson(), containsPair('created_date', '2026-10-21'));
    verify(() => waybills.submitToMechanic(55)).called(1);
  });

  test('shows server validation errors on their fields', () async {
    when(() => waybills.create(any())).thenThrow(
      const ApiException(
        'bad',
        statusCode: 422,
        fieldErrors: {'destination': 'Juda uzun'},
      ),
    );
    final viewModel = await loadedFor('dispatcher');
    viewModel
      ..selectVehicle(truck.id)
      ..selectDriver(driver.id);

    final result = await viewModel.save(
      const WaybillFormText(destination: 'Xiva'),
      submitToMechanic: false,
    );

    expect(result, isNull);
    expect(viewModel.fieldErrors, {'destination': 'Juda uzun'});
    expect(viewModel.isSaving, isFalse);
  });
}
