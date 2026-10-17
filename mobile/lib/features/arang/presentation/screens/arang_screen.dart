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
      appBar: AppBar(
        title: const Text('Modul Arang Kiloan'),
        bottom: TabBar(
          controller: _tabController,
          labelColor: AppColors.primary,
          unselectedLabelColor: AppColors.textSecondary,
          indicatorColor: AppColors.primary,
          indicatorWeight: 3,
          tabs: const [
            Tab(text: 'Penjualan Arang', icon: Icon(Icons.sell_outlined, size: 20)),
            Tab(text: 'Pembelian Arang', icon: Icon(Icons.shopping_bag_outlined, size: 20)),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          // Tab Jual Arang
          SingleChildScrollView(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Text('Jual Arang ke Pelanggan', style: Theme.of(context).textTheme.titleMedium),
                        const SizedBox(height: 16),
                        AppTextField(
                          controller: _beratJualController,
                          label: 'Berat Timbangan (Kg)',
                          hint: 'Contoh: 12.5',
                          keyboardType: const TextInputType.numberWithOptions(decimal: true),
                          onChanged: (_) => setState(() {}),
                        ),
                        const SizedBox(height: 12),
                        AppTextField(
                          controller: _hargaJualController,
                          label: 'Harga per Kg',
                          hint: '8000',
                          keyboardType: TextInputType.number,
                          onChanged: (_) => setState(() {}),
                        ),
                        const Divider(height: 28),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text('Total Pembayaran', style: TextStyle(fontWeight: FontWeight.w600)),
                            Builder(
                              builder: (context) {
                                final berat = double.tryParse(_beratJualController.text) ?? 0.0;
                                final harga = int.tryParse(_hargaJualController.text) ?? 0;
                                final total = (berat * harga).round();
                                return Text(
                                  CurrencyFormatter.format(total),
                                  style: AppTheme.dataMono(
                                    fontSize: 18,
                                    fontWeight: FontWeight.w800,
                                    color: AppColors.primary,
                                  ),
                                );
                              },
                            ),
                          ],
                        ),
                        const SizedBox(height: 20),
                        AppButton(
                          label: 'Simpan Penjualan & Cetak Struk',
                          icon: Icons.print_outlined,
                          onPressed: () {
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(content: Text('Penjualan arang tersimpan & siap dicetak')),
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

          // Tab Beli Arang
          SingleChildScrollView(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Text('Beli Arang dari Pemasok / Pengrajin', style: Theme.of(context).textTheme.titleMedium),
                        const SizedBox(height: 16),
                        AppTextField(
                          controller: _beratBeliController,
                          label: 'Berat Masuk (Kg)',
                          hint: 'Contoh: 50.0',
                          keyboardType: const TextInputType.numberWithOptions(decimal: true),
                          onChanged: (_) => setState(() {}),
                        ),
                        const SizedBox(height: 12),
                        AppTextField(
                          controller: _hargaBeliController,
                          label: 'Harga Beli per Kg',
                          hint: '5000',
                          keyboardType: TextInputType.number,
                          onChanged: (_) => setState(() {}),
                        ),
                        const Divider(height: 28),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text('Total Pembayaran ke Pemasok', style: TextStyle(fontWeight: FontWeight.w600)),
                            Builder(
                              builder: (context) {
                                final berat = double.tryParse(_beratBeliController.text) ?? 0.0;
                                final harga = int.tryParse(_hargaBeliController.text) ?? 0;
                                final total = (berat * harga).round();
                                return Text(
                                  CurrencyFormatter.format(total),
                                  style: AppTheme.dataMono(
                                    fontSize: 18,
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
                          variant: AppButtonVariant.primary,
                          onPressed: () {
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(content: Text('Pembelian arang berhasil dicatat')),
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
        ],
      ),
    );
  }
}
