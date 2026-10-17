import 'package:flutter/material.dart';
import 'package:kios_berkah_mobile/core/constants/app_colors.dart';
import 'package:kios_berkah_mobile/core/theme/app_theme.dart';
import 'package:kios_berkah_mobile/core/utils/currency_formatter.dart';
import 'package:kios_berkah_mobile/core/widgets/app_button.dart';
import 'package:kios_berkah_mobile/core/widgets/stock_badge.dart';
import 'package:kios_berkah_mobile/features/products/data/models/product_model.dart';
import 'package:kios_berkah_mobile/features/pos/data/models/cart_item_model.dart';

class PosScreen extends StatefulWidget {
  const PosScreen({super.key});

  @override
  State<PosScreen> createState() => _PosScreenState();
}

class _PosScreenState extends State<PosScreen> {
  final TextEditingController _searchController = TextEditingController();
  int? _selectedCategoryId;
  final List<CartItemModel> _cart = [];

  // Sample data awal (sebelum terhubung API)
  final List<CategoryModel> _categories = [
    CategoryModel(id: 0, name: 'Semua'),
    CategoryModel(id: 1, name: 'Sembako'),
    CategoryModel(id: 2, name: 'Minuman'),
    CategoryModel(id: 3, name: 'Rokok'),
    CategoryModel(id: 4, name: 'Snack'),
  ];

  final List<ProductModel> _products = [
    ProductModel(id: 1, barcode: '899123456789', name: 'Beras Ramos 5kg', price: 68000, stock: 14, minStock: 3),
    ProductModel(id: 2, barcode: '899234567890', name: 'Minyak Goreng 2L', price: 34000, stock: 8, minStock: 5),
    ProductModel(id: 3, barcode: '899345678901', name: 'Gula Pasir 1kg', price: 17500, stock: 2, minStock: 5),
    ProductModel(id: 4, barcode: '899456789012', name: 'Kopi Kapal Api 65g', price: 7000, stock: 25, minStock: 10),
    ProductModel(id: 5, barcode: '899567890123', name: 'Teh Celup Sosro', price: 8500, stock: 0, minStock: 5),
    ProductModel(id: 6, barcode: '899678901234', name: 'Telur Ayam 1kg', price: 28000, stock: 12, minStock: 4),
  ];

  int get _cartTotal => _cart.fold(0, (sum, item) => sum + item.subtotal);
  int get _cartItemCount => _cart.fold(0, (sum, item) => sum + item.qty);

