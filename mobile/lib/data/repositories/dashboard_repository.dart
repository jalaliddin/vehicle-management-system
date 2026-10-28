import '../../domain/models/dashboard_stats.dart';
import '../services/api_client.dart';

class DashboardRepository {
  DashboardRepository({required ApiClient apiClient}) : _api = apiClient;

  final ApiClient _api;

  Future<DashboardStats> stats() async => DashboardStats.fromJson(
    await _api.get('/dashboard/stats') as Map<String, dynamic>,
  );
}
