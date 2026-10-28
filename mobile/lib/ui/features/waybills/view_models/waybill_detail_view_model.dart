import '../../../../data/repositories/auth_repository.dart';
import '../../../../data/repositories/waybill_repository.dart';
import '../../../../data/services/api_exception.dart';
import '../../../../domain/models/waybill.dart';
import '../../../../domain/models/waybill_action.dart';
import '../../../core/view_model.dart';

class WaybillDetailViewModel extends ViewModel {
  WaybillDetailViewModel({
    required this.waybillId,
    required AuthRepository authRepository,
    required WaybillRepository waybillRepository,
  }) : _auth = authRepository,
       _repository = waybillRepository;

  final int waybillId;
  final AuthRepository _auth;
  final WaybillRepository _repository;

  Waybill? _waybill;
  bool _isLoading = false;
  String? _error;
  WaybillAction? _runningAction;

  Waybill? get waybill => _waybill;
  bool get isLoading => _isLoading;
  String? get error => _error;

  /// The action in flight, if any; all actions are disabled meanwhile.
  WaybillAction? get runningAction => _runningAction;

  Set<WaybillAction> get actions {
    final user = _auth.currentUser;
    final waybill = _waybill;
    if (user == null || waybill == null) return const {};
    return WaybillAction.availableFor(user, waybill);
  }

  Future<void> load() async {
    _isLoading = true;
    _error = null;
    notifyListeners();
    try {
      _waybill = await _repository.get(waybillId);
    } on ApiException catch (e) {
      _error = e.message;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  // Each action returns an error message, or null on success.

  Future<String?> submitToMechanic() => _run(
    WaybillAction.submitToMechanic,
    () => _repository.submitToMechanic(waybillId),
  );

  /// The mechanic's or doctor's check, whichever the user may do now.
  Future<String?> inspect({required bool passed, String? notes}) {
    final isMechanic = actions.contains(WaybillAction.mechanicCheck);
    return _run(
      isMechanic ? WaybillAction.mechanicCheck : WaybillAction.doctorCheck,
      () => isMechanic
          ? _repository.mechanicCheck(waybillId, passed: passed, notes: notes)
          : _repository.doctorCheck(waybillId, passed: passed, notes: notes),
    );
  }

  Future<String?> approve() =>
      _run(WaybillAction.approve, () => _repository.approve(waybillId));

  Future<String?> depart() =>
      _run(WaybillAction.depart, () => _repository.depart(waybillId));

  Future<String?> complete({
    double? actualKm,
    double? fuelConsumed,
    double? fuelReturned,
  }) => _run(
    WaybillAction.complete,
    () => _repository.complete(
      waybillId,
      actualKm: actualKm,
      fuelConsumed: fuelConsumed,
      fuelReturned: fuelReturned,
    ),
  );

  Future<String?> _run(
    WaybillAction action,
    Future<void> Function() call,
  ) async {
    if (_runningAction != null) return null;
    _runningAction = action;
    notifyListeners();
    try {
      await call();
    } on ApiException catch (e) {
      _runningAction = null;
      notifyListeners();
      return e.message;
    }
    // Stay busy until the new status is loaded, so the old status's buttons
    // can't be tapped again.
    try {
      _waybill = await _repository.get(waybillId);
    } on ApiException {
      // The action succeeded; a failed refresh just leaves older data.
    }
    _runningAction = null;
    notifyListeners();
    return null;
  }
}
