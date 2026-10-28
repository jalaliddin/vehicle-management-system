import 'json_parsing.dart';

/// A vehicle as the station's refuel lookup describes it.
class FuelVehicle {
  const FuelVehicle({
    required this.id,
    required this.stateNumber,
    required this.model,
    required this.fuelType,
    required this.fuelTypeMatches,
    this.tankCapacity,
    this.odometer,
    this.driverName,
    this.waybillNumber,
  });

  factory FuelVehicle.fromJson(Map<String, dynamic> json) => FuelVehicle(
    id: json['id'] as int,
    stateNumber: json['state_number'] as String? ?? '',
    model: json['model'] as String? ?? '',
    fuelType: json['fuel_type'] as String? ?? '',
    fuelTypeMatches: json['fuel_type_matches'] == true,
    tankCapacity: parseDecimal(json['tank_capacity']),
    odometer: json['odometer'] as int?,
    driverName: asJsonMap(json['driver'])?['full_name'] as String?,
    waybillNumber: asJsonMap(json['waybill'])?['waybill_number'] as String?,
  );

  final int id;
  final String stateNumber;
  final String model;
  final String fuelType;

  /// Whether the vehicle takes the station's fuel.
  final bool fuelTypeMatches;
  final double? tankCapacity;

  /// The last known reading, in km.
  final int? odometer;
  final String? driverName;

  /// The vehicle's open (approved or in progress) waybill.
  final String? waybillNumber;
}
