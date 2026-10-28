import 'vehicle.dart';

/// The payload for `POST /waybills`.
class NewWaybill {
  const NewWaybill({
    required this.organizationId,
    required this.vehicleId,
    required this.driverId,
    required this.vehicleType,
    required this.createdDate,
    required this.validUntil,
    required this.destination,
    this.tripCount = 1,
    this.tabelNumber,
    this.destinationOrganization,
    this.plannedKm,
    this.fuelIssued,
    this.hasAc = false,
    this.notes,
  });

  final int organizationId;
  final int vehicleId;
  final int driverId;
  final VehicleType vehicleType;
  final DateTime createdDate;
  final DateTime validUntil;
  final String destination;
  final int tripCount;
  final String? tabelNumber;
  final String? destinationOrganization;
  final double? plannedKm;
  final double? fuelIssued;
  final bool hasAc;
  final String? notes;

  /// Omits blank optional fields, like the web form does.
  Map<String, dynamic> toJson() => {
    'organization_id': organizationId,
    'vehicle_id': vehicleId,
    'driver_id': driverId,
    'vehicle_type': vehicleType.apiValue,
    'created_date': _isoDate(createdDate),
    'valid_until': _isoDate(validUntil),
    'destination': destination,
    'trip_count': tripCount,
    'has_ac': hasAc,
    if (_hasText(tabelNumber)) 'tabel_number': tabelNumber,
    if (_hasText(destinationOrganization))
      'destination_organization': destinationOrganization,
    if (plannedKm != null) 'planned_km': plannedKm,
    if (fuelIssued != null) 'fuel_issued': fuelIssued,
    if (_hasText(notes)) 'notes': notes,
  };

  static bool _hasText(String? value) => value?.trim().isNotEmpty ?? false;

  static String _isoDate(DateTime date) =>
      '${date.year.toString().padLeft(4, '0')}-'
      '${date.month.toString().padLeft(2, '0')}-'
      '${date.day.toString().padLeft(2, '0')}';
}
