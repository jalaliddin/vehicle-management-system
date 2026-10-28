import '../../../../data/repositories/auth_repository.dart';
import '../../../../data/repositories/dashboard_repository.dart';
import '../../../../data/repositories/waybill_repository.dart';
import '../../../../data/services/api_exception.dart';
import '../../../../domain/models/dashboard_stats.dart';
import '../../../../domain/models/permission.dart';
import '../../../../domain/models/user.dart';
import '../../../../domain/models/waybill.dart';
import '../../../../domain/models/waybill_action.dart';
import '../../../core/view_model.dart';

class TaskSection {
  const TaskSection({
    required this.title,
    required this.emptyText,
    required this.waybills,
  });

  final String title;
  final String emptyText;
  final List<Waybill> waybills;
}

/// The home layouts, from the most senior duty down. A user gets the layout
/// of the highest duty their permissions allow.
enum _Duty { approver, dispatcher, inspector, observer }

/// The home tab: what the signed-in user should act on, by permission.
class TasksViewModel extends ViewModel {
  TasksViewModel({
    required AuthRepository authRepository,
    required WaybillRepository waybillRepository,
    required DashboardRepository dashboardRepository,
  }) : _auth = authRepository,
       _waybills = waybillRepository,
       _dashboard = dashboardRepository {
    _waybills.addListener(load);
  }

  // Queues are short; one large page avoids paging through them.
  static const _queueSize = 100;

  final AuthRepository _auth;
  final WaybillRepository _waybills;
  final DashboardRepository _dashboard;

  bool _isLoading = false;
  bool _hasLoaded = false;
  String? _error;
  DashboardStats? _stats;
  List<TaskSection> _sections = const [];
  int _generation = 0;

  User? get user => _auth.currentUser;
  bool get isLoading => _isLoading;
  bool get hasLoaded => _hasLoaded;
  String? get error => _error;
  DashboardStats? get stats => _stats;
  List<TaskSection> get sections => _sections;

  String get title {
    final user = this.user;
    if (user == null || _dutyOf(user) != _Duty.inspector) return 'Vazifalar';
    final mechanic = user.can(Permission.waybillsMechanicCheck);
    final doctor = user.can(Permission.waybillsDoctorCheck);
    if (mechanic && !doctor) return 'Mexanik navbati';
    if (doctor && !mechanic) return 'Shifokor navbati';
    return 'Vazifalar';
  }

  static _Duty _dutyOf(User user) {
    if (user.can(Permission.waybillsApprove)) return _Duty.approver;
    if (user.canAny(const [
      Permission.waybillsSubmit,
      Permission.waybillsDepart,
      Permission.waybillsComplete,
    ])) {
      return _Duty.dispatcher;
    }
    if (user.canAny(const [
      Permission.waybillsMechanicCheck,
      Permission.waybillsDoctorCheck,
    ])) {
      return _Duty.inspector;
    }
    return _Duty.observer;
  }

  Future<void> load() async {
    final user = _auth.currentUser;
    if (user == null) return;
    final generation = ++_generation;
    _isLoading = true;
    _error = null;
    notifyListeners();
    try {
      final (stats, sections) = await _loadFor(user);
      if (generation != _generation) return;
      _stats = stats;
      _sections = sections;
      _hasLoaded = true;
    } on ApiException catch (e) {
      if (generation == _generation) _error = e.message;
    } finally {
      if (generation == _generation) {
        _isLoading = false;
        notifyListeners();
      }
    }
  }

