import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:utg_transport/data/repositories/fuel_repository.dart';
import 'package:utg_transport/data/services/api_client.dart';
import 'package:utg_transport/data/services/api_exception.dart';

import '../helpers/fixtures.dart';

void main() {
  late Map<String, http.Response Function(http.Request)> routes;
  late List<http.Request> sent;
  late FuelRepository fuel;

  http.Response json(Object body, [int status = 200]) => http.Response(
    jsonEncode(body),
    status,
    headers: {'content-type': 'application/json'},
  );

  Map<String, dynamic> bodyOf(String route) => jsonDecode(
    sent.lastWhere((r) => '${r.method} ${r.url.path}' == route).body,
  );

  setUp(() {
    routes = {};
    sent = [];
    fuel = FuelRepository(
      apiClient: ApiClient(
        baseUrl: 'https://vms.test/api',
        httpClient: MockClient((request) async {
          sent.add(request);
          final handler = routes['${request.method} ${request.url.path}'];
          return handler?.call(request) ?? http.Response('', 404);
        }),
      ),
    );
  });

  test('stations parses the list', () async {
    routes['GET /api/fuel/stations'] = (_) => json([
      fuelStationJson(),
      fuelStationJson(id: 2, fuelType: 'gaz', balance: '900.00'),
    ]);

    final stations = await fuel.stations();

    expect(stations.map((s) => s.id), [1, 2]);
    expect(stations.last.unit, 'm³');
    expect(stations.last.balance, 900);
  });

  test('currentShift reads the report', () async {
    routes['GET /api/fuel/stations/1/current-shift'] = (_) =>
        json(shiftReportJson(operations: [fuelOperationJson()]));

    final report = await fuel.currentShift(1);

    expect(report.shift!.id, 4);
    expect(report.operations.single.subject, '01A001AA');
  });

  test('openShift sends the measured balance', () async {
    routes['POST /api/fuel/stations/1/shifts'] = (_) => json({'id': 4}, 201);

    await fuel.openShift(1, '3200');

    expect(bodyOf('POST /api/fuel/stations/1/shifts'), {
      'opening_measured': '3200',
    });
  });

  test('closeShift sends the note only when given', () async {
    routes['POST /api/fuel/shifts/4/close'] = (_) => json({'id': 4});

    await fuel.closeShift(4, closingMeasured: '4160', note: null);
    expect(bodyOf('POST /api/fuel/shifts/4/close'), {
      'closing_measured': '4160',
    });

    await fuel.closeShift(4, closingMeasured: '4159.5', note: 'Kam');
    expect(bodyOf('POST /api/fuel/shifts/4/close'), {
      'closing_measured': '4159.5',
      'discrepancy_note': 'Kam',
    });
  });

  test('shiftReport reads a closed shift', () async {
    routes['GET /api/fuel/shifts/4'] = (_) =>
        json(shiftReportJson(open: false));

    final report = await fuel.shiftReport(4);

    expect(report.shift!.discrepancy, -0.5);
  });

  test('vehicles passes the search', () async {
    routes['GET /api/fuel/stations/1/vehicles'] = (request) => json([
      if (request.url.queryParameters['search'] == '01A') fuelVehicleJson(),
    ]);

    final vehicles = await fuel.vehicles(1, '01A');

    expect(vehicles.single.stateNumber, '01A001AA');
  });

  test('refuel posts vehicle, odometer and amount', () async {
    routes['POST /api/fuel/stations/1/refuels'] = (_) => json({'id': 9}, 201);

    await fuel.refuel(1, vehicleId: 5, odometer: 125200, amount: '40.5');

    expect(bodyOf('POST /api/fuel/stations/1/refuels'), {
      'vehicle_id': 5,
      'odometer': 125200,
      'amount': '40.5',
    });
  });

  test('receive formats the date and drops a blank price', () async {
    routes['POST /api/fuel/stations/1/receipts'] = (_) => json({'id': 9}, 201);

    await fuel.receive(
      1,
      supplierId: 1,
      documentNumber: 'TTN-1',
      documentDate: DateTime(2026, 10, 8),
      documentAmount: '1000',
      amount: '995.5',
      pricePerLiter: ' ',
    );

    expect(bodyOf('POST /api/fuel/stations/1/receipts'), {
      'fuel_supplier_id': 1,
      'document_number': 'TTN-1',
      'document_date': '2026-10-08',
      'document_amount': '1000',
      'amount': '995.5',
    });
  });

  test('activeSuppliers drops inactive ones', () async {
    routes['GET /api/fuel/suppliers'] = (_) =>
        json([fuelSupplierJson(), fuelSupplierJson(id: 2, active: false)]);

    final suppliers = await fuel.activeSuppliers();

    expect(suppliers.map((s) => s.id), [1]);
  });

  test('cancel sends the reason', () async {
    routes['POST /api/fuel/transactions/9/cancel'] = (_) => json({'id': 9});

    await fuel.cancel(9, 'Xato');

    expect(bodyOf('POST /api/fuel/transactions/9/cancel'), {'reason': 'Xato'});
  });

  group('vehicleFromWaybill', () {
    setUp(() {
      routes['GET /api/waybills/10/public'] = (_) => json({
        'waybill_number': 'UTG-202610-0001',
        'vehicle': {'state_number': '01a 001aa', 'model': 'Toyota Camry'},
      });
    });

    test('picks the exact state number', () async {
      routes['GET /api/fuel/stations/1/vehicles'] = (request) => json([
        fuelVehicleJson(id: 6, stateNumber: '01A001AB'),
        fuelVehicleJson(id: 5, stateNumber: '01A001AA'),
      ]);

      final vehicle = await fuel.vehicleFromWaybill(1, 10);

      expect(vehicle?.id, 5);
      expect(
        sent.last.url.queryParameters['search'],
        '01A001AA',
        reason: 'searches by the normalized number',
      );
    });

    test('returns null when the station has no such vehicle', () async {
      routes['GET /api/fuel/stations/1/vehicles'] = (_) => json([]);

      expect(await fuel.vehicleFromWaybill(1, 10), isNull);
    });

    test('returns null for a waybill without a vehicle', () async {
      routes['GET /api/waybills/10/public'] = (_) =>
          json({'waybill_number': 'UTG-202610-0001', 'vehicle': null});

      expect(await fuel.vehicleFromWaybill(1, 10), isNull);
    });
  });

  test('a 422 surfaces field errors', () async {
    routes['POST /api/fuel/stations/1/refuels'] = (_) => json({
      'message': "Spidometr ko'rsatkichi oldingisidan kam",
      'errors': {
        'odometer': ["Spidometr ko'rsatkichi oldingisidan kam"],
      },
    }, 422);

    await expectLater(
      fuel.refuel(1, vehicleId: 5, odometer: 100, amount: '40'),
      throwsA(
        isA<ApiException>().having(
          (e) => e.fieldErrors['odometer'],
          'odometer error',
          "Spidometr ko'rsatkichi oldingisidan kam",
        ),
      ),
    );
  });
}
