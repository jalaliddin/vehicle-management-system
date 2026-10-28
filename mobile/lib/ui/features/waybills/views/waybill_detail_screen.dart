import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../../domain/models/vehicle.dart';
import '../../../../domain/models/waybill.dart';
import '../../../../domain/models/waybill_action.dart';
import '../../../core/feedback.dart';
import '../../../core/formatters.dart';
import '../../../core/status_chip.dart';
import '../../../core/theme.dart';
import '../view_models/waybill_detail_view_model.dart';
import 'widgets/complete_sheet.dart';
import 'widgets/inspection_sheet.dart';
import 'widgets/waybill_timeline.dart';

class WaybillDetailScreen extends StatelessWidget {
  const WaybillDetailScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final viewModel = context.watch<WaybillDetailViewModel>();
    final waybill = viewModel.waybill;

    final Widget body;
    if (waybill == null) {
      body = viewModel.error != null
          ? ErrorRetry(message: viewModel.error!, onRetry: viewModel.load)
          : const Center(child: CircularProgressIndicator());
    } else {
      body = RefreshIndicator(
        onRefresh: viewModel.load,
        child: _WaybillDetails(waybill: waybill),
      );
    }

    return Scaffold(
      appBar: AppBar(title: Text(waybill?.number ?? "Yo'l varaqasi")),
      body: body,
      bottomNavigationBar: waybill == null || viewModel.actions.isEmpty
          ? null
          : _ActionBar(viewModel: viewModel, waybill: waybill),
    );
  }
}

class _WaybillDetails extends StatelessWidget {
  const _WaybillDetails({required this.waybill});

  final Waybill waybill;

  @override
  Widget build(BuildContext context) {
    final w = waybill;
    final vehicle = w.vehicle;
    final driver = w.driver;

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                StatusChip(status: w.status),
                const SizedBox(height: 12),
                Text(
                  w.destination.isEmpty ? '—' : w.destination,
                  style: Theme.of(context).textTheme.titleLarge
                      ?.copyWith(fontWeight: FontWeight.w600),
                ),
                if (w.organization case final organization?)
                  Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: Text(
                      organization.name,
                      style: const TextStyle(color: AppColors.textSecondary),
                    ),
                  ),
                const SizedBox(height: 8),
                Text(
                  '${formatDate(w.createdDate)} — ${formatDate(w.validUntil)}',
                  style: const TextStyle(color: AppColors.textSecondary),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        _Section(
          title: 'Transport',
          rows: [
            ('Avtomobil', vehicle?.title ?? '—'),
            ('Turi', w.vehicleType?.label ?? '—'),
            ("Yoqilg'i turi", fuelTypeLabel(vehicle?.fuelType)),
            ('Haydovchi', driver?.fullName ?? '—'),
            if (driver?.licenseCategory case final category?)
              ('Guvohnoma toifasi', category),
            if (w.tabelNumber case final tabel? when tabel.isNotEmpty)
              ('Tabel raqami', tabel),
          ],
        ),
        const SizedBox(height: 12),
        _Section(
          title: 'Reys',
          rows: [
            if (w.destinationOrganization case final org? when org.isNotEmpty)
              ('Qabul qiluvchi', org),
            ('Reyslar soni', '${w.tripCount}'),
            ('Konditsioner', w.hasAc ? 'Bor' : "Yo'q"),
            ('Rejadagi masofa', formatAmount(w.plannedKm, 'km')),
            ('Bosib o\'tilgan', formatAmount(w.actualKm, 'km')),
            ("Berilgan yoqilg'i", formatAmount(w.fuelIssued, 'L')),
            ('Sarflangan', formatAmount(w.fuelConsumed, 'L')),
            ('Qaytarilgan', formatAmount(w.fuelReturned, 'L')),
            if (w.notes case final notes? when notes.isNotEmpty)
              ('Izoh', notes),
          ],
        ),
        const SizedBox(height: 12),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const _SectionTitle('Jarayon'),
                const SizedBox(height: 12),
                WaybillTimeline(waybill: w),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.text);

  final String text;

  @override
  Widget build(BuildContext context) => Text(
    text,
    style: Theme.of(context).textTheme.titleMedium
        ?.copyWith(fontWeight: FontWeight.w600),
  );
}

class _Section extends StatelessWidget {
  const _Section({required this.title, required this.rows});

  final String title;
  final List<(String, String)> rows;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _SectionTitle(title),
          const SizedBox(height: 8),
          for (final (label, value) in rows)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 6),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    flex: 2,
                    child: Text(
                      label,
                      style: const TextStyle(color: AppColors.textSecondary),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    flex: 3,
                    child: Text(
                      value,
                      textAlign: TextAlign.end,
                      style: const TextStyle(fontWeight: FontWeight.w500),
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    ),
  );
}

