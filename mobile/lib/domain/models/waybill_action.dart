import 'permission.dart';
import 'user.dart';
import 'waybill.dart';

enum WaybillAction {
  submitToMechanic(Permission.waybillsSubmit, {WaybillStatus.draft}),
  mechanicCheck(Permission.waybillsMechanicCheck, {
    WaybillStatus.mechanicCheck,
  }),
  // Nothing moves a waybill to doctor_check yet, so doctors pick up
  // mechanic_ok directly.
  doctorCheck(Permission.waybillsDoctorCheck, {
    WaybillStatus.mechanicOk,
    WaybillStatus.doctorCheck,
  }),
  approve(Permission.waybillsApprove, {
    WaybillStatus.doctorOk,
    WaybillStatus.hqReview,
  }),
  depart(Permission.waybillsDepart, {WaybillStatus.approved}),
  complete(Permission.waybillsComplete, {WaybillStatus.inProgress});

  const WaybillAction(this.permission, this.allowedFrom);

  final Permission permission;

  /// The statuses this step can be taken from.
  final Set<WaybillStatus> allowedFrom;

  /// The workflow steps [user] may take on [waybill] in its current status.
  ///
  /// Mirrors the backend, which enforces the same permission and status
  /// rules; this only decides which buttons to show.
  static Set<WaybillAction> availableFor(User user, Waybill waybill) => {
    for (final action in values)
      if (action.allowedFrom.contains(waybill.status) &&
          user.can(action.permission))
        action,
  };
}
