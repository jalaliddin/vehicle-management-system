import 'json_parsing.dart';

/// The unit a fuel type is measured in, as in the server's `fuel.units`;
/// for payloads that name the fuel type but not the unit.
String fuelUnit(Object? fuelType) => fuelType == 'gaz' ? 'm³' : 'L';

/// A fuel point the user may operate, from `/fuel/stations`.
class FuelStation {
  const FuelStation({
    required this.id,
    required this.name,
    required this.fuelType,
    required this.unit,
    required this.capacity,
    required this.balance,
    required this.percentage,
  });

  factory FuelStation.fromJson(Map<String, dynamic> json) => FuelStation(
    id: json['id'] as int,
    name: json['name'] as String? ?? '',
    fuelType: json['fuel_type'] as String? ?? '',
    unit: json['unit'] as String? ?? fuelUnit(json['fuel_type']),
    capacity: parseDecimal(json['capacity']) ?? 0,
    balance: parseDecimal(json['current_balance']) ?? 0,
    percentage: parseDecimal(json['percentage']) ?? 0,
  );

  final int id;
  final String name;
  final String fuelType;

  /// `L` or `m³`.
  final String unit;
  final double capacity;

  /// The book balance.
  final double balance;
  final double percentage;
}
