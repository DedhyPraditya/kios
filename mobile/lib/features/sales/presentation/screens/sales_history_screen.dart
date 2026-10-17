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
  // Mock data riwayat nota
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
        SaleItemModel(id: 2, productName: 'Minyak Goreng 2L', price: 34000, qty: 1, subtotal: 34000),
      ],
    ),
    SaleModel(
      id: 2,
      invoiceNumber: 'INV20261017-0002',
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
      createdAt: DateTime.now().subtract(const Duration(hours: 1)),
      items: [
        SaleItemModel(id: 3, productName: 'Gula Pasir 1kg', price: 17500, qty: 1, subtotal: 17500),
        SaleItemModel(id: 4, productName: 'Telur Ayam 1kg', price: 28000, qty: 1, subtotal: 28000),
      ],
    ),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Riwayat Transaksi'),
        actions: [
          IconButton(
            icon: const Icon(Icons.filter_list_rounded),
            onPressed: () {},
          ),
        ],
      ),
      body: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: _sales.length,
        itemBuilder: (ctx, idx) {
          final sale = _sales[idx];
          return Card(
            margin: const EdgeInsets.only(bottom: 12),
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
                          color: AppColors.primary,
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: sale.isPaid ? AppColors.successSurface : AppColors.amberSurface,
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          sale.isPaid ? 'LUNAS' : 'KASBON',
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                            color: sale.isPaid ? AppColors.success : AppColors.amberText,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  if (sale.customerName != null) ...[
                    Row(
                      children: [
                        const Icon(Icons.person_outline, size: 16, color: AppColors.textSecondary),
                        const SizedBox(width: 4),
                        Text(
                          sale.customerName!,
                          style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
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
                  const Divider(height: 20),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.end,
                    children: [
                      OutlinedButton.icon(
                        style: OutlinedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                          textStyle: const TextStyle(fontSize: 12),
                        ),
                        icon: const Icon(Icons.print_outlined, size: 16),
                        label: const Text('Cetak Struk'),
                        onPressed: () {
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(content: Text('Mengirim ke printer Bluetooth 58mm...')),
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
    );
  }
}
