class Driver {
  const Driver({
    required this.id,
    required this.fullName,
    this.organizationId,
    this.licenseCategory,
  });

  factory Driver.fromJson(Map<String, dynamic> json) => Driver(
    id: json['id'] as int,
    fullName: json['full_name'] as String? ?? '',
    organizationId: json['organization_id'] as int?,
    licenseCategory: json['license_category'] as String?,
  );

  final int id;
  final String fullName;
  final int? organizationId;
  final String? licenseCategory;
}
