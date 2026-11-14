import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_theme.dart';
import '../../auth/application/auth_controller.dart';
import '../application/children_providers.dart';

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final name = ref.watch(meNameProvider);
    final children = ref.watch(childrenProvider);

    final firstName = name.maybeWhen(
      data: (n) {
        final trimmed = n.trim();
        return trimmed.isEmpty ? 'Parent' : trimmed.split(' ').first;
      },
      orElse: () => '…',
    );

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 430),
            child: ListView(
              padding: const EdgeInsets.all(18),
              children: [
                Text('Salut $firstName 👋',
                    style: const TextStyle(
                        fontSize: 23, fontWeight: FontWeight.w800, color: AppColors.navy)),
                const Text('Espace famille',
                    style: TextStyle(fontSize: 12, color: AppColors.muted)),
                const SizedBox(height: 20),
                const Text('Mes enfants',
                    style: TextStyle(
                        fontSize: 15, fontWeight: FontWeight.w800, color: AppColors.navy)),
                const SizedBox(height: 8),
                children.when(
                  loading: () => const Padding(
                    padding: EdgeInsets.all(24),
                    child: Center(child: CircularProgressIndicator()),
                  ),
                  error: (e, _) => Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(e is ApiException ? e.message : 'Une erreur est survenue.',
                          style: const TextStyle(color: AppColors.error)),
                      TextButton(
                        onPressed: () => ref.invalidate(childrenProvider),
                        child: const Text('Réessayer'),
                      ),
                    ],
                  ),
                  data: (list) => list.isEmpty
                      ? _card(const Text(
                      "Aucun enfant pour le moment. La création arrive à l'étape suivante.",
                      style: TextStyle(color: AppColors.muted, fontSize: 12)))
                      : Column(
                    children: [
                      for (final c in list)
                        _card(Row(
                          children: [
                            Container(
                              width: 45,
                              height: 45,
                              decoration: BoxDecoration(
                                color: const Color(0xFFDFF1EF),
                                borderRadius: BorderRadius.circular(15),
                              ),
                              alignment: Alignment.center,
                              child: const Text('🧑🏽‍🎓', style: TextStyle(fontSize: 22)),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(c.firstName,
                                      style: const TextStyle(
                                          fontWeight: FontWeight.w800,
                                          color: AppColors.navy)),
                                  Text(
                                    '${c.age} ans · ${c.levelCode}'
                                        '${c.trackCode != null ? ' · ${c.trackCode}' : ''}',
                                    style: const TextStyle(
                                        fontSize: 12, color: AppColors.muted),
                                  ),
                                ],
                              ),
                            ),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 8, vertical: 4),
                              decoration: BoxDecoration(
                                color: AppColors.tealLight,
                                borderRadius: BorderRadius.circular(30),
                              ),
                              child: Text(
                                c.myRole == 'payer' ? 'Payeur' : 'Suiveur',
                                style: const TextStyle(
                                    fontSize: 10,
                                    fontWeight: FontWeight.w800,
                                    color: AppColors.teal),
                              ),
                            ),
                          ],
                        )),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                OutlinedButton(
                  onPressed: () => ref.read(authControllerProvider.notifier).logout(),
                  child: const Text('Se déconnecter'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _card(Widget child) => Container(
    width: double.infinity,
    margin: const EdgeInsets.only(bottom: 12),
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(17),
      border: Border.all(color: AppColors.border),
    ),
    child: child,
  );
}