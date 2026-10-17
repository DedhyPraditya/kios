import 'package:flutter/material.dart';
import 'package:kios_berkah_mobile/core/constants/app_colors.dart';
import 'package:kios_berkah_mobile/core/theme/app_theme.dart';
import 'package:kios_berkah_mobile/core/utils/currency_formatter.dart';
import 'package:kios_berkah_mobile/core/widgets/app_button.dart';
import 'package:kios_berkah_mobile/core/widgets/app_text_field.dart';

class ArangScreen extends StatefulWidget {
  const ArangScreen({super.key});

  @override
  State<ArangScreen> createState() => _ArangScreenState();
}

class _ArangScreenState extends State<ArangScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final _beratJualController = TextEditingController();
  final _hargaJualController = TextEditingController(text: '8000');
  final _beratBeliController = TextEditingController();
  final _hargaBeliController = TextEditingController(text: '5000');

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    _beratJualController.dispose();
    _hargaJualController.dispose();
    _beratBeliController.dispose();
    _hargaBeliController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Modul Arang Kiloan', style: TextStyle(fontWeight: FontWeight.w800)),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(48),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
            child: Container(
              height: 40,
              decoration: BoxDecoration(
                color: AppColors.surface,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: AppColors.border),
              ),
              child: TabBar(
                controller: _tabController,
                indicator: BoxDecoration(
                  color: AppColors.primary,
                  borderRadius: BorderRadius.circular(18),
                ),
                indicatorSize: TabBarIndicatorSize.tab,
                labelColor: AppColors.textOnPrimary,
                unselectedLabelColor: AppColors.textSecondary,
                labelStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
                dividerColor: Colors.transparent,
                tabs: const [
                  Tab(text: 'Jual Arang'),
                  Tab(text: 'Beli Arang'),
                ],
              ),
            ),
          ),
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          // Tab Jual Arang
          SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Container(
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(
                    color: AppColors.surface,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: AppColors.border),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        'Penjualan Arang ke Pembeli',
                        style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                      ),
                      const SizedBox(height: 16),
                      AppTextField(
                        controller: _beratJualController,
                        label: 'Berat Timbangan (Kg)',
                        hint: 'Contoh: 12.5',
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
                        onChanged: (_) => setState(() {}),
                      ),
                      const SizedBox(height: 14),
                      AppTextField(
                        controller: _hargaJualController,
                        label: 'Harga per Kg (Rp)',
                        hint: '8000',
                        keyboardType: TextInputType.number,
                        onChanged: (_) => setState(() {}),
                      ),
                      const Divider(height: 32),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text(
                            'Total Pembayaran',
                            style: TextStyle(fontWeight: FontWeight.w600, fontSize: 15),
                          ),
                          Builder(
                            builder: (context) {
                              final berat = double.tryParse(_beratJualController.text) ?? 0.0;
                              final harga = int.tryParse(_hargaJualController.text) ?? 0;
                              final total = (berat * harga).round();
                              return Text(
                                CurrencyFormatter.format(total),
                                style: AppTheme.dataMono(
                                  fontSize: 20,
                                  fontWeight: FontWeight.w800,
                                  color: AppColors.primaryBright,
                                ),
                              );
                            },
                          ),
                        ],
                      ),
                      const SizedBox(height: 20),
                      AppButton(
                        label: 'Simpan & Cetak Struk Arang',
                        icon: Icons.print_outlined,
                        onPressed: () {
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(
                              content: Text('Penjualan arang tersimpan! Mencetak nota...'),
                              backgroundColor: AppColors.primary,
                              behavior: SnackBarBehavior.floating,
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

          // Tab Beli Arang
          SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Container(
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(
                    color: AppColors.surface,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: AppColors.border),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        'Pembelian Arang dari Pemasok',
                        style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                      ),
                      const SizedBox(height: 16),
                      AppTextField(
                        controller: _beratBeliController,
                        label: 'Berat Masuk (Kg)',
                        hint: 'Contoh: 50.0',
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
                        onChanged: (_) => setState(() {}),
                      ),
                      const SizedBox(height: 14),
                      AppTextField(
                        controller: _hargaBeliController,
                        label: 'Harga Beli per Kg (Rp)',
                        hint: '5000',
                        keyboardType: TextInputType.number,
                        onChanged: (_) => setState(() {}),
                      ),
                      const Divider(height: 32),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text(
                            'Total Dibayar ke Pemasok',
                            style: TextStyle(fontWeight: FontWeight.w600, fontSize: 15),
                          ),
                          Builder(
                            builder: (context) {
                              final berat = double.tryParse(_beratBeliController.text) ?? 0.0;
                              final harga = int.tryParse(_hargaBeliController.text) ?? 0;
                              final total = (berat * harga).round();
                              return Text(
                                CurrencyFormatter.format(total),
                                style: AppTheme.dataMono(
                                  fontSize: 20,
                                  fontWeight: FontWeight.w800,
                                  color: AppColors.danger,
                                ),
                              );
                            },
                          ),
                        ],
                      ),
                      const SizedBox(height: 20),
                      AppButton(
                        label: 'Catat Pembelian Arang',
                        onPressed: () {
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(
                              content: Text('Pembelian arang berhasil dicatat!'),
                              backgroundColor: AppColors.primary,
                              behavior: SnackBarBehavior.floating,
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
        ],
      ),
    );
  }
}
