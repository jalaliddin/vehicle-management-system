import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../../../domain/models/fuel_station.dart';
import '../../../../domain/models/fuel_vehicle.dart';
import '../../../../routing/routes.dart';
import '../../../core/feedback.dart';
import '../../../core/formatters.dart';
import '../../../core/theme.dart';
import '../view_models/refuel_view_model.dart';

/// Records a refuel; pops `true` once saved.
class RefuelScreen extends StatefulWidget {
  const RefuelScreen({super.key});

  @override
  State<RefuelScreen> createState() => _RefuelScreenState();
}

class _RefuelScreenState extends State<RefuelScreen> {
  final _search = TextEditingController();
  final _odometer = TextEditingController();
  final _amount = TextEditingController();
  Timer? _debounce;

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    _odometer.dispose();
    _amount.dispose();
    super.dispose();
  }

  void _onSearchChanged(String text) {
    _debounce?.cancel();
    _debounce = Timer(
      const Duration(milliseconds: 300),
      () => context.read<RefuelViewModel>().search(text),
    );
  }

  Future<void> _scan() async {
    final viewModel = context.read<RefuelViewModel>();
    final waybillId = await context.push<int>(Routes.fuelScan);
    if (waybillId != null) await viewModel.pickFromWaybill(waybillId);
  }

  Future<void> _save(RefuelViewModel viewModel) async {
    final saved = await viewModel.save(
      odometer: _odometer.text,
      amount: _amount.text,
    );
    if (saved && mounted) {
      showMessage(context, "Yoqilg'i quyildi");
      context.pop(true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final viewModel = context.watch<RefuelViewModel>();
    final station = viewModel.station;
    final vehicle = viewModel.vehicle;
    return Scaffold(
      appBar: AppBar(title: const Text("Yoqilg'i quyish")),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (viewModel.message case final message?) ...[
            InlineError(message: message),
            const SizedBox(height: 12),
          ],
          if (vehicle == null) ...[
            TextField(
              controller: _search,
              textCapitalization: TextCapitalization.characters,
              decoration: InputDecoration(
                labelText: 'Davlat raqami',
                prefixIcon: const Icon(Icons.directions_car),
                suffixIcon: IconButton(
                  tooltip: 'QR kodni skanerlash',
                  icon: const Icon(Icons.qr_code_scanner),
                  onPressed: _scan,
                ),
              ),
              onChanged: _onSearchChanged,
            ),
            const SizedBox(height: 8),
            if (viewModel.searching) const LinearProgressIndicator(),
            for (final result in viewModel.results)
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(result.stateNumber),
                subtitle: Text(
                  '${result.model} · '
                  '${result.driverName ?? 'haydovchi biriktirilmagan'}',
                ),
                trailing: const Icon(Icons.chevron_right),
                onTap: () => viewModel.select(result),
              ),
            if (!viewModel.searching && viewModel.results.isEmpty)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 16),
                child: Text(
                  'Avtomobil topilmadi',
                  style: TextStyle(color: AppColors.textSecondary),
                ),
              ),
          ] else ...[
            _VehicleCard(
              vehicle: vehicle,
              station: station,
              warning: viewModel.fuelTypeWarning,
              onChange: viewModel.saving ? null : viewModel.clearVehicle,
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _odometer,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(
                labelText: 'Spidometr (km)',
                prefixIcon: const Icon(Icons.speed),
                errorText: viewModel.fieldErrors['odometer'],
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _amount,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              decoration: InputDecoration(
                labelText: 'Miqdor (${station.unit})',
                prefixIcon: const Icon(Icons.local_gas_station),
                errorText: viewModel.fieldErrors['amount'],
              ),
            ),
            const SizedBox(height: 20),
            FilledButton(
              style: FilledButton.styleFrom(
                minimumSize: const Size.fromHeight(56),
              ),
              onPressed: viewModel.canSave ? () => _save(viewModel) : null,
              child: viewModel.saving
                  ? const SizedBox.square(
                      dimension: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text('Saqlash'),
            ),
          ],
        ],
      ),
    );
  }
}

class _VehicleCard extends StatelessWidget {
  const _VehicleCard({
    required this.vehicle,
    required this.station,
    required this.warning,
    required this.onChange,
  });

  final FuelVehicle vehicle;
  final FuelStation station;
  final String? warning;
  final VoidCallback? onChange;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  '${vehicle.stateNumber} — ${vehicle.model}',
                  style: const TextStyle(
                    fontWeight: FontWeight.w700,
                    fontSize: 16,
                  ),
                ),
              ),
              TextButton(
                onPressed: onChange,
                child: const Text("O'zgartirish"),
              ),
            ],
          ),
          Text('Haydovchi: ${vehicle.driverName ?? '—'}'),
          Text("Yo'l varaqasi: ${vehicle.waybillNumber ?? "yo'q"}"),
          Text(
            'Oxirgi spidometr: '
            '${vehicle.odometer == null ? '—' : '${vehicle.odometer} km'} · '
            'Bak: ${formatQuantity(vehicle.tankCapacity, station.unit)}',
          ),
          if (warning case final warning?) ...[
            const SizedBox(height: 8),
            InlineError(message: warning),
          ] else if (vehicle.waybillNumber == null) ...[
            const SizedBox(height: 8),
            const Text(
              "Ochiq yo'l varaqasi yo'q",
              style: TextStyle(
                color: AppColors.warning,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ],
      ),
    ),
  );
}
