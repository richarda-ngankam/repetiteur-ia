import 'dart:io' show Platform;

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/providers.dart';
import '../../../core/storage/session_storage.dart';
import '../domain/session.dart';

class AuthRepository {
  AuthRepository(this._api, this._storage);

  final ApiClient _api;
  final SessionStorage _storage;

  Future<Map<String, String>> _device() async => {
    'install_id': await _storage.installId(),
    'label': 'Application mobile',
    'platform': Platform.isIOS ? 'ios' : 'android',
  };

  Future<void> sendCode({required String purpose, required String destination}) {
    return _api.request('POST', '/auth/code', data: {
      'purpose': purpose,
      'channel': 'sms',
      'destination': destination,
    });
  }

  Future<Session> login({required String destination, required String code}) async {
    final response = await _api.request('POST', '/auth/login', data: {
      'channel': 'sms',
      'destination': destination,
      'code': code,
      'device': await _device(),
    });
    return Session(
      token: response['token'] as String,
      deviceToken: (response['device_token'] as String?) ?? '',
    );
  }

  Future<Session> register({
    required String destination,
    required String code,
    required String name,
  }) async {
    final response = await _api.request('POST', '/auth/register', data: {
      'channel': 'sms',
      'destination': destination,
      'code': code,
      'name': name,
      'country_code': 'CM',
      'accept_terms': true,
      'accept_privacy': true,
      'device': await _device(),
    });
    return Session(
      token: response['token'] as String,
      deviceToken: (response['device_token'] as String?) ?? '',
    );
  }

  Future<String> meName() async {
    final response = await _api.request('GET', '/me');
    return (response['name'] as String?) ?? '';
  }

  Future<void> logout() => _api.request('POST', '/auth/logout');
}

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepository(
    ref.watch(apiClientProvider),
    ref.watch(sessionStorageProvider),
  );
});