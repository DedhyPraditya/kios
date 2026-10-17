import 'package:flutter/material.dart';
import 'package:kios_berkah_mobile/core/constants/app_colors.dart';
import 'package:kios_berkah_mobile/core/widgets/app_button.dart';
import 'package:kios_berkah_mobile/core/widgets/app_text_field.dart';
import 'package:kios_berkah_mobile/features/auth/presentation/screens/login_screen.dart';

class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  final _serverUrlController = TextEditingController(text: 'https://kios.ndrsolution.my.id/api');
  String _selectedPrinter = 'RPP02N Thermal (58mm)';

  @override
  void dispose() {
    _serverUrlController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Pengaturan & Perangkat'),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Sambungan Printer Thermal Bluetooth
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const Icon(Icons.print_rounded, color: AppColors.primary),
                      const SizedBox(width: 8),
                      Text('Printer Thermal Bluetooth', style: Theme.of(context).textTheme.titleMedium),
                    ],
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Pindai dan sambungkan printer kasir 58mm/80mm',
                    style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
                  ),
                  const SizedBox(height: 16),
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: AppColors.surfaceMuted,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(_selectedPrinter, style: const TextStyle(fontWeight: FontWeight.w600)),
                        const Icon(Icons.bluetooth_connected, color: AppColors.primary, size: 20),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),
                  AppButton(
                    label: 'Cari Perangkat Bluetooth',
                    icon: Icons.bluetooth_searching_rounded,
                    variant: AppButtonVariant.outline,
                    onPressed: () {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Memindai perangkat Bluetooth thermal terdekat...')),
                      );
                    },
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),

          // Server Endpoint
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const Icon(Icons.cloud_sync_rounded, color: AppColors.teal),
                      const SizedBox(width: 8),
                      Text('Server & Basis Data', style: Theme.of(context).textTheme.titleMedium),
                    ],
                  ),
                  const SizedBox(height: 12),
                  AppTextField(
                    controller: _serverUrlController,
                    label: 'Alamat API Server',
                    hint: 'http://192.168.1.x:8000/api atau https://...',
                  ),
                  const SizedBox(height: 12),
                  AppButton(
                    label: 'Tes & Simpan Koneksi',
                    variant: AppButtonVariant.primary,
                    onPressed: () {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Koneksi server tersimpan.')),
                      );
                    },
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),

          // Identitas Toko
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Identitas Toko', style: Theme.of(context).textTheme.titleMedium),
                  const Divider(height: 16),
                  _infoTile('Nama Toko', 'Kios BERKAH'),
                  _infoTile('Status Akun', 'Admin Toko (admin@kios.test)'),
                  _infoTile('Versi Aplikasi', 'v1.0.0 (Native Flutter Android 8+)'),
                ],
              ),
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
      padding: const EdgeInsets.symmetric(vertical: 4),
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
