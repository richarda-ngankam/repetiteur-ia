import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/network/providers.dart';
import '../data/auth_repository.dart';
import '../domain/session.dart';

class AuthState {
  const AuthState({this.loading = true, this.token, this.deviceToken});

  final bool loading;
  final String? token;
  final String? deviceToken;

  bool get isAuthenticated => token != null;
}

class AuthController extends Notifier<AuthState> {
  @override
  AuthState build() {
    Future.microtask(_restore);
    return const AuthState();
  }

  Future<void> _restore() async {
    final storage = ref.read(sessionStorageProvider);
    final token = await storage.readToken();
    if (!ref.mounted) return;
    final deviceToken = await storage.readDeviceToken();
    if (!ref.mounted) return;

    if (token == null) {
      state = const AuthState(loading: false);
      return;
    }

    try {
      await ref.read(authRepositoryProvider).meName();
      if (!ref.mounted) return;
      state = AuthState(loading: false, token: token, deviceToken: deviceToken);
    } on ApiException catch (e) {
      if (!ref.mounted) return;
      if (e.status == 401) {
        await expire();
      } else {
        state = AuthState(loading: false, token: token, deviceToken: deviceToken);
      }
    } catch (_) {
      if (!ref.mounted) return;
      state = AuthState(loading: false, token: token, deviceToken: deviceToken);
    }
  }

  Future<void> setSession(Session session) async {
    final storage = ref.read(sessionStorageProvider);
    await storage.writeToken(session.token);
    if (!ref.mounted) return;
    if (session.deviceToken.isNotEmpty) {
      await storage.writeDeviceToken(session.deviceToken);
      if (!ref.mounted) return;
    }
    state = AuthState(
      loading: false,
      token: session.token,
      deviceToken: session.deviceToken.isNotEmpty ? session.deviceToken : null,
    );
  }

  Future<void> logout() async {
    try {
      await ref.read(authRepositoryProvider).logout();
    } catch (_) {
      // Déconnexion locale même si le serveur est injoignable.
    }
    if (!ref.mounted) return;
    await expire();
  }

  // Session invalide ou expirée : on efface les deux tokens locaux.
  Future<void> expire() async {
    await ref.read(sessionStorageProvider).clearSession();
    if (!ref.mounted) return;
    state = const AuthState(loading: false);
  }
}

final authControllerProvider =
    NotifierProvider<AuthController, AuthState>(AuthController.new);

final meNameProvider = FutureProvider.autoDispose<String>(
  (ref) => ref.watch(authRepositoryProvider).meName(),
);