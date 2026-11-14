import 'package:flutter/material.dart';

class AppColors {
  static const navy = Color(0xFF1B2F4B);
  static const teal = Color(0xFF0E7C86);
  static const tealDark = Color(0xFF08666E);
  static const tealLight = Color(0xFFE4F5F4);
  static const background = Color(0xFFF3F6FA);
  static const text = Color(0xFF203047);
  static const muted = Color(0xFF66768A);
  static const border = Color(0xFFE1E7EF);
  static const error = Color(0xFFC44747);
}

class AppTheme {
  static ThemeData get light {
    final scheme = ColorScheme.fromSeed(
      seedColor: AppColors.teal,
      primary: AppColors.teal,
      secondary: AppColors.navy,
      error: AppColors.error,
      surface: Colors.white,
    );

    OutlineInputBorder border(Color color) => OutlineInputBorder(
      borderRadius: BorderRadius.circular(11),
      borderSide: BorderSide(color: color),
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: AppColors.background,
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        contentPadding:
        const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
        border: border(const Color(0xFFD7E0E9)),
        enabledBorder: border(const Color(0xFFD7E0E9)),
        focusedBorder: border(AppColors.teal),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: AppColors.teal,
          foregroundColor: Colors.white,
          minimumSize: const Size.fromHeight(48),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          textStyle: const TextStyle(fontWeight: FontWeight.w800),
        ),
      ),
    );
  }
}