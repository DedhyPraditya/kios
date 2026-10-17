import 'package:flutter/material.dart';
import 'package:kios_berkah_mobile/core/constants/app_colors.dart';
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

  final List<SaleModel> _sales = [
    SaleModel(
      id: 1,
      invoiceNumber: 'INV20261017-0001',
      cashierId: 1,
      cashierName: 'Admin',
      paymentMethod: 'tunai',
      isPaid: true,
      subtotal: 102000,
      total: 102000,
      paid: 110000,
      change: 8000,
      createdAt: DateTime.now().subtract(const Duration(minutes: 15)),
      items: [
        SaleItemModel(id: 1, productName: 'Beras Ramos 5kg', price: 68000, qty: 1, subtotal: 68000),
        SaleItemModel(id: 2, productName: 'Minyak Goreng Sania 2L', price: 34000, qty: 1, subtotal: 34000),
      ],
    ),
    SaleModel(
      id: 2,
      invoiceNumber: 'INV20261017-0002',
      cashierId: 1,
      cashierName: 'Admin',
      paymentMethod: 'qris',
      isPaid: true,
      subtotal: 35000,
      total: 35000,
      paid: 35000,
      change: 0,
      createdAt: DateTime.now().subtract(const Duration(minutes: 42)),
      items: [
        SaleItemModel(id: 3, productName: 'Rokok Sampoerna Mild 16', price: 35000, qty: 1, subtotal: 35000),
      ],
    ),
    SaleModel(
      id: 3,
      invoiceNumber: 'INV20261017-0003',
      cashierId: 1,
      cashierName: 'Admin',
      customerId: 1,
      customerName: 'Pak Budi (RT 03)',
      paymentMethod: 'kasbon',
      isPaid: false,
      subtotal: 45500,
      total: 45500,
      paid: 10000, // DP
      change: 0,
      dueDate: DateTime.now().add(const Duration(days: 7)),
      createdAt: DateTime.now().subtract(const Duration(hours: 2)),
      items: [
        SaleItemModel(id: 4, productName: 'Gula Pasir Gulaku 1kg', price: 17500, qty: 1, subtotal: 17500),
        SaleItemModel(id: 5, productName: 'Telur Ayam Ras 1kg', price: 28000, qty: 1, subtotal: 28000),
      ],
    ),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text(
          'Riwayat Transaksi',
          style: TextStyle(fontWeight: FontWeight.w800),
        ),
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
              separatorBuilder: (_, __) => const SizedBox(width: 8),
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
            child: ListView.builder(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 100),
              itemCount: _sales.length,
              itemBuilder: (ctx, idx) {
                final sale = _sales[idx];
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
                              onPressed: () {
                                ScaffoldMessenger.of(context).showSnackBar(
                                  const SnackBar(
                                    content: Text('Mengirim ke printer Bluetooth thermal 58mm...'),
                                    backgroundColor: AppColors.primary,
                                    behavior: SnackBarBehavior.floating,
                                  ),
                                );
                              },
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
        ],
      ),
    );
  }
}
