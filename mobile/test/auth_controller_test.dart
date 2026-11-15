import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:repetiteur_ia/core/network/api_client.dart';
import 'package:repetiteur_ia/core/network/api_exception.dart';
import 'package:repetiteur_ia/core/network/providers.dart';
import 'package:repetiteur_ia/core/storage/session_storage.dart';
import 'package:repetiteur_ia/features/auth/application/auth_controller.dart';
import 'package:repetiteur_ia/features/auth/data/auth_repository.dart';
import 'package:repetiteur_ia/features/auth/domain/session.dart';

class FakeSessionStorage extends SessionStorage {
  String? storedToken;
  String? storedDeviceToken;

  @override
  Future<String?> readToken() async => storedToken;

  @override
  Future<void> writeToken(String val) async => storedToken = val;

  @override
  Future<String?> readDeviceToken() async => storedDeviceToken;

  @override
  Future<void> writeDeviceToken(String val) async => storedDeviceToken = val;

  @override
  Future<void> clearSession() async {
    storedToken = null;
    storedDeviceToken = null;
  }
}

class FakeAuthRepository extends AuthRepository {
  FakeAuthRepository(SessionStorage storage) : super(ApiClient(storage), storage);

  bool throw401OnMe = false;
  bool throwNetworkOnMe = false;
  bool logoutCalled = false;

  @override
  Future<String> meName() async {
    if (throw401OnMe) {
      throw const ApiException('Non autorisé', status: 401);
    }
    if (throwNetworkOnMe) {
      throw const ApiException('Serveur injoignable.');
    }
    return 'Marie Ngono';
  }

  @override
  Future<void> logout() async {
    logoutCalled = true;
  }
}

void main() {
  late FakeSessionStorage fakeStorage;
  late FakeAuthRepository fakeRepository;

  setUp(() {
    fakeStorage = FakeSessionStorage();
    fakeRepository = FakeAuthRepository(fakeStorage);
  });

  ProviderContainer createContainer() {
    final container = ProviderContainer(
      overrides: [
        sessionStorageProvider.overrideWithValue(fakeStorage),
        authRepositoryProvider.overrideWithValue(fakeRepository),
      ],
    );
    // Active le provider pour déclencher son initialisation
    container.listen(authControllerProvider, (_, __) {});
    return container;
  }

  group('AuthController - Machine à états', () {
    test('état initial en chargement puis déconnecté si aucun token stocké', () async {
      final container = createContainer();
      addTearDown(container.dispose);

      expect(container.read(authControllerProvider).loading, true);
      expect(container.read(authControllerProvider).isAuthenticated, false);

      await pumpEventQueue();

      final state = container.read(authControllerProvider);
      expect(state.loading, false);
      expect(state.isAuthenticated, false);
      expect(state.token, isNull);
    });

    test('restauration réussie avec token valide (/me répond 200)', () async {
      fakeStorage.storedToken = 'parent-token-123';
      fakeStorage.storedDeviceToken = 'device-token-456';

      final container = createContainer();
      addTearDown(container.dispose);

      await pumpEventQueue();

      final state = container.read(authControllerProvider);
      expect(state.loading, false);
      expect(state.isAuthenticated, true);
      expect(state.token, 'parent-token-123');
      expect(state.deviceToken, 'device-token-456');
    });

    test('restauration avec token révoqué (/me répond 401) efface la session', () async {
      fakeStorage.storedToken = 'expired-token';
      fakeStorage.storedDeviceToken = 'expired-device-token';
      fakeRepository.throw401OnMe = true;

      final container = createContainer();
      addTearDown(container.dispose);

      await pumpEventQueue();

      final state = container.read(authControllerProvider);
      expect(state.loading, false);
      expect(state.isAuthenticated, false);
      expect(state.token, isNull);
      expect(fakeStorage.storedToken, isNull);
      expect(fakeStorage.storedDeviceToken, isNull);
    });

    test('restauration avec erreur réseau conserve la session locale', () async {
      fakeStorage.storedToken = 'saved-token';
      fakeStorage.storedDeviceToken = 'saved-device';
      fakeRepository.throwNetworkOnMe = true;

      final container = createContainer();
      addTearDown(container.dispose);

      await pumpEventQueue();

      final state = container.read(authControllerProvider);
      expect(state.loading, false);
      expect(state.isAuthenticated, true);
      expect(state.token, 'saved-token');
      expect(fakeStorage.storedToken, 'saved-token');
    });

    test('setSession enregistre les deux tokens et passe en connecté', () async {
      final container = createContainer();
      addTearDown(container.dispose);

      await pumpEventQueue();

      await container.read(authControllerProvider.notifier).setSession(
            const Session(token: 'new-token', deviceToken: 'new-device-token'),
          );

      final state = container.read(authControllerProvider);
      expect(state.loading, false);
      expect(state.isAuthenticated, true);
      expect(state.token, 'new-token');
      expect(state.deviceToken, 'new-device-token');
      expect(fakeStorage.storedToken, 'new-token');
      expect(fakeStorage.storedDeviceToken, 'new-device-token');
    });

    test('logout appelle le repository et purge les jetons', () async {
      fakeStorage.storedToken = 'tok';
      fakeStorage.storedDeviceToken = 'dev';

      final container = createContainer();
      addTearDown(container.dispose);

      await pumpEventQueue();

      await container.read(authControllerProvider.notifier).logout();

      final state = container.read(authControllerProvider);
      expect(state.isAuthenticated, false);
      expect(fakeRepository.logoutCalled, true);
      expect(fakeStorage.storedToken, isNull);
      expect(fakeStorage.storedDeviceToken, isNull);
    });

    test('expire purge directement les jetons sans appel réseau', () async {
      fakeStorage.storedToken = 'tok';
      fakeStorage.storedDeviceToken = 'dev';

      final container = createContainer();
      addTearDown(container.dispose);

      await pumpEventQueue();

      await container.read(authControllerProvider.notifier).expire();

      final state = container.read(authControllerProvider);
      expect(state.isAuthenticated, false);
      expect(fakeRepository.logoutCalled, false);
      expect(fakeStorage.storedToken, isNull);
      expect(fakeStorage.storedDeviceToken, isNull);
    });
  });
}
