import 'package:flutter/material.dart';

import '../../../../../domain/models/waybill.dart';
import '../../../../core/formatters.dart';
import '../../../../core/theme.dart';

enum TimelineStepState { done, failed, current, pending }

class TimelineStep {
  const TimelineStep({
    required this.title,
    required this.state,
    this.detail,
    this.notes,
  });

  final String title;
  final TimelineStepState state;
  final String? detail;
  final String? notes;
}

/// The approval chain built from the waybill's timestamps rather than its
/// status alone, so each step shows who acted and when.
List<TimelineStep> buildTimeline(Waybill w) {
  final status = w.status;

  TimelineStepState stateOf(
    DateTime? at, {
    bool? passed,
    required bool isCurrent,
  }) {
    if (at != null)
      return passed == false
          ? TimelineStepState.failed
          : TimelineStepState.done;
    return isCurrent ? TimelineStepState.current : TimelineStepState.pending;
  }

  String? who(String? name, DateTime? at) =>
      at == null ? null : [?name, formatDateTime(at)].join(' · ');

  return [
    TimelineStep(
      title: 'Yaratildi',
      state: TimelineStepState.done,
      detail: formatDate(w.createdDate),
    ),
    TimelineStep(
      title: "Mexanik ko'rigi",
      state: stateOf(
        w.mechanicCheckedAt,
        passed: w.mechanicPassed,
        isCurrent: status == WaybillStatus.mechanicCheck,
      ),
      detail: who(w.mechanicName, w.mechanicCheckedAt),
      notes: w.mechanicNotes,
    ),
    TimelineStep(
      title: "Shifokor ko'rigi",
      state: stateOf(
        w.doctorCheckedAt,
        passed: w.doctorPassed,
        isCurrent:
            status == WaybillStatus.mechanicOk ||
            status == WaybillStatus.doctorCheck,
      ),
      detail: who(w.doctorName, w.doctorCheckedAt),
      notes: w.doctorNotes,
    ),
    TimelineStep(
      title: 'Markaziy tasdiq',
      state: stateOf(
        w.approvedAt,
        isCurrent:
            status == WaybillStatus.doctorOk ||
            status == WaybillStatus.hqReview,
      ),
      detail: who(w.approvedByName, w.approvedAt),
    ),
    TimelineStep(
      title: "Yo'lga chiqdi",
      state: stateOf(w.departedAt, isCurrent: status == WaybillStatus.approved),
      detail: who(null, w.departedAt),
    ),
    TimelineStep(
      title: 'Yakunlandi',
      state: stateOf(
        w.arrivedAt,
        isCurrent: status == WaybillStatus.inProgress,
      ),
      detail: who(null, w.arrivedAt),
    ),
  ];
}

class WaybillTimeline extends StatelessWidget {
  const WaybillTimeline({super.key, required this.waybill});

  final Waybill waybill;

  @override
  Widget build(BuildContext context) {
    final steps = buildTimeline(waybill);
    return Column(
      children: [
        for (final (index, step) in steps.indexed)
          _StepRow(step: step, isLast: index == steps.length - 1),
      ],
    );
  }
}

class _StepRow extends StatelessWidget {
  const _StepRow({required this.step, required this.isLast});

  final TimelineStep step;
  final bool isLast;

  @override
  Widget build(BuildContext context) {
    final (color, icon) = switch (step.state) {
      TimelineStepState.done => (AppColors.success, Icons.check),
      TimelineStepState.failed => (AppColors.error, Icons.close),
      TimelineStepState.current => (AppColors.warning, Icons.more_horiz),
      TimelineStepState.pending => (AppColors.border, null),
    };
    final isPending = step.state == TimelineStepState.pending;

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(
            width: 28,
            child: Column(
              children: [
                Container(
                  width: 24,
                  height: 24,
                  decoration: BoxDecoration(
                    color: isPending ? Colors.white : color,
                    shape: BoxShape.circle,
                    border: Border.all(color: color, width: 2),
                  ),
                  child: icon == null
                      ? null
                      : Icon(icon, size: 14, color: Colors.white),
                ),
                if (!isLast)
                  Expanded(child: Container(width: 2, color: AppColors.border)),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Padding(
              padding: EdgeInsets.only(bottom: isLast ? 0 : 16, top: 2),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    step.title,
                    style: TextStyle(
                      fontWeight: FontWeight.w600,
                      color: isPending ? AppColors.textSecondary : null,
                    ),
                  ),
                  if (step.state == TimelineStepState.current)
                    const Text(
                      'Kutilmoqda',
                      style: TextStyle(color: AppColors.warning, fontSize: 13),
                    ),
                  if (step.detail case final detail?)
                    Text(
                      detail,
                      style: const TextStyle(
                        color: AppColors.textSecondary,
                        fontSize: 13,
                      ),
                    ),
                  if (step.notes case final notes? when notes.isNotEmpty)
                    Container(
                      margin: const EdgeInsets.only(top: 6),
                      padding: const EdgeInsets.all(8),
                      width: double.infinity,
                      decoration: BoxDecoration(
                        color: AppColors.background,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(notes, style: const TextStyle(fontSize: 13)),
                    ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
