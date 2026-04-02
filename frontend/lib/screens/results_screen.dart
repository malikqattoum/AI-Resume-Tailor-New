import 'package:flutter/material.dart';
import 'package:share_plus/share_plus.dart';
import 'cover_letter_screen.dart';

class ResultsScreen extends StatelessWidget {
  final Map<String, dynamic> result;
  final String jobTitle;
  final String company;

  // Color constants for consistent theming
  static const _colorSuccess = Color(0xFF10B981);
  static const _colorPrimary = Color(0xFF3B82F6);
  static const _colorAccent = Color(0xFF8B5CF6);

  const ResultsScreen({
    super.key,
    required this.result,
    required this.jobTitle,
    required this.company,
  });

  @override
  Widget build(BuildContext context) {
    // API returns fully-qualified URLs — use directly
    final resumeUrl = result['tailored_resume_url'] as String;
    final coverLetterUrl = result['cover_letter_url'] as String;

    return Scaffold(
      backgroundColor: Colors.grey[50],
      appBar: AppBar(
        backgroundColor: _colorSuccess,
        foregroundColor: Colors.white,
        elevation: 0,
        title: const Text('Your Tailored Resume'),
        centerTitle: true,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Success banner
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: _colorSuccess.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(
                  color: _colorSuccess,
                  width: 2,
                ),
              ),
              child: Column(
                children: [
                  const Icon(
                    Icons.check_circle,
                    color: _colorSuccess,
                    size: 48,
                  ),
                  const SizedBox(height: 12),
                  const Text(
                    'Resume Tailored Successfully!',
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                      color: _colorSuccess,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Your resume has been customized for $jobTitle at $company',
                    style: TextStyle(
                      fontSize: 14,
                      color: Colors.grey[700],
                    ),
                    textAlign: TextAlign.center,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 32),
            // Tailored Resume Card
            _buildFileCard(
              context: context,
              icon: Icons.description,
              iconColor: _colorPrimary,
              title: 'Tailored Resume',
              subtitle: 'Your resume customized for this job',
              downloadUrl: resumeUrl,
              onView: () {
                _showPreviewDialog(context, resumeUrl, 'Tailored Resume');
              },
            ),
            const SizedBox(height: 16),
            // Cover Letter Card
            _buildFileCard(
              context: context,
              icon: Icons.mail_outline,
              iconColor: _colorAccent,
              title: 'Cover Letter',
              subtitle: 'Professional cover letter',
              downloadUrl: coverLetterUrl,
              onView: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (context) => CoverLetterScreen(
                      coverLetterUrl: coverLetterUrl,
                      jobTitle: jobTitle,
                      company: company,
                    ),
                  ),
                );
              },
            ),
            const SizedBox(height: 32),
            // Tips
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: Colors.blue[50],
                borderRadius: BorderRadius.circular(16),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Row(
                    children: [
                      Icon(Icons.lightbulb_outline, color: _colorPrimary),
                      SizedBox(width: 8),
                      Text(
                        'Tips for Success',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                          color: _colorPrimary,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  _buildTip('Review the tailored resume before submitting'),
                  _buildTip('Customize the cover letter with your unique voice'),
                  _buildTip('Apply to similar roles to increase interview chances'),
                ],
              ),
            ),
            const SizedBox(height: 32),
            // Actions
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () {
                      Navigator.popUntil(context, (route) => route.isFirst);
                    },
                    icon: const Icon(Icons.refresh),
                    label: const Text('Start Over'),
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: ElevatedButton.icon(
                    onPressed: () {
                      Share.share(
                        'Check out my tailored resume for $jobTitle at $company! Created with AI Resume Tailor.',
                        subject: 'Tailored Resume for $jobTitle',
                      );
                    },
                    icon: const Icon(Icons.share),
                    label: const Text('Share'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: _colorPrimary,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildFileCard({
    required BuildContext context,
    required IconData icon,
    required Color iconColor,
    required String title,
    required String subtitle,
    required String downloadUrl,
    required VoidCallback onView,
  }) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: iconColor.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: iconColor, size: 32),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: Colors.black87,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  subtitle,
                  style: TextStyle(
                    fontSize: 14,
                    color: Colors.grey[600],
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            onPressed: onView,
            icon: Icon(Icons.visibility, color: _colorPrimary),
          ),
          IconButton(
            onPressed: () async {
              await Share.shareXFiles(
                [XFile(downloadUrl)],
                subject: title,
              );
            },
            icon: Icon(Icons.download, color: _colorSuccess),
          ),
        ],
      ),
    );
  }

  Widget _buildTip(String text) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('• ', style: TextStyle(color: _colorPrimary)),
          Expanded(
            child: Text(
              text,
              style: TextStyle(
                fontSize: 14,
                color: Colors.grey[700],
              ),
            ),
          ),
        ],
      ),
    );
  }

  void _showPreviewDialog(BuildContext context, String url, String title) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(title),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.picture_as_pdf, size: 64, color: Colors.red),
            const SizedBox(height: 16),
            const Text('PDF Preview'),
            const SizedBox(height: 8),
            Text(
              'URL: ${Uri.parse(url).pathSegments.last}',
              style: TextStyle(fontSize: 12, color: Colors.grey[600]),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Close'),
          ),
        ],
      ),
    );
  }
}
