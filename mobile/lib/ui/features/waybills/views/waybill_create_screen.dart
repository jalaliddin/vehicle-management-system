import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../../../domain/models/driver.dart';
import '../../../../domain/models/organization.dart';
import '../../../../domain/models/vehicle.dart';
import '../../../../routing/routes.dart';
import '../../../core/feedback.dart';
import '../../../core/formatters.dart';
import '../../../core/search_picker.dart';
import '../../../core/theme.dart';
import '../view_models/waybill_create_view_model.dart';

class WaybillCreateScreen extends StatefulWidget {
  const WaybillCreateScreen({super.key});

  @override
  State<WaybillCreateScreen> createState() => _WaybillCreateScreenState();
}

class _WaybillCreateScreenState extends State<WaybillCreateScreen> {
  final _destination = TextEditingController();
  final _destinationOrganization = TextEditingController();
  final _tabelNumber = TextEditingController();
  final _tripCount = TextEditingController(text: '1');
  final _plannedKm = TextEditingController();
  final _fuelIssued = TextEditingController();
  final _notes = TextEditingController();

  @override
  void dispose() {
    for (final controller in [
      _destination,
      _destinationOrganization,
      _tabelNumber,
      _tripCount,
      _plannedKm,
      _fuelIssued,
      _notes,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _pickDate(
    DateTime initial,
    ValueChanged<DateTime> onPicked, {
    DateTime? first,
  }) async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: first ?? DateTime(now.year - 1),
      lastDate: DateTime(now.year + 2),
    );
    if (picked != null) onPicked(picked);
  }

  Future<void> _save({required bool submit}) async {
    FocusScope.of(context).unfocus();
    final result = await context.read<WaybillCreateViewModel>().save(
      WaybillFormText(
        destination: _destination.text,
        destinationOrganization: _destinationOrganization.text,
        tabelNumber: _tabelNumber.text,
        tripCount: _tripCount.text,
        plannedKm: _plannedKm.text,
        fuelIssued: _fuelIssued.text,
        notes: _notes.text,
      ),
      submitToMechanic: submit,
    );
    if (result == null || !mounted) return;

    if (result.submitError case final error?) {
      showMessage(
        context,
        'Saqlandi, lekin yuborilmadi: $error',
        isError: true,
      );
    } else {
      showMessage(
        context,
        submit ? 'Saqlandi va mexanikka yuborildi' : 'Saqlandi',
      );
    }
    context.pushReplacement(Routes.waybill(result.waybill.id));
  }

  @override
  Widget build(BuildContext context) {
    final viewModel = context.watch<WaybillCreateViewModel>();
    final errors = viewModel.fieldErrors;

    if (viewModel.isLoading) {
      return Scaffold(
        appBar: AppBar(title: const Text("Yangi yo'l varaqasi")),
        body: const Center(child: CircularProgressIndicator()),
      );
    }
    if (viewModel.loadError case final error?) {
      return Scaffold(
        appBar: AppBar(title: const Text("Yangi yo'l varaqasi")),
        body: ErrorRetry(message: error, onRetry: viewModel.load),
      );
    }

    final estimate = viewModel.estimateFuel(_plannedKm.text);

    return Scaffold(
      appBar: AppBar(title: const Text("Yangi yo'l varaqasi")),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (viewModel.formError case final error?) ...[
            InlineError(message: error),
            const SizedBox(height: 16),
          ],
          const _GroupTitle('Transport'),
          if (viewModel.canChooseOrganization) ...[
            SearchPickerField<Organization>(
              label: 'Tashkilot',
              icon: Icons.domain_outlined,
              items: viewModel.organizations,
              selected: viewModel.organization,
              itemLabel: (o) => o.name,
              onSelected: (o) => viewModel.selectOrganization(o.id),
              errorText: errors['organization_id'],
            ),
            const SizedBox(height: 12),
          ] else if (errors['organization_id'] case final error?) ...[
            InlineError(message: error),
            const SizedBox(height: 12),
          ],
          SearchPickerField<Vehicle>(
            label: 'Avtomobil',
            icon: Icons.directions_car_outlined,
            items: viewModel.vehicles,
            selected: viewModel.vehicle,
            itemLabel: (v) => v.title,
            itemSubtitle: (v) => v.vehicleType?.label,
            onSelected: (v) => viewModel.selectVehicle(v.id),
            errorText: errors['vehicle_id'],
            emptyText: "Faol avtomobil yo'q",
          ),
          const SizedBox(height: 12),
          SearchPickerField<Driver>(
            label: 'Haydovchi',
            icon: Icons.person_outline,
            items: viewModel.drivers,
            selected: viewModel.driver,
            itemLabel: (d) => d.fullName,
            itemSubtitle: (d) => d.licenseCategory == null
                ? null
                : 'Toifa: ${d.licenseCategory}',
            onSelected: (d) => viewModel.selectDriver(d.id),
            errorText: errors['driver_id'],
            emptyText: "Faol haydovchi yo'q",
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<VehicleType>(
            // Re-created when picking a vehicle changes the type.
            key: ValueKey(viewModel.vehicleType),
            initialValue: viewModel.vehicleType,
            // Dropdowns default to titleMedium; match the text fields.
            style: Theme.of(context).textTheme.bodyLarge,
            decoration: const InputDecoration(
              labelText: 'Transport turi',
              prefixIcon: Icon(Icons.category_outlined),
            ),
            items: [
              for (final type in VehicleType.values)
                DropdownMenuItem(value: type, child: Text(type.label)),
            ],
            onChanged: (type) {
              if (type != null) viewModel.selectVehicleType(type);
            },
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _tabelNumber,
            decoration: InputDecoration(
              labelText: 'Tabel raqami (ixtiyoriy)',
              prefixIcon: const Icon(Icons.badge_outlined),
              errorText: errors['tabel_number'],
            ),
          ),
          const SizedBox(height: 24),
          const _GroupTitle('Muddat'),
          Row(
            children: [
              Expanded(
                child: _DateField(
                  label: 'Sana',
                  value: viewModel.createdDate,
                  errorText: errors['created_date'],
                  onTap: () => _pickDate(
                    viewModel.createdDate,
                    viewModel.setCreatedDate,
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _DateField(
                  label: 'Amal qiladi',
                  value: viewModel.validUntil,
                  errorText: errors['valid_until'],
                  onTap: () => _pickDate(
                    viewModel.validUntil,
                    viewModel.setValidUntil,
                    first: viewModel.createdDate,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 24),
          const _GroupTitle('Reys'),
          TextField(
            controller: _destination,
            textCapitalization: TextCapitalization.sentences,
            decoration: InputDecoration(
              labelText: "Yo'nalish",
              prefixIcon: const Icon(Icons.place_outlined),
              errorText: errors['destination'],
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _destinationOrganization,
            decoration: InputDecoration(
              labelText: 'Qabul qiluvchi tashkilot (ixtiyoriy)',
              prefixIcon: const Icon(Icons.business_outlined),
              errorText: errors['destination_organization'],
            ),
          ),
          const SizedBox(height: 12),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: TextField(
                  controller: _tripCount,
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(
                    labelText: 'Reyslar soni',
                    errorText: errors['trip_count'],
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: TextField(
                  controller: _plannedKm,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                  onChanged: (_) => setState(() {}),
                  decoration: InputDecoration(
                    labelText: 'Masofa',
                    suffixText: 'km',
                    errorText: errors['planned_km'],
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('Konditsioner yoqiladi'),
            value: viewModel.hasAc,
            onChanged: viewModel.setHasAc,
          ),
          TextField(
            controller: _fuelIssued,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: InputDecoration(
              labelText: "Berilgan yoqilg'i (ixtiyoriy)",
              prefixIcon: const Icon(Icons.local_gas_station_outlined),
              suffixText: 'L',
              errorText: errors['fuel_issued'],
              helperText: estimate == null
                  ? null
                  : "Me'yor bo'yicha: ${formatAmount(estimate, 'L')}",
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _notes,
            maxLines: 3,
            textCapitalization: TextCapitalization.sentences,
            decoration: InputDecoration(
              labelText: 'Izoh (ixtiyoriy)',
              alignLabelWithHint: true,
              errorText: errors['notes'],
            ),
          ),
          const SizedBox(height: 24),
        ],
      ),
      bottomNavigationBar: Material(
        color: Colors.white,
        elevation: 8,
        child: SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: viewModel.isSaving
                        ? null
                        : () => _save(submit: false),
                    child: const Text('Saqlash'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  flex: 2,
                  child: FilledButton(
                    onPressed: viewModel.isSaving
                        ? null
                        : () => _save(submit: true),
                    child: viewModel.isSaving
                        ? const SizedBox.square(
                            dimension: 20,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Text('Saqlash va yuborish'),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _GroupTitle extends StatelessWidget {
  const _GroupTitle(this.text);

  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: Text(
      text,
      style: Theme.of(context).textTheme.titleMedium?.copyWith(
        fontWeight: FontWeight.w600,
        color: AppColors.textSecondary,
      ),
    ),
  );
}

class _DateField extends StatelessWidget {
  const _DateField({
    required this.label,
    required this.value,
    required this.onTap,
    this.errorText,
  });

  final String label;
  final DateTime value;
  final VoidCallback onTap;
  final String? errorText;

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    borderRadius: BorderRadius.circular(12),
    child: InputDecorator(
      decoration: InputDecoration(
        labelText: label,
        errorText: errorText,
        prefixIcon: const Icon(Icons.event_outlined),
      ),
      child: Text(formatDate(value)),
    ),
  );
}
