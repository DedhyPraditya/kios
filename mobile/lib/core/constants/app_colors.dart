import 'package:flutter/material.dart';

/// Palet warna Resmi Kios BERKAH (Clean White / Light Theme).
/// - Latar belakang putih bersih & segar (#F8FAFC).
/// - Kartu & kontainer putih murni (#FFFFFF) dengan border halus (#E2E8F0).
/// - Aksen utama Forest Green (#1E6B4F) yang profesional dan tegas.
/// - Teks gelap tajam & kontras tinggi (#0F172A & #475569) untuk kemudahan membaca kasir.
class AppColors {
  AppColors._();

  // Background Canvas (Putih Segar & Bersih)
  static const Color background = Color(0xFFF8FAFC);
  static const Color backgroundLight = Color(0xFFFFFFFF);

  // Cards & Surfaces
  static const Color surface = Color(0xFFFFFFFF);
  static const Color surfaceElevated = Color(0xFFFFFFFF);
  static const Color surfaceMuted = Color(0xFFF1F5F9);

  // Borders & Dividers
  static const Color border = Color(0xFFE2E8F0);
  static const Color borderSubtle = Color(0xFFEDF2F7);

  // Primary Accent: Forest Green Kios BERKAH
  static const Color primary = Color(0xFF1E6B4F);
  static const Color primaryBright = Color(0xFF22C55E);
  static const Color primaryDark = Color(0xFF16503B);
  static const Color primarySurface = Color(0xFFE8F5EE);

  // Secondary Accents
  static const Color amber = Color(0xFFD97706);
  static const Color amberSurface = Color(0xFFFEF3C7);
  static const Color amberText = Color(0xFFB45309);

  static const Color teal = Color(0xFF0D9488);
  static const Color tealSurface = Color(0xFFCCFBF1);

  static const Color danger = Color(0xFFDC2626);
  static const Color dangerSurface = Color(0xFFFEE2E2);

  static const Color success = Color(0xFF16A34A);
  static const Color successSurface = Color(0xFFDCFCE7);

  // Typography / Ink
  static const Color textPrimary = Color(0xFF0F172A);
  static const Color textSecondary = Color(0xFF64748B);
  static const Color textMuted = Color(0xFF94A3B8);
  static const Color textOnPrimary = Colors.white;

  // Floating Pill Navigation Bar (Putih Bersih dengan Soft Shadow)
  static const Color navBackground = Color(0xFFFFFFFF);
  static const Color navBorder = Color(0xFFE2E8F0);

  // Struk Thermal Paper
  static const Color tapeBg = Color(0xFFFAFAF9);
  static const Color tapeBorder = Color(0xFFE7E5E4);
}
