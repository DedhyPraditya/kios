import 'package:flutter/material.dart';
import 'package:kios_berkah_mobile/core/constants/app_colors.dart';
import 'package:kios_berkah_mobile/core/theme/app_theme.dart';
import 'package:kios_berkah_mobile/core/utils/currency_formatter.dart';
import 'package:kios_berkah_mobile/core/widgets/app_button.dart';
import 'package:kios_berkah_mobile/core/widgets/app_text_field.dart';

class ShiftScreen extends StatefulWidget {
  const ShiftScreen({super.key});

  @override
  State<ShiftScreen> createState() => _ShiftScreenState();
}

class _ShiftScreenState extends State<ShiftScreen> {
  bool _isShiftOpen = true;
  final _modalAwal = 100000;
  final _penjualanTunai = 385000;
  final _dpKasbon = 25000;
  final _kasMasuk = 0;
  final _kasKeluar = 15000; // misal beli kantong kresek
  final _uangDihitungController = TextEditingController();

  int get _uangSeharusnya => _modalAwal + _penjualanTunai + _dpKasbon + _kasMasuk - _kasKeluar;

  @override
  void dispose() {
    _uangDihitungController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Tutup Kasir & Shift Laci'),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Status Shift
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: _isShiftOpen ? AppColors.primarySurface : AppColors.surfaceMuted,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: _isShiftOpen ? AppColors.primary.withValues(alpha: 0.3) : AppColors.border),
              ),
              child: Row(
                children: [
                  Icon(
                    _isShiftOpen ? Icons.lock_open_rounded : Icons.lock_outline_rounded,
                    color: _isShiftOpen ? AppColors.primary : AppColors.textSecondary,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          _isShiftOpen ? 'Shift Sedang Aktif' : 'Shift Belum Dibuka',
                          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
                        ),
                        Text(
                          _isShiftOpen ? 'Dibuka hari ini, 07:45 WIB' : 'Buka shift untuk merekap transaksi kasir',
                          style: const TextStyle(fontSize: 12, color: AppColors.textSecondary),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // Ringkasan Laci Kasir
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Rekap Hitungan Laci', style: Theme.of(context).textTheme.titleMedium),
                    const Divider(height: 20),
                    _rekapRow('Modal Awal Laci', _modalAwal),
                    const SizedBox(height: 8),
                    _rekapRow('Penjualan Tunai', _penjualanTunai),
                    const SizedBox(height: 8),
                    _rekapRow('DP Kasbon', _dpKasbon),
                    const SizedBox(height: 8),
                    _rekapRow('Kas Masuk Lain', _kasMasuk),
                    const SizedBox(height: 8),
                    _rekapRow('Kas Keluar (Beli Plastik, dll)', -_kasKeluar, isNegative: true),
                    const Divider(height: 24),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Uang Seharusnya di Laci', style: TextStyle(fontWeight: FontWeight.w700)),
                        Text(
                          CurrencyFormatter.format(_uangSeharusnya),
                          style: AppTheme.dataMono(
                            fontSize: 16,
                            fontWeight: FontWeight.w800,
                            color: AppColors.primary,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),

            // Form Tutup Kasir
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text('Hitung Uang Fisik & Tutup Shift', style: Theme.of(context).textTheme.titleMedium),
                    const SizedBox(height: 12),
                    AppTextField(
                      controller: _uangDihitungController,
                      label: 'Uang Fisik Dihitung (Rp)',
                      hint: 'Masukkan total uang di laci...',
                      keyboardType: TextInputType.number,
                      onChanged: (_) => setState(() {}),
                    ),
                    const SizedBox(height: 16),
                    Builder(
                      builder: (ctx) {
                        final dihitung = int.tryParse(_uangDihitungController.text) ?? 0;
                        if (_uangDihitungController.text.isEmpty) return const SizedBox.shrink();
                        final selisih = dihitung - _uangSeharusnya;
                        return Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: selisih == 0
                                ? AppColors.successSurface
                                : (selisih < 0 ? AppColors.dangerSurface : AppColors.amberSurface),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(
                                selisih == 0 ? 'Status: Pas / Cocok' : (selisih < 0 ? 'Status: Kurang' : 'Status: Lebih'),
                                style: const TextStyle(fontWeight: FontWeight.w700),
                              ),
                              Text(
                                CurrencyFormatter.format(selisih.abs()),
                                style: AppTheme.dataMono(
                                  fontWeight: FontWeight.w800,
                                  color: selisih == 0
                                      ? AppColors.success
                                      : (selisih < 0 ? AppColors.danger : AppColors.amberText),
                                ),
                              ),
                            ],
                          ),
                        );
                      },
                    ),
                    const SizedBox(height: 16),
                    AppButton(
                      label: 'Tutup Shift Sekarang',
                      variant: AppButtonVariant.primary,
                      icon: Icons.check_circle_outline,
                      onPressed: () {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Shift berhasil ditutup & selisih tercatat.')),
                        );
                      },
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _rekapRow(String label, int value, {bool isNegative = false}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: const TextStyle(color: AppColors.textSecondary, fontSize: 13)),
        Text(
          CurrencyFormatter.format(value.abs()),
          style: AppTheme.dataMono(
            fontSize: 13,
            fontWeight: FontWeight.w600,
            color: isNegative ? AppColors.danger : AppColors.textPrimary,
          ),
        ),
      ],
    );
  }
}
