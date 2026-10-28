import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../../../domain/models/fuel_station.dart';
import '../../../../domain/models/shift_report.dart';
import '../../../../routing/routes.dart';
import '../../../core/feedback.dart';
import '../../../core/formatters.dart';
import '../../../core/theme.dart';
import '../view_models/fuel_desk_view_model.dart';

/// The fueler's home: station balance, the shift, and its operations.
class FuelDeskScreen extends StatefulWidget {
  const FuelDeskScreen({super.key});

  @override
  State<FuelDeskScreen> createState() => _FuelDeskScreenState();
}

class _FuelDeskScreenState extends State<FuelDeskScreen> {
  final _measured = TextEditingController();
  final _note = TextEditingController();

  @override
  void initState() {
    super.initState();
    // The difference and the note field follow the typed balance.
    _measured.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _measured.dispose();
    _note.dispose();
    super.dispose();
  }

  void _clearInputs() {
    _measured.clear();
    _note.clear();
  }

  @override
  Widget build(BuildContext context) {
    final viewModel = context.watch<FuelDeskViewModel>();
    return Scaffold(
      appBar: AppBar(title: const Text("Yoqilg'i quyish")),
      body: RefreshIndicator(
        onRefresh: viewModel.load,
        child: _buildBody(viewModel),
      ),
    );
  }

