import '../../../../data/repositories/auth_repository.dart';
import '../../../../data/repositories/reference_repository.dart';
import '../../../../data/repositories/waybill_repository.dart';
import '../../../../data/services/api_exception.dart';
import '../../../../domain/models/driver.dart';
import '../../../../domain/models/new_waybill.dart';
import '../../../../domain/models/organization.dart';
import '../../../../domain/models/vehicle.dart';
import '../../../../domain/models/waybill.dart';
import '../../../core/formatters.dart';
import '../../../core/view_model.dart';

/// The free-text fields of the form, as typed.
class WaybillFormText {
  const WaybillFormText({
    required this.destination,
    this.destinationOrganization = '',
    this.tabelNumber = '',
    this.tripCount = '1',
    this.plannedKm = '',
    this.fuelIssued = '',
    this.notes = '',
  });

  final String destination;
  final String destinationOrganization;
  final String tabelNumber;
  final String tripCount;
  final String plannedKm;
  final String fuelIssued;
  final String notes;
}

class SaveResult {
  const SaveResult(this.waybill, {this.submitError});

  final Waybill waybill;

  /// Set when the waybill was created but sending it to the mechanic failed.
  final String? submitError;
}

class WaybillCreateViewModel extends ViewModel {
  WaybillCreateViewModel({
    required AuthRepository authRepository,
    required WaybillRepository waybillRepository,
    required ReferenceRepository referenceRepository,
    DateTime? today,
  }) : _auth = authRepository,
       _waybills = waybillRepository,
       _references = referenceRepository {
    final now = today ?? DateTime.now();
    _createdDate = DateTime(now.year, now.month, now.day);
    _validUntil = _createdDate.add(const Duration(days: 1));
    _organizationId = _auth.currentUser?.organizationId;
  }

  final AuthRepository _auth;
  final WaybillRepository _waybills;
  final ReferenceRepository _references;

  List<Organization> _organizations = const [];
  List<Vehicle> _vehicles = const [];
  List<Driver> _drivers = const [];
  bool _isLoading = false;
  String? _loadError;
  bool _isSaving = false;
  String? _formError;
  Map<String, String> _fieldErrors = const {};

  int? _organizationId;
  int? _vehicleId;
  int? _driverId;
  VehicleType _vehicleType = VehicleType.car;
  late DateTime _createdDate;
  late DateTime _validUntil;
  bool _hasAc = false;

  bool get isLoading => _isLoading;
  String? get loadError => _loadError;
  bool get isSaving => _isSaving;
  String? get formError => _formError;

  /// Errors keyed by API field name (e.g. `vehicle_id`).
  Map<String, String> get fieldErrors => _fieldErrors;

  /// Only HQ users pick the organization; others create for their own.
  bool get canChooseOrganization =>
      _auth.currentUser?.canAccessAllOrganizations ?? false;

  List<Organization> get organizations => _organizations;
  List<Vehicle> get vehicles =>
      _forOrganization(_vehicles, (v) => v.organizationId);
  List<Driver> get drivers =>
      _forOrganization(_drivers, (d) => d.organizationId);

  int? get organizationId => _organizationId;
  Organization? get organization =>
      _organizations.where((o) => o.id == _organizationId).firstOrNull;
  Vehicle? get vehicle =>
      _vehicles.where((v) => v.id == _vehicleId).firstOrNull;
  Driver? get driver => _drivers.where((d) => d.id == _driverId).firstOrNull;
  VehicleType get vehicleType => _vehicleType;
  DateTime get createdDate => _createdDate;
  DateTime get validUntil => _validUntil;
  bool get hasAc => _hasAc;

  List<T> _forOrganization<T>(List<T> items, int? Function(T) orgOf) =>
      _organizationId == null
      ? items
      : items.where((item) => orgOf(item) == _organizationId).toList();

