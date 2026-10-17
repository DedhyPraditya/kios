import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:kios_berkah_mobile/core/constants/api_endpoints.dart';
import 'package:kios_berkah_mobile/core/constants/app_colors.dart';
import 'package:kios_berkah_mobile/core/services/bluetooth_printer_service.dart';
import 'package:kios_berkah_mobile/core/theme/app_theme.dart';
import 'package:kios_berkah_mobile/core/utils/currency_formatter.dart';
import 'package:kios_berkah_mobile/core/utils/date_formatter.dart';
import 'package:kios_berkah_mobile/features/sales/data/models/sale_model.dart';

class SalesHistoryScreen extends StatefulWidget {
  const SalesHistoryScreen({super.key});

  @override
  State<SalesHistoryScreen> createState() => _SalesHistoryScreenState();
}

class _SalesHistoryScreenState extends State<SalesHistoryScreen> {
  String _selectedFilter = 'Semua';
  final List<String> _filters = ['Semua', 'Tunai', 'QRIS', 'Kasbon'];

  List<SaleModel> _sales = [];
  bool _isLoading = false;

  List<SaleModel> get _filteredSales {
    if (_selectedFilter == 'Semua') return _sales;
    final lower = _selectedFilter.toLowerCase();
    return _sales.where((s) => s.paymentMethod.toLowerCase() == lower).toList();
  }

  @override
  void initState() {
    super.initState();
    fetchSalesData();
  }

  Future<void> fetchSalesData({bool showIndicator = true}) async {
    if (showIndicator) {
      setState(() => _isLoading = true);
    }
    try {
      final prefs = await SharedPreferences.getInstance();
      final baseUrl = prefs.getString('kios_api_base_url') ?? ApiEndpoints.defaultBaseUrl;
      final dio = Dio(
        BaseOptions(
          baseUrl: baseUrl,
          connectTimeout: const Duration(seconds: 12),
          receiveTimeout: const Duration(seconds: 12),
          headers: {'Accept': 'application/json'},
        ),
      );

      final response = await dio.get('/sales');
      if (response.statusCode == 200 && response.data['success'] == true) {
        final salesRaw = response.data['sales'] as List? ?? [];
        final loadedSales = salesRaw.map((s) => SaleModel.fromJson(s)).toList();

        if (mounted) {
          setState(() {
            _sales = loadedSales;
            _isLoading = false;
          });
        }
      }
    } catch (e) {
      debugPrint('Error fetch sales: $e');
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  Future<void> _handlePrintReceipt(SaleModel sale) async {
    final messenger = ScaffoldMessenger.of(context);
    messenger.showSnackBar(
      SnackBar(
        content: const Row(
          children: [
            SizedBox(
              width: 18,
              height: 18,
              child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
            ),
            SizedBox(width: 12),
            Text('Mengirim struk ke printer Bluetooth...'),
          ],
        ),
        backgroundColor: AppColors.primary,
        behavior: SnackBarBehavior.floating,
        margin: const EdgeInsets.fromLTRB(16, 0, 16, 88),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
    );

    final success = await BluetoothPrinterService.instance.printSaleReceipt(sale);
    if (!mounted) return;

    messenger.hideCurrentSnackBar();
    messenger.showSnackBar(
      SnackBar(
        content: Row(
          children: [
            Icon(
              success ? Icons.check_circle_rounded : Icons.info_outline_rounded,
              color: Colors.white,
              size: 20,
            ),
            const SizedBox(width: 10),
            Text(
              success
                  ? 'Struk transaksi berhasil dicetak!'
                  : 'Printer belum terhubung. Buka Pengaturan > Printer.',
            ),
          ],
        ),
        backgroundColor: success ? AppColors.primary : AppColors.amberText,
        behavior: SnackBarBehavior.floating,
        margin: const EdgeInsets.fromLTRB(16, 0, 16, 88),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text(
          'Riwayat Transaksi',
          style: TextStyle(fontWeight: FontWeight.w800),
        ),
        actions: [
          IconButton(
            tooltip: 'Sinkronisasi Riwayat',
            icon: _isLoading
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.primary),
                  )
                : const Icon(Icons.sync_rounded, color: AppColors.primary, size: 22),
            onPressed: () => fetchSalesData(),
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: Column(
        children: [
          // Filter Chips
          SizedBox(
            height: 38,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              itemCount: _filters.length,
              separatorBuilder: (context, index) => const SizedBox(width: 8),
              itemBuilder: (ctx, idx) {
                final filter = _filters[idx];
                final isSelected = _selectedFilter == filter;
                return GestureDetector(
                  onTap: () => setState(() => _selectedFilter = filter),
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 180),
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                    decoration: BoxDecoration(
                      color: isSelected ? AppColors.primary : AppColors.surface,
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(
                        color: isSelected ? AppColors.primary : AppColors.border,
                      ),
                    ),
                    alignment: Alignment.center,
                    child: Text(
                      filter,
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                        color: isSelected ? AppColors.textOnPrimary : AppColors.textPrimary,
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
          const SizedBox(height: 12),

          // Daftar Nota Transaksi
          Expanded(
            child: RefreshIndicator(
              onRefresh: () => fetchSalesData(showIndicator: false),
              color: AppColors.primary,
              child: _isLoading && _sales.isEmpty
                  ? const Center(
                      child: CircularProgressIndicator(color: AppColors.primary),
                    )
                  : _filteredSales.isEmpty
                      ? Center(
                          child: SingleChildScrollView(
                            physics: const AlwaysScrollableScrollPhysics(),
                            child: Column(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Icon(Icons.receipt_long_outlined, size: 54, color: AppColors.textSecondary.withValues(alpha: 0.4)),
                                const SizedBox(height: 12),
                                const Text(
                                  'Belum ada transaksi ditemukan',
                                  style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: AppColors.textSecondary),
                                ),
                                const SizedBox(height: 6),
                                const Text(
                                  'Tarik ke bawah atau tekan ikon sinkron di atas',
                                  style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
                                ),
                              ],
                            ),
                          ),
                        )
                      : ListView.builder(
                          physics: const AlwaysScrollableScrollPhysics(),
                          padding: const EdgeInsets.fromLTRB(16, 0, 16, 130),
                          itemCount: _filteredSales.length,
                          itemBuilder: (ctx, idx) {
                            final sale = _filteredSales[idx];
                            return Container(
                              margin: const EdgeInsets.only(bottom: 12),
                              decoration: BoxDecoration(
                                color: AppColors.surface,
                                borderRadius: BorderRadius.circular(16),
                                border: Border.all(color: AppColors.border),
                              ),
                              child: Padding(
                                padding: const EdgeInsets.all(16),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Row(
                                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                      children: [
                                        Text(
                                          sale.invoiceNumber,
                                          style: AppTheme.dataMono(
                                            fontSize: 13,
                                            fontWeight: FontWeight.w700,
                                            color: AppColors.primaryBright,
                                          ),
                                        ),
                                        Container(
                                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                          decoration: BoxDecoration(
                                            color: sale.isPaid ? AppColors.primarySurface : AppColors.amberSurface,
                                            borderRadius: BorderRadius.circular(8),
                                            border: Border.all(
                                              color: sale.isPaid
                                                  ? AppColors.primary.withValues(alpha: 0.3)
                                                  : AppColors.amber.withValues(alpha: 0.3),
                                            ),
                                          ),
                                          child: Text(
                                            sale.paymentMethod.toUpperCase(),
                                            style: TextStyle(
                                              fontSize: 10,
                                              fontWeight: FontWeight.w800,
                                              color: sale.isPaid ? AppColors.primaryBright : AppColors.amberText,
                                            ),
                                          ),
                                        ),
                                      ],
                                    ),
                                    const SizedBox(height: 10),
                                    if (sale.customerName != null) ...[
                                      Row(
                                        children: [
                                          const Icon(Icons.person_outline, size: 15, color: AppColors.textSecondary),
                                          const SizedBox(width: 5),
                                          Text(
                                            sale.customerName!,
                                            style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                                          ),
                                        ],
                                      ),
                                      const SizedBox(height: 6),
                                    ],
                                    Row(
                                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                      children: [
                                        Text(
                                          DateFormatter.formatWithTime(sale.createdAt),
                                          style: const TextStyle(fontSize: 12, color: AppColors.textSecondary),
                                        ),
                                        Text(
                                          CurrencyFormatter.format(sale.total),
                                          style: AppTheme.dataMono(
                                            fontSize: 16,
                                            fontWeight: FontWeight.w800,
                                            color: AppColors.textPrimary,
                                          ),
                                        ),
                                      ],
                                    ),
                                    const Divider(height: 22),
                                    Row(
                                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                      children: [
                                        Text(
                                          '${sale.items.length} item barang',
                                          style: const TextStyle(fontSize: 12, color: AppColors.textSecondary),
                                        ),
                                        ElevatedButton.icon(
                                          style: ElevatedButton.styleFrom(
                                            backgroundColor: AppColors.surfaceElevated,
                                            foregroundColor: AppColors.textPrimary,
                                            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                                            shape: RoundedRectangleBorder(
                                              borderRadius: BorderRadius.circular(12),
                                              side: const BorderSide(color: AppColors.border),
                                            ),
                                          ),
                                          icon: const Icon(Icons.print_outlined, size: 16, color: AppColors.primaryBright),
                                          label: const Text('Cetak Struk', style: TextStyle(fontSize: 12)),
                                          onPressed: () => _handlePrintReceipt(sale),
                                        ),
                                      ],
                                    ),
                                  ],
                                ),
                              ),
                            );
                          },
                        ),
            ),
          ),
        ],
      ),
    );
  }
}


