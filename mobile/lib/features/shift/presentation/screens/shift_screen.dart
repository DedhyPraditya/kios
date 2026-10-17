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
  final bool _isShiftOpen = true;
  final _modalAwal = 100000;
  final _penjualanTunai = 385000;
  final _dpKasbon = 25000;
  final _kasMasuk = 0;
  final _kasKeluar = 15000;
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
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Tutup Kasir & Shift Laci', style: TextStyle(fontWeight: FontWeight.w800)),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 100),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Status Shift
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: _isShiftOpen ? AppColors.primarySurface : AppColors.surface,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(
                  color: _isShiftOpen ? AppColors.primary.withValues(alpha: 0.4) : AppColors.border,
                ),
              ),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: _isShiftOpen ? AppColors.primary : AppColors.surfaceMuted,
                      shape: BoxShape.circle,
                    ),
                    child: Icon(
                      _isShiftOpen ? Icons.lock_open_rounded : Icons.lock_outline_rounded,
                      color: _isShiftOpen ? AppColors.textOnPrimary : AppColors.textSecondary,
                      size: 20,
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          _isShiftOpen ? 'Shift Sedang Aktif' : 'Shift Belum Dibuka',
                          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          _isShiftOpen ? 'Dibuka hari ini, 07:45 WIB' : 'Buka shift untuk mencatat transaksi',
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
                  Text('Rekap Hitungan Laci', style: Theme.of(context).textTheme.titleMedium),
                  const Divider(height: 24),
                  _rekapRow('Modal Awal Laci', _modalAwal),
                  const SizedBox(height: 10),
                  _rekapRow('Penjualan Tunai', _penjualanTunai),
                  const SizedBox(height: 10),
                  _rekapRow('DP Kasbon', _dpKasbon),
                  const SizedBox(height: 10),
                  _rekapRow('Kas Masuk Lain', _kasMasuk),
                  const SizedBox(height: 10),
                  _rekapRow('Kas Keluar (Operasional)', -_kasKeluar, isNegative: true),
                  const Divider(height: 28),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Uang Seharusnya di Laci',
                        style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
                      ),
                      Text(
                        CurrencyFormatter.format(_uangSeharusnya),
                        style: AppTheme.dataMono(
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                          color: AppColors.primaryBright,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // Form Hitung Uang Fisik & Tutup Shift
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: AppColors.surface,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: AppColors.border),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text('Hitung Uang Fisik & Tutup Shift', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 14),
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
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: selisih == 0
                              ? AppColors.primarySurface
                              : (selisih < 0 ? AppColors.dangerSurface : AppColors.amberSurface),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(
                            color: selisih == 0
                                ? AppColors.primary.withValues(alpha: 0.3)
                                : (selisih < 0 ? AppColors.danger.withValues(alpha: 0.3) : AppColors.amber.withValues(alpha: 0.3)),
                          ),
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              selisih == 0 ? 'Status: Pas / Cocok' : (selisih < 0 ? 'Status: Kurang' : 'Status: Lebih'),
                              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
                            ),
                            Text(
                              CurrencyFormatter.format(selisih.abs()),
                              style: AppTheme.dataMono(
                                fontWeight: FontWeight.w800,
                                fontSize: 16,
                                color: selisih == 0
                                    ? AppColors.primaryBright
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
                    icon: Icons.check_circle_outline,
                    onPressed: () {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(
                          content: Text('Shift kasir berhasil ditutup!'),
                          backgroundColor: AppColors.primary,
                        ),
                      );
                    },
                  ),
                ],
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
            fontSize: 14,
            fontWeight: FontWeight.w600,
            color: isNegative ? AppColors.danger : AppColors.textPrimary,
          ),
        ),
      ],
    );
  }
}