  Future<void> load() async {
    _isLoading = true;
    _loadError = null;
    notifyListeners();
    try {
      final results = await Future.wait<List<Object>>([
        canChooseOrganization
            ? _references.organizations()
            : Future.value(const <Organization>[]),
        _references.activeVehicles(),
        _references.activeDrivers(),
      ]);
      _organizations = results[0] as List<Organization>;
      _vehicles = results[1] as List<Vehicle>;
      _drivers = results[2] as List<Driver>;
    } on ApiException catch (e) {
      _loadError = e.message;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  void selectOrganization(int? id) {
    if (id == _organizationId) return;
    _organizationId = id;
    _vehicleId = null;
    _driverId = null;
    _clearError('organization_id');
  }

  void selectVehicle(int? id) {
    _vehicleId = id;
    if (vehicle?.vehicleType case final type?) _vehicleType = type;
    _clearError('vehicle_id');
  }

  void selectDriver(int? id) {
    _driverId = id;
    _clearError('driver_id');
  }

  void selectVehicleType(VehicleType type) {
    _vehicleType = type;
    notifyListeners();
  }

  void setCreatedDate(DateTime date) {
    _createdDate = date;
    if (_validUntil.isBefore(date)) _validUntil = date;
    notifyListeners();
  }

  void setValidUntil(DateTime date) {
    _validUntil = date;
    _clearError('valid_until');
  }

  void setHasAc(bool value) {
    _hasAc = value;
    notifyListeners();
  }

  /// Expected fuel in liters for [plannedKm], from the vehicle's norm.
  double? estimateFuel(String plannedKm) {
    final km = parseAmount(plannedKm);
    final norm = vehicle?.normFor(withAc: _hasAc);
    if (km == null || km <= 0 || norm == null || norm <= 0) return null;
    return km * norm / 100;
  }

  Future<SaveResult?> save(
    WaybillFormText text, {
    required bool submitToMechanic,
  }) async {
    if (_isSaving) return null;
    final errors = _validate(text);
    if (errors.isNotEmpty) {
      _fieldErrors = errors;
      _formError = "Formada xatolar bor, tekshiring.";
      notifyListeners();
      return null;
    }

    _isSaving = true;
    _formError = null;
    _fieldErrors = const {};
    notifyListeners();
    try {
      final created = await _waybills.create(
        NewWaybill(
          organizationId: _organizationId!,
          vehicleId: _vehicleId!,
          driverId: _driverId!,
          vehicleType: _vehicleType,
          createdDate: _createdDate,
          validUntil: _validUntil,
          destination: text.destination.trim(),
          tripCount: int.parse(text.tripCount.trim()),
          tabelNumber: text.tabelNumber.trim(),
          destinationOrganization: text.destinationOrganization.trim(),
          plannedKm: parseAmount(text.plannedKm),
          fuelIssued: parseAmount(text.fuelIssued),
          hasAc: _hasAc,
          notes: text.notes.trim(),
        ),
      );
      String? submitError;
      if (submitToMechanic) {
        try {
          await _waybills.submitToMechanic(created.id);
        } on ApiException catch (e) {
          submitError = e.message;
        }
      }
      return SaveResult(created, submitError: submitError);
    } on ApiException catch (e) {
      _fieldErrors = e.fieldErrors;
      _formError = e.fieldErrors.isEmpty
          ? e.message
          : "Formada xatolar bor, tekshiring.";
      return null;
    } finally {
      _isSaving = false;
      notifyListeners();
    }
  }

  Map<String, String> _validate(WaybillFormText text) {
    final tripCount = int.tryParse(text.tripCount.trim());
    final plannedKm = parseAmount(text.plannedKm);
    final fuelIssued = parseAmount(text.fuelIssued);
    return {
      if (_organizationId == null)
        'organization_id': canChooseOrganization
            ? 'Tashkilotni tanlang'
            : 'Sizga tashkilot biriktirilmagan',
      if (_vehicleId == null) 'vehicle_id': 'Avtomobilni tanlang',
      if (_driverId == null) 'driver_id': 'Haydovchini tanlang',
      if (text.destination.trim().isEmpty)
        'destination': "Yo'nalishni kiriting",
      if (tripCount == null || tripCount < 1) 'trip_count': 'Kamida 1 ta reys',
      if (text.plannedKm.trim().isNotEmpty &&
          (plannedKm == null || plannedKm < 0))
        'planned_km': "Noto'g'ri qiymat",
      if (text.fuelIssued.trim().isNotEmpty &&
          (fuelIssued == null || fuelIssued < 0))
        'fuel_issued': "Noto'g'ri qiymat",
      if (_validUntil.isBefore(_createdDate))
        'valid_until': 'Muddat sanadan oldin bo\'lmasin',
    };
  }

  void _clearError(String field) {
    if (_fieldErrors.containsKey(field)) {
      _fieldErrors = Map.of(_fieldErrors)..remove(field);
    }
    notifyListeners();
  }
}
