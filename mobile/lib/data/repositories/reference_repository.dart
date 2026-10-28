import '../../domain/models/driver.dart';
import '../../domain/models/json_parsing.dart';
import '../../domain/models/organization.dart';
import '../../domain/models/vehicle.dart';
import '../services/api_client.dart';

/// Pick lists for the waybill form. The server already limits vehicles and
/// drivers to the user's organization unless they are HQ.
class ReferenceRepository {
  ReferenceRepository({required ApiClient apiClient}) : _api = apiClient;

  final ApiClient _api;

  Future<List<Organization>> organizations() async =>
      asJsonList(await _api.get('/organizations'))
          .map(Organization.fromJson)
          .toList();

  Future<List<Vehicle>> activeVehicles() async =>
      asJsonList(await _api.get('/vehicles', query: {'status': 'active'}))
          .map(Vehicle.fromJson)
          .toList();

  Future<List<Driver>> activeDrivers() async =>
      asJsonList(await _api.get('/drivers', query: {'status': 'active'}))
          .map(Driver.fromJson)
          .toList();
}