  Widget _buildBody(FuelDeskViewModel viewModel) {
    final station = viewModel.station;
    if (station == null) {
      if (viewModel.loadError case final error?) {
        return ErrorRetry(message: error, onRetry: viewModel.load);
      }
      if (viewModel.noStations) {
        return const EmptyState(
          icon: Icons.local_gas_station_outlined,
          message: 'Sizga shahobcha biriktirilmagan',
        );
      }
      return const Center(child: CircularProgressIndicator());
    }

    final shift = viewModel.shift;
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      children: [
        for (final error in [?viewModel.loadError, ?viewModel.actionError]) ...[
          InlineError(message: error),
          const SizedBox(height: 12),
        ],
        if (viewModel.stations.length > 1) ...[
          DropdownButtonFormField<int>(
            initialValue: station.id,
            decoration: const InputDecoration(labelText: 'Shahobcha'),
            items: [
              for (final s in viewModel.stations)
                DropdownMenuItem(value: s.id, child: Text(s.name)),
            ],
            onChanged: viewModel.busy
                ? null
                : (id) {
                    if (id == null) return;
                    _clearInputs();
                    viewModel.selectStation(id);
                  },
          ),
          const SizedBox(height: 12),
        ],
        _BalanceCard(station: station),
        const SizedBox(height: 12),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: switch (shift) {
              // Without the shift's state, "closed" would be a guess.
              null when viewModel.report == null =>
                viewModel.loadError == null
                    ? const Center(child: CircularProgressIndicator())
                    : Column(
                        children: [
                          const Text("Smena holatini yuklab bo'lmadi"),
                          const SizedBox(height: 8),
                          OutlinedButton.icon(
                            onPressed: viewModel.load,
                            icon: const Icon(Icons.refresh),
                            label: const Text('Qayta urinish'),
                          ),
                        ],
                      ),
              null => _buildClosedShift(viewModel, station),
              final shift => _buildOpenShift(viewModel, station, shift),
            },
          ),
        ),
        if (shift != null) ...[
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _BigButton(
                  icon: Icons.local_gas_station,
                  label: "Yoqilg'i quyish",
                  onPressed: () => _openForm(Routes.fuelRefuel, station),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _BigButton(
                  icon: Icons.local_shipping,
                  label: 'Qabul qilish',
                  color: AppColors.purple,
                  onPressed: () => _openForm(Routes.fuelReceipt, station),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          _OperationsCard(
            operations: viewModel.report!.operations,
            unit: station.unit,
            onCancel: viewModel.busy ? null : _cancel,
          ),
        ],
      ],
    );
  }

  Widget _buildClosedShift(FuelDeskViewModel viewModel, FuelStation station) =>
      Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Smena yopiq',
            style: TextStyle(fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 12),
          _measuredField(viewModel, station, 'opening_measured'),
          const SizedBox(height: 12),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: AppColors.success,
              minimumSize: const Size.fromHeight(52),
            ),
            onPressed: viewModel.busy || _measured.text.trim().isEmpty
                ? null
                : () async {
                    if (await viewModel.openShift(_measured.text)) {
                      _clearInputs();
                    }
                  },
            child: const Text('Smenani ochish'),
          ),
        ],
      );

  Widget _buildOpenShift(
    FuelDeskViewModel viewModel,
    FuelStation station,
    FuelShift shift,
  ) {
    final report = viewModel.report!;
    final difference = viewModel.discrepancyHundredths(_measured.text);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Smena ochiq',
                    style: TextStyle(fontWeight: FontWeight.w600),
                  ),
                  Text(
                    [
                      formatDateTime(shift.openedAt),
                      ?shift.openedBy,
                    ].join(' · '),
                    style: const TextStyle(
                      color: AppColors.textSecondary,
                      fontSize: 13,
                    ),
                  ),
                ],
              ),
            ),
            TextButton.icon(
              onPressed: () => _openReport(viewModel, shift.id),
              icon: const Icon(Icons.description_outlined),
              label: const Text('Hisobot'),
            ),
          ],
        ),
        const SizedBox(height: 8),
        Text(
          'Qabul: ${formatQuantity(report.receiptsTotal)} · '
          'Quyildi: ${formatQuantity(report.refuelsTotal, station.unit)}',
        ),
        const SizedBox(height: 12),
        if (!viewModel.closing)
          OutlinedButton(
            style: OutlinedButton.styleFrom(
              foregroundColor: AppColors.error,
              minimumSize: const Size.fromHeight(48),
            ),
            onPressed: viewModel.busy
                ? null
                : () {
                    _clearInputs();
                    viewModel.beginClosing();
                  },
            child: const Text('Smenani yopish'),
          )
        else ...[
          _measuredField(
            viewModel,
            station,
            'closing_measured',
            helper:
                "Hisob bo'yicha: ${formatQuantity(station.balance, station.unit)}",
          ),
          if (viewModel.noteRequired(_measured.text)) ...[
            const SizedBox(height: 12),
            TextField(
              controller: _note,
              minLines: 2,
              maxLines: 4,
              decoration: InputDecoration(
                labelText: difference == null
                    ? 'Farq izohi'
                    : 'Farq ${formatQuantity(difference / 100, station.unit)} — izoh',
                errorText: viewModel.fieldErrors['discrepancy_note'],
              ),
            ),
          ],
          const SizedBox(height: 12),
          Row(
            children: [
              TextButton(
                onPressed: viewModel.busy ? null : viewModel.cancelClosing,
                child: const Text('Bekor'),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: FilledButton(
                  style: FilledButton.styleFrom(
                    backgroundColor: AppColors.error,
                    minimumSize: const Size.fromHeight(48),
                  ),
                  onPressed: viewModel.busy || _measured.text.trim().isEmpty
                      ? null
                      : () => _close(viewModel),
                  child: const Text('Smenani yopish'),
                ),
              ),
            ],
          ),
        ],
      ],
    );
  }

  Widget _measuredField(
    FuelDeskViewModel viewModel,
    FuelStation station,
    String field, {
    String? helper,
  }) => TextField(
    controller: _measured,
    keyboardType: const TextInputType.numberWithOptions(decimal: true),
    decoration: InputDecoration(
      labelText: "O'lchov bo'yicha qoldiq (${station.unit})",
      helperText: helper,
      errorText: viewModel.fieldErrors[field],
    ),
  );

  Future<void> _close(FuelDeskViewModel viewModel) async {
    final shiftId = await viewModel.closeShift(_measured.text, _note.text);
    if (shiftId == null || !mounted) return;
    _clearInputs();
    await context.push(Routes.fuelShift(shiftId));
    await viewModel.load();
  }

  Future<void> _openReport(FuelDeskViewModel viewModel, int shiftId) async {
    await context.push(Routes.fuelShift(shiftId));
    await viewModel.load();
  }

  Future<void> _openForm(String route, FuelStation station) async {
    final viewModel = context.read<FuelDeskViewModel>();
    final saved = await context.push<bool>(route, extra: station);
    if (saved == true) await viewModel.load();
  }

  Future<void> _cancel(FuelOperation operation) async {
    final viewModel = context.read<FuelDeskViewModel>();
    final reason = await showDialog<String>(
      context: context,
      builder: (context) => _CancelDialog(operation: operation),
    );
    if (reason == null || !mounted) return;
    if (await viewModel.cancel(operation, reason)) {
      if (mounted) showMessage(context, 'Bekor qilindi');
    }
  }
}

class _BalanceCard extends StatelessWidget {
  const _BalanceCard({required this.station});

