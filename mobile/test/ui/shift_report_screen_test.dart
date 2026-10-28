import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:provider/provider.dart';
import 'package:utg_transport/data/services/api_exception.dart';
import 'package:utg_transport/domain/models/shift_report.dart';
import 'package:utg_transport/ui/features/fuel/view_models/shift_report_view_model.dart';
import 'package:utg_transport/ui/features/fuel/views/shift_report_screen.dart';

import '../helpers/fixtures.dart';

void main() {
  late MockFuelRepository fuel;

  setUp(() => fuel = MockFuelRepository());

  Future<void> pump(WidgetTester tester) async {
    await tester.pumpWidget(
      ChangeNotifierProvider(
        create: (_) =>
            ShiftReportViewModel(fuelRepository: fuel, shiftId: 4)..load(),
        child: const MaterialApp(home: ShiftReportScreen()),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets(
    'shows a closed shift with its difference and cancelled operations',
    (tester) async {
      when(() => fuel.shiftReport(4)).thenAnswer(
        (_) async => ShiftReport.fromJson(
          shiftReportJson(
            open: false,
            operations: [
              fuelOperationJson(id: 9, cancelled: true),
              fuelOperationJson(id: 8, kind: 'receipt', amount: '1000.00'),
            ],
          ),
        ),
      );

      await pump(tester);

      expect(find.text('QQP-MGQB Benzin'), findsOneWidget);
      expect(find.text('4 159.50 L'), findsOneWidget);
      expect(find.text('-0.50 L'), findsOneWidget);
      expect(find.text("O'lchovdagi xatolik"), findsOneWidget);
      expect(find.text('Quyish (bekor)'), findsOneWidget);
      expect(find.text('+1 000.00 L'), findsOneWidget);
    },
  );

  testWidgets('a failed load offers a retry', (tester) async {
    when(() => fuel.shiftReport(4))
        .thenThrow(const ApiException('Tarmoq xatosi'));

    await pump(tester);

    expect(find.text('Tarmoq xatosi'), findsOneWidget);
    expect(find.text('Qayta urinish'), findsOneWidget);
  });
}
