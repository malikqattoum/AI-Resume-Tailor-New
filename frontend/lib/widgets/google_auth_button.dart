import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_sign_in/google_sign_in.dart';
import '../services/auth_provider.dart';
import 'home_screen.dart';

class GoogleAuthButton extends ConsumerStatefulWidget {
  final String label;
  final bool isLoading;
  final String Function(String?) errorFormatter;
  final VoidCallback? onError;

  const GoogleAuthButton({
    super.key,
    required this.label,
    this.isLoading = false,
    String Function(String?)? errorFormatter,
    this.onError,
  }) : errorFormatter = errorFormatter ?? _defaultErrorFormatter;

  static String _defaultErrorFormatter(String? error) {
    if (error == null) return 'An error occurred';
    if (error.contains('SocketException') || error.contains('Connection refused')) {
      return 'Could not connect to server';
    }
    if (error.contains('timeout')) {
      return 'Request timed out';
    }
    return error.replaceAll('Exception: ', '');
  }

  @override
  ConsumerState<GoogleAuthButton> createState() => _GoogleAuthButtonState();
}

class _GoogleAuthButtonState extends ConsumerState<GoogleAuthButton> {
  final GoogleSignIn _googleSignIn = GoogleSignIn(
    webClientId: 'YOUR_WEB_CLIENT_ID',
  );

  Future<void> _handleGoogleAuth() async {
    try {
      final GoogleSignInAccount? googleUser = await _googleSignIn.signIn();
      if (googleUser == null) return;

      final GoogleSignInAuthentication googleAuth = await googleUser.authentication;
      final success = await ref.read(authProvider.notifier).googleSignIn(googleAuth.idToken!);

      if (success && mounted) {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (context) => const HomeScreen()),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(widget.errorFormatter(e.toString())),
            backgroundColor: Colors.red,
          ),
        );
        widget.onError?.call();
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      height: 56,
      child: OutlinedButton.icon(
        onPressed: widget.isLoading ? null : _handleGoogleAuth,
        style: OutlinedButton.styleFrom(
          foregroundColor: Colors.white,
          side: const BorderSide(color: Colors.white, width: 2),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
        ),
        icon: const Icon(Icons.g_mobiledata, size: 28),
        label: Text(
          widget.label,
          style: const TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.bold,
          ),
        ),
      ),
    );
  }
}
