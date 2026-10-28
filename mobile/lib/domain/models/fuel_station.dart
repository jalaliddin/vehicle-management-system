import 'json_parsing.dart';

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
    unit: json['unit'] as String? ?? 'L',
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
