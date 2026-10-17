import 'package:dio/dio.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../constants/api_endpoints.dart';
import 'api_response.dart';

/// HTTP Client berbasis Dio untuk Kios BERKAH.
/// Mendukung:
/// - Bearer Token (Laravel Sanctum) otomatis.
/// - Dynamic Base URL (bisa diarahkan ke IP Laragon lokal maupun VPS produksi).
/// - Handling error terstruktur (401 Unauthorized, 422 Validation, 500 Server Error).
class ApiClient {
  static const String _keyBaseUrl = 'kios_api_base_url';
  static const String _keyToken = 'kios_auth_token';

  late final Dio _dio;
  final SharedPreferences _prefs;

  ApiClient(this._prefs) {
    final baseUrl = _prefs.getString(_keyBaseUrl) ?? ApiEndpoints.defaultBaseUrl;

    _dio = Dio(
      BaseOptions(
        baseUrl: baseUrl,
        connectTimeout: const Duration(seconds: 15),
        receiveTimeout: const Duration(seconds: 15),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      ),
    );

    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          final token = _prefs.getString(_keyToken);
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          return handler.next(options);
        },
        onError: (DioException error, handler) {
          // Tangani global error (e.g. 401 unauthenticated)
          return handler.next(error);
        },
      ),
    );
  }

  Dio get dio => _dio;

  /// Simpan atau update token autentikasi
  Future<void> saveToken(String token) async {
    await _prefs.setString(_keyToken, token);
  }

  /// Hapus token saat logout
  Future<void> clearToken() async {
    await _prefs.remove(_keyToken);
  }

  /// Ganti Base URL (misal beralih dari VPS ke server lokal Wi-Fi)
  Future<void> updateBaseUrl(String newUrl) async {
    await _prefs.setString(_keyBaseUrl, newUrl);
    _dio.options.baseUrl = newUrl;
  }

  String get currentBaseUrl => _dio.options.baseUrl;

  /// Helper GET
  Future<ApiResponse<dynamic>> get(
    String path, {
    Map<String, dynamic>? queryParameters,
  }) async {
    try {
      final response = await _dio.get(path, queryParameters: queryParameters);
      return ApiResponse.success(response.data, statusCode: response.statusCode);
    } on DioException catch (e) {
      return _handleDioError(e);
    } catch (e) {
      return ApiResponse.error(e.toString());
    }
  }

  /// Helper POST
  Future<ApiResponse<dynamic>> post(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
  }) async {
    try {
      final response = await _dio.post(path, data: data, queryParameters: queryParameters);
      return ApiResponse.success(response.data, statusCode: response.statusCode);
    } on DioException catch (e) {
      return _handleDioError(e);
    } catch (e) {
      return ApiResponse.error(e.toString());
    }
  }

  /// Helper PATCH
  Future<ApiResponse<dynamic>> patch(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
  }) async {
    try {
      final response = await _dio.patch(path, data: data, queryParameters: queryParameters);
      return ApiResponse.success(response.data, statusCode: response.statusCode);
    } on DioException catch (e) {
      return _handleDioError(e);
    } catch (e) {
      return ApiResponse.error(e.toString());
    }
  }

  /// Helper DELETE
  Future<ApiResponse<dynamic>> delete(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
  }) async {
    try {
      final response = await _dio.delete(path, data: data, queryParameters: queryParameters);
      return ApiResponse.success(response.data, statusCode: response.statusCode);
    } on DioException catch (e) {
      return _handleDioError(e);
    } catch (e) {
      return ApiResponse.error(e.toString());
    }
  }

  ApiResponse<dynamic> _handleDioError(DioException e) {
    String message = 'Terjadi kesalahan jaringan.';
    if (e.response != null && e.response?.data != null) {
      final data = e.response?.data;
      if (data is Map && data.containsKey('message')) {
        message = data['message'].toString();
      } else if (data is String && data.isNotEmpty) {
        message = data;
      }
    } else if (e.type == DioExceptionType.connectionTimeout) {
      message = 'Koneksi ke server timeout. Periksa jaringan Anda.';
    } else if (e.type == DioExceptionType.connectionError) {
      message = 'Gagal terhubung ke server. Periksa alamat server.';
    }
    return ApiResponse.error(message, statusCode: e.response?.statusCode ?? 500);
  }
}