  Future<(DashboardStats?, List<TaskSection>)> _loadFor(User user) async {
    switch (_dutyOf(user)) {
      case _Duty.inspector:
        final mechanic = user.can(Permission.waybillsMechanicCheck);
        final doctor = user.can(Permission.waybillsDoctorCheck);
        final both = mechanic && doctor;
        return (
          null,
          [
            if (mechanic)
              TaskSection(
                title: both ? 'Mexanik tekshiruvi' : 'Tekshiruvni kutmoqda',
                emptyText: "Navbatda yo'l varaqasi yo'q",
                waybills: await _queue([WaybillStatus.mechanicCheck]),
              ),
            if (doctor)
              TaskSection(
                title: both ? 'Shifokor tekshiruvi' : 'Tekshiruvni kutmoqda',
                emptyText: "Navbatda yo'l varaqasi yo'q",
                waybills: await _queue([
                  WaybillStatus.mechanicOk,
                  WaybillStatus.doctorCheck,
                ]),
              ),
          ],
        );
      case _Duty.dispatcher:
        final results = await Future.wait<Object>([
          _dashboard.stats(),
          _queueIf(user.can(Permission.waybillsSubmit), [WaybillStatus.draft]),
          _queueIf(user.can(Permission.waybillsDepart), [
            WaybillStatus.approved,
          ]),
          _queueIf(user.can(Permission.waybillsComplete), [
            WaybillStatus.inProgress,
          ]),
        ]);
        return (
          results[0] as DashboardStats,
          [
            if (user.can(Permission.waybillsSubmit))
              TaskSection(
                title: 'Mexanikka yuborilmagan',
                emptyText: "Qoralama yo'q",
                waybills: results[1] as List<Waybill>,
              ),
            if (user.can(Permission.waybillsDepart))
              TaskSection(
                title: "Yo'lga chiqishga tayyor",
                emptyText: "Tasdiqlangan varaqa yo'q",
                waybills: results[2] as List<Waybill>,
              ),
            if (user.can(Permission.waybillsComplete))
              TaskSection(
                title: "Yo'ldagi transport",
                emptyText: "Hozir yo'lda transport yo'q",
                waybills: results[3] as List<Waybill>,
              ),
          ],
        );
      case _Duty.approver:
        final results = await Future.wait<Object>([
          _dashboard.stats(),
          _queue([WaybillStatus.doctorOk, WaybillStatus.hqReview]),
        ]);
        final stats = results[0] as DashboardStats;
        return (
          stats,
          [
            TaskSection(
              title: 'Tasdiqlash kutilmoqda',
              emptyText: "Tasdiqlanadigan varaqa yo'q",
              waybills: results[1] as List<Waybill>,
            ),
            TaskSection(
              title: "Yo'ldagi transport",
              emptyText: "Hozir yo'lda transport yo'q",
              waybills: stats.activeTrips,
            ),
          ],
        );
      case _Duty.observer:
        final stats = await _dashboard.stats();
        return (
          stats,
          [
            TaskSection(
              title: "Yo'ldagi transport",
              emptyText: "Hozir yo'lda transport yo'q",
              waybills: stats.activeTrips,
            ),
          ],
        );
    }
  }

  Future<List<Waybill>> _queueIf(bool wanted, List<WaybillStatus> statuses) =>
      wanted ? _queue(statuses) : Future.value(const []);

  Future<List<Waybill>> _queue(List<WaybillStatus> statuses) async {
    final pages = await Future.wait([
      for (final status in statuses)
        _waybills.list(status: status, perPage: _queueSize),
    ]);
    return [for (final page in pages) ...page.items];
  }

  /// The quick pass/reject from a mechanic's or doctor's queue.
  /// Returns an error message, or null on success.
  Future<String?> inspect(
    Waybill waybill, {
    required bool passed,
    String? notes,
  }) async {
    final user = _auth.currentUser;
    if (user == null) return null;
    final actions = WaybillAction.availableFor(user, waybill);
    try {
      if (actions.contains(WaybillAction.mechanicCheck)) {
        await _waybills.mechanicCheck(waybill.id, passed: passed, notes: notes);
      } else if (actions.contains(WaybillAction.doctorCheck)) {
        await _waybills.doctorCheck(waybill.id, passed: passed, notes: notes);
      }
      return null;
    } on ApiException catch (e) {
      return e.message;
    }
  }

  @override
  void dispose() {
    _waybills.removeListener(load);
    super.dispose();
  }
}
