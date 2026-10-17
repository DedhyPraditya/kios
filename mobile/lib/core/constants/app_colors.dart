import 'package:flutter/material.dart';

/// Palet warna resmi Kios BERKAH.
/// Mengikuti standar dari acuan desain (design.md) dan web POS:
/// - Dominan Forest Green (#1E6B4F) yang bersih, tenang, dan profesional.
/// - Aksen Amber (#F4A261) untuk peringatan & perhatian.
/// - Latar belakang netral hangat (#F4F6F5) dan kartu putih bersih (#FFFFFF).
/// - Bebas dari corak 'AI slop' yang berantakan atau generik.
class AppColors {
  AppColors._();

  // Primary
  static const Color primary = Color(0xFF1E6B4F);
  static const Color primaryDark = Color(0xFF16503B);
  static const Color primaryLight = Color(0xFF2A8B67);
  static const Color primarySurface = Color(0xFFE8F3EE);

  // Accents & Badges
  static const Color amber = Color(0xFFF4A261);
  static const Color amberSurface = Color(0xFFFEF4EB);
  static const Color amberText = Color(0xFF7A3D06);

  static const Color teal = Color(0xFF2A9D8F);
  static const Color tealSurface = Color(0xFFE6F5F3);

  static const Color danger = Color(0xFFBA1A1A);
  static const Color dangerSurface = Color(0xFFFCE8E8);

  static const Color success = Color(0xFF1B8754);
  static const Color successSurface = Color(0xFFE8F6EF);

  // Neutrals & Surface
  static const Color background = Color(0xFFF4F6F5);
  static const Color card = Color(0xFFFFFFFF);
  static const Color surfaceMuted = Color(0xFFEDF1EF);
  static const Color border = Color(0xFFE2E8F0);
  static const Color borderSubtle = Color(0xFFECEFF1);

  // Text / Ink
  static const Color textPrimary = Color(0xFF121212);
  static const Color textSecondary = Color(0xFF64748B);
  static const Color textMuted = Color(0xFF94A3B8);
  static const Color textOnPrimary = Colors.white;

  // Struk / Thermal Paper Simulation
  static const Color tapeBg = Color(0xFFFAFAF9);
  static const Color tapeBorder = Color(0xFFE7E5E4);
}