class _ActionBar extends StatelessWidget {
  const _ActionBar({required this.viewModel, required this.waybill});

  final WaybillDetailViewModel viewModel;
  final Waybill waybill;

  Future<void> _report(
    BuildContext context,
    Future<String?> result,
    String success,
  ) async {
    final error = await result;
    if (!context.mounted) return;
    showMessage(context, error ?? success, isError: error != null);
  }

  Future<void> _confirmThen(
    BuildContext context, {
    required String title,
    required String message,
    required String confirmLabel,
    required Future<String?> Function() action,
    required String success,
  }) async {
    final confirmed = await confirmAction(
      context,
      title: title,
      message: message,
      confirmLabel: confirmLabel,
    );
    if (!confirmed || !context.mounted) return;
    await _report(context, action(), success);
  }

  Future<void> _inspect(BuildContext context, {required bool passed}) async {
    final isMechanic = viewModel.actions.contains(WaybillAction.mechanicCheck);
    final result = await showInspectionSheet(
      context,
      title: isMechanic ? 'Mexanik xulosasi' : 'Shifokor xulosasi',
      passed: passed,
    );
    if (result == null || !context.mounted) return;
    await _report(
      context,
      viewModel.inspect(passed: result.passed, notes: result.notes),
      result.passed ? 'Tasdiqlandi' : 'Rad etildi',
    );
  }

  Future<void> _complete(BuildContext context) async {
    final result = await showCompleteSheet(context, waybill);
    if (result == null || !context.mounted) return;
    await _report(
      context,
      viewModel.complete(
        actualKm: result.actualKm,
        fuelConsumed: result.fuelConsumed,
        fuelReturned: result.fuelReturned,
      ),
      'Reys yakunlandi',
    );
  }

  @override
  Widget build(BuildContext context) {
    final running = viewModel.runningAction;
    final busy = running != null;
    final actions = viewModel.actions;

    const spinner = SizedBox.square(
      dimension: 20,
      child: CircularProgressIndicator(strokeWidth: 2),
    );
    Widget label(WaybillAction action, String text) =>
        running == action ? spinner : Text(text);

    final buttons = <Widget>[
      if (actions.contains(WaybillAction.submitToMechanic))
        FilledButton(
          onPressed: busy
              ? null
              : () => _confirmThen(
                  context,
                  title: 'Mexanikka yuborish',
                  message: "${waybill.number} mexanik ko'rigiga yuborilsinmi?",
                  confirmLabel: 'Yuborish',
                  action: viewModel.submitToMechanic,
                  success: 'Mexanikka yuborildi',
                ),
          child: label(WaybillAction.submitToMechanic, 'Mexanikka yuborish'),
        ),
      if (actions.contains(WaybillAction.mechanicCheck) ||
          actions.contains(WaybillAction.doctorCheck)) ...[
        OutlinedButton(
          style: OutlinedButton.styleFrom(
            foregroundColor: AppColors.error,
            side: const BorderSide(color: AppColors.error),
          ),
          onPressed: busy ? null : () => _inspect(context, passed: false),
          child: const Text('Rad etish'),
        ),
        FilledButton(
          style: FilledButton.styleFrom(backgroundColor: AppColors.success),
          onPressed: busy ? null : () => _inspect(context, passed: true),
          child: busy ? spinner : const Text('Tasdiqlash'),
        ),
      ],
      if (actions.contains(WaybillAction.approve))
        FilledButton(
          style: FilledButton.styleFrom(backgroundColor: AppColors.success),
          onPressed: busy
              ? null
              : () => _confirmThen(
                  context,
                  title: 'Tasdiqlash',
                  message: '${waybill.number} tasdiqlansinmi?',
                  confirmLabel: 'Tasdiqlash',
                  action: viewModel.approve,
                  success: 'Tasdiqlandi',
                ),
          child: label(WaybillAction.approve, 'Tasdiqlash'),
        ),
      if (actions.contains(WaybillAction.depart))
        FilledButton(
          onPressed: busy
              ? null
              : () => _confirmThen(
                  context,
                  title: "Yo'lga chiqish",
                  message:
                      "${waybill.vehicle?.stateNumber ?? waybill.number} yo'lga chiqdimi?",
                  confirmLabel: "Yo'lga chiqdi",
                  action: viewModel.depart,
                  success: "Yo'lga chiqdi deb belgilandi",
                ),
          child: label(WaybillAction.depart, "Yo'lga chiqdi"),
        ),
      if (actions.contains(WaybillAction.complete))
        FilledButton(
          onPressed: busy ? null : () => _complete(context),
          child: label(WaybillAction.complete, 'Yakunlash'),
        ),
    ];

    return Material(
      color: Colors.white,
      elevation: 8,
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              for (final (index, button) in buttons.indexed) ...[
                if (index > 0) const SizedBox(width: 12),
                Expanded(child: button),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
