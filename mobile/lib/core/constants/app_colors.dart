import 'package:flutter/material.dart';

/// Palet warna Vendra POS x Kios BERKAH (Opsi B: Deep Emerald Modern).
/// - Canvas gelap berkelas (Deep Dark #0D1410 / #111B15).
/// - Aksen utama Emerald Green (#22C55E / #10B981) yang menyala tajam dan profesional.
/// - Permukaan kartu elevated (#17231C) dengan border tegas (#25382D).
/// - Aksen peringatan Amber (#F59E0B) & Danger (#EF4444).
/// - Teks kontras tinggi: Putih (#F8FAFC) & Slate Muted (#94A3B8).
class AppColors {
  AppColors._();

  // Background Canvas (Deep Emerald Tone)
  static const Color background = Color(0xFF0D1410);
  static const Color backgroundLight = Color(0xFFF4F6F5);

  // Cards & Surfaces
  static const Color surface = Color(0xFF141F18);
  static const Color surfaceElevated = Color(0xFF1B2A21);
  static const Color surfaceMuted = Color(0xFF22352A);

  // Borders & Dividers
  static const Color border = Color(0xFF25392E);
  static const Color borderSubtle = Color(0xFF1C2C23);

  // Primary Accent: Kios BERKAH Vibrant Emerald
  static const Color primary = Color(0xFF10B981);
  static const Color primaryBright = Color(0xFF34D399);
  static const Color primaryDark = Color(0xFF059669);
  static const Color primarySurface = Color(0xFF133224);

  // Secondary Accents
  static const Color amber = Color(0xFFF59E0B);
  static const Color amberSurface = Color(0xFF32240A);
  static const Color amberText = Color(0xFFFBBF24);

  static const Color teal = Color(0xFF14B8A6);
  static const Color tealSurface = Color(0xFF0F3631);

  static const Color danger = Color(0xFFEF4444);
  static const Color dangerSurface = Color(0xFF3B1515);

  static const Color success = Color(0xFF10B981);
  static const Color successSurface = Color(0xFF133224);

  // Typography / Ink
  static const Color textPrimary = Color(0xFFF8FAFC);
  static const Color textSecondary = Color(0xFF94A3B8);
  static const Color textMuted = Color(0xFF64748B);
  static const Color textOnPrimary = Color(0xFF062315);

  // Navigation Bar Melayang
  static const Color navBackground = Color(0xFF15221A);
  static const Color navBorder = Color(0xFF293F33);

  // Struk / Thermal Paper Simulation
  static const Color tapeBg = Color(0xFFFAFAF9);
  static const Color tapeBorder = Color(0xFFE7E5E4);
}
