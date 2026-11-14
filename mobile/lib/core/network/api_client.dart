import 'dart:ui' show VoidCallback;

import 'package:dio/dio.dart';

import '../config/app_config.dart';
import '../storage/session_storage.dart';
import 'api_exception.dart';

class ApiClient {
  ApiClient(
    this._storage, {
    this.onUnauthorized,
    Dio? dio,
  }) : _dio = dio ??
            Dio(BaseOptions(
              baseUrl: AppConfig.apiBaseUrl,
              connectTimeout: const Duration(seconds: 15),
              receiveTimeout: const Duration(seconds: 20),
              headers: {'Accept': 'application/json'},
            )) {
    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await _storage.readToken();
        if (token != null) options.headers['Authorization'] = 'Bearer $token';
        handler.next(options);
      },
      onError: (e, handler) {
        if (e.response?.statusCode == 401) {
          onUnauthorized?.call();
        }
        handler.next(e);
      },
    ));
  }

  final Dio _dio;
  final SessionStorage _storage;
  final VoidCallback? onUnauthorized;

  // Les chemins commencent par « / » : /auth/code, /children, etc.
  Future<Map<String, dynamic>> request(
      String method,
      String path, {
        Object? data,
      }) async {
    try {
      final response = await _dio.request<dynamic>(
        path,
        data: data,
        options: Options(method: method),
      );
      final body = response.data;
      return body is Map<String, dynamic> ? body : <String, dynamic>{};
    } on DioException catch (e) {
      throw _toException(e);
    }
  }

  ApiException _toException(DioException e) {
    final status = e.response?.statusCode;
    if (status == null) {
      return const ApiException('Serveur injoignable. Vérifie ta connexion.');
    }

    var message = 'Une erreur est survenue.';
    final data = e.response?.data;
    if (data is Map<String, dynamic>) {
      final errors = data['errors'];
      if (errors is Map && errors.isNotEmpty) {
        final first = errors.values.first;
        if (first is List && first.isNotEmpty) message = first.first.toString();
      } else if (data['message'] is String) {
        message = data['message'] as String;
      }
    }
    if (status == 429) message = 'Trop de tentatives. Attends une minute.';
    return ApiException(message, status: status);
  }
}