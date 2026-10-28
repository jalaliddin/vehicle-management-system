import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:utg_transport/data/services/api_exception.dart';
import 'package:utg_transport/domain/models/fuel_station.dart';
import 'package:utg_transport/domain/models/fuel_supplier.dart';
import 'package:utg_transport/ui/features/fuel/view_models/receipt_view_model.dart';

import '../helpers/fixtures.dart';

void main() {
  late MockFuelRepository fuel;
  late ReceiptViewModel viewModel;
  final supplier = FuelSupplier.fromJson(fuelSupplierJson());
  final now = DateTime.now();
  final today = DateTime(now.year, now.month, now.day);

  setUpAll(() => registerFallbackValue(DateTime(2026)));

  setUp(() {
    fuel = MockFuelRepository();
    viewModel = ReceiptViewModel(
      fuelRepository: fuel,
      station: FuelStation.fromJson(fuelStationJson()),
    );
  });

  test('load offers the active suppliers', () async {
    when(() => fuel.activeSuppliers()).thenAnswer(
      (_) async => [supplier, FuelSupplier.fromJson(fuelSupplierJson(id: 2))],
    );

    await viewModel.load();

    expect(viewModel.suppliers.map((s) => s.id), [1, 2]);
    expect(viewModel.documentDate, today);
  });

  test('a failed supplier load can be retried', () async {
    when(() => fuel.activeSuppliers())
        .thenThrow(const ApiException('Tarmoq xatosi'));

    await viewModel.load();

    expect(viewModel.loadError, 'Tarmoq xatosi');
  });

  test('save requires supplier and document number', () async {
    expect(
      await viewModel.save(
        documentNumber: ' ',
        documentAmount: '1000',
        amount: '995',
      ),
      isFalse,
    );
    expect(viewModel.fieldErrors, {
      'fuel_supplier_id': 'Yetkazib beruvchini tanlang',
      'document_number': 'Hujjat raqamini kiriting',
    });
    verifyNever(
      () => fuel.receive(
        any(),
        supplierId: any(named: 'supplierId'),
        documentNumber: any(named: 'documentNumber'),
        documentDate: any(named: 'documentDate'),
        documentAmount: any(named: 'documentAmount'),
        amount: any(named: 'amount'),
        pricePerLiter: any(named: 'pricePerLiter'),
      ),
    );
  });

  test('save checks the quantities and an optional price', () async {
    viewModel.selectSupplier(supplier);

    expect(
      await viewModel.save(
        documentNumber: 'TTN-1',
        documentAmount: '',
        amount: '995.555',
        pricePerLiter: '11500.123',
      ),
      isFalse,
    );
    expect(viewModel.fieldErrors, {
      'document_amount': 'Miqdorni kiriting',
      'amount': "Ko'pi bilan 2 xona kasr",
      'price_per_liter': "Ko'pi bilan 2 xona kasr",
    });
  });

  test('mismatchHint shows the difference', () {
    expect(
      viewModel.mismatchHint('1000', '995'),
      'Hujjatdagidan farq: -5.00 L',
    );
    expect(viewModel.mismatchHint('1000', '1000.00'), isNull);
    expect(viewModel.mismatchHint('', '995'), isNull);
  });

  test('save sends normalized amounts and the date', () async {
    when(
      () => fuel.receive(
        1,
        supplierId: 1,
        documentNumber: 'TTN-1',
        documentDate: today,
        documentAmount: '1000',
        amount: '995.5',
        pricePerLiter: null,
      ),
    ).thenAnswer((_) async {});
    viewModel.selectSupplier(supplier);

    expect(
      await viewModel.save(
        documentNumber: 'TTN-1',
        documentAmount: '1000',
        amount: '995,5',
      ),
      isTrue,
    );
  });

  test('a capacity refusal lands under the amount', () async {
    const message = "Sig'imdan oshib ketadi";
    when(
      () => fuel.receive(
        any(),
        supplierId: any(named: 'supplierId'),
        documentNumber: any(named: 'documentNumber'),
        documentDate: any(named: 'documentDate'),
        documentAmount: any(named: 'documentAmount'),
        amount: any(named: 'amount'),
        pricePerLiter: any(named: 'pricePerLiter'),
      ),
    ).thenThrow(
      const ApiException(
        message,
        statusCode: 422,
        fieldErrors: {'amount': message},
      ),
    );
    viewModel.selectSupplier(supplier);

    expect(
      await viewModel.save(
        documentNumber: 'TTN-1',
        documentAmount: '9000',
        amount: '9000',
      ),
      isFalse,
    );
    expect(viewModel.fieldErrors['amount'], message);
    expect(viewModel.message, isNull);
  });
}
