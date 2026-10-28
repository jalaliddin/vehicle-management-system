import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../../../domain/models/dashboard_stats.dart';
import '../../../../domain/models/waybill.dart';
import '../../../../domain/models/waybill_action.dart';
import '../../../../routing/routes.dart';
import '../../../core/feedback.dart';
import '../../../core/formatters.dart';
import '../../../core/theme.dart';
import '../../waybills/views/widgets/inspection_sheet.dart';
import '../../waybills/views/widgets/waybill_card.dart';
import '../view_models/tasks_view_model.dart';

class TasksScreen extends StatefulWidget {
  const TasksScreen({super.key});

  @override
  State<TasksScreen> createState() => _TasksScreenState();
}

class _TasksScreenState extends State<TasksScreen> {
  late final AppLifecycleListener _lifecycle;

  @override
  void initState() {
    super.initState();
    // Without push notifications, catch up whenever the app is reopened.
    _lifecycle = AppLifecycleListener(
      onResume: () => context.read<TasksViewModel>().load(),
    );
  }

  @override
  void dispose() {
    _lifecycle.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final viewModel = context.watch<TasksViewModel>();
    return Scaffold(
      appBar: AppBar(title: Text(viewModel.title)),
      body: RefreshIndicator(
        onRefresh: viewModel.load,
        child: _buildBody(viewModel),
      ),
    );
  }

  Widget _buildBody(TasksViewModel viewModel) {
    if (!viewModel.hasLoaded) {
      if (viewModel.error case final error?) {
        return ErrorRetry(message: error, onRetry: viewModel.load);
      }
      return const Center(child: CircularProgressIndicator());
    }

    final user = viewModel.user;
    final stats = viewModel.stats;
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      children: [
        if (viewModel.error case final error?) ...[
          InlineError(message: error),
          const SizedBox(height: 16),
        ],
        if (user != null) ...[
          Text(
            'Salom, ${user.firstName}!',
            style: Theme.of(context).textTheme.titleLarge
                ?.copyWith(fontWeight: FontWeight.w600),
          ),
          Text(
            [user.roleName, ?user.organization?.displayName].join(' · '),
            style: const TextStyle(color: AppColors.textSecondary),
          ),
          const SizedBox(height: 16),
        ],
        if (stats != null) ...[
          _StatsGrid(stats: stats),
          if (stats.fuelAlerts.isNotEmpty) ...[
            const SizedBox(height: 12),
            _FuelAlerts(stations: stats.fuelAlerts),
          ],
          const SizedBox(height: 8),
        ],
        for (final section in viewModel.sections) ...[
          const SizedBox(height: 16),
          _SectionHeader(title: section.title, count: section.waybills.length),
          const SizedBox(height: 8),
          if (section.waybills.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: Text(
                section.emptyText,
                style: const TextStyle(color: AppColors.textSecondary),
              ),
            )
          else
            for (final waybill in section.waybills)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: _TaskCard(waybill: waybill),
              ),
        ],
      ],
    );
  }
}

/// A queue card with pass/reject buttons for mechanics and doctors.
class _TaskCard extends StatelessWidget {
  const _TaskCard({required this.waybill});

  final Waybill waybill;

  Future<void> _inspect(BuildContext context, {required bool passed}) async {
    final viewModel = context.read<TasksViewModel>();
    final isMechanic = waybill.status == WaybillStatus.mechanicCheck;
    final result = await showInspectionSheet(
      context,
      title: isMechanic ? 'Mexanik xulosasi' : 'Shifokor xulosasi',
      passed: passed,
    );
    if (result == null || !context.mounted) return;
    final error = await viewModel.inspect(
      waybill,
      passed: result.passed,
      notes: result.notes,
    );
    if (!context.mounted) return;
    showMessage(
      context,
      error ??
          '${waybill.number}: ${result.passed ? 'tasdiqlandi' : 'rad etildi'}',
      isError: error != null,
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = context.read<TasksViewModel>().user;
    final actions = user == null
        ? const <WaybillAction>{}
        : WaybillAction.availableFor(user, waybill);
    final canInspect =
        actions.contains(WaybillAction.mechanicCheck) ||
        actions.contains(WaybillAction.doctorCheck);

    return WaybillCard(
      waybill: waybill,
      onTap: () => context.push(Routes.waybill(waybill.id)),
      footer: canInspect
          ? Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppColors.error,
                      side: const BorderSide(color: AppColors.error),
                    ),
                    onPressed: () => _inspect(context, passed: false),
                    child: const Text('Rad etish'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: FilledButton(
                    style: FilledButton.styleFrom(
                      backgroundColor: AppColors.success,
                    ),
                    onPressed: () => _inspect(context, passed: true),
                    child: const Text('Tasdiqlash'),
                  ),
                ),
              ],
            )
          : null,
    );
  }
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({required this.title, required this.count});

  final String title;
  final int count;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Expanded(
        child: Text(
          title,
          style: Theme.of(context).textTheme.titleMedium
              ?.copyWith(fontWeight: FontWeight.w600),
        ),
      ),
      Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
        decoration: BoxDecoration(
          color: AppColors.primary.withValues(alpha: 0.1),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Text(
          '$count',
          style: const TextStyle(
            color: AppColors.primary,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
    ],
  );
}

