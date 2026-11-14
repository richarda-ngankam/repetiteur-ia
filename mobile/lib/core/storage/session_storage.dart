import 'dart:math';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class SessionStorage {
  SessionStorage([FlutterSecureStorage? storage])
      : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;

  static const _tokenKey = 'token';
  static const _installKey = 'install_id';

  Future<String?> readToken() => _storage.read(key: _tokenKey);

  Future<void> writeToken(String token) =>
      _storage.write(key: _tokenKey, value: token);

  Future<void> clearToken() => _storage.delete(key: _tokenKey);

  // Identifiant d'installation stable (16 caractères minimum exigés par l'API).
  Future<String> installId() async {
    var id = await _storage.read(key: _installKey);
    if (id == null) {
      final random = Random.secure();
      id = 'app-${List.generate(24, (_) => random.nextInt(16).toRadixString(16)).join()}';
      await _storage.write(key: _installKey, value: id);
    }
    return id;
  }
}