import 'json_parsing.dart';

enum VehicleType {
  car('car', 'Yengil avtomobil'),
  truck('truck', 'Yuk avtomobili'),
  bus('bus', 'Avtobus'),
  special('special', 'Maxsus texnika');

  const VehicleType(this.apiValue, this.label);

  final String apiValue;
  final String label;

  static VehicleType? fromApi(Object? value) {
    for (final type in values) {
      if (type.apiValue == value) return type;
    }
    return null;
  }
}

String fuelTypeLabel(String? fuelType) => switch (fuelType) {
  'benzin' => 'Benzin',
  'dizel' => 'Dizel',
  'gaz' => 'Gaz',
  null || '' => '—',
  _ => fuelType,
};

class Vehicle {
  const Vehicle({
    required this.id,
    required this.stateNumber,
    required this.model,
    this.organizationId,
    this.vehicleType,
    this.fuelType,
    this.fuelNorm,
    this.fuelNormAc,
  });

  /// Tolerates the trimmed relation (`id,state_number,model`) that waybill
  /// endpoints embed.
  factory Vehicle.fromJson(Map<String, dynamic> json) => Vehicle(
    id: json['id'] as int,
    stateNumber: json['state_number'] as String? ?? '',
    model: json['model'] as String? ?? '',
    organizationId: json['organization_id'] as int?,
    vehicleType: VehicleType.fromApi(json['vehicle_type']),
    fuelType: json['fuel_type'] as String?,
    fuelNorm: parseDecimal(json['fuel_norm']),
    fuelNormAc: parseDecimal(json['fuel_norm_ac']),
  );

  final int id;
  final String stateNumber;
  final String model;
  final int? organizationId;
  final VehicleType? vehicleType;
  final String? fuelType;

  /// Liters per 100 km.
  final double? fuelNorm;
  final double? fuelNormAc;

  String get title => model.isEmpty ? stateNumber : '$stateNumber · $model';

  /// The norm in liters per 100 km, preferring the A/C norm when [withAc].
  double? normFor({required bool withAc}) =>
      withAc ? (fuelNormAc ?? fuelNorm) : fuelNorm;
}
