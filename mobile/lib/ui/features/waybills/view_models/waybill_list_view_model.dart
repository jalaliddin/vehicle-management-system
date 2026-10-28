import 'dart:async';

import '../../../../data/repositories/waybill_repository.dart';
import '../../../../data/services/api_exception.dart';
import '../../../../domain/models/waybill.dart';
import '../../../core/view_model.dart';

class WaybillListViewModel extends ViewModel {
  WaybillListViewModel({required WaybillRepository waybillRepository})
    : _repository = waybillRepository {
    _repository.addListener(refresh);
  }

  /// Filter chips, in workflow order; null means all statuses.
  static const filters = <WaybillStatus?>[
    null,
    WaybillStatus.draft,
    WaybillStatus.mechanicCheck,
    WaybillStatus.mechanicOk,
    WaybillStatus.doctorOk,
    WaybillStatus.approved,
    WaybillStatus.inProgress,
    WaybillStatus.completed,
    WaybillStatus.cancelled,
  ];

  final WaybillRepository _repository;

  final _items = <Waybill>[];
  int _page = 0;
  bool _hasMore = false;
  bool _isLoading = false;
  bool _isLoadingMore = false;
  String? _error;
  bool _loadMoreFailed = false;
  WaybillStatus? _status;
  String _search = '';
  Timer? _searchDebounce;

  // Bumped by every refresh so responses for an old filter are dropped.
  int _generation = 0;

  List<Waybill> get items => List.unmodifiable(_items);
  bool get hasMore => _hasMore;
  bool get isLoading => _isLoading;
  bool get isLoadingMore => _isLoadingMore;
  String? get error => _error;
  bool get loadMoreFailed => _loadMoreFailed;
  WaybillStatus? get status => _status;

  Future<void> refresh() async {
    final generation = ++_generation;
    _isLoading = true;
    _isLoadingMore = false;
    _loadMoreFailed = false;
    _error = null;
    notifyListeners();
    try {
      final page = await _repository.list(status: _status, search: _search);
      if (generation != _generation) return;
      _items
        ..clear()
        ..addAll(page.items);
      _page = page.currentPage;
      _hasMore = page.hasMore;
    } on ApiException catch (e) {
      if (generation == _generation) _error = e.message;
    } finally {
      if (generation == _generation) {
        _isLoading = false;
        notifyListeners();
      }
    }
  }

  Future<void> loadMore() async {
    if (_isLoading || _isLoadingMore || !_hasMore) return;
    final generation = _generation;
    _isLoadingMore = true;
    _loadMoreFailed = false;
    notifyListeners();
    try {
      final page = await _repository.list(
        page: _page + 1,
        status: _status,
        search: _search,
      );
      if (generation != _generation) return;
      _items.addAll(page.items);
      _page = page.currentPage;
      _hasMore = page.hasMore;
    } on ApiException {
      if (generation == _generation) _loadMoreFailed = true;
    } finally {
      if (generation == _generation) {
        _isLoadingMore = false;
        notifyListeners();
      }
    }
  }

  void setStatus(WaybillStatus? status) {
    if (status == _status) return;
    _status = status;
    refresh();
  }

  void setSearch(String query) {
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 400), () {
      if (query.trim() == _search) return;
      _search = query.trim();
      refresh();
    });
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _repository.removeListener(refresh);
    super.dispose();
  }
}
