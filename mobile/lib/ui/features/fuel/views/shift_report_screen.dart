import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../../domain/models/shift_report.dart';
import '../../../core/feedback.dart';
import '../../../core/formatters.dart';
import '../../../core/theme.dart';
import '../view_models/shift_report_view_model.dart';

/// A shift's balances, totals and operations, read-only; printing stays
/// on the web.
class ShiftReportScreen extends StatelessWidget {
  const ShiftReportScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final viewModel = context.watch<ShiftReportViewModel>();
    final report = viewModel.report;
    return Scaffold(
      appBar: AppBar(title: const Text('Smena hisoboti')),
      body: switch ((report, viewModel.loadError)) {
        (final report?, _) => RefreshIndicator(
          onRefresh: viewModel.load,
          child: _ReportBody(report: report),
        ),
        (null, final error?) => ErrorRetry(
          message: error,
          onRetry: viewModel.load,
        ),
        _ => const Center(child: CircularProgressIndicator()),
      },
    );
  }
}

class _ReportBody extends StatelessWidget {
  const _ReportBody({required this.report});

  final ShiftReport report;

  @override
  Widget build(BuildContext context) {
    final shift = report.shift;
    if (shift == null) {
      return const EmptyState(
        icon: Icons.description_outlined,
        message: 'Smena topilmadi',
      );
    }
    final unit = shift.unit;
    String quantity(double? value) => formatQuantity(value, unit);

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      children: [
        Text(
          shift.stationName ?? '—',
          style: Theme.of(context).textTheme.titleLarge
              ?.copyWith(fontWeight: FontWeight.w600),
        ),
        const SizedBox(height: 4),
        Text(
          'Ochildi: ${formatDateTime(shift.openedAt)} (${shift.openedBy ?? '—'})',
          style: const TextStyle(color: AppColors.textSecondary),
        ),
        Text(
          shift.isOpen
              ? 'Yopildi: —'
              : 'Yopildi: ${formatDateTime(shift.closedAt)} '
                    '(${shift.closedBy ?? '—'})',
          style: const TextStyle(color: AppColors.textSecondary),
        ),
        const SizedBox(height: 12),
        Card(
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 8),
            child: Column(
              children: [
                _Row(
                  "Boshlang'ich qoldiq (hisob)",
                  quantity(shift.openingBalance),
                ),
                _Row("Boshlang'ich o'lchov", quantity(shift.openingMeasured)),
                _Row('Qabul qilindi', quantity(report.receiptsTotal)),
                _Row('Quyildi', quantity(report.refuelsTotal)),
                if (!shift.isOpen) ...[
                  _Row(
                    'Yakuniy qoldiq (hisob)',
                    quantity(shift.closingBalance),
                  ),
                  _Row("Yakuniy o'lchov", quantity(shift.closingMeasured)),
                  _Row('Farq', quantity(shift.discrepancy)),
                  if (shift.discrepancyNote case final note?)
                    _Row('Farq izohi', note),
                ],
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        Card(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Padding(
                padding: EdgeInsets.fromLTRB(16, 16, 16, 4),
                child: Text(
                  'Amallar',
                  style: TextStyle(fontWeight: FontWeight.w600),
                ),
              ),
              if (report.operations.isEmpty)
                const Padding(
                  padding: EdgeInsets.all(16),
                  child: Text(
                    "Amallar yo'q",
                    style: TextStyle(color: AppColors.textSecondary),
                  ),
                ),
              for (final operation in report.operations)
                _OperationRow(operation: operation, unit: unit),
            ],
          ),
        ),
      ],
    );
  }
}

class _Row extends StatelessWidget {
  const _Row(this.label, this.value);

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Text(
            label,
            style: const TextStyle(color: AppColors.textSecondary),
          ),
        ),
        const SizedBox(width: 12),
        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.end,
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
        ),
      ],
    ),
  );
}

class _OperationRow extends StatelessWidget {
  const _OperationRow({required this.operation, required this.unit});

  final FuelOperation operation;
  final String unit;

  @override
  Widget build(BuildContext context) {
    final cancelled = operation.isCancelled;
    final muted = cancelled
        ? const TextStyle(
            color: AppColors.textSecondary,
            decoration: TextDecoration.lineThrough,
          )
        : const TextStyle();
    return ListTile(
      title: Text(
        '${operation.kind.label}${cancelled ? ' (bekor)' : ''}',
        style: muted,
      ),
      subtitle: Text(
        [
          formatDateTime(operation.createdAt),
          operation.subject,
          if (operation.odometer case final km?) '$km km',
        ].join(' · '),
      ),
      trailing: Text(
        '${operation.isIncoming ? '+' : '−'}'
        '${formatQuantity(operation.amount, unit)}',
        style: muted.copyWith(fontWeight: FontWeight.w600),
      ),
    );
  }
}
