import 'package:flutter/material.dart';
import 'package:kios_berkah_mobile/core/constants/app_colors.dart';
import 'package:kios_berkah_mobile/core/theme/app_theme.dart';
import 'package:kios_berkah_mobile/core/widgets/app_button.dart';
import 'package:kios_berkah_mobile/core/widgets/app_text_field.dart';
import 'package:kios_berkah_mobile/features/navigation/presentation/screens/main_navigation_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _emailController = TextEditingController(text: 'admin@kios.test');
  final _passwordController = TextEditingController(text: 'password');
  bool _isLoading = false;
  bool _obscurePassword = true;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  void _handleLogin() {
    setState(() => _isLoading = true);
    // Simulasi atau integrasi API
    Future.delayed(const Duration(milliseconds: 600), () {
      if (mounted) {
        setState(() => _isLoading = false);
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (_) => const MainNavigationScreen()),
        );
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // App Brand Logo & Title
                  Center(
                    child: Container(
                      width: 64,
                      height: 64,
                      decoration: BoxDecoration(
                        color: AppColors.primary,
                        borderRadius: BorderRadius.circular(16),
                        boxShadow: [
                          BoxShadow(
                            color: AppColors.primary.withValues(alpha: 0.25),
                            blurRadius: 16,
                            offset: const Offset(0, 6),
                          ),
                        ],
                      ),
                      child: const Icon(
                        Icons.point_of_sale_rounded,
                        color: Colors.white,
                        size: 34,
                      ),
                    ),
                  ),
                  const SizedBox(height: 20),
                  Text(
                    'Kios BERKAH',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.displayLarge?.copyWith(
                          fontSize: 26,
                          fontWeight: FontWeight.w800,
                          color: AppColors.primary,
                        ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Sistem Kasir & Manajemen Toko',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                  const SizedBox(height: 36),

                  // Login Form Card
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Text(
                            'Masuk Akun',
                            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                                  fontSize: 18,
                                ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            'Silakan masukkan email dan kata sandi Anda',
                            style: Theme.of(context).textTheme.bodyMedium,
                          ),
                          const SizedBox(height: 24),
                          AppTextField(
                            controller: _emailController,
                            label: 'Email',
                            hint: 'nama@kios.test',
                            keyboardType: TextInputType.emailAddress,
                            prefixIcon: const Icon(Icons.email_outlined, size: 20),
                          ),
                          const SizedBox(height: 16),
                          AppTextField(
                            controller: _passwordController,
                            label: 'Kata Sandi',
                            hint: '••••••••',
                            obscureText: _obscurePassword,
                            prefixIcon: const Icon(Icons.lock_outline_rounded, size: 20),
                            suffixIcon: IconButton(
                              icon: Icon(
                                _obscurePassword ? Icons.visibility_outlined : Icons.visibility_off_outlined,
                                size: 20,
                              ),
                              onPressed: () {
                                setState(() => _obscurePassword = !_obscurePassword);
                              },
                            ),
                          ),
                          const SizedBox(height: 24),
                          AppButton(
                            label: 'Masuk Kasir',
                            isLoading: _isLoading,
                            onPressed: _handleLogin,
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 28),
                  Text(
                    'Versi Mobile Android 8+ • Flutter Native',
                    textAlign: TextAlign.center,
                    style: AppTheme.dataMono(
                      fontSize: 11,
                      color: AppColors.textMuted,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
