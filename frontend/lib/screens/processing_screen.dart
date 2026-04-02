import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_spinkit/flutter_spinkit.dart';
import '../services/api_service.dart';
import 'results_screen.dart';

class ProcessingScreen extends StatefulWidget {
  final String resultId;
  final File resumeFile;
  final String jobTitle;
  final String company;

  const ProcessingScreen({
    super.key,
    required this.resultId,
    required this.resumeFile,
    required this.jobTitle,
    required this.company,
  });

  @override
  State<ProcessingScreen> createState() => _ProcessingScreenState();
}

class _ProcessingScreenState extends State<ProcessingScreen> {
  final _apiService = ApiService();
  Map<String, dynamic>? _result;
  String _statusMessage = 'Uploading your resume...';
  int _progress = 0;

  @override
  void initState() {
    super.initState();
    _fetchResults();
  }

  Future<void> _fetchResults() async {
    try {
      // Simulate progress updates for better UX
      setState(() {
        _statusMessage = 'Analyzing your resume...';
        _progress = 25;
      });

      await Future.delayed(const Duration(milliseconds: 800));

      setState(() {
        _statusMessage = 'Understanding job requirements...';
        _progress = 40;
      });

      await Future.delayed(const Duration(milliseconds: 800));

      setState(() {
        _statusMessage = 'Tailoring your resume with AI...';
        _progress = 60;
      });

      await Future.delayed(const Duration(milliseconds: 800));

      setState(() {
        _statusMessage = 'Generating cover letter...';
        _progress = 80;
      });

      // Fetch the actual result
      final result = await _apiService.getTailoredResult(widget.resultId);

      setState(() {
        _result = result;
        _statusMessage = 'Done!';
        _progress = 100;
      });

      // Wait a moment for the 100% to show
      await Future.delayed(const Duration(milliseconds: 500));

      // Navigate to results
      if (mounted) {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(
            builder: (context) => ResultsScreen(
              result: _result!,
              jobTitle: widget.jobTitle,
              company: widget.company,
            ),
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error: $e'),
            backgroundColor: Colors.red,
          ),
        );
        Navigator.pop(context);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              Color(0xFF1E3A8A),
              Color(0xFF3B82F6),
            ],
          ),
        ),
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(32.0),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Spacer(),
                // Animated loading indicator
                const SpinKitRing(
                  size: 80,
                  lineWidth: 6,
                  color: Colors.white,
                ),
                const SizedBox(height: 48),
                // Status message
                Text(
                  _statusMessage,
                  style: const TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w600,
                    color: Colors.white,
                  ),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 16),
                // Progress bar
                Container(
                  width: 200,
                  height: 6,
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.3),
                    borderRadius: BorderRadius.circular(3),
                  ),
                  child: FractionallySizedBox(
                    alignment: Alignment.centerLeft,
                    widthFactor: _progress / 100,
                    child: Container(
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(3),
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  '$_progress%',
                  style: const TextStyle(
                    fontSize: 14,
                    color: Colors.white70,
                  ),
                ),
                const Spacer(),
                // Cancel button
                TextButton(
                  onPressed: () => Navigator.pop(context),
                  child: const Text(
                    'Cancel',
                    style: TextStyle(
                      color: Colors.white70,
                      fontSize: 16,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
