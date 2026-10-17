import 'package:flutter/material.dart';
import 'package:kios_berkah_mobile/core/constants/app_colors.dart';
import 'package:kios_berkah_mobile/features/arang/presentation/screens/arang_screen.dart';
import 'package:kios_berkah_mobile/features/pos/presentation/screens/pos_screen.dart';
import 'package:kios_berkah_mobile/features/sales/presentation/screens/sales_history_screen.dart';
import 'package:kios_berkah_mobile/features/settings/presentation/screens/settings_screen.dart';
import 'package:kios_berkah_mobile/features/shift/presentation/screens/shift_screen.dart';

class MainNavigationScreen extends StatefulWidget {
  const MainNavigationScreen({super.key});

  @override
  State<MainNavigationScreen> createState() => _MainNavigationScreenState();
}

class _MainNavigationScreenState extends State<MainNavigationScreen> {
  int _currentIndex = 0;

  final List<Widget> _screens = const [
    PosScreen(),
    SalesHistoryScreen(),
    ArangScreen(),
    ShiftScreen(),
    SettingsScreen(),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(
        index: _currentIndex,
        children: _screens,
      ),
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _currentIndex,
        onTap: (index) => setState(() => _currentIndex = index),
        type: BottomNavigationBarType.fixed,
        selectedItemColor: AppColors.primary,
        unselectedItemColor: AppColors.textSecondary,
        items: const [
          BottomNavigationBarItem(
            icon: Icon(Icons.point_of_sale_rounded),
            activeIcon: Icon(Icons.point_of_sale_rounded),
            label: 'Kasir',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.receipt_long_outlined),
            activeIcon: Icon(Icons.receipt_long_rounded),
            label: 'Riwayat',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.local_fire_department_outlined),
            activeIcon: Icon(Icons.local_fire_department_rounded),
            label: 'Arang',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.account_balance_wallet_outlined),
            activeIcon: Icon(Icons.account_balance_wallet_rounded),
            label: 'Shift',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.tune_rounded),
            activeIcon: Icon(Icons.tune_rounded),
            label: 'Setelan',
          ),
        ],
      ),
    );
  }
}
