import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../../config.dart';
import '../../../../data/repositories/auth_repository.dart';
import '../../../core/feedback.dart';
import '../../../core/theme.dart';

/// Too simple for a view model: it only reads the user and signs out.
class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  Future<void> _logout(BuildContext context) async {
    final auth = context.read<AuthRepository>();
    final confirmed = await confirmAction(
      context,
      title: 'Chiqish',
      message: 'Hisobdan chiqmoqchimisiz?',
      confirmLabel: 'Chiqish',
    );
    if (confirmed) await auth.logout();
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthRepository>().currentUser;
    if (user == null) return const Scaffold();

    return Scaffold(
      appBar: AppBar(title: const Text('Profil')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                children: [
                  CircleAvatar(
                    radius: 36,
                    backgroundColor: AppColors.primary,
                    child: Text(
                      user.initials,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 24,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    user.name,
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.titleLarge
                        ?.copyWith(fontWeight: FontWeight.w600),
                  ),
                  Text(
                    user.email,
                    style: const TextStyle(color: AppColors.textSecondary),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          Card(
            child: Column(
              children: [
                ListTile(
                  leading: const Icon(Icons.verified_user_outlined),
                  title: const Text('Rol'),
                  subtitle: Text(user.roleName),
                ),
                const Divider(indent: 16, endIndent: 16),
                ListTile(
                  leading: const Icon(Icons.domain_outlined),
                  title: const Text('Tashkilot'),
                  subtitle: Text(user.organization?.name ?? '—'),
                ),
                const Divider(indent: 16, endIndent: 16),
                const ListTile(
                  leading: Icon(Icons.dns_outlined),
                  title: Text('Server'),
                  subtitle: Text(AppConfig.apiBaseUrl),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          OutlinedButton.icon(
            style: OutlinedButton.styleFrom(
              foregroundColor: AppColors.error,
              side: const BorderSide(color: AppColors.error),
            ),
            onPressed: () => _logout(context),
            icon: const Icon(Icons.logout),
            label: const Text('Chiqish'),
          ),
        ],
      ),
    );
  }
}
