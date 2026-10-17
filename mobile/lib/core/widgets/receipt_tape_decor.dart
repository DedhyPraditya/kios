import 'package:flutter/material.dart';
import '../constants/app_colors.dart';

/// Ornamen gerigi sobekan kertas struk (thermal receipt tape effect).
/// Ciri khas visual POS Kios BERKAH.
class ReceiptTapeDecor extends StatelessWidget {
  final double height;
  final Color color;

  const ReceiptTapeDecor({
    super.key,
    this.height = 8,
    this.color = AppColors.tapeBg,
  });

  @override
  Widget build(BuildContext context) {
    return CustomPaint(
      size: Size(double.infinity, height),
      painter: _ZigZagPainter(color: color),
    );
  }
}

class _ZigZagPainter extends CustomPainter {
  final Color color;

  _ZigZagPainter({required this.color});

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..style = PaintingStyle.fill;

    final path = Path();
    const toothWidth = 8.0;
    final teethCount = (size.width / toothWidth).ceil();

    path.moveTo(0, size.height);

    for (int i = 0; i < teethCount; i++) {
      final x = i * toothWidth;
      path.lineTo(x + toothWidth / 2, 0);
      path.lineTo(x + toothWidth, size.height);
    }

    path.lineTo(size.width, size.height);
    path.close();

    canvas.drawPath(path, paint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
