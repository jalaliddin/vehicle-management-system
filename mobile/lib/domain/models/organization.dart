class Organization {
  const Organization({required this.id, required this.name, this.shortName});

  factory Organization.fromJson(Map<String, dynamic> json) => Organization(
    id: json['id'] as int,
    name: json['name'] as String? ?? '',
    shortName: json['short_name'] as String?,
  );

  final int id;
  final String name;
  final String? shortName;

  String get displayName =>
      (shortName?.isNotEmpty ?? false) ? shortName! : name;
}
