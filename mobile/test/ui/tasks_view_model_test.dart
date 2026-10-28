import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:utg_transport/data/services/api_exception.dart';
import 'package:utg_transport/domain/models/waybill.dart';
import 'package:utg_transport/ui/features/tasks/view_models/tasks_view_model.dart';

import '../helpers/fixtures.dart';

void main() {
  late MockAuthRepository auth;
  late MockWaybillRepository waybills;
  late MockDashboardRepository dashboard;

  setUpAll(registerFallbacks);

  setUp(() {
    auth = MockAuthRepository();
    waybills = MockWaybillRepository();
    dashboard = MockDashboardRepository();
    when(() => dashboard.stats()).thenAnswer((_) async => statsWith());
  });

  TasksViewModel viewModelFor(String role, {List<String>? permissions}) {
    when(() => auth.currentUser)
        .thenReturn(userWith(role, permissions: permissions));
    return TasksViewModel(
      authRepository: auth,
      waybillRepository: waybills,
      dashboardRepository: dashboard,
    );
  }

  void stubQueue(WaybillStatus status, List<Waybill> items) {
    when(
      () => waybills.list(
        status: status,
        perPage: any(named: 'perPage'),
      ),
    ).thenAnswer((_) async => pageOf(items));
  }

  test('a mechanic sees the mechanic_check queue and no stats', () async {
    stubQueue(WaybillStatus.mechanicCheck, [
      waybillWith(WaybillStatus.mechanicCheck, id: 1),
      waybillWith(WaybillStatus.mechanicCheck, id: 2),
    ]);
    final viewModel = viewModelFor('mechanic');

    await viewModel.load();

    expect(viewModel.title, 'Mexanik navbati');
    expect(viewModel.stats, isNull);
    expect(viewModel.sections.single.waybills.map((w) => w.id), [1, 2]);
    verifyNever(() => dashboard.stats());
  });

  test('a custom role with both checks sees both queues', () async {
    stubQueue(WaybillStatus.mechanicCheck, [
      waybillWith(WaybillStatus.mechanicCheck, id: 1),
    ]);
    stubQueue(WaybillStatus.mechanicOk, []);
    stubQueue(WaybillStatus.doctorCheck, [
      waybillWith(WaybillStatus.doctorCheck, id: 2),
    ]);
    final viewModel = viewModelFor(
      'inspector',
      permissions: ['waybills.mechanic_check', 'waybills.doctor_check'],
    );

    await viewModel.load();

    expect(viewModel.title, 'Vazifalar');
    expect(viewModel.sections.map((s) => s.title), [
      'Mexanik tekshiruvi',
      'Shifokor tekshiruvi',
    ]);
    expect(viewModel.sections.map((s) => s.waybills.length), [1, 1]);
    verifyNever(() => dashboard.stats());
  });

  test(
    'a custom dispatching role only gets the queues it can act on',
    () async {
      stubQueue(WaybillStatus.approved, [
        waybillWith(WaybillStatus.approved, id: 9),
      ]);
      final viewModel = viewModelFor(
        'gate_keeper',
        permissions: ['waybills.view', 'waybills.depart'],
      );

      await viewModel.load();

      expect(viewModel.sections.map((s) => s.title), [
        "Yo'lga chiqishga tayyor",
      ]);
      verifyNever(
        () => waybills.list(
          status: WaybillStatus.draft,
          perPage: any(named: 'perPage'),
        ),
      );
      verifyNever(
        () => waybills.list(
          status: WaybillStatus.inProgress,
          perPage: any(named: 'perPage'),
        ),
      );
    },
  );

  test('a doctor sees waybills the mechanic passed', () async {
    stubQueue(WaybillStatus.mechanicOk, [
      waybillWith(WaybillStatus.mechanicOk, id: 3),
    ]);
    stubQueue(WaybillStatus.doctorCheck, [
      waybillWith(WaybillStatus.doctorCheck, id: 4),
    ]);
    final viewModel = viewModelFor('doctor');

    await viewModel.load();

    expect(viewModel.sections.single.waybills.map((w) => w.id), [3, 4]);
  });

  test(
    'a dispatcher sees stats plus drafts, approved and on-the-road',
    () async {
      stubQueue(WaybillStatus.draft, [waybillWith(WaybillStatus.draft, id: 5)]);
      stubQueue(WaybillStatus.approved, []);
      stubQueue(WaybillStatus.inProgress, [
        waybillWith(WaybillStatus.inProgress, id: 6),
      ]);
      final viewModel = viewModelFor('dispatcher');

      await viewModel.load();

      expect(viewModel.stats?.totalVehicles, 12);
      expect(viewModel.sections.map((s) => s.waybills.length), [1, 0, 1]);
    },
  );

  test('HQ sees waybills awaiting approval and active trips', () async {
    stubQueue(WaybillStatus.doctorOk, [
      waybillWith(WaybillStatus.doctorOk, id: 7),
    ]);
    stubQueue(WaybillStatus.hqReview, []);
    when(() => dashboard.stats()).thenAnswer(
      (_) async => statsWith(
        activeTrips: [waybillWith(WaybillStatus.inProgress, id: 8)],
      ),
    );
    final viewModel = viewModelFor('hq_dispatcher');

    await viewModel.load();

    expect(viewModel.sections.first.waybills.single.id, 7);
    expect(viewModel.sections.last.waybills.single.id, 8);
  });

  test('keeps the error when loading fails', () async {
    when(
      () => waybills.list(
        status: any(named: 'status'),
        perPage: any(named: 'perPage'),
      ),
    ).thenThrow(const ApiException("Server bilan aloqa yo'q."));
    final viewModel = viewModelFor('mechanic');

    await viewModel.load();

    expect(viewModel.error, "Server bilan aloqa yo'q.");
    expect(viewModel.hasLoaded, isFalse);
    expect(viewModel.isLoading, isFalse);
  });

  test('reloads when waybills change elsewhere', () async {
    stubQueue(WaybillStatus.mechanicCheck, []);
    final viewModel = viewModelFor('mechanic');
    final listener =
        verify(() => waybills.addListener(captureAny())).captured.single
            as void Function();

    listener();
    await pumpEventQueue();

    verify(
      () => waybills.list(status: WaybillStatus.mechanicCheck, perPage: 100),
    ).called(1);
    viewModel.dispose();
    verify(() => waybills.removeListener(listener)).called(1);
  });

  test('inspect sends the mechanic check for a mechanic', () async {
    when(
      () => waybills.mechanicCheck(
        any(),
        passed: any(named: 'passed'),
        notes: any(named: 'notes'),
      ),
    ).thenAnswer((_) async {});
    final viewModel = viewModelFor('mechanic');

    final error = await viewModel.inspect(
      waybillWith(WaybillStatus.mechanicCheck, id: 9),
      passed: false,
      notes: 'Shina yeyilgan',
    );

    expect(error, isNull);
    verify(
      () => waybills.mechanicCheck(9, passed: false, notes: 'Shina yeyilgan'),
    ).called(1);
  });
}
