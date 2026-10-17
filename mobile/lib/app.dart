import 'package:flutter/material.dart';
import 'core/theme/app_theme.dart';
import 'features/auth/presentation/screens/login_screen.dart';

class KiosBerkahApp extends StatelessWidget {
  const KiosBerkahApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Kios BERKAH',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.lightTheme,
      home: const LoginScreen(),
    );
  }
}