  final FuelStation station;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            station.name,
            style: const TextStyle(color: AppColors.textSecondary),
          ),
          const SizedBox(height: 4),
          Text(
            formatQuantity(station.balance, station.unit),
            style: Theme.of(context).textTheme.headlineMedium
                ?.copyWith(fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 8),
          LinearProgressIndicator(
            value: (station.percentage / 100).clamp(0.0, 1.0),
            minHeight: 8,
            borderRadius: BorderRadius.circular(4),
          ),
        ],
      ),
    ),
  );
}

class _BigButton extends StatelessWidget {
  const _BigButton({
    required this.icon,
    required this.label,
    required this.onPressed,
    this.color,
  });

  final IconData icon;
  final String label;
  final VoidCallback onPressed;
  final Color? color;

  @override
  Widget build(BuildContext context) => FilledButton(
    style: FilledButton.styleFrom(
      backgroundColor: color,
      minimumSize: const Size.fromHeight(72),
      padding: const EdgeInsets.all(8),
    ),
    onPressed: onPressed,
    child: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon),
        const SizedBox(height: 4),
        Text(label, textAlign: TextAlign.center),
      ],
    ),
  );
}

class _OperationsCard extends StatelessWidget {
  const _OperationsCard({
    required this.operations,
    required this.unit,
    required this.onCancel,
  });

  final List<FuelOperation> operations;
  final String unit;
  final void Function(FuelOperation)? onCancel;

  @override
  Widget build(BuildContext context) => Card(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Padding(
          padding: EdgeInsets.fromLTRB(16, 16, 16, 4),
          child: Text(
            'Smena amallari',
            style: TextStyle(fontWeight: FontWeight.w600),
          ),
        ),
        if (operations.isEmpty)
          const Padding(
            padding: EdgeInsets.all(16),
            child: Text(
              "Hali amal yo'q",
              style: TextStyle(color: AppColors.textSecondary),
            ),
          ),
        for (final operation in operations)
          _OperationTile(
            operation: operation,
            unit: unit,
            onCancel:
                operation.isCancelled ||
                    operation.kind == FuelOperationKind.adjustment
                ? null
                : onCancel,
          ),
      ],
    ),
  );
}

class _OperationTile extends StatelessWidget {
  const _OperationTile({
    required this.operation,
    required this.unit,
    required this.onCancel,
  });

  final FuelOperation operation;
  final String unit;
  final void Function(FuelOperation)? onCancel;

  @override
  Widget build(BuildContext context) {
    final cancelled = operation.isCancelled;
    final style = cancelled
        ? const TextStyle(
            color: AppColors.textSecondary,
            decoration: TextDecoration.lineThrough,
          )
        : null;
    final sign = operation.isIncoming ? '+' : '−';
    return ListTile(
      title: Text(
        '${operation.kind.label} · ${operation.subject}',
        style: style,
      ),
      subtitle: Text(
        [
          formatDateTime(operation.createdAt),
          if (operation.odometer case final km?) '$km km',
          ?operation.waybillNumber,
          if (cancelled) 'Bekor: ${operation.cancelReason ?? ''}',
        ].join(' · '),
      ),
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            '$sign${formatQuantity(operation.amount, unit)}',
            style: (style ?? const TextStyle()).copyWith(
              fontWeight: FontWeight.w600,
            ),
          ),
          if (onCancel case final cancel?)
            IconButton(
              tooltip: 'Bekor qilish',
              icon: const Icon(Icons.undo),
              onPressed: () => cancel(operation),
            ),
        ],
      ),
    );
  }
}

/// Asks why an operation is cancelled; pops the reason or null.
class _CancelDialog extends StatefulWidget {
  const _CancelDialog({required this.operation});

  final FuelOperation operation;

  @override
  State<_CancelDialog> createState() => _CancelDialogState();
}

class _CancelDialogState extends State<_CancelDialog> {
  final _reason = TextEditingController();

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text('${widget.operation.kind.label} · ${widget.operation.subject}'),
    content: TextField(
      controller: _reason,
      autofocus: true,
      decoration: const InputDecoration(labelText: 'Bekor qilish sababi'),
      onChanged: (_) => setState(() {}),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: const Text('Yopish'),
      ),
      FilledButton(
        style: FilledButton.styleFrom(backgroundColor: AppColors.error),
        onPressed: _reason.text.trim().isEmpty
            ? null
            : () => Navigator.pop(context, _reason.text.trim()),
        child: const Text('Bekor qilish'),
      ),
    ],
  );
}
