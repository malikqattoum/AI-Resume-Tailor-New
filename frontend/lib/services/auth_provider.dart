import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../services/api_service.dart';

// Auth state
class AuthState {
  final bool isAuthenticated;
  final User? user;
  final String? token;
  final bool isLoading;
  final String? error;

  const AuthState({
    this.isAuthenticated = false,
    this.user,
    this.token,
    this.isLoading = false,
    this.error,
  });

  AuthState copyWith({
    bool? isAuthenticated,
    User? user,
    String? token,
    bool? isLoading,
    String? error,
  }) {
    return AuthState(
      isAuthenticated: isAuthenticated ?? this.isAuthenticated,
      user: user ?? this.user,
      token: token ?? this.token,
      isLoading: isLoading ?? this.isLoading,
      error: error,
    );
  }
}

// Auth notifier
class AuthNotifier extends StateNotifier<AuthState> {
  final ApiService _apiService;

  AuthNotifier(this._apiService) : super(const AuthState());

  Future<bool> googleSignIn(String idToken) async {
    state = state.copyWith(isLoading: true, error: null);
    try {
      final response = await _apiService.googleAuth(idToken);
      if (response.success && response.user != null) {
        state = state.copyWith(
          isAuthenticated: true,
          user: response.user,
          token: response.token,
          isLoading: false,
        );
        return true;
      }
      state = state.copyWith(
        isLoading: false,
        error: response.error ?? 'Google sign-in failed',
      );
      return false;
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: e.toString(),
      );
      return false;
    }
  }

  Future<bool> login(String email, String password) async {
    state = state.copyWith(isLoading: true, error: null);
    try {
      final response = await _apiService.login(
        email: email,
        password: password,
      );
      if (response.success && response.user != null) {
        state = state.copyWith(
          isAuthenticated: true,
          user: response.user,
          token: response.token,
          isLoading: false,
        );
        return true;
      }
      state = state.copyWith(
        isLoading: false,
        error: response.error ?? 'Login failed',
      );
      return false;
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: e.toString(),
      );
      return false;
    }
  }

  Future<bool> register(String name, String email, String password, String passwordConfirmation) async {
    state = state.copyWith(isLoading: true, error: null);
    try {
      final response = await _apiService.register(
        name: name,
        email: email,
        password: password,
        passwordConfirmation: passwordConfirmation,
      );
      if (response.success && response.user != null) {
        state = state.copyWith(
          isAuthenticated: true,
          user: response.user,
          token: response.token,
          isLoading: false,
        );
        return true;
      }
      state = state.copyWith(
        isLoading: false,
        error: response.error ?? 'Registration failed',
      );
      return false;
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: e.toString(),
      );
      return false;
    }
  }

  Future<void> logout() async {
    await _apiService.logout();
    state = const AuthState();
  }

  void clearError() {
    state = state.copyWith(error: null);
  }
}

// Provider
final apiServiceProvider = Provider<ApiService>((ref) {
  return ApiService();
});

final authProvider = StateNotifierProvider<AuthNotifier, AuthState>((ref) {
  final apiService = ref.watch(apiServiceProvider);
  return AuthNotifier(apiService);
});
