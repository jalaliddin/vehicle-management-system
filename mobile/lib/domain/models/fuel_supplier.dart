class FuelSupplier {
  const FuelSupplier({
    required this.id,
    required this.name,
    required this.isActive,
  });

  factory FuelSupplier.fromJson(Map<String, dynamic> json) => FuelSupplier(
    id: json['id'] as int,
    name: json['name'] as String? ?? '',
    isActive: json['is_active'] == true,
  );

  final int id;
  final String name;
  final bool isActive;
}
