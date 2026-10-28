import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:provider/provider.dart';
import 'package:utg_transport/data/services/api_exception.dart';
import 'package:utg_transport/domain/models/waybill.dart';
import 'package:utg_transport/ui/features/waybills/view_models/waybill_detail_view_model.dart';
import 'package:utg_transport/ui/features/waybills/views/waybill_detail_screen.dart';

import '../helpers/fixtures.dart';

void main() {
  late MockAuthRepository auth;
  late MockWaybillRepository waybills;

  setUpAll(registerFallbacks);

  setUp(() {
    auth = MockAuthRepository();
    waybills = MockWaybillRepository();
  });

  Future<void> pumpDetail(
    WidgetTester tester, {
    required String role,
    required WaybillStatus status,
  }) async {
    when(() => auth.currentUser).thenReturn(userWith(role));
    when(() => waybills.get(10)).thenAnswer((_) async => waybillWith(status));
    await tester.pumpWidget(
      MaterialApp(
        home: ChangeNotifierProvider(
          create: (_) => WaybillDetailViewModel(
            waybillId: 10,
            authRepository: auth,
            waybillRepository: waybills,
          )..load(),
          child: const WaybillDetailScreen(),
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  Finder inSheet(Finder finder) =>
      find.descendant(of: find.byType(BottomSheet), matching: finder);

  testWidgets('shows the waybill details and timeline', (tester) async {
    await pumpDetail(
      tester,
      role: 'viewer',
      status: WaybillStatus.mechanicCheck,
    );

    expect(find.text('UTG-202610-0010'), findsOneWidget);
    expect(find.text('Urganch - Xiva'), findsOneWidget);
    expect(find.text('90A123BC · Nexia'), findsOneWidget);
    expect(find.text('Mexanik tekshiruvi'), findsOneWidget);
    expect(find.byType(FilledButton), findsNothing);

    // The timeline is below the fold.
    await tester.scrollUntilVisible(find.text('Kutilmoqda'), 300);
    expect(find.text("Mexanik ko'rigi"), findsOneWidget);
  });

  testWidgets('a dispatcher gets no inspection buttons', (tester) async {
    await pumpDetail(
      tester,
      role: 'dispatcher',
      status: WaybillStatus.mechanicCheck,
    );

    expect(find.text('Rad etish'), findsNothing);
    expect(find.widgetWithText(FilledButton, 'Tasdiqlash'), findsNothing);
  });

  testWidgets('a mechanic passes a waybill through the sheet', (tester) async {
    when(
      () => waybills.mechanicCheck(10, passed: true, notes: 'Hammasi joyida'),
    ).thenAnswer((_) async {});
    await pumpDetail(
      tester,
      role: 'mechanic',
      status: WaybillStatus.mechanicCheck,
    );

    await tester.tap(find.widgetWithText(FilledButton, 'Tasdiqlash'));
    await tester.pumpAndSettle();
    expect(find.text('Mexanik xulosasi'), findsOneWidget);

    when(() => waybills.get(10))
        .thenAnswer((_) async => waybillWith(WaybillStatus.mechanicOk));
    await tester.enterText(inSheet(find.byType(TextField)), 'Hammasi joyida');
    await tester.tap(inSheet(find.widgetWithText(FilledButton, 'Tasdiqlash')));
    await tester.pumpAndSettle();

    verify(
      () => waybills.mechanicCheck(10, passed: true, notes: 'Hammasi joyida'),
    ).called(1);
    expect(find.text('Mexanik tasdiqladi'), findsOneWidget);
    expect(find.widgetWithText(FilledButton, 'Tasdiqlash'), findsNothing);
  });

  testWidgets('rejecting requires a reason', (tester) async {
    await pumpDetail(
      tester,
      role: 'mechanic',
      status: WaybillStatus.mechanicCheck,
    );

    await tester.tap(find.widgetWithText(OutlinedButton, 'Rad etish'));
    await tester.pumpAndSettle();
    await tester.tap(inSheet(find.widgetWithText(FilledButton, 'Rad etish')));
    await tester.pump();

    expect(find.text('Rad etish sababini yozing'), findsOneWidget);
    verifyNever(
      () => waybills.mechanicCheck(
        any(),
        passed: any(named: 'passed'),
        notes: any(named: 'notes'),
      ),
    );
  });

  testWidgets('a dispatcher completes a trip with the distance driven', (
    tester,
  ) async {
    when(
      () => waybills.complete(
        10,
        actualKm: any(named: 'actualKm'),
        fuelConsumed: any(named: 'fuelConsumed'),
        fuelReturned: any(named: 'fuelReturned'),
      ),
    ).thenAnswer((_) async {});
    await pumpDetail(
      tester,
      role: 'dispatcher',
      status: WaybillStatus.inProgress,
    );

    await tester.tap(find.widgetWithText(FilledButton, 'Yakunlash'));
    await tester.pumpAndSettle();
    await tester.enterText(
      inSheet(find.widgetWithText(TextField, "Bosib o'tilgan masofa")),
      '134,5',
    );
    await tester.enterText(
      inSheet(find.widgetWithText(TextField, "Sarflangan yoqilg'i")),
      '14',
    );
    await tester.tap(inSheet(find.widgetWithText(FilledButton, 'Yakunlash')));
    await tester.pumpAndSettle();

    verify(
      () => waybills.complete(
        10,
        actualKm: 134.5,
        fuelConsumed: 14,
        fuelReturned: null,
      ),
    ).called(1);
  });

  testWidgets('offers a retry when loading fails', (tester) async {
    when(() => auth.currentUser).thenReturn(userWith('mechanic'));
    var attempts = 0;
    when(() => waybills.get(10)).thenAnswer((_) async {
      if (attempts++ == 0) throw const ApiException("Server bilan aloqa yo'q.");
      return waybillWith(WaybillStatus.mechanicCheck);
    });
    await tester.pumpWidget(
      MaterialApp(
        home: ChangeNotifierProvider(
          create: (_) => WaybillDetailViewModel(
            waybillId: 10,
            authRepository: auth,
            waybillRepository: waybills,
          )..load(),
          child: const WaybillDetailScreen(),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text("Server bilan aloqa yo'q."), findsOneWidget);

    await tester.tap(find.text('Qayta urinish'));
    await tester.pumpAndSettle();

    expect(find.text('UTG-202610-0010'), findsOneWidget);
  });
}