  void _addToCart(ProductModel product) {
    if (product.stock <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Stok produk habis!'),
          backgroundColor: AppColors.danger,
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    setState(() {
      final index = _cart.indexWhere((item) => item.product.id == product.id);
      if (index >= 0) {
        if (_cart[index].qty < product.stock) {
          _cart[index].qty++;
        } else {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Stok tersisa hanya ${product.stock} pcs!'),
              backgroundColor: AppColors.amber,
              behavior: SnackBarBehavior.floating,
            ),
          );
        }
      } else {
        _cart.add(CartItemModel(product: product, qty: 1));
      }
    });
  }

  void _showCartSheet() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) {
        return StatefulBuilder(
          builder: (ctx, setSheetState) {
            return SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          'Keranjang Kasir (${_cart.length} item)',
                          style: Theme.of(context).textTheme.titleLarge?.copyWith(fontSize: 18),
                        ),
                        if (_cart.isNotEmpty)
                          TextButton(
                            onPressed: () {
                              setState(() => _cart.clear());
                              setSheetState(() {});
                              Navigator.pop(ctx);
                            },
                            child: const Text('Kosongkan', style: TextStyle(color: AppColors.danger)),
                          ),
                      ],
                    ),
                    const Divider(),
                    if (_cart.isEmpty)
                      const Padding(
                        padding: EdgeInsets.symmetric(vertical: 36),
                        child: Center(child: Text('Keranjang masih kosong')),
                      )
                    else
                      ConstrainedBox(
                        constraints: BoxConstraints(
                          maxHeight: MediaQuery.of(context).size.height * 0.45,
                        ),
                        child: ListView.separated(
                          shrinkWrap: true,
                          itemCount: _cart.length,
                          separatorBuilder: (_, __) => const Divider(height: 1),
                          itemBuilder: (ctx, idx) {
                            final item = _cart[idx];
                            return Padding(
                              padding: const EdgeInsets.symmetric(vertical: 8),
                              child: Row(
                                children: [
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          item.product.name,
                                          style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
                                        ),
                                        const SizedBox(height: 2),
                                        Text(
                                          CurrencyFormatter.format(item.product.price),
                                          style: AppTheme.dataMono(fontSize: 13, color: AppColors.textSecondary),
                                        ),
                                      ],
                                    ),
                                  ),
                                  Row(
                                    children: [
                                      IconButton(
                                        icon: const Icon(Icons.remove_circle_outline, size: 22),
                                        onPressed: () {
                                          setState(() {
                                            if (item.qty > 1) {
                                              item.qty--;
                                            } else {
                                              _cart.removeAt(idx);
                                            }
                                          });
                                          setSheetState(() {});
                                        },
                                      ),
                                      Text(
                                        '${item.qty}',
                                        style: AppTheme.dataMono(fontSize: 15, fontWeight: FontWeight.w700),
                                      ),
                                      IconButton(
                                        icon: const Icon(Icons.add_circle_outline, size: 22),
                                        onPressed: () {
                                          if (item.qty < item.product.stock) {
                                            setState(() => item.qty++);
                                            setSheetState(() {});
                                          }
                                        },
                                      ),
                                    ],
                                  ),
                                  const SizedBox(width: 8),
                                  Text(
                                    CurrencyFormatter.format(item.subtotal),
                                    style: AppTheme.dataMono(fontSize: 14, fontWeight: FontWeight.w700),
                                  ),
                                ],
                              ),
                            );
                          },
                        ),
                      ),
                    const Divider(),
                    const SizedBox(height: 8),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Total Pembayaran', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 16)),
                        Text(
                          CurrencyFormatter.format(_cartTotal),
                          style: AppTheme.dataMono(
                            fontSize: 18,
                            fontWeight: FontWeight.w800,
                            color: AppColors.primary,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    AppButton(
                      label: 'Pilih Pembayaran (Tunai / QRIS / Kasbon)',
                      onPressed: _cart.isEmpty
                          ? null
                          : () {
                              Navigator.pop(ctx);
                              _showCheckoutDialog();
                            },
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }

  void _showCheckoutDialog() {
    showDialog(
      context: context,
      builder: (ctx) {
        return AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: const Text('Metode Pembayaran', style: TextStyle(fontWeight: FontWeight.w700)),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ListTile(
                leading: const Icon(Icons.money_rounded, color: AppColors.primary),
                title: const Text('Tunai', style: TextStyle(fontWeight: FontWeight.w600)),
                subtitle: const Text('Pembayaran tunai & kembalian'),
                onTap: () {
                  Navigator.pop(ctx);
                  _processTransaction('Tunai');
                },
              ),
              const Divider(height: 1),
              ListTile(
                leading: const Icon(Icons.qr_code_scanner_rounded, color: AppColors.teal),
                title: const Text('QRIS Toko', style: TextStyle(fontWeight: FontWeight.w600)),
                subtitle: const Text('Scan kode QRIS toko (Langsung lunas)'),
                onTap: () {
                  Navigator.pop(ctx);
                  _processTransaction('QRIS');
                },
              ),
              const Divider(height: 1),
              ListTile(
                leading: const Icon(Icons.book_outlined, color: AppColors.amber),
                title: const Text('Kasbon / Hutang', style: TextStyle(fontWeight: FontWeight.w600)),
                subtitle: const Text('Pilih pelanggan & tanggal jatuh tempo'),
                onTap: () {
                  Navigator.pop(ctx);
                  _processTransaction('Kasbon');
                },
              ),
            ],
          ),
        );
      },
    );
  }

  void _processTransaction(String method) {
    setState(() => _cart.clear());
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('Transaksi $method Berhasil Disimpan! Struk siap dicetak.'),
        backgroundColor: AppColors.primary,
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Kasir POS'),
        actions: [
          IconButton(
            tooltip: 'Scan Barcode',
            icon: const Icon(Icons.qr_code_scanner_rounded),
            onPressed: () {
              // Siap mengaktifkan kamera barcode scanner
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text('Barcode scanner kamera siap digunakan.'),
                  behavior: SnackBarBehavior.floating,
                ),
              );
            },
          ),
        ],
      ),
      body: Column(
        children: [
          // Bar Pencarian
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: TextField(
              controller: _searchController,
              decoration: InputDecoration(
                hintText: 'Cari nama produk atau scan barcode...',
                prefixIcon: const Icon(Icons.search, size: 20),
                suffixIcon: _searchController.text.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear, size: 18),
                        onPressed: () {
                          _searchController.clear();
                          setState(() {});
                        },
                      )
                    : null,
              ),
              onChanged: (_) => setState(() {}),
            ),
          ),

          // Chips Kategori
          SizedBox(
            height: 42,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              itemCount: _categories.length,
              separatorBuilder: (_, __) => const SizedBox(width: 8),
              itemBuilder: (ctx, idx) {
                final cat = _categories[idx];
                final isSelected = (_selectedCategoryId ?? 0) == cat.id;
                return ChoiceChip(
                  label: Text(cat.name),
                  selected: isSelected,
                  selectedColor: AppColors.primary,
                  labelStyle: TextStyle(
                    color: isSelected ? Colors.white : AppColors.textPrimary,
                    fontWeight: isSelected ? FontWeight.w600 : FontWeight.w400,
                    fontSize: 13,
                  ),
                  backgroundColor: Colors.white,
                  side: BorderSide(
                    color: isSelected ? AppColors.primary : AppColors.border,
                  ),
                  onSelected: (val) {
                    setState(() {
                      _selectedCategoryId = val ? cat.id : null;
                    });
                  },
                );
              },
            ),
          ),
          const SizedBox(height: 8),

          // Grid / List Produk
          Expanded(
            child: ListView.builder(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 100),
              itemCount: _products.length,
              itemBuilder: (ctx, idx) {
                final product = _products[idx];
                return Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(12),
                    onTap: () => _addToCart(product),
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  product.name,
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w600,
                                    fontSize: 15,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Row(
                                  children: [
                                    Text(
                                      CurrencyFormatter.format(product.price),
                                      style: AppTheme.dataMono(
                                        fontSize: 14,
                                        fontWeight: FontWeight.w700,
                                        color: AppColors.primary,
                                      ),
                                    ),
                                    const SizedBox(width: 12),
                                    StockBadge(stock: product.stock, threshold: product.minStock),
                                  ],
                                ),
                              ],
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.all(8),
                            decoration: BoxDecoration(
                              color: AppColors.primarySurface,
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: const Icon(
                              Icons.add_shopping_cart_rounded,
                              color: AppColors.primary,
                              size: 20,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),

      // Floating Cart Banner
      bottomSheet: _cart.isEmpty
          ? null
          : Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              decoration: BoxDecoration(
                color: Colors.white,
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.08),
                    blurRadius: 12,
                    offset: const Offset(0, -4),
                  ),
                ],
              ),
              child: SafeArea(
                child: Row(
                  children: [
                    Expanded(
                      child: GestureDetector(
                        onTap: _showCartSheet,
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              '$_cartItemCount Item Terpilih',
                              style: const TextStyle(fontSize: 12, color: AppColors.textSecondary),
                            ),
                            Text(
                              CurrencyFormatter.format(_cartTotal),
                              style: AppTheme.dataMono(
                                fontSize: 18,
                                fontWeight: FontWeight.w800,
                                color: AppColors.primary,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    AppButton(
                      label: 'Buka Keranjang',
                      icon: Icons.shopping_basket_rounded,
                      onPressed: _showCartSheet,
                    ),
                  ],
                ),
              ),
            ),
    );
  }
}
