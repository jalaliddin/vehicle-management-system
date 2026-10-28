import 'fuel_station.dart';
import 'json_parsing.dart';

enum FuelOperationKind {
  receipt('receipt', 'Qabul'),
  refuel('refuel', 'Quyish'),
  adjustment('adjustment', 'Inventarizatsiya');

  const FuelOperationKind(this.apiValue, this.label);

  final String apiValue;
  final String label;

  static FuelOperationKind fromApi(Object? value) => values.firstWhere(
    (kind) => kind.apiValue == value,
    orElse: () => adjustment,
  );
}

String? _relationText(Object? relation, String key) =>
    asJsonMap(relation)?[key] as String?;

/// One ledger row of a shift: a receipt, a refuel or an inventory act.
class FuelOperation {
  const FuelOperation({
    required this.id,
    required this.kind,
    required this.isIncoming,
    required this.amount,
    this.odometer,
    this.createdAt,
    this.cancelledAt,
    this.cancelReason,
    this.documentNumber,
    this.vehicleNumber,
    this.driverName,
    this.supplierName,
    this.waybillNumber,
  });

  factory FuelOperation.fromJson(Map<String, dynamic> json) => FuelOperation(
    id: json['id'] as int,
    kind: FuelOperationKind.fromApi(json['kind']),
    isIncoming: json['type'] == 'in',
    amount: parseDecimal(json['amount']) ?? 0,
    odometer: json['odometer'] as int?,
    createdAt: parseDateTime(json['created_at']),
    cancelledAt: parseDateTime(json['cancelled_at']),
    cancelReason: json['cancel_reason'] as String?,
    documentNumber: json['document_number'] as String?,
    vehicleNumber: _relationText(json['vehicle'], 'state_number'),
    driverName: _relationText(json['driver'], 'full_name'),
    supplierName: _relationText(json['supplier'], 'name'),
    waybillNumber: _relationText(json['waybill'], 'waybill_number'),
  );

  final int id;
  final FuelOperationKind kind;
  final bool isIncoming;
  final double amount;
  final int? odometer;
  final DateTime? createdAt;
  final DateTime? cancelledAt;
  final String? cancelReason;
  final String? documentNumber;
  final String? vehicleNumber;
  final String? driverName;
  final String? supplierName;
  final String? waybillNumber;

  bool get isCancelled => cancelledAt != null;

  /// Who or what the operation was for.
  String get subject => vehicleNumber ?? supplierName ?? documentNumber ?? '—';
}

/// A fueler's shift with its book and measured balances.
class FuelShift {
  const FuelShift({
    required this.id,
    required this.isOpen,
    required this.openingBalance,
    required this.openingMeasured,
    this.unit = 'L',
    this.stationName,
    this.openedAt,
    this.openedBy,
    this.closedAt,
    this.closedBy,
    this.closingBalance,
    this.closingMeasured,
    this.discrepancy,
    this.discrepancyNote,
  });

  factory FuelShift.fromJson(Map<String, dynamic> json) => FuelShift(
    id: json['id'] as int,
    isOpen: json['status'] == 'open',
    openingBalance: parseDecimal(json['opening_balance']) ?? 0,
    openingMeasured: parseDecimal(json['opening_measured']) ?? 0,
    unit: fuelUnit(asJsonMap(json['station'])?['fuel_type']),
    stationName: _relationText(json['station'], 'name'),
    openedAt: parseDateTime(json['opened_at']),
    openedBy: _relationText(json['opened_by'], 'name'),
    closedAt: parseDateTime(json['closed_at']),
    closedBy: _relationText(json['closed_by'], 'name'),
    closingBalance: parseDecimal(json['closing_balance']),
    closingMeasured: parseDecimal(json['closing_measured']),
    discrepancy: parseDecimal(json['discrepancy']),
    discrepancyNote: json['discrepancy_note'] as String?,
  );

  final int id;
  final bool isOpen;
  final double openingBalance;
  final double openingMeasured;

  /// The station's unit, `L` or `m³`.
  final String unit;
  final String? stationName;
  final DateTime? openedAt;
  final String? openedBy;
  final DateTime? closedAt;
  final String? closedBy;
  final double? closingBalance;
  final double? closingMeasured;

  /// Measured minus book at closing.
  final double? discrepancy;
  final String? discrepancyNote;
}

/// A shift with its operations (newest first) and totals; [shift] is null
/// when a station has no open shift.
class ShiftReport {
  const ShiftReport({
    required this.shift,
    required this.operations,
    required this.receiptsTotal,
    required this.refuelsTotal,
  });

  factory ShiftReport.fromJson(Map<String, dynamic> json) {
    final shift = asJsonMap(json['shift']);
    final totals = asJsonMap(json['totals']);
    return ShiftReport(
      shift: shift == null ? null : FuelShift.fromJson(shift),
      operations: asJsonList(json['operations'])
          .map(FuelOperation.fromJson)
          .toList(),
      receiptsTotal: parseDecimal(totals?['receipts']) ?? 0,
      refuelsTotal: parseDecimal(totals?['refuels']) ?? 0,
    );
  }

  final FuelShift? shift;
  final List<FuelOperation> operations;
  final double receiptsTotal;
  final double refuelsTotal;
}
