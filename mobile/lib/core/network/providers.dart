import 'dart:ui' show VoidCallback;

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/auth/application/auth_controller.dart';
import '../storage/session_storage.dart';
import 'api_client.dart';

final sessionStorageProvider = Provider<SessionStorage>((ref) => SessionStorage());

final unauthorizedHandlerProvider = Provider<VoidCallback>((ref) {
  bool isExpiring = false;
  return () {
    if (isExpiring) return;
    isExpiring = true;
    Future.microtask(() async {
      try {
        await ref.read(authControllerProvider.notifier).expire();
      } finally {
        isExpiring = false;
      }
    });
  };
});

final apiClientProvider = Provider<ApiClient>((ref) {
  return ApiClient(
    ref.watch(sessionStorageProvider),
    onUnauthorized: ref.watch(unauthorizedHandlerProvider),
  );
});