import 'package:flutter/material.dart';
import '../constants/app_colors.dart';

enum AppButtonVariant { primary, secondary, danger, outline }

class AppButton extends StatelessWidget {
  final String label;
  final VoidCallback? onPressed;
  final AppButtonVariant variant;
  final IconData? icon;
  final bool isLoading;
  final double? width;
  final double height;

  const AppButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.variant = AppButtonVariant.primary,
    this.icon,
    this.isLoading = false,
    this.width,
    this.height = 48,
  });

  @override
  Widget build(BuildContext context) {
    Color bg;
    Color fg;
    BorderSide? border;

    switch (variant) {
      case AppButtonVariant.primary:
        bg = AppColors.primary;
        fg = Colors.white;
        border = null;
        break;
      case AppButtonVariant.secondary:
        bg = AppColors.amber;
        fg = Colors.white;
        border = null;
        break;
      case AppButtonVariant.danger:
        bg = AppColors.danger;
        fg = Colors.white;
        border = null;
        break;
      case AppButtonVariant.outline:
        bg = Colors.white;
        fg = AppColors.textPrimary;
        border = const BorderSide(color: AppColors.border);
        break;
    }

    return SizedBox(
      width: width,
      height: height,
      child: Material(
        color: onPressed == null ? AppColors.surfaceMuted : bg,
        borderRadius: BorderRadius.circular(10),
        child: InkWell(
          onTap: isLoading ? null : onPressed,
          borderRadius: BorderRadius.circular(10),
          child: Container(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(10),
              border: border != null ? Border.fromBorderSide(border) : null,
            ),
            padding: const EdgeInsets.symmetric(horizontal: 16),
            alignment: Alignment.center,
            child: isLoading
                ? SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      valueColor: AlwaysStoppedAnimation<Color>(fg),
                    ),
                  )
                : Row(
                    mainAxisSize: MainAxisSize.min,
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      if (icon != null) ...[
                        Icon(icon, size: 18, color: fg),
                        const SizedBox(width: 8),
                      ],
                      Text(
                        label,
                        style: TextStyle(
                          color: fg,
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
          ),
        ),
      ),
    );
  }
}
