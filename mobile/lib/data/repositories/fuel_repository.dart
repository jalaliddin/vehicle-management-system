import '../../domain/models/fuel_station.dart';
import '../../domain/models/fuel_supplier.dart';
import '../../domain/models/fuel_vehicle.dart';
import '../../domain/models/json_parsing.dart';
import '../../domain/models/shift_report.dart';
import '../services/api_client.dart';

/// The fueler's side of the fuel API: shifts, refuels, receipts and
/// cancellations at the stations the server lets the user operate.
/// Quantities travel as the text the user typed, already normalized.
class FuelRepository {
  FuelRepository({required ApiClient apiClient}) : _api = apiClient;

  final ApiClient _api;

  Future<List<FuelStation>> stations() async =>
      asJsonList(await _api.get('/fuel/stations'))
          .map(FuelStation.fromJson)
          .toList();

  Future<ShiftReport> currentShift(int stationId) async => ShiftReport.fromJson(
    asJsonMap(await _api.get('/fuel/stations/$stationId/current-shift')) ??
        const {},
  );

  Future<void> openShift(int stationId, String openingMeasured) => _api.post(
    '/fuel/stations/$stationId/shifts',
    {'opening_measured': openingMeasured},
  );

  Future<void> closeShift(
    int shiftId, {
    required String closingMeasured,
    String? note,
  }) => _api.post('/fuel/shifts/$shiftId/close', {
    'closing_measured': closingMeasured,
    if (note != null && note.trim().isNotEmpty) 'discrepancy_note': note.trim(),
  });

  Future<ShiftReport> shiftReport(int shiftId) async => ShiftReport.fromJson(
    asJsonMap(await _api.get('/fuel/shifts/$shiftId')) ?? const {},
  );

  Future<List<FuelVehicle>> vehicles(int stationId, String search) async =>
      asJsonList(
        await _api.get(
          '/fuel/stations/$stationId/vehicles',
          query: {'search': search.trim()},
        ),
      ).map(FuelVehicle.fromJson).toList();

  /// The vehicle of a scanned waybill, if the station may refuel it. The
  /// public waybill page names the vehicle; the station lookup decides
  /// whether it belongs to the station's organization.
  Future<FuelVehicle?> vehicleFromWaybill(int stationId, int waybillId) async {
    final waybill = asJsonMap(await _api.get('/waybills/$waybillId/public'));
    final stateNumber = asJsonMap(waybill?['vehicle'])?['state_number'];
    if (stateNumber is! String || stateNumber.trim().isEmpty) return null;

    // The server matches the number as stored, spaces included; compare
    // the results without spaces and case.
    final wanted = _plateKey(stateNumber);
    final candidates = await vehicles(stationId, stateNumber);
    return candidates
        .where((vehicle) => _plateKey(vehicle.stateNumber) == wanted)
        .firstOrNull;
  }

  Future<void> refuel(
    int stationId, {
    required int vehicleId,
    required int odometer,
    required String amount,
  }) => _api.post('/fuel/stations/$stationId/refuels', {
    'vehicle_id': vehicleId,
    'odometer': odometer,
    'amount': amount,
  });

  Future<List<FuelSupplier>> activeSuppliers() async =>
      asJsonList(await _api.get('/fuel/suppliers'))
          .map(FuelSupplier.fromJson)
          .where((supplier) => supplier.isActive)
          .toList();

  Future<void> receive(
    int stationId, {
    required int supplierId,
    required String documentNumber,
    required DateTime documentDate,
    required String documentAmount,
    required String amount,
    String? pricePerLiter,
  }) => _api.post('/fuel/stations/$stationId/receipts', {
    'fuel_supplier_id': supplierId,
    'document_number': documentNumber.trim(),
    'document_date': _isoDate(documentDate),
    'document_amount': documentAmount,
    'amount': amount,
    if (pricePerLiter != null && pricePerLiter.trim().isNotEmpty)
      'price_per_liter': pricePerLiter.trim(),
  });

  Future<void> cancel(int operationId, String reason) => _api.post(
    '/fuel/transactions/$operationId/cancel',
    {'reason': reason.trim()},
  );

  static String _plateKey(String stateNumber) =>
      stateNumber.replaceAll(RegExp(r'\s+'), '').toUpperCase();

  static String _isoDate(DateTime date) =>
      '${date.year.toString().padLeft(4, '0')}-'
      '${date.month.toString().padLeft(2, '0')}-'
      '${date.day.toString().padLeft(2, '0')}';
}
