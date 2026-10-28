import 'package:flutter/material.dart';

import '../../../../../domain/models/waybill.dart';
import '../../../../core/formatters.dart';
import '../../../../core/theme.dart';

class CompletionResult {
  const CompletionResult({this.actualKm, this.fuelConsumed, this.fuelReturned});

  final double? actualKm;
  final double? fuelConsumed;
  final double? fuelReturned;
}

/// Collects the trip results when a waybill is completed.
Future<CompletionResult?> showCompleteSheet(
  BuildContext context,
  Waybill waybill,
) => showModalBottomSheet<CompletionResult>(
  context: context,
  isScrollControlled: true,
  showDragHandle: true,
  builder: (context) => _CompleteSheet(waybill: waybill),
);

class _CompleteSheet extends StatefulWidget {
  const _CompleteSheet({required this.waybill});

  final Waybill waybill;

  @override
  State<_CompleteSheet> createState() => _CompleteSheetState();
}

class _CompleteSheetState extends State<_CompleteSheet> {
  late final _actualKm = TextEditingController(
    text: _prefill(widget.waybill.actualKm),
  );
  late final _fuelConsumed = TextEditingController(
    text: _prefill(widget.waybill.fuelConsumed),
  );
  late final _fuelReturned = TextEditingController(
    text: _prefill(widget.waybill.fuelReturned),
  );
  final _errors = <TextEditingController, String>{};

  static String _prefill(double? value) =>
      value == null ? '' : formatAmount(value);

  @override
  void dispose() {
    _actualKm.dispose();
    _fuelConsumed.dispose();
    _fuelReturned.dispose();
    super.dispose();
  }

  void _submit() {
    _errors.clear();
    for (final controller in [_actualKm, _fuelConsumed, _fuelReturned]) {
      final text = controller.text.trim();
      final value = parseAmount(text);
      if (text.isNotEmpty && (value == null || value < 0)) {
        _errors[controller] = "Noto'g'ri qiymat";
      }
    }
    if (_actualKm.text.trim().isEmpty) _errors[_actualKm] = 'Masofani kiriting';
    if (_errors.isNotEmpty) {
      setState(() {});
      return;
    }
    Navigator.pop(
      context,
      CompletionResult(
        actualKm: parseAmount(_actualKm.text),
        fuelConsumed: parseAmount(_fuelConsumed.text),
        fuelReturned: parseAmount(_fuelReturned.text),
      ),
    );
  }

  Widget _numberField(
    TextEditingController controller,
    String label,
    String unit,
  ) => TextField(
    controller: controller,
    keyboardType: const TextInputType.numberWithOptions(decimal: true),
    decoration: InputDecoration(
      labelText: label,
      suffixText: unit,
      errorText: _errors[controller],
    ),
  );

  @override
  Widget build(BuildContext context) {
    final waybill = widget.waybill;
    return Padding(
      padding: EdgeInsets.fromLTRB(
        20,
        0,
        20,
        20 + MediaQuery.viewInsetsOf(context).bottom,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Reysni yakunlash',
            style: Theme.of(context).textTheme.titleLarge,
          ),
          const SizedBox(height: 4),
          Text(
            'Rejada: ${formatAmount(waybill.plannedKm, 'km')} · '
            'Berilgan: ${formatAmount(waybill.fuelIssued, 'L')}',
            style: const TextStyle(color: AppColors.textSecondary),
          ),
          const SizedBox(height: 16),
          _numberField(_actualKm, 'Bosib o\'tilgan masofa', 'km'),
          const SizedBox(height: 12),
          _numberField(_fuelConsumed, "Sarflangan yoqilg'i", 'L'),
          const SizedBox(height: 12),
          _numberField(_fuelReturned, "Qaytarilgan yoqilg'i", 'L'),
          const SizedBox(height: 20),
          FilledButton(onPressed: _submit, child: const Text('Yakunlash')),
        ],
      ),
    );
  }
}
