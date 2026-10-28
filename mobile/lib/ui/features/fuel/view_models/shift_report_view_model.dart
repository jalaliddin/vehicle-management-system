import '../../../../data/repositories/fuel_repository.dart';
import '../../../../data/services/api_exception.dart';
import '../../../../domain/models/shift_report.dart';
import '../../../core/view_model.dart';

class ShiftReportViewModel extends ViewModel {
  ShiftReportViewModel({
    required FuelRepository fuelRepository,
    required this.shiftId,
  }) : _fuel = fuelRepository;

  final FuelRepository _fuel;
  final int shiftId;

  ShiftReport? _report;
  bool _loading = false;
  String? _loadError;

  ShiftReport? get report => _report;
  bool get loading => _loading;
  String? get loadError => _loadError;

  Future<void> load() async {
    _loading = true;
    _loadError = null;
    notifyListeners();
    try {
      _report = await _fuel.shiftReport(shiftId);
    } on ApiException catch (e) {
      _loadError = e.message;
    } finally {
      _loading = false;
      notifyListeners();
    }
  }
}
