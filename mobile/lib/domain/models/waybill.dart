import 'driver.dart';
import 'json_parsing.dart';
import 'organization.dart';
import 'vehicle.dart';

enum WaybillStatus {
  draft('draft', 'Yaratilgan'),
  mechanicCheck('mechanic_check', 'Mexanik tekshiruvi'),
  mechanicOk('mechanic_ok', 'Mexanik tasdiqladi'),
  doctorCheck('doctor_check', 'Shifokor tekshiruvi'),
  doctorOk('doctor_ok', 'Shifokor tasdiqladi'),
  hqReview('hq_review', 'Markaziy apparat'),
  approved('approved', 'Tasdiqlandi'),
  inProgress('in_progress', "Yo'lda"),
  completed('completed', 'Bajarildi'),
  cancelled('cancelled', 'Bekor qilindi');

  const WaybillStatus(this.apiValue, this.label);

  final String apiValue;
  final String label;

  static WaybillStatus fromApi(Object? value) => values.firstWhere(
    (status) => status.apiValue == value,
    orElse: () => draft,
  );
}

class Waybill {
  const Waybill({
    required this.id,
    required this.number,
    required this.status,
    this.organization,
    this.vehicle,
    this.driver,
    this.vehicleType,
    this.tabelNumber,
    this.createdDate,
    this.validUntil,
    this.tripCount = 1,
    this.destination = '',
    this.destinationOrganization,
    this.plannedKm,
    this.actualKm,
    this.fuelIssued,
    this.fuelConsumed,
    this.fuelReturned,
    this.hasAc = false,
    this.notes,
    this.mechanicName,
    this.mechanicCheckedAt,
    this.mechanicPassed,
    this.mechanicNotes,
    this.doctorName,
    this.doctorCheckedAt,
    this.doctorPassed,
    this.doctorNotes,
    this.approvedByName,
    this.approvedAt,
    this.departedAt,
    this.arrivedAt,
  });

  factory Waybill.fromJson(Map<String, dynamic> json) {
    final organization = asJsonMap(json['organization']);
    final vehicle = asJsonMap(json['vehicle']);
    final driver = asJsonMap(json['driver']);
    return Waybill(
      id: json['id'] as int,
      number: json['waybill_number'] as String? ?? '#${json['id']}',
      status: WaybillStatus.fromApi(json['status']),
      organization: organization == null
          ? null
          : Organization.fromJson(organization),
      vehicle: vehicle == null ? null : Vehicle.fromJson(vehicle),
      driver: driver == null ? null : Driver.fromJson(driver),
      vehicleType: VehicleType.fromApi(json['vehicle_type']),
      tabelNumber: json['tabel_number'] as String?,
      createdDate: parseDateTime(json['created_date']),
      validUntil: parseDateTime(json['valid_until']),
      tripCount: json['trip_count'] as int? ?? 1,
      destination: json['destination'] as String? ?? '',
      destinationOrganization: json['destination_organization'] as String?,
      plannedKm: parseDecimal(json['planned_km']),
      actualKm: parseDecimal(json['actual_km']),
      fuelIssued: parseDecimal(json['fuel_issued']),
      fuelConsumed: parseDecimal(json['fuel_consumed']),
      fuelReturned: parseDecimal(json['fuel_returned']),
      hasAc: json['has_ac'] as bool? ?? false,
      notes: json['notes'] as String?,
      mechanicName: asJsonMap(json['mechanic'])?['name'] as String?,
      mechanicCheckedAt: parseDateTime(json['mechanic_checked_at']),
      mechanicPassed: json['mechanic_passed'] as bool?,
      mechanicNotes: json['mechanic_notes'] as String?,
      doctorName: asJsonMap(json['doctor'])?['name'] as String?,
      doctorCheckedAt: parseDateTime(json['doctor_checked_at']),
      doctorPassed: json['doctor_passed'] as bool?,
      doctorNotes: json['doctor_notes'] as String?,
      approvedByName: asJsonMap(json['approved_by_user'])?['name'] as String?,
      approvedAt: parseDateTime(json['approved_at']),
      departedAt: parseDateTime(json['departed_at']),
      arrivedAt: parseDateTime(json['arrived_at']),
    );
  }

  final int id;
  final String number;
  final WaybillStatus status;
  final Organization? organization;
  final Vehicle? vehicle;
  final Driver? driver;
  final VehicleType? vehicleType;
  final String? tabelNumber;
  final DateTime? createdDate;
  final DateTime? validUntil;
  final int tripCount;
  final String destination;
  final String? destinationOrganization;
  final double? plannedKm;
  final double? actualKm;
  final double? fuelIssued;
  final double? fuelConsumed;
  final double? fuelReturned;
  final bool hasAc;
  final String? notes;

  final String? mechanicName;
  final DateTime? mechanicCheckedAt;
  final bool? mechanicPassed;
  final String? mechanicNotes;

  final String? doctorName;
  final DateTime? doctorCheckedAt;
  final bool? doctorPassed;
  final String? doctorNotes;

  final String? approvedByName;
  final DateTime? approvedAt;
  final DateTime? departedAt;
  final DateTime? arrivedAt;
}
