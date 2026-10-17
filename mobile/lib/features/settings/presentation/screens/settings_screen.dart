import 'dart:io';
import 'package:dio/dio.dart';
import 'package:dio/io.dart';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:kios_berkah_mobile/core/constants/api_endpoints.dart';
import 'package:kios_berkah_mobile/core/constants/app_colors.dart';
import 'package:kios_berkah_mobile/core/services/bluetooth_printer_service.dart';
import 'package:kios_berkah_mobile/core/widgets/app_button.dart';
import 'package:kios_berkah_mobile/core/widgets/app_text_field.dart';
import 'package:kios_berkah_mobile/features/auth/presentation/screens/login_screen.dart';
import 'package:kios_berkah_mobile/features/shift/presentation/screens/shift_screen.dart';
import 'package:print_bluetooth_thermal/print_bluetooth_thermal.dart';

class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  final _serverUrlController = TextEditingController(text: ApiEndpoints.defaultBaseUrl);
  final _printerService = BluetoothPrinterService.instance;

  bool _isBluetoothConnected = false;
  String? _printerName;
  String? _printerMac;
  bool _isLoadingPrinters = false;
  bool _isTestingServer = false;

  @override
  void initState() {
    super.initState();
    _initBluetoothPrinter();
    _loadSavedServerUrl();
  }

  Future<void> _loadSavedServerUrl() async {
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getString('kios_api_base_url')?.trim();
    if (saved != null && saved.isNotEmpty) {
      if (mounted) {
        setState(() {
          _serverUrlController.text = saved;
        });
      }
    }
  }

  Future<void> _handleTestAndSaveServer() async {
    final rawUrl = _serverUrlController.text.trim();
    if (rawUrl.isEmpty) {
      _showToast('Alamat URL tidak boleh kosong', isError: true);
      return;
    }
    final cleanUrl = rawUrl.endsWith('/') ? rawUrl.substring(0, rawUrl.length - 1) : rawUrl;

    setState(() => _isTestingServer = true);
    final messenger = ScaffoldMessenger.of(context);

    try {
      final dio = Dio(
        BaseOptions(
          connectTimeout: const Duration(seconds: 12),
          receiveTimeout: const Duration(seconds: 12),
          headers: {'Accept': 'application/json'},
        ),
      );
      (dio.httpClientAdapter as IOHttpClientAdapter).createHttpClient = () {
        final client = HttpClient();
        client.badCertificateCallback = (X509Certificate cert, String host, int port) => true;
        return client;
      };

      final response = await dio.get('$cleanUrl/pos');
      if (response.statusCode == 200 && response.data['success'] == true) {
        final count = (response.data['products'] as List?)?.length ?? 0;
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('kios_api_base_url', cleanUrl);

        if (!mounted) return;
        messenger.showSnackBar(
          SnackBar(
            content: Row(
              children: [
                const Icon(Icons.check_circle_rounded, color: Colors.white, size: 20),
                const SizedBox(width: 10),
                Expanded(child: Text('Koneksi Sukses! Ditemukan $count produk di server.')),
              ],
            ),
            backgroundColor: AppColors.primary,
            behavior: SnackBarBehavior.floating,
            margin: const EdgeInsets.fromLTRB(16, 0, 16, 88),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          ),
        );
      } else {
        throw 'Server merespon dengan status: ${response.statusCode}';
      }
    } catch (e) {
      if (!mounted) return;
      messenger.showSnackBar(
        SnackBar(
          content: Row(
            children: [
              const Icon(Icons.error_outline_rounded, color: Colors.white, size: 20),
              const SizedBox(width: 10),
              Expanded(child: Text('Gagal tersambung ke $cleanUrl:\n$e', maxLines: 3)),
            ],
          ),
          backgroundColor: AppColors.danger,
          behavior: SnackBarBehavior.floating,
          margin: const EdgeInsets.fromLTRB(16, 0, 16, 88),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          duration: const Duration(seconds: 5),
        ),
      );
    } finally {
      if (mounted) {
        setState(() => _isTestingServer = false);
      }
    }
  }

  Future<void> _initBluetoothPrinter() async {
    await _printerService.init();
    final isConn = await _printerService.isConnected();
    if (mounted) {
      setState(() {
        _isBluetoothConnected = isConn;
        _printerName = _printerService.connectedName ?? 'Belum ada printer dipilih';
        _printerMac = _printerService.connectedMac;
      });
    }
  }

  Future<void> _showPrinterPickerSheet() async {
    final enabled = await _printerService.isBluetoothEnabled();
    if (!enabled) {
      if (mounted) {
        _showToast('Silakan aktifkan Bluetooth di HP Anda terlebih dahulu.', isError: true);
      }
      return;
    }

    setState(() => _isLoadingPrinters = true);
    List<BluetoothInfo> devices = [];
    try {
      devices = await _printerService.getPairedPrinters();
    } catch (e) {
      debugPrint('Error ambil paired bluetooth: $e');
    }
    setState(() => _isLoadingPrinters = false);

    if (!mounted) return;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) {
        return StatefulBuilder(
          builder: (ctx, setSheetState) {
            return Padding(
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'Pilih Printer Bluetooth',
                        style: Theme.of(ctx).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                      ),
                      IconButton(
                        icon: const Icon(Icons.close_rounded, size: 20),
                        onPressed: () => Navigator.of(ctx).pop(),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Pilih printer thermal yang sudah dipasangkan (paired) di pengaturan HP Anda:',
                    style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
                  ),
                  const SizedBox(height: 16),
                  if (devices.isEmpty) ...[
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: AppColors.surfaceMuted,
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: const Column(
                        children: [
                          Icon(Icons.bluetooth_disabled_rounded, size: 36, color: AppColors.textSecondary),
                          SizedBox(height: 8),
                          Text(
                            'Tidak ada printer Bluetooth tersimpan',
                            style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                          ),
                          SizedBox(height: 4),
                          Text(
                            'Pastikan printer thermal sudah dihidupkan dan di-pairing di menu Bluetooth HP Android Anda (PIN biasanya 0000 atau 1234).',
                            textAlign: TextAlign.center,
                            style: TextStyle(fontSize: 11, color: AppColors.textSecondary),
                          ),
                        ],
                      ),
                    ),
                  ] else ...[
                    ConstrainedBox(
                      constraints: const BoxConstraints(maxHeight: 280),
                      child: ListView.separated(
                        shrinkWrap: true,
                        itemCount: devices.length,
                        separatorBuilder: (context, index) => const Divider(height: 12),
                        itemBuilder: (context, index) {
                          final device = devices[index];
                          final isCurrent = _printerMac == device.macAdress;

                          return ListTile(
                            contentPadding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                            leading: Container(
                              padding: const EdgeInsets.all(10),
                              decoration: BoxDecoration(
                                color: isCurrent ? AppColors.primarySurface : AppColors.surfaceMuted,
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: Icon(
                                Icons.print_rounded,
                                color: isCurrent ? AppColors.primary : AppColors.textSecondary,
                                size: 22,
                              ),
                            ),
                            title: Text(
                              device.name.isNotEmpty ? device.name : 'Printer Thermal',
                              style: TextStyle(
                                fontWeight: isCurrent ? FontWeight.w700 : FontWeight.w600,
                                fontSize: 14,
                                color: isCurrent ? AppColors.primary : AppColors.textPrimary,
                              ),
                            ),
                            subtitle: Text(
                              device.macAdress,
                              style: const TextStyle(fontSize: 11, color: AppColors.textSecondary),
                            ),
                            trailing: isCurrent && _isBluetoothConnected
                                ? const Chip(
                                    label: Text('Tersambung', style: TextStyle(fontSize: 10, color: Colors.white)),
                                    backgroundColor: AppColors.primary,
                                    padding: EdgeInsets.zero,
                                  )
                                : ElevatedButton(
                                    style: ElevatedButton.styleFrom(
                                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                      textStyle: const TextStyle(fontSize: 12),
                                    ),
                                    onPressed: () async {
                                      Navigator.of(ctx).pop();
                                      _connectToPrinter(device.macAdress, device.name);
                                    },
                                    child: const Text('Hubungkan'),
                                  ),
                          );
                        },
                      ),
                    ),
                  ],
                ],
              ),
            );
          },
        );
      },
    );
  }

  Future<void> _connectToPrinter(String mac, String name) async {
    _showToast('Menghubungkan ke $name...');
    final success = await _printerService.connect(mac, name);
    if (mounted) {
      setState(() {
        _isBluetoothConnected = success;
        _printerName = name;
        _printerMac = mac;
      });
      if (success) {
        _showToast('Berhasil terhubung ke $name!');
      } else {
        _showToast('Gagal terhubung ke $name. Pastikan printer hidup.', isError: true);
      }
    }
  }

  Future<void> _handleTestPrint() async {
    if (!_isBluetoothConnected) {
      _showToast('Printer belum tersambung. Silakan pilih printer terlebih dahulu.', isError: true);
      return;
    }

    _showToast('Mengirim struk tes ke printer...');
    final result = await _printerService.printTestReceipt();
    if (mounted) {
      if (result) {
        _showToast('Struk tes berhasil dicetak!');
      } else {
        _showToast('Gagal mencetak struk tes. Cek kertas & bluetooth.', isError: true);
      }
    }
  }

  void _showToast(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Row(
          children: [
            Icon(
              isError ? Icons.error_outline_rounded : Icons.check_circle_outline_rounded,
              color: Colors.white,
              size: 20,
            ),
            const SizedBox(width: 10),
            Expanded(child: Text(message)),
          ],
        ),
        backgroundColor: isError ? AppColors.danger : AppColors.primary,
        behavior: SnackBarBehavior.floating,
        margin: const EdgeInsets.fromLTRB(16, 0, 16, 88),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
    );
  }

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

                // Kartu Status Printer Saat Ini
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                  decoration: BoxDecoration(
                    color: AppColors.surfaceElevated,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: _isBluetoothConnected ? AppColors.primary.withValues(alpha: 0.5) : AppColors.border,
                    ),
                  ),
                  child: Row(
                    children: [
                      Icon(
                        _isBluetoothConnected ? Icons.bluetooth_connected_rounded : Icons.bluetooth_disabled_rounded,
                        color: _isBluetoothConnected ? AppColors.primary : AppColors.textSecondary,
                        size: 20,
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              _printerName ?? 'Belum ada printer dipilih',
                              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
                            ),
                            if (_printerMac != null)
                              Text(
                                _printerMac!,
                                style: const TextStyle(fontSize: 11, color: AppColors.textSecondary),
                              ),
                          ],
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                        decoration: BoxDecoration(
                          color: _isBluetoothConnected ? AppColors.primarySurface : AppColors.surfaceMuted,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          _isBluetoothConnected ? 'Tersambung' : 'Terputus',
                          style: TextStyle(
                            color: _isBluetoothConnected ? AppColors.primary : AppColors.textSecondary,
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),

                // Tombol Aksi: Pilih Printer & Tes Cetak
                Row(
                  children: [
                    Expanded(
                      child: AppButton(
                        label: 'Pilih Printer',
                        icon: Icons.bluetooth_searching_rounded,
                        variant: AppButtonVariant.outline,
                        height: 44,
                        isLoading: _isLoadingPrinters,
                        onPressed: _showPrinterPickerSheet,
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: AppButton(
                        label: 'Tes Cetak',
                        icon: Icons.receipt_long_rounded,
                        variant: AppButtonVariant.primary,
                        height: 44,
                        onPressed: _handleTestPrint,
                      ),
                    ),
                  ],
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
                Row(
                  children: [
                    Expanded(
                      flex: 3,
                      child: AppButton(
                        label: 'Tes & Simpan Koneksi',
                        icon: Icons.check_circle_outline_rounded,
                        variant: AppButtonVariant.primary,
                        height: 44,
                        isLoading: _isTestingServer,
                        onPressed: _handleTestAndSaveServer,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      flex: 2,
                      child: AppButton(
                        label: 'URL Produksi',
                        icon: Icons.restore_rounded,
                        variant: AppButtonVariant.outline,
                        height: 44,
                        onPressed: () {
                          _serverUrlController.text = ApiEndpoints.defaultBaseUrl;
                          _handleTestAndSaveServer();
                        },
                      ),
                    ),
                  ],
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
