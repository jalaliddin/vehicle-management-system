import 'package:flutter/material.dart';

import '../../domain/models/waybill.dart';
import 'theme.dart';

extension WaybillStatusStyle on WaybillStatus {
  Color get color => switch (this) {
    WaybillStatus.draft => const Color(0xFF475569),
    WaybillStatus.mechanicCheck ||
    WaybillStatus.doctorCheck => AppColors.warning,
    WaybillStatus.mechanicOk ||
    WaybillStatus.doctorOk => const Color(0xFF1D4ED8),
    WaybillStatus.hqReview => AppColors.purple,
    WaybillStatus.approved => const Color(0xFF047857),
    WaybillStatus.inProgress => AppColors.primary,
    WaybillStatus.completed => AppColors.success,
    WaybillStatus.cancelled => AppColors.error,
  };

  IconData get icon => switch (this) {
    WaybillStatus.draft => Icons.description_outlined,
    WaybillStatus.mechanicCheck => Icons.build_outlined,
    WaybillStatus.mechanicOk => Icons.build_circle_outlined,
    WaybillStatus.doctorCheck => Icons.medical_services_outlined,
    WaybillStatus.doctorOk => Icons.health_and_safety_outlined,
    WaybillStatus.hqReview => Icons.domain_verification_outlined,
    WaybillStatus.approved => Icons.verified_outlined,
    WaybillStatus.inProgress => Icons.local_shipping_outlined,
    WaybillStatus.completed => Icons.flag_outlined,
    WaybillStatus.cancelled => Icons.cancel_outlined,
  };
}

class StatusChip extends StatelessWidget {
  const StatusChip({super.key, required this.status});

  final WaybillStatus status;

  @override
  Widget build(BuildContext context) {
    final color = status.color;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(status.icon, size: 14, color: color),
          const SizedBox(width: 4),
          Text(
            status.label,
            style: TextStyle(
              color: color,
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}