class _StatsGrid extends StatelessWidget {
  const _StatsGrid({required this.stats});

  final DashboardStats stats;

  @override
  Widget build(BuildContext context) {
    final tiles = [
      _StatTile(
        icon: Icons.directions_car_outlined,
        label: 'Faol avtomobillar',
        value: '${stats.activeVehicles}/${stats.totalVehicles}',
        color: AppColors.success,
      ),
      _StatTile(
        icon: Icons.car_crash_outlined,
        label: "Nosoz / ta'mirda",
        value: '${stats.brokenVehicles} / ${stats.maintenanceVehicles}',
        color: AppColors.error,
      ),
      _StatTile(
        icon: Icons.pending_actions_outlined,
        label: 'Tekshiruvda',
        value: '${stats.pendingWaybills}',
        color: AppColors.warning,
      ),
      _StatTile(
        icon: Icons.local_shipping_outlined,
        label: 'Faol varaqalar',
        value: '${stats.activeWaybills}',
        color: AppColors.primary,
      ),
      _StatTile(
        icon: Icons.today_outlined,
        label: 'Bugungi varaqalar',
        value: '${stats.todayWaybills}',
        color: AppColors.info,
      ),
      _StatTile(
        icon: Icons.badge_outlined,
        label: 'Faol haydovchilar',
        value: '${stats.activeDrivers}/${stats.totalDrivers}',
        color: AppColors.purple,
      ),
    ];

    return LayoutBuilder(
      builder: (context, constraints) {
        const spacing = 12.0;
        final width = (constraints.maxWidth - spacing) / 2;
        return Wrap(
          spacing: spacing,
          runSpacing: spacing,
          children: [
            for (final tile in tiles) SizedBox(width: width, child: tile),
          ],
        );
      },
    );
  }
}

class _StatTile extends StatelessWidget {
  const _StatTile({
    required this.icon,
    required this.label,
    required this.value,
    required this.color,
  });

  final IconData icon;
  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color, size: 22),
          const SizedBox(height: 8),
          Text(
            value,
            style: Theme.of(context).textTheme.titleLarge
                ?.copyWith(fontWeight: FontWeight.w700),
          ),
          Text(
            label,
            style: const TextStyle(
              color: AppColors.textSecondary,
              fontSize: 13,
            ),
          ),
        ],
      ),
    ),
  );
}

class _FuelAlerts extends StatelessWidget {
  const _FuelAlerts({required this.stations});

  final List<FuelStation> stations;

  @override
  Widget build(BuildContext context) => Card(
    color: AppColors.error.withValues(alpha: 0.05),
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.local_gas_station_outlined, color: AppColors.error),
              SizedBox(width: 8),
              Text(
                "Yoqilg'i kam qolgan",
                style: TextStyle(
                  fontWeight: FontWeight.w600,
                  color: AppColors.error,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          for (final station in stations)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 3),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      [
                        station.name,
                        ?station.organization?.displayName,
                      ].join(' · '),
                    ),
                  ),
                  Text(
                    '${formatAmount(station.currentBalance, 'L')} / '
                    '${formatAmount(station.minThreshold)}',
                    style: const TextStyle(fontWeight: FontWeight.w600),
                  ),
                ],
              ),
            ),
        ],
      ),
    ),
  );
}
