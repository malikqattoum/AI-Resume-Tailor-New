import 'dart:io';
import 'package:flutter/material.dart';
import '../services/api_service.dart';
import 'processing_screen.dart';

class JobDescriptionScreen extends StatefulWidget {
  final File resumeFile;

  const JobDescriptionScreen({super.key, required this.resumeFile});

  @override
  State<JobDescriptionScreen> createState() => _JobDescriptionScreenState();
}

class _JobDescriptionScreenState extends State<JobDescriptionScreen> {
  static const _colorPrimary = Color(0xFF3B82F6);

  final _formKey = GlobalKey<FormState>();
  final _jobTitleController = TextEditingController();
  final _companyController = TextEditingController();
  final _descriptionController = TextEditingController();
  final _apiService = ApiService();

  bool _isUploading = false;

  // Common decoration style for form fields
  InputDecoration _textFieldDecoration(String label, String hint, {bool alignLabelWithHint = false}) {
    return InputDecoration(
      labelText: label,
      hintText: hint,
      filled: true,
      fillColor: Colors.white,
      alignLabelWithHint: alignLabelWithHint,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: BorderSide(color: Colors.grey[300]!),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: BorderSide(color: Colors.grey[300]!),
      ),
    );
  }

  // Validation helper
  String? Function(String?) _requiredValidator(String fieldName) {
    return (value) {
      if (value == null || value.trim().isEmpty) {
        return 'Please enter the $fieldName';
      }
      return null;
    };
  }

  String _friendlyError(dynamic e) {
    final msg = e.toString();
    if (msg.contains('SocketException') || msg.contains('Connection refused')) {
      return 'Could not connect to server. Please check your internet connection.';
    } else if (msg.contains('timeout') || msg.contains('TimeoutException')) {
      return 'Request timed out. Please try again.';
    } else if (msg.contains('404')) {
      return 'Service not found. Please update the app.';
    } else if (msg.contains('401') || msg.contains('403')) {
      return 'Access denied. Please check your settings.';
    }
    return 'Something went wrong. Please try again.';
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isUploading = true);

    try {
      // Step 1: Upload resume
      final uploadResult = await _apiService.uploadResume(widget.resumeFile);
      final resumeId = uploadResult['resume_id'] as String;

      // Step 2: Tailor resume
      final tailorResult = await _apiService.tailorResume(
        resumeId: resumeId,
        jobTitle: _jobTitleController.text.trim(),
        company: _companyController.text.trim(),
        jobDescription: _descriptionController.text.trim(),
      );

      // Step 3: Navigate to processing (results) screen
      if (mounted) {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(
            builder: (context) => ProcessingScreen(
              resultId: tailorResult['result_id'] as String,
              resumeFile: widget.resumeFile,
              jobTitle: _jobTitleController.text.trim(),
              company: _companyController.text.trim(),
            ),
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(_friendlyError(e)),
            backgroundColor: Colors.red,
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _isUploading = false);
      }
    }
  }

  @override
  void dispose() {
    _jobTitleController.dispose();
    _companyController.dispose();
    _descriptionController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.grey[50],
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Colors.black87),
          onPressed: () => Navigator.pop(context),
        ),
        title: const Text(
          'Job Details',
          style: TextStyle(
            color: Colors.black87,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      body: Stack(
        children: [
          Padding(
            padding: const EdgeInsets.all(24.0),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Step 2 of 2',
                    style: TextStyle(
                      color: Color(0xFF3B82F6),
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Job Description',
                    style: TextStyle(
                      fontSize: 28,
                      fontWeight: FontWeight.bold,
                      color: Colors.black87,
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Enter the job details you\'re applying for. Paste the job description for the best results.',
                    style: TextStyle(
                      fontSize: 16,
                      color: Colors.black54,
                      height: 1.5,
                    ),
                  ),
                  const SizedBox(height: 24),
                  // Job Title
                  TextFormField(
                    controller: _jobTitleController,
                    decoration: _textFieldDecoration(
                      'Job Title',
                      'e.g. Senior Software Engineer',
                    ),
                    validator: _requiredValidator('job title'),
                  ),
                  const SizedBox(height: 16),
                  // Company
                  TextFormField(
                    controller: _companyController,
                    decoration: _textFieldDecoration(
                      'Company Name',
                      'e.g. Acme Corporation',
                    ),
                    validator: _requiredValidator('company name'),
                  ),
                  const SizedBox(height: 16),
                  // Job Description
                  Expanded(
                    child: TextFormField(
                      controller: _descriptionController,
                      maxLines: null,
                      expands: true,
                      textAlignVertical: TextAlignVertical.top,
                      decoration: _textFieldDecoration(
                        'Job Description',
                        'Paste the full job description here...',
                        alignLabelWithHint: true,
                      ),
                      validator: (value) {
                        if (value == null || value.trim().isEmpty) {
                          return 'Please enter the job description';
                        }
                        if (value.trim().length < 50) {
                          return 'Job description should be at least 50 characters';
                        }
                        return null;
                      },
                    ),
                  ),
                  const SizedBox(height: 24),
                  // Submit Button
                  SizedBox(
                    width: double.infinity,
                    height: 56,
                    child: ElevatedButton(
                      onPressed: _isUploading ? null : _submit,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: _colorPrimary,
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                        disabledBackgroundColor: Colors.grey[300],
                      ),
                      child: const Text(
                        'Generate Tailored Resume',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
          if (_isUploading)
            Container(
              color: Colors.black.withValues(alpha: 0.5),
              child: const Center(
                child: CircularProgressIndicator(
                  color: Colors.white,
                ),
              ),
            ),
        ],
      ),
    );
  }
}
