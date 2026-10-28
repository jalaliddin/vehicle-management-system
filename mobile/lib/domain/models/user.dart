import 'json_parsing.dart';
import 'organization.dart';
import 'permission.dart';

class User {
  const User({
    required this.id,
    required this.name,
    required this.email,
    required this.roleName,
    this.roleSlug,
    this.permissions = const {},
    this.organizationId,
    this.organization,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    final organization = asJsonMap(json['organization']);
    final roleSlug = json['role'] as String?;
    return User(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      roleSlug: roleSlug,
      roleName: json['role_name'] as String? ?? roleSlug ?? '',
      permissions: Permission.setFromApi(json['permissions']),
      organizationId: json['organization_id'] as int?,
      organization: organization == null
          ? null
          : Organization.fromJson(organization),
    );
  }

  final int id;
  final String name;
  final String email;

  /// The role's stable identifier, e.g. "dispatcher"; custom roles get one too.
  final String? roleSlug;

  /// The role's display name, e.g. "Dispetcher".
  final String roleName;
  final Set<Permission> permissions;
  final int? organizationId;
  final Organization? organization;

  bool can(Permission permission) => permissions.contains(permission);

  bool canAny(Iterable<Permission> permissions) => permissions.any(can);

  /// Sees and acts on every organization's records, not just their own.
  bool get canAccessAllOrganizations => can(Permission.allOrganizations);

  String get initials => name
      .trim()
      .split(RegExp(r'\s+'))
      .where((part) => part.isNotEmpty)
      .take(2)
      .map((part) => part[0].toUpperCase())
      .join();

  String get firstName => name.trim().split(RegExp(r'\s+')).first;
}
