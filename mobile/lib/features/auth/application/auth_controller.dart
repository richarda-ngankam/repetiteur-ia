import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/providers.dart';
import '../data/auth_repository.dart';

class AuthState {
  const AuthState({this.loading = true, this.token});

  final bool loading;
  final String? token;

  bool get isAuthenticated => token != null;
}

class AuthController extends Notifier<AuthState> {
  @override
  AuthState build() {
    Future.microtask(_restore);
    return const AuthState();
  }

  Future<void> _restore() async {
    final token = await ref.read(sessionStorageProvider).readToken();
    state = AuthState(loading: false, token: token);
  }

  Future<void> setSession(String token) async {
    await ref.read(sessionStorageProvider).writeToken(token);
    state = AuthState(loading: false, token: token);
  }

  Future<void> logout() async {
    try {
      await ref.read(authRepositoryProvider).logout();
    } catch (_) {
      // Déconnexion locale même si le serveur est injoignable.
    }
    await expire();
  }

  // Session invalide ou expirée : on efface le token local.
  Future<void> expire() async {
    await ref.read(sessionStorageProvider).clearToken();
    state = const AuthState(loading: false);
  }
}

final authControllerProvider =
NotifierProvider<AuthController, AuthState>(AuthController.new);

final meNameProvider = FutureProvider.autoDispose<String>(
      (ref) => ref.watch(authRepositoryProvider).meName(),
);