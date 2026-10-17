import 'package:flutter/material.dart';
import 'package:kios_berkah_mobile/core/constants/app_colors.dart';
import 'package:kios_berkah_mobile/features/arang/presentation/screens/arang_screen.dart';
import 'package:kios_berkah_mobile/features/pos/presentation/screens/pos_screen.dart';
import 'package:kios_berkah_mobile/features/sales/presentation/screens/sales_history_screen.dart';
import 'package:kios_berkah_mobile/features/settings/presentation/screens/settings_screen.dart';

import 'package:kios_berkah_mobile/features/scanner/presentation/screens/barcode_scanner_screen.dart';
import 'package:kios_berkah_mobile/features/scanner/data/models/scan_result.dart';

class MainNavigationScreen extends StatefulWidget {
  const MainNavigationScreen({super.key});

  @override
  State<MainNavigationScreen> createState() => _MainNavigationScreenState();
}

class _MainNavigationScreenState extends State<MainNavigationScreen> {
  int _currentIndex = 0;
  final GlobalKey<PosScreenState> _posKey = GlobalKey<PosScreenState>();

  late final List<Widget> _screens = [
    PosScreen(key: _posKey),
    const SalesHistoryScreen(),
    const ArangScreen(),
    const SettingsScreen(),
  ];

  Future<void> _onScanBarcodePressed() async {
    if (_currentIndex != 0) {
      setState(() => _currentIndex = 0);
    }
    final posState = _posKey.currentState;
    final shouldOpenCart = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => BarcodeScannerScreen(
          initialCartCount: posState?.cartItemCount ?? 0,
          initialCartTotal: posState?.cartTotal ?? 0,
          onScanBarcode: (code) async {
            if (_posKey.currentState != null) {
              return await _posKey.currentState!.scanAndAddToCart(code);
            }
            return ScanResult(success: false, message: 'Kasir belum siap');
          },
        ),
      ),
    );
    if (shouldOpenCart == true && _posKey.currentState != null && _posKey.currentState!.cartItemCount > 0) {
      _posKey.currentState?.showCartSheetExternal();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      extendBody: false,
      body: IndexedStack(
        index: _currentIndex,
        children: _screens,
      ),
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          border: const Border(top: BorderSide(color: AppColors.border, width: 1)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.05),
              blurRadius: 8,
              offset: const Offset(0, -2),
            ),
          ],
        ),
        child: SafeArea(
          top: false,
          child: SizedBox(
            height: 64,
            child: Align(
              alignment: Alignment.center,
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 580),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                  children: [
                    _navItem(0, Icons.point_of_sale_rounded, 'Kasir'),
                    _navItem(1, Icons.receipt_long_rounded, 'Riwayat'),

                    // Tombol Tengah Floating Scan Barcode
                    GestureDetector(
                      onTap: _onScanBarcodePressed,
                      child: Container(
                        width: 48,
                        height: 48,
                        decoration: BoxDecoration(
                          gradient: const LinearGradient(
                            colors: [AppColors.primary, AppColors.primaryBright],
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                          ),
                          shape: BoxShape.circle,
                          boxShadow: [
                            BoxShadow(
                              color: AppColors.primary.withValues(alpha: 0.35),
                              blurRadius: 10,
                              offset: const Offset(0, 3),
                            ),
                          ],
                        ),
                        child: const Icon(
                          Icons.qr_code_scanner_rounded,
                          color: Colors.white,
                          size: 24,
                        ),
                      ),
                    ),

                    _navItem(2, Icons.local_fire_department_rounded, 'Arang'),
                    _navItem(3, Icons.tune_rounded, 'Setelan'),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _navItem(int index, IconData icon, String label) {
    final isSelected = _currentIndex == index;
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: () => setState(() => _currentIndex = index),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        decoration: BoxDecoration(
          color: isSelected ? AppColors.primarySurface : Colors.transparent,
          borderRadius: BorderRadius.circular(20),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              icon,
              size: 22,
              color: isSelected ? AppColors.primary : AppColors.textSecondary,
            ),
            const SizedBox(height: 3),
            Text(
              label,
              style: TextStyle(
                fontSize: 11,
                fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                color: isSelected ? AppColors.primary : AppColors.textSecondary,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
