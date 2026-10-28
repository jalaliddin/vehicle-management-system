import 'package:flutter/material.dart';

import '../../../../../domain/models/waybill.dart';
import '../../../../core/formatters.dart';
import '../../../../core/status_chip.dart';
import '../../../../core/theme.dart';

class WaybillCard extends StatelessWidget {
  const WaybillCard({
    super.key,
    required this.waybill,
    required this.onTap,
    this.footer,
  });

  final Waybill waybill;
  final VoidCallback onTap;

  /// Extra content under the details, such as quick action buttons.
  final Widget? footer;

  @override
  Widget build(BuildContext context) {
    final vehicle = waybill.vehicle;
    final organization = waybill.organization?.displayName;

    return Card(
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      waybill.number,
                      style: const TextStyle(
                        fontWeight: FontWeight.w700,
                        color: AppColors.primary,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  StatusChip(status: waybill.status),
                ],
              ),
              const SizedBox(height: 10),
              _DetailLine(
                icon: Icons.directions_car_outlined,
                text: vehicle?.title ?? '—',
              ),
              _DetailLine(
                icon: Icons.person_outline,
                text: waybill.driver?.fullName ?? '—',
              ),
              _DetailLine(
                icon: Icons.place_outlined,
                text: waybill.destination.isEmpty ? '—' : waybill.destination,
              ),
              const SizedBox(height: 6),
              Text(
                [?organization, formatDate(waybill.createdDate)].join(' · '),
                style: const TextStyle(
                  color: AppColors.textSecondary,
                  fontSize: 12,
                ),
              ),
              if (footer case final footer?) ...[
                const SizedBox(height: 12),
                footer,
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _DetailLine extends StatelessWidget {
  const _DetailLine({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 4),
    child: Row(
      children: [
        Icon(icon, size: 16, color: AppColors.textSecondary),
        const SizedBox(width: 8),
        Expanded(
          child: Text(text, maxLines: 1, overflow: TextOverflow.ellipsis),
        ),
      ],
    ),
  );
}
