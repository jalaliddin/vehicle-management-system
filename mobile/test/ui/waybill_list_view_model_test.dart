import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:utg_transport/domain/models/paginated.dart';
import 'package:utg_transport/domain/models/waybill.dart';
import 'package:utg_transport/ui/features/waybills/view_models/waybill_list_view_model.dart';

import '../helpers/fixtures.dart';

void main() {
  late MockWaybillRepository repository;
  late WaybillListViewModel viewModel;

  setUpAll(registerFallbacks);

  setUp(() {
    repository = MockWaybillRepository();
    viewModel = WaybillListViewModel(waybillRepository: repository);
  });

  tearDown(() => viewModel.dispose());

  test('loads the first page, then appends the next', () async {
    when(() => repository.list(page: 1, status: null, search: '')).thenAnswer(
      (_) async =>
          pageOf([waybillWith(WaybillStatus.draft, id: 1)], lastPage: 2),
    );
    when(() => repository.list(page: 2, status: null, search: '')).thenAnswer(
      (_) async => pageOf(
        [waybillWith(WaybillStatus.draft, id: 2)],
        currentPage: 2,
        lastPage: 2,
      ),
    );

    await viewModel.refresh();
    expect(viewModel.items.map((w) => w.id), [1]);
    expect(viewModel.hasMore, isTrue);

    await viewModel.loadMore();
    expect(viewModel.items.map((w) => w.id), [1, 2]);
    expect(viewModel.hasMore, isFalse);

    await viewModel.loadMore();
    verify(() => repository.list(page: 2, status: null, search: '')).called(1);
  });

  test('drops a response that arrives after the filter changed', () async {
    final slowAll = Completer<Paginated<Waybill>>();
    when(() => repository.list(page: 1, status: null, search: ''))
        .thenAnswer((_) => slowAll.future);
    when(
      () =>
          repository.list(page: 1, status: WaybillStatus.completed, search: ''),
    ).thenAnswer(
      (_) async => pageOf([waybillWith(WaybillStatus.completed, id: 7)]),
    );

    final firstLoad = viewModel.refresh();
    viewModel.setStatus(WaybillStatus.completed);
    await pumpEventQueue();
    slowAll.complete(pageOf([waybillWith(WaybillStatus.draft, id: 1)]));
    await firstLoad;

    expect(viewModel.status, WaybillStatus.completed);
    expect(viewModel.items.map((w) => w.id), [7]);
  });
}
