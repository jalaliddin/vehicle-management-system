import 'json_parsing.dart';
import 'organization.dart';
import 'vehicle.dart';
import 'waybill.dart';

class FuelStation {
  const FuelStation({
    required this.id,
    required this.name,
    required this.currentBalance,
    required this.minThreshold,
    required this.capacity,
    this.fuelType,
    this.organization,
  });

  factory FuelStation.fromJson(Map<String, dynamic> json) {
    final organization = asJsonMap(json['organization']);
    return FuelStation(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      currentBalance: parseDecimal(json['current_balance']) ?? 0,
      minThreshold: parseDecimal(json['min_threshold']) ?? 0,
      capacity: parseDecimal(json['capacity']) ?? 0,
      fuelType: json['fuel_type'] as String?,
      organization: organization == null
          ? null
          : Organization.fromJson(organization),
    );
  }

  final int id;
  final String name;

  /// Liters.
  final double currentBalance;
  final double minThreshold;
  final double capacity;
  final String? fuelType;
  final Organization? organization;

  String get fuelTypeName => fuelTypeLabel(fuelType);
}

/// `GET /dashboard/stats`, already scoped to the user's organization
/// unless they are HQ.
class DashboardStats {
  const DashboardStats({
    required this.totalVehicles,
    required this.activeVehicles,
    required this.brokenVehicles,
    required this.maintenanceVehicles,
    required this.totalDrivers,
    required this.activeDrivers,
    required this.todayWaybills,
    required this.pendingWaybills,
    required this.activeWaybills,
    required this.fuelAlerts,
    required this.activeTrips,
  });

  factory DashboardStats.fromJson(Map<String, dynamic> json) => DashboardStats(
    totalVehicles: json['total_vehicles'] as int? ?? 0,
    activeVehicles: json['active_vehicles'] as int? ?? 0,
    brokenVehicles: json['broken_vehicles'] as int? ?? 0,
    maintenanceVehicles: json['maintenance_vehicles'] as int? ?? 0,
    totalDrivers: json['total_drivers'] as int? ?? 0,
    activeDrivers: json['active_drivers'] as int? ?? 0,
    todayWaybills: json['today_waybills'] as int? ?? 0,
    pendingWaybills: json['pending_waybills'] as int? ?? 0,
    activeWaybills: json['active_waybills'] as int? ?? 0,
    fuelAlerts: asJsonList(json['fuel_alerts'])
        .map(FuelStation.fromJson)
        .toList(),
    activeTrips: asJsonList(json['active_waybills_list'])
        .map(Waybill.fromJson)
        .toList(),
  );

  final int totalVehicles;
  final int activeVehicles;
  final int brokenVehicles;
  final int maintenanceVehicles;
  final int totalDrivers;
  final int activeDrivers;
  final int todayWaybills;

  /// Waiting on a mechanic, doctor or HQ.
  final int pendingWaybills;

  /// Approved or on the road.
  final int activeWaybills;

  /// Stations at or below their minimum threshold.
  final List<FuelStation> fuelAlerts;

  /// Waybills currently on the road.
  final List<Waybill> activeTrips;
}
