import '../../../../data/repositories/fuel_repository.dart';
import '../../../../data/services/api_exception.dart';
import '../../../../domain/models/fuel_station.dart';
import '../../../../domain/models/fuel_supplier.dart';
import '../../../../domain/models/quantity.dart';
import '../../../core/formatters.dart';
import '../../../core/view_model.dart';
import 'form_errors.dart';

DateTime _today() {
  final now = DateTime.now();
  return DateTime(now.year, now.month, now.day);
}

/// Receiving fuel from a supplier at [station], against their document.
class ReceiptViewModel extends ViewModel {
  ReceiptViewModel({
    required FuelRepository fuelRepository,
    required this.station,
  }) : _fuel = fuelRepository;

  final FuelRepository _fuel;
  final FuelStation station;

  List<FuelSupplier> _suppliers = const [];
  FuelSupplier? _supplier;
  DateTime _documentDate = _today();
  bool _loading = false;
  String? _loadError;
  bool _saving = false;
  String? _message;
  Map<String, String> _fieldErrors = const {};

  List<FuelSupplier> get suppliers => _suppliers;
  FuelSupplier? get supplier => _supplier;
  DateTime get documentDate => _documentDate;
  bool get loading => _loading;
  String? get loadError => _loadError;
  bool get saving => _saving;
  String? get message => _message;
  Map<String, String> get fieldErrors => _fieldErrors;

  Future<void> load() async {
    _loading = true;
    _loadError = null;
    notifyListeners();
    try {
      _suppliers = await _fuel.activeSuppliers();
    } on ApiException catch (e) {
      _loadError = e.message;
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  void selectSupplier(FuelSupplier supplier) {
    _supplier = supplier;
    _fieldErrors = {..._fieldErrors}..remove('fuel_supplier_id');
    notifyListeners();
  }

  void setDocumentDate(DateTime date) {
    _documentDate = DateTime(date.year, date.month, date.day);
    notifyListeners();
  }

  /// How much the received amount differs from the document, when both
  /// are valid and not equal.
  String? mismatchHint(String documentAmount, String amount) {
    final document = normalizeQuantity(documentAmount);
    final received = normalizeQuantity(amount);
    if (document == null || received == null) return null;
    final difference =
        toHundredths(double.parse(received)) -
        toHundredths(double.parse(document));
    if (difference == 0) return null;
    return 'Hujjatdagidan farq: ${formatQuantity(difference / 100, station.unit)}';
  }

  Future<bool> save({
    required String documentNumber,
    required String documentAmount,
    required String amount,
    String pricePerLiter = '',
  }) async {
    final supplier = _supplier;
    final errors = {
      if (supplier == null) 'fuel_supplier_id': 'Yetkazib beruvchini tanlang',
      if (documentNumber.trim().isEmpty)
        'document_number': 'Hujjat raqamini kiriting',
      'document_amount': ?quantityError(documentAmount),
      'amount': ?quantityError(amount),
      if (pricePerLiter.trim().isNotEmpty)
        'price_per_liter': ?quantityError(pricePerLiter),
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
      await _fuel.receive(
        station.id,
        supplierId: supplier!.id,
        documentNumber: documentNumber.trim(),
        documentDate: _documentDate,
        documentAmount: normalizeQuantity(documentAmount)!,
        amount: normalizeQuantity(amount)!,
        pricePerLiter: pricePerLiter.trim().isEmpty
            ? null
            : normalizeQuantity(pricePerLiter),
      );
      return true;
    } on ApiException catch (e) {
      final split = splitErrors(e, const {
        'fuel_supplier_id',
        'document_number',
        'document_date',
        'document_amount',
        'amount',
        'price_per_liter',
      });
      _fieldErrors = split.fields;
      _message = split.message;
      return false;
    } finally {
      _saving = false;
      notifyListeners();
    }
  }
}
