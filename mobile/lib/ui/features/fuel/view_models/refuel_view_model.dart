import '../../../../data/repositories/fuel_repository.dart';
import '../../../../data/services/api_exception.dart';
import '../../../../domain/models/fuel_station.dart';
import '../../../../domain/models/fuel_vehicle.dart';
import '../../../../domain/models/quantity.dart';
import '../../../core/view_model.dart';
import 'form_errors.dart';

/// Refuelling one vehicle at [station]: find it by state number or by its
/// waybill's QR code, then record the odometer and the amount.
class RefuelViewModel extends ViewModel {
  RefuelViewModel({
    required FuelRepository fuelRepository,
    required this.station,
  }) : _fuel = fuelRepository;

  final FuelRepository _fuel;
  final FuelStation station;

  List<FuelVehicle> _results = const [];
  FuelVehicle? _vehicle;
  bool _searching = false;
  bool _saving = false;
  String? _message;
  Map<String, String> _fieldErrors = const {};
  int _lastSearch = 0;

  List<FuelVehicle> get results => _results;
  FuelVehicle? get vehicle => _vehicle;
  bool get searching => _searching;
  bool get saving => _saving;
  String? get message => _message;
  Map<String, String> get fieldErrors => _fieldErrors;

  String? get fuelTypeWarning {
    final vehicle = _vehicle;
    if (vehicle == null || vehicle.fuelTypeMatches) return null;
    return "Yoqilg'i turi mos emas: avtomobil ${vehicle.fuelType}, "
        'shahobcha ${station.fuelType}';
  }

  bool get canSave => _vehicle != null && _vehicle!.fuelTypeMatches && !_saving;

  /// Answers to an older search are dropped once a newer one has started.
  Future<void> search(String text) async {
    final current = ++_lastSearch;
    _searching = true;
    notifyListeners();
    try {
      final results = await _fuel.vehicles(station.id, text);
      if (current != _lastSearch) return;
      _results = results;
      _message = null;
    } on ApiException catch (e) {
      if (current != _lastSearch) return;
      _message = e.message;
    } finally {
      if (current == _lastSearch) {
        _searching = false;
        notifyListeners();
      }
    }
  }

  void select(FuelVehicle vehicle) {
    _vehicle = vehicle;
    _fieldErrors = const {};
    _message = null;
    notifyListeners();
  }

  void clearVehicle() {
    _vehicle = null;
    _fieldErrors = const {};
    _message = null;
    notifyListeners();
  }

  Future<void> pickFromWaybill(int waybillId) async {
    _searching = true;
    _message = null;
    notifyListeners();
    try {
      final vehicle = await _fuel.vehicleFromWaybill(station.id, waybillId);
      if (vehicle == null) {
        _message = 'Avtomobil bu shahobcha tashkilotida topilmadi';
      } else {
        _vehicle = vehicle;
        _fieldErrors = const {};
      }
    } on ApiException catch (e) {
      _message = e.message;
    } finally {
      _searching = false;
      notifyListeners();
    }
  }

  Future<bool> save({required String odometer, required String amount}) async {
    final vehicle = _vehicle;
    if (vehicle == null || !canSave) return false;

    final km = int.tryParse(odometer.trim());
    final errors = {
      if (km == null || km < 0) 'odometer': 'Butun son kiriting',
      'amount': ?quantityError(amount),
    };
    _message = null;
    if (errors.isNotEmpty) {
      _fieldErrors = errors;
      notifyListeners();
      return false;
    }

    _saving = true;
    _fieldErrors = const {};
    notifyListeners();
    try {
      await _fuel.refuel(
        station.id,
        vehicleId: vehicle.id,
        odometer: km!,
        amount: normalizeQuantity(amount)!,
      );
      return true;
    } on ApiException catch (e) {
      final split = splitErrors(e, const {'odometer', 'amount'});
      _fieldErrors = split.fields;
      _message = split.message;
      return false;
    } finally {
      _saving = false;
      notifyListeners();
    }
  }
}
