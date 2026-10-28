import 'package:flutter/foundation.dart';

import '../../domain/models/new_waybill.dart';
import '../../domain/models/paginated.dart';
import '../../domain/models/waybill.dart';
import '../services/api_client.dart';

/// Waybill reads and workflow transitions.
///
/// Notifies listeners after every successful change so lists and queues
/// that show waybills can reload.
class WaybillRepository extends ChangeNotifier {
  WaybillRepository({required ApiClient apiClient}) : _api = apiClient;

  final ApiClient _api;

  Future<Paginated<Waybill>> list({
    int page = 1,
    int perPage = 20,
    WaybillStatus? status,
    String? search,
  }) async {
    final json = await _api.get(
      '/waybills',
      query: {
        'page': '$page',
        'per_page': '$perPage',
        'status': status?.apiValue,
        'search': search?.trim(),
      },
    ) as Map<String, dynamic>;
    return Paginated.fromJson(json, Waybill.fromJson);
  }

  Future<Waybill> get(int id) async =>
      Waybill.fromJson(await _api.get('/waybills/$id') as Map<String, dynamic>);

  Future<Waybill> create(NewWaybill waybill) async {
    final json =
        await _api.post('/waybills', waybill.toJson()) as Map<String, dynamic>;
    notifyListeners();
    return Waybill.fromJson(json);
  }

  Future<void> submitToMechanic(int id) => _transition(id, 'submit');

  Future<void> mechanicCheck(int id, {required bool passed, String? notes}) =>
      _transition(id, 'mechanic-check', {'passed': passed, 'notes': notes});

  Future<void> doctorCheck(int id, {required bool passed, String? notes}) =>
      _transition(id, 'doctor-check', {'passed': passed, 'notes': notes});

  Future<void> approve(int id) => _transition(id, 'approve');

  Future<void> depart(int id) => _transition(id, 'depart');

  Future<void> complete(
    int id, {
    double? actualKm,
    double? fuelConsumed,
    double? fuelReturned,
  }) => _transition(id, 'complete', {
    'actual_km': actualKm,
    'fuel_consumed': fuelConsumed,
    'fuel_returned': fuelReturned,
  });

  // Transition endpoints return a partial waybill, so callers reload with
  // [get] instead of using the response.
  Future<void> _transition(
    int id,
    String action, [
    Map<String, dynamic>? body,
  ]) async {
    await _api.post('/waybills/$id/$action', body);
    notifyListeners();
  }
}
