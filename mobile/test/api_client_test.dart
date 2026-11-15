import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:repetiteur_ia/core/network/api_client.dart';
import 'package:repetiteur_ia/core/network/api_exception.dart';
import 'package:repetiteur_ia/core/storage/session_storage.dart';

class FakeSessionStorage extends SessionStorage {
  String? token;

  @override
  Future<String?> readToken() async => token;

  @override
  Future<void> writeToken(String val) async => token = val;

  @override
  Future<String?> readDeviceToken() async => null;

  @override
  Future<void> writeDeviceToken(String val) async {}

  @override
  Future<void> clearSession() async => token = null;

  @override
  Future<String> installId() async => 'fake-install-id-12345';
}

class MockHttpClientAdapter implements HttpClientAdapter {
  ResponseBody Function(RequestOptions options)? handler;

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    if (handler != null) {
      return handler!(options);
    }
    throw DioException(
      requestOptions: options,
      type: DioExceptionType.connectionError,
      message: 'Serveur injoignable.',
    );
  }

  @override
  void close({bool force = false}) {}
}

void main() {
  late MockHttpClientAdapter mockAdapter;
  late Dio dio;
  late FakeSessionStorage storage;
  int unauthorizedCallCount = 0;

  setUp(() {
    mockAdapter = MockHttpClientAdapter();
    dio = Dio(BaseOptions(baseUrl: 'http://10.0.2.2:8000/api/v1'));
    dio.httpClientAdapter = mockAdapter;
    storage = FakeSessionStorage();
    unauthorizedCallCount = 0;
  });

  ApiClient createClient() {
    return ApiClient(
      storage,
      dio: dio,
      onUnauthorized: () {
        unauthorizedCallCount++;
      },
    );
  }

  ResponseBody jsonResponse(dynamic body, int statusCode) {
    return ResponseBody.fromString(
      jsonEncode(body),
      statusCode,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }

  group('ApiClient', () {
    test('succès 200 renvoie les données', () async {
      mockAdapter.handler = (options) => jsonResponse({'name': 'Marie'}, 200);
      final client = createClient();

      final result = await client.request('GET', '/me');

      expect(result, {'name': 'Marie'});
      expect(unauthorizedCallCount, 0);
    });

    test('erreur 401 déclenche onUnauthorized et lance ApiException(status: 401)', () async {
      mockAdapter.handler = (options) => jsonResponse({'message': 'Non authentifié.'}, 401);
      final client = createClient();

      await expectLater(
        client.request('GET', '/me'),
        throwsA(isA<ApiException>()
            .having((e) => e.status, 'status', 401)
            .having((e) => e.message, 'message', 'Non authentifié.')),
      );

      expect(unauthorizedCallCount, 1);
    });

    test('erreur 409 renvoie le message de conflit', () async {
      mockAdapter.handler = (options) => jsonResponse({'message': 'Un compte existe déjà.'}, 409);
      final client = createClient();

      await expectLater(
        client.request('POST', '/auth/register'),
        throwsA(isA<ApiException>()
            .having((e) => e.status, 'status', 409)
            .having((e) => e.message, 'message', 'Un compte existe déjà.')),
      );
    });

    test('erreur 422 extrait le premier message de validation', () async {
      mockAdapter.handler = (options) => jsonResponse({
        'message': 'Données invalides.',
        'errors': {
          'destination': ['Le numéro est requis.'],
          'code': ['Le code est invalide.'],
        },
      }, 422);
      final client = createClient();

      await expectLater(
        client.request('POST', '/auth/login'),
        throwsA(isA<ApiException>()
            .having((e) => e.status, 'status', 422)
            .having((e) => e.message, 'message', 'Le numéro est requis.')),
      );
    });

    test('erreur 429 renvoie le message de limitation', () async {
      mockAdapter.handler = (options) => jsonResponse({'message': 'Too Many Attempts.'}, 429);
      final client = createClient();

      await expectLater(
        client.request('POST', '/auth/code'),
        throwsA(isA<ApiException>()
            .having((e) => e.status, 'status', 429)
            .having((e) => e.message, 'message', 'Trop de tentatives. Attends une minute.')),
      );
    });

    test('erreur réseau (serveur injoignable) renvoie message connexion', () async {
      mockAdapter.handler = null;
      final client = createClient();

      await expectLater(
        client.request('GET', '/me'),
        throwsA(isA<ApiException>()
            .having((e) => e.status, 'status', isNull)
            .having((e) => e.message, 'message', 'Serveur injoignable. Vérifie ta connexion.')),
      );
    });
  });
}
