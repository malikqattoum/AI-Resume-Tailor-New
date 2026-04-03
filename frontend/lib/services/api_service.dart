import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';

class ApiService {
  // Configure these values for your environment
  // For Android emulator, use 10.0.2.2 to access host localhost
  // For iOS simulator, use localhost
  // For production, use your actual API domain
  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api',
  );

  String? _authToken;

  /// Set the authentication token (call after login)
  void setAuthToken(String token) {
    _authToken = token;
  }

  /// Clear the authentication token (call on logout)
  void clearAuthToken() {
    _authToken = null;
  }

  /// Get headers with authentication if token is set
  Map<String, String> get _headers {
    final headers = <String, String>{
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    if (_authToken != null) {
      headers['Authorization'] = 'Bearer $_authToken';
    }
    return headers;
  }

  /// Register a new user
  Future<AuthResponse> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
  }) async {
    final uri = Uri.parse('$baseUrl/auth/register');
    final response = await http.post(
      uri,
      headers: {'Content-Type': 'application/json'},
      body: json.encode({
        'name': name,
        'email': email,
        'password': password,
        'password_confirmation': passwordConfirmation,
      }),
    );

    if (response.statusCode == 201) {
      final data = json.decode(response.body);
      if (data['success'] == true) {
        final authData = data['data'];
        final token = authData['access_token'] as String;
        setAuthToken(token);
        return AuthResponse(
          success: true,
          token: token,
          user: User.fromJson(authData['user']),
        );
      }
      throw Exception(data['message'] ?? 'Registration failed');
    } else {
      final error = json.decode(response.body);
      throw Exception(error['message'] ?? 'Registration failed');
    }
  }

  /// Login user
  Future<AuthResponse> login({
    required String email,
    required String password,
  }) async {
    final uri = Uri.parse('$baseUrl/auth/login');
    final response = await http.post(
      uri,
      headers: {'Content-Type': 'application/json'},
      body: json.encode({
        'email': email,
        'password': password,
      }),
    );

    if (response.statusCode == 200) {
      final data = json.decode(response.body);
      if (data['success'] == true) {
        final authData = data['data'];
        final token = authData['access_token'] as String;
        setAuthToken(token);
        return AuthResponse(
          success: true,
          token: token,
          user: User.fromJson(authData['user']),
        );
      }
      throw Exception(data['message'] ?? 'Login failed');
    } else {
      final error = json.decode(response.body);
      throw Exception(error['message'] ?? 'Invalid credentials');
    }
  }

  /// Logout user
  Future<void> logout() async {
    if (_authToken == null) return;

    final uri = Uri.parse('$baseUrl/auth/logout');
    try {
      await http.post(
        uri,
        headers: _headers,
      );
    } finally {
      clearAuthToken();
    }
  }

  /// Get current user profile
  Future<User> getCurrentUser() async {
    final uri = Uri.parse('$baseUrl/auth/user');
    final response = await http.get(uri, headers: _headers);

    if (response.statusCode == 200) {
      final data = json.decode(response.body);
      if (data['success'] == true) {
        return User.fromJson(data['data']);
      }
      throw Exception(data['message'] ?? 'Failed to get user');
    } else {
      throw Exception('Failed to get user');
    }
  }

  /// Upload a PDF resume and get back a resume_id
  Future<Map<String, dynamic>> uploadResume(File file) async {
    if (_authToken == null) {
      throw Exception('Not authenticated. Please login first.');
    }

    final uri = Uri.parse('$baseUrl/resume/upload');
    final request = http.MultipartRequest('POST', uri);

    // Add auth header to multipart request
    request.headers['Authorization'] = 'Bearer $_authToken';

    // Create multipart file from the PDF
    final multipartFile = await http.MultipartFile.fromPath(
      'resume',
      file.path,
      contentType: MediaType('application', 'pdf'),
    );

    request.files.add(multipartFile);

    final streamedResponse = await request.send();
    final response = await http.Response.fromStream(streamedResponse);

    if (response.statusCode == 200 || response.statusCode == 201) {
      final data = json.decode(response.body);
      if (data['success'] == true) {
        return data['data'];
      }
      throw Exception(data['message'] ?? 'Upload failed');
    } else {
      final error = json.decode(response.body);
      throw Exception(error['message'] ?? 'Upload failed');
    }
  }

  /// Tailor the resume with job description
  Future<Map<String, dynamic>> tailorResume({
    required String resumeId,
    required String jobTitle,
    required String company,
    required String jobDescription,
  }) async {
    if (_authToken == null) {
      throw Exception('Not authenticated. Please login first.');
    }

    final uri = Uri.parse('$baseUrl/tailor');
    final response = await http.post(
      uri,
      headers: _headers,
      body: json.encode({
        'resume_id': resumeId,
        'job_title': jobTitle,
        'company': company,
        'job_description': jobDescription,
      }),
    );

    if (response.statusCode == 200) {
      final data = json.decode(response.body);
      if (data['success'] == true) {
        return data['data'];
      }
      throw Exception(data['message'] ?? 'Tailoring failed');
    } else {
      final error = json.decode(response.body);
      throw Exception(error['message'] ?? 'Tailoring failed');
    }
  }

  /// Get tailored result by ID
  Future<Map<String, dynamic>> getTailoredResult(String resultId) async {
    if (_authToken == null) {
      throw Exception('Not authenticated. Please login first.');
    }

    final uri = Uri.parse('$baseUrl/tailored/$resultId');
    final response = await http.get(uri, headers: _headers);

    if (response.statusCode == 200) {
      final data = json.decode(response.body);
      if (data['success'] == true) {
        return data['data'];
      }
      throw Exception(data['message'] ?? 'Result not found');
    } else {
      throw Exception('Failed to get result');
    }
  }

  /// Get full download URL for a file (opaque ID now - no path exposure)
  String getDownloadUrl(String opaqueId) {
    return '$baseUrl/download/$opaqueId';
  }

  /// Health check
  Future<HealthCheckResult> healthCheck() async {
    try {
      final uri = Uri.parse('$baseUrl/health');
      final response = await http.get(uri);
      return HealthCheckResult(
        isHealthy: response.statusCode == 200,
        statusCode: response.statusCode,
      );
    } catch (e) {
      final msg = e.toString();
      if (msg.contains('SocketException') || msg.contains('Connection refused')) {
        return HealthCheckResult(isHealthy: false, error: 'Network error: Could not reach server');
      } else if (msg.contains('timeout')) {
        return HealthCheckResult(isHealthy: false, error: 'Connection timed out');
      }
      return HealthCheckResult(isHealthy: false, error: 'Could not connect to server');
    }
  }
}

/// Authentication response
class AuthResponse {
  final bool success;
  final String? token;
  final User? user;
  final String? error;

  AuthResponse({
    required this.success,
    this.token,
    this.user,
    this.error,
  });
}

/// User model
class User {
  final int id;
  final String name;
  final String email;
  final DateTime? createdAt;

  User({
    required this.id,
    required this.name,
    required this.email,
    this.createdAt,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: json['id'] as int,
      name: json['name'] as String,
      email: json['email'] as String,
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'] as String)
          : null,
    );
  }
}

/// Result of a health check
class HealthCheckResult {
  final bool isHealthy;
  final int? statusCode;
  final String? error;

  HealthCheckResult({required this.isHealthy, this.statusCode, this.error});
}
