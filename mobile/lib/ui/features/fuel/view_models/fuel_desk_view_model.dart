import '../../../../data/repositories/fuel_repository.dart';
import '../../../../data/services/api_exception.dart';
import '../../../../domain/models/fuel_station.dart';
import '../../../../domain/models/quantity.dart';
import '../../../../domain/models/shift_report.dart';
import '../../../core/view_model.dart';
import 'form_errors.dart';

/// The fueler's desk: pick a station, open and close its shift, and see or
/// cancel the shift's operations.
class FuelDeskViewModel extends ViewModel {
  FuelDeskViewModel({required FuelRepository fuelRepository})
    : _fuel = fuelRepository;

  final FuelRepository _fuel;

  List<FuelStation> _stations = const [];
  FuelStation? _station;
  ShiftReport? _report;
  bool _loading = false;
  bool _loaded = false;
  String? _loadError;
  bool _busy = false;
  String? _actionError;
  Map<String, String> _fieldErrors = const {};
  bool _closing = false;
  bool _noteRejected = false;
  int _lastLoad = 0;

  List<FuelStation> get stations => _stations;
  FuelStation? get station => _station;
  ShiftReport? get report => _report;
  FuelShift? get shift => _report?.shift;
  bool get loading => _loading;
  String? get loadError => _loadError;
  bool get busy => _busy;
  String? get actionError => _actionError;
  Map<String, String> get fieldErrors => _fieldErrors;
  bool get closing => _closing;

  /// True only once the server has answered with an empty station list.
  bool get noStations => _loaded && _loadError == null && _stations.isEmpty;

  /// Answers to an older load are dropped once a newer one has started, so
  /// a slow reply for one station never lands on another.
  Future<void> load() async {
    final current = ++_lastLoad;
    _loading = true;
    _loadError = null;
    notifyListeners();
    try {
      final stations = await _fuel.stations();
      if (current != _lastLoad) return;
      _stations = stations;
      _station =
          stations.where((s) => s.id == _station?.id).firstOrNull ??
          stations.firstOrNull;
      final station = _station;
      final report = station == null
          ? null
          : await _fuel.currentShift(station.id);
      if (current != _lastLoad) return;
      _report = report;
      _loaded = true;
    } on ApiException catch (e) {
      if (current != _lastLoad) return;
      _loadError = e.message;
    } finally {
      if (current == _lastLoad) {
        _loading = false;
        notifyListeners();
      }
    }
  }

  Future<void> selectStation(int id) async {
    _station = _stations.firstWhere((s) => s.id == id);
    _report = null;
    _clearForm();
    await load();
  }

  Future<bool> openShift(String measured) async {
    final station = _station;
    if (station == null) return false;
    final error = quantityError(measured);
    if (error != null) return _refuse({'opening_measured': error});
    return _act(
      () => _fuel.openShift(station.id, normalizeQuantity(measured)!),
      {'opening_measured'},
    );
  }

  /// Reloads first, so the difference is measured against the current book
  /// balance even when another device changed it.
  Future<void> beginClosing() async {
    _clearForm();
    await load();
    if (_loadError == null && shift?.isOpen == true) {
      _closing = true;
      notifyListeners();
    }
  }

  void cancelClosing() {
    _clearForm();
    notifyListeners();
  }

  /// Measured minus book balance in hundredths; null for invalid input.
  int? discrepancyHundredths(String measured) {
    final station = _station;
    final normalized = normalizeQuantity(measured);
    if (station == null || normalized == null) return null;
    return toHundredths(double.parse(normalized)) -
        toHundredths(station.balance);
  }

  bool noteRequired(String measured) =>
      _noteRejected || (discrepancyHundredths(measured) ?? 0) != 0;

  /// Returns the closed shift's id, or null when closing failed.
  Future<int?> closeShift(String measured, String note) async {
    final shift = this.shift;
    if (shift == null) return null;
    final error = quantityError(measured);
    if (error != null) {
      _refuse({'closing_measured': error});
      return null;
    }
    if (noteRequired(measured) && note.trim().isEmpty) {
      _refuse({'discrepancy_note': 'Farq izohini yozing'});
      return null;
    }
    final closed = await _act(
      () => _fuel.closeShift(
        shift.id,
        closingMeasured: normalizeQuantity(measured)!,
        note: note.trim().isEmpty ? null : note.trim(),
      ),
      {'closing_measured', 'discrepancy_note'},
    );
    if (!closed) {
      _noteRejected =
          _noteRejected || _fieldErrors.containsKey('discrepancy_note');
      notifyListeners();
      return null;
    }
    return shift.id;
  }

  Future<bool> cancel(FuelOperation operation, String reason) =>
      _act(() => _fuel.cancel(operation.id, reason), const {});

  void _clearForm() {
    _closing = false;
    _noteRejected = false;
    _fieldErrors = const {};
    _actionError = null;
  }

  bool _refuse(Map<String, String> errors) {
    _fieldErrors = errors;
    _actionError = null;
    notifyListeners();
    return false;
  }

  /// Runs [action], then reloads; failures land in [fieldErrors] (for the
  /// [shownFields]) or [actionError].
  Future<bool> _act(
    Future<void> Function() action,
    Set<String> shownFields,
  ) async {
    _busy = true;
    _actionError = null;
    _fieldErrors = const {};
    notifyListeners();
    try {
      await action();
    } on ApiException catch (e) {
      final errors = splitErrors(e, shownFields);
      _fieldErrors = errors.fields;
      _actionError = errors.message;
      _busy = false;
      notifyListeners();
      return false;
    }
    _busy = false;
    _clearForm();
    await load();
    return true;
  }
}
