import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../../../domain/models/fuel_supplier.dart';
import '../../../core/feedback.dart';
import '../../../core/formatters.dart';
import '../../../core/search_picker.dart';
import '../../../core/theme.dart';
import '../view_models/receipt_view_model.dart';

/// Records fuel received from a supplier; pops `true` once saved.
class ReceiptScreen extends StatefulWidget {
  const ReceiptScreen({super.key});

  @override
  State<ReceiptScreen> createState() => _ReceiptScreenState();
}

class _ReceiptScreenState extends State<ReceiptScreen> {
  final _documentNumber = TextEditingController();
  final _documentAmount = TextEditingController();
  final _amount = TextEditingController();
  final _price = TextEditingController();

  @override
  void initState() {
    super.initState();
    // The mismatch hint follows both amounts.
    _documentAmount.addListener(() => setState(() {}));
    _amount.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    for (final controller in [
      _documentNumber,
      _documentAmount,
      _amount,
      _price,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _pickDate(ReceiptViewModel viewModel) async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: viewModel.documentDate,
      firstDate: DateTime(now.year - 1),
      lastDate: now,
    );
    if (picked != null) viewModel.setDocumentDate(picked);
  }

  Future<void> _save(ReceiptViewModel viewModel) async {
    final saved = await viewModel.save(
      documentNumber: _documentNumber.text,
      documentAmount: _documentAmount.text,
      amount: _amount.text,
      pricePerLiter: _price.text,
    );
    if (saved && mounted) {
      showMessage(context, 'Yoqilg\'i qabul qilindi');
      context.pop(true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final viewModel = context.watch<ReceiptViewModel>();
    final unit = viewModel.station.unit;
    final errors = viewModel.fieldErrors;
    final hint = viewModel.mismatchHint(_documentAmount.text, _amount.text);
    return Scaffold(
      appBar: AppBar(title: const Text("Yoqilg'i qabul qilish")),
      body: switch (viewModel) {
        ReceiptViewModel(loading: true) => const Center(
          child: CircularProgressIndicator(),
        ),
        ReceiptViewModel(:final loadError?) => ErrorRetry(
          message: loadError,
          onRetry: viewModel.load,
        ),
        _ => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (viewModel.message case final message?) ...[
              InlineError(message: message),
              const SizedBox(height: 12),
            ],
            SearchPickerField<FuelSupplier>(
              label: 'Yetkazib beruvchi',
              icon: Icons.business,
              items: viewModel.suppliers,
              selected: viewModel.supplier,
              itemLabel: (supplier) => supplier.name,
              onSelected: viewModel.selectSupplier,
              errorText: errors['fuel_supplier_id'],
              emptyText: "Faol yetkazib beruvchi yo'q",
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _documentNumber,
              decoration: InputDecoration(
                labelText: "Yo'l xati raqami",
                errorText: errors['document_number'],
              ),
            ),
            const SizedBox(height: 12),
            InkWell(
              onTap: () => _pickDate(viewModel),
              child: InputDecorator(
                decoration: InputDecoration(
                  labelText: 'Sanasi',
                  prefixIcon: const Icon(Icons.event),
                  errorText: errors['document_date'],
                ),
                child: Text(formatDate(viewModel.documentDate)),
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _documentAmount,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              decoration: InputDecoration(
                labelText: 'Hujjatda ($unit)',
                errorText: errors['document_amount'],
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _amount,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              decoration: InputDecoration(
                labelText: 'Qabul qilindi ($unit)',
                errorText: errors['amount'],
                helperText: hint,
                helperStyle: const TextStyle(color: AppColors.warning),
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _price,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              decoration: InputDecoration(
                labelText: "Narxi (so'm, ixtiyoriy)",
                errorText: errors['price_per_liter'],
              ),
            ),
            const SizedBox(height: 20),
            FilledButton(
              style: FilledButton.styleFrom(
                minimumSize: const Size.fromHeight(56),
              ),
              onPressed: viewModel.saving ? null : () => _save(viewModel),
              child: viewModel.saving
                  ? const SizedBox.square(
                      dimension: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text('Saqlash'),
            ),
          ],
        ),
      },
    );
  }
}
