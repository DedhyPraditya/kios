import 'package:flutter/material.dart';
import 'package:kios_berkah_mobile/core/constants/app_colors.dart';
import 'package:kios_berkah_mobile/core/theme/app_theme.dart';

/// Badge indikator stok produk bergaya minimalis Vendra POS.
class StockBadge extends StatelessWidget {
  final int stock;
  final int threshold;

  const StockBadge({
    super.key,
    required this.stock,
    this.threshold = 5,
  });

  @override
  Widget build(BuildContext context) {
    Color bg;
    Color fg;
    String label;

    if (stock <= 0) {
      bg = AppColors.dangerSurface;
      fg = AppColors.danger;
      label = 'Habis';
    } else if (stock <= threshold) {
      bg = AppColors.dangerSurface;
      fg = AppColors.danger;
      label = '$stock pcs';
    } else if (stock <= threshold * 2) {
      bg = AppColors.amberSurface;
      fg = AppColors.amberText;
      label = '$stock pcs';
    } else {
      bg = AppColors.primarySurface;
      fg = AppColors.primary;
      label = '$stock pcs';
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: fg.withValues(alpha: 0.25), width: 1),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 6,
            height: 6,
            decoration: BoxDecoration(
              color: fg,
              shape: BoxShape.circle,
            ),
          ),
          const SizedBox(width: 5),
          Text(
            label,
            style: AppTheme.dataMono(
              fontSize: 11,
              fontWeight: FontWeight.w700,
              color: fg,
            ),
          ),
        ],
      ),
    );
  }
}
