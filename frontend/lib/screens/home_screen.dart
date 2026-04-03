import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../services/auth_provider.dart';
import 'upload_resume_screen.dart';
import 'login_screen.dart';

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authProvider);

    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              Color(0xFF1E3A8A), // Deep blue
              Color(0xFF3B82F6), // Bright blue
            ],
          ),
        ),
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(24.0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Auth status bar
                if (authState.isAuthenticated)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 16.0),
                    child: Row(
                      children: [
                        CircleAvatar(
                          backgroundColor: Colors.white.withValues(alpha: 0.2),
                          child: Text(
                            authState.user?.name.substring(0, 1).toUpperCase() ?? 'U',
                            style: const TextStyle(color: Colors.white),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Welcome, ${authState.user?.name ?? 'User'}',
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              const Text(
                                'Ready to tailor your resume',
                                style: TextStyle(
                                  color: Colors.white60,
                                  fontSize: 12,
                                ),
                              ),
                            ],
                          ),
                        ),
                        IconButton(
                          icon: const Icon(Icons.logout, color: Colors.white70),
                          onPressed: () async {
                            await ref.read(authProvider.notifier).logout();
                          },
                          tooltip: 'Logout',
                        ),
                      ],
                    ),
                  ),
                const Spacer(flex: 2),
                // Logo / Icon
                Container(
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.2),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: const Icon(
                    Icons.description_outlined,
                    size: 60,
                    color: Colors.white,
                  ),
                ),
                const SizedBox(height: 32),
                // Title
                const Text(
                  'AI Resume\nTailor',
                  style: TextStyle(
                    fontSize: 48,
                    fontWeight: FontWeight.bold,
                    color: Colors.white,
                    height: 1.1,
                  ),
                ),
                const SizedBox(height: 16),
                // Subtitle
                const Text(
                  'Tailor your resume for any job in seconds. Land more interviews with AI-powered customization.',
                  style: TextStyle(
                    fontSize: 18,
                    color: Colors.white70,
                    height: 1.5,
                  ),
                ),
                const Spacer(flex: 3),
                // CTA Button
                SizedBox(
                  width: double.infinity,
                  height: 60,
                  child: ElevatedButton(
                    onPressed: () {
                      if (authState.isAuthenticated) {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (context) => const UploadResumeScreen(),
                          ),
                        );
                      } else {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (context) => const LoginScreen(),
                          ),
                        );
                      }
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.white,
                      foregroundColor: const Color(0xFF1E3A8A),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(16),
                      ),
                      elevation: 0,
                    ),
                    child: Text(
                      authState.isAuthenticated ? 'Get Started' : 'Sign In to Start',
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                // Secondary text
                Center(
                  child: Text(
                    authState.isAuthenticated
                        ? 'Free to use • Takes 10 seconds'
                        : 'Sign in to start tailoring your resume',
                    style: const TextStyle(
                      color: Colors.white60,
                      fontSize: 14,
                    ),
                  ),
                ),
                const Spacer(),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
