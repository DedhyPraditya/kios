import 'package:flutter/material.dart';
import 'package:kios_berkah_mobile/core/constants/app_colors.dart';
import 'package:kios_berkah_mobile/core/widgets/app_button.dart';
import 'package:kios_berkah_mobile/core/widgets/app_text_field.dart';
import 'package:kios_berkah_mobile/features/auth/presentation/screens/login_screen.dart';
import 'package:kios_berkah_mobile/features/shift/presentation/screens/shift_screen.dart';

class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  final _serverUrlController = TextEditingController(text: 'https://kios.ndrsolution.my.id/api');
  final String _selectedPrinter = 'RPP02N Thermal (58mm)';

  @override
  void dispose() {
    _serverUrlController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Setelan & Shift', style: TextStyle(fontWeight: FontWeight.w800)),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 120),
        children: [
          // Navigasi Cepat ke Tutup Kasir / Shift
          Container(
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: AppColors.primary.withValues(alpha: 0.3)),
            ),
            child: ListTile(
              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
              leading: Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: AppColors.primarySurface,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(Icons.account_balance_wallet_rounded, color: AppColors.primaryBright),
              ),
              title: const Text(
                'Tutup Kasir & Shift Laci',
                style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
              ),
              subtitle: const Text(
                'Rekap uang fisik laci, kas masuk/keluar, dan selisih',
                style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
              ),
              trailing: const Icon(Icons.chevron_right_rounded, color: AppColors.textSecondary),
              onTap: () {
                Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const ShiftScreen()),
                );
              },
            ),
          ),
          const SizedBox(height: 16),

          // Sambungan Printer Thermal Bluetooth
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: AppColors.border),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: AppColors.primarySurface,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: const Icon(Icons.print_rounded, color: AppColors.primaryBright, size: 20),
                    ),
                    const SizedBox(width: 10),
                    Text('Printer Thermal Bluetooth', style: Theme.of(context).textTheme.titleMedium),
                  ],
                ),
                const SizedBox(height: 6),
                const Text(
                  'Format struk disetel untuk kertas 58mm & 80mm',
                  style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
                ),
                const SizedBox(height: 14),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                  decoration: BoxDecoration(
                    color: AppColors.surfaceElevated,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: AppColors.border),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(_selectedPrinter, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                      const Row(
                        children: [
                          Icon(Icons.bluetooth_connected, color: AppColors.primaryBright, size: 18),
                          SizedBox(width: 4),
                          Text(
                            'Tersambung',
                            style: TextStyle(color: AppColors.primaryBright, fontSize: 12, fontWeight: FontWeight.w700),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                AppButton(
                  label: 'Pindai Ulang Bluetooth',
                  icon: Icons.bluetooth_searching_rounded,
                  variant: AppButtonVariant.outline,
                  height: 44,
                  onPressed: () {
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        content: const Row(
                          children: [
                            Icon(Icons.bluetooth_searching_rounded, color: Colors.white, size: 20),
                            SizedBox(width: 10),
                            Text('Memindai printer Bluetooth terdekat...'),
                          ],
                        ),
                        backgroundColor: AppColors.primary,
                        behavior: SnackBarBehavior.floating,
                        margin: const EdgeInsets.fromLTRB(16, 0, 16, 88),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                      ),
                    );
                  },
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),

          // Server Endpoint
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: AppColors.border),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: AppColors.tealSurface,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: const Icon(Icons.cloud_sync_rounded, color: AppColors.teal, size: 20),
                    ),
                    const SizedBox(width: 10),
                    Text('Server & Basis Data', style: Theme.of(context).textTheme.titleMedium),
                  ],
                ),
                const SizedBox(height: 14),
                AppTextField(
                  controller: _serverUrlController,
                  label: 'Alamat Endpoint API Backend',
                  hint: 'https://kios.ndrsolution.my.id/api',
                ),
                const SizedBox(height: 12),
                AppButton(
                  label: 'Tes & Simpan Koneksi Server',
                  variant: AppButtonVariant.primary,
                  height: 44,
                  onPressed: () {
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        content: const Row(
                          children: [
                            Icon(Icons.check_circle_outline_rounded, color: Colors.white, size: 20),
                            SizedBox(width: 10),
                            Text('Koneksi backend server berhasil diverifikasi!'),
                          ],
                        ),
                        backgroundColor: AppColors.primary,
                        behavior: SnackBarBehavior.floating,
                        margin: const EdgeInsets.fromLTRB(16, 0, 16, 88),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                      ),
                    );
                  },
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),

          // Info Toko & Akun
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: AppColors.border),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Informasi Sistem', style: Theme.of(context).textTheme.titleMedium),
                const Divider(height: 20),
                _infoTile('Nama Toko', 'Kios BERKAH'),
                _infoTile('Akun Aktif', 'Admin Toko (admin@kios.test)'),
                _infoTile('Mesin Kasir', 'Flutter Android 8+ (Vendra POS Design)'),
              ],
            ),
          ),
          const SizedBox(height: 24),

          // Tombol Keluar
          AppButton(
            label: 'Keluar Akun Kasir',
            icon: Icons.logout_rounded,
            variant: AppButtonVariant.danger,
            onPressed: () {
              Navigator.of(context).pushAndRemoveUntil(
                MaterialPageRoute(builder: (_) => const LoginScreen()),
                (route) => false,
              );
            },
          ),
        ],
      ),
    );
  }

  Widget _infoTile(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: AppColors.textSecondary, fontSize: 13)),
          Text(value, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
        ],
      ),
    );
  }
}
