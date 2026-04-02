import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';

class ApiService {
  // Change this to your Laravel backend URL
  static const String baseUrl = 'http://10.0.2.2:8000/api';

  /// Upload a PDF resume and get back a resume_id
  Future<Map<String, dynamic>> uploadResume(File file) async {
    final uri = Uri.parse('$baseUrl/resume/upload');
    final request = http.MultipartRequest('POST', uri);

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
      throw Exception(error['message'] ?? 'Upload failed with status ${response.statusCode}');
    }
  }

  /// Tailor the resume with job description
  Future<Map<String, dynamic>> tailorResume({
    required String resumeId,
    required String jobTitle,
    required String company,
    required String jobDescription,
  }) async {
    final uri = Uri.parse('$baseUrl/tailor');
    final response = await http.post(
      uri,
      headers: {'Content-Type': 'application/json'},
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
      throw Exception(error['message'] ?? 'Tailoring failed with status ${response.statusCode}');
    }
  }

  /// Get tailored result by ID
  Future<Map<String, dynamic>> getTailoredResult(String resultId) async {
    final uri = Uri.parse('$baseUrl/tailored/$resultId');
    final response = await http.get(uri);

    if (response.statusCode == 200) {
      final data = json.decode(response.body);
      if (data['success'] == true) {
        return data['data'];
      }
      throw Exception(data['message'] ?? 'Result not found');
    } else {
      throw Exception('Failed to get result with status ${response.statusCode}');
    }
  }

  /// Get full download URL for a file
  String getDownloadUrl(String path) {
    final encodedPath = Uri.encodeComponent(path);
    return '$baseUrl/download/$encodedPath';
  }

  /// Health check
  Future<bool> healthCheck() async {
    try {
      final uri = Uri.parse('$baseUrl/health');
      final response = await http.get(uri);
      return response.statusCode == 200;
    } catch (e) {
      return false;
    }
  }
}
