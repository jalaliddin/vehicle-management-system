import 'json_parsing.dart';

/// One page of a Laravel `paginate()` response.
class Paginated<T> {
  const Paginated({
    required this.items,
    required this.currentPage,
    required this.lastPage,
    required this.total,
  });

  factory Paginated.fromJson(
    Map<String, dynamic> json,
    T Function(Map<String, dynamic>) fromJson,
  ) => Paginated(
    items: asJsonList(json['data']).map(fromJson).toList(),
    currentPage: json['current_page'] as int? ?? 1,
    lastPage: json['last_page'] as int? ?? 1,
    total: json['total'] as int? ?? 0,
  );

  final List<T> items;
  final int currentPage;
  final int lastPage;
  final int total;

  bool get hasMore => currentPage < lastPage;
}
