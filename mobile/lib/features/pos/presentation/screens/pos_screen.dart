import 'package:flutter/material.dart';
import 'package:kios_berkah_mobile/core/constants/app_colors.dart';
import 'package:kios_berkah_mobile/core/theme/app_theme.dart';
import 'package:kios_berkah_mobile/core/utils/currency_formatter.dart';
import 'package:kios_berkah_mobile/core/widgets/app_button.dart';
import 'package:kios_berkah_mobile/core/widgets/stock_badge.dart';
import 'package:kios_berkah_mobile/features/pos/data/models/cart_item_model.dart';
import 'package:kios_berkah_mobile/features/products/data/models/product_model.dart';

class PosScreen extends StatefulWidget {
  const PosScreen({super.key});

  @override
  State<PosScreen> createState() => _PosScreenState();
}

class _PosScreenState extends State<PosScreen> {
  final TextEditingController _searchController = TextEditingController();
  int? _selectedCategoryId;
  final List<CartItemModel> _cart = [];

  final List<CategoryModel> _categories = [
    CategoryModel(id: 0, name: 'Semua'),
    CategoryModel(id: 1, name: 'Sembako'),
    CategoryModel(id: 2, name: 'Minuman'),
    CategoryModel(id: 3, name: 'Rokok'),
    CategoryModel(id: 4, name: 'Bumbu & Dapur'),
    CategoryModel(id: 5, name: 'Sabun & Cuci'),
  ];

  final List<ProductModel> _products = [
    ProductModel(id: 1, barcode: '899123456789', name: 'Beras Ramos 5kg', price: 68000, stock: 14, minStock: 3, categoryName: 'Sembako'),
    ProductModel(id: 2, barcode: '899234567890', name: 'Minyak Goreng Sania 2L', price: 34000, stock: 8, minStock: 5, categoryName: 'Sembako'),
    ProductModel(id: 3, barcode: '899345678901', name: 'Gula Pasir Gulaku 1kg', price: 17500, stock: 2, minStock: 5, categoryName: 'Sembako'),
    ProductModel(id: 4, barcode: '899456789012', name: 'Kopi Kapal Api Spesial 65g', price: 7000, stock: 25, minStock: 10, categoryName: 'Minuman'),
    ProductModel(id: 5, barcode: '899567890123', name: 'Teh Celup Sosro Isi 30', price: 8500, stock: 0, minStock: 5, categoryName: 'Minuman'),
    ProductModel(id: 6, barcode: '899678901234', name: 'Telur Ayam Ras 1kg', price: 28000, stock: 12, minStock: 4, categoryName: 'Sembako'),
    ProductModel(id: 7, barcode: '899789012345', name: 'Indomie Goreng Original', price: 3100, stock: 48, minStock: 15, categoryName: 'Sembako'),
    ProductModel(id: 8, barcode: '899890123456', name: 'Rokok Sampoerna Mild 16', price: 35000, stock: 6, minStock: 5, categoryName: 'Rokok'),
  ];

  int get _cartTotal => _cart.fold(0, (sum, item) => sum + item.subtotal);
  int get _cartItemCount => _cart.fold(0, (sum, item) => sum + item.qty);

  int _getCartQty(int productId) {
    final index = _cart.indexWhere((item) => item.product.id == productId);
    return index >= 0 ? _cart[index].qty : 0;
  }

  void _addToCart(ProductModel product) {
    if (product.stock <= 0) {
      _showToast('Stok produk habis!', isError: true);
      return;
    }

    setState(() {
      final index = _cart.indexWhere((item) => item.product.id == product.id);
      if (index >= 0) {
        if (_cart[index].qty < product.stock) {
          _cart[index].qty++;
        } else {
          _showToast('Stok tersisa hanya ${product.stock} pcs!');
        }
      } else {
        _cart.add(CartItemModel(product: product, qty: 1));
      }
    });
  }

  void _removeFromCart(ProductModel product) {
    setState(() {
      final index = _cart.indexWhere((item) => item.product.id == product.id);
      if (index >= 0) {
        if (_cart[index].qty > 1) {
          _cart[index].qty--;
        } else {
          _cart.removeAt(index);
        }
      }
    });
  }

  void _showToast(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: isError ? AppColors.danger : AppColors.primary,
        behavior: SnackBarBehavior.floating,
        margin: const EdgeInsets.fromLTRB(16, 0, 16, 88),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
    );
  }

  void _showCartSheet() {
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
            return SafeArea(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Center(
                      child: Container(
                        width: 44,
                        height: 4,
                        decoration: BoxDecoration(
                          color: AppColors.border,
                          borderRadius: BorderRadius.circular(2),
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
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
                    const Divider(height: 24),
                    if (_cart.isEmpty)
                      const Padding(
                        padding: EdgeInsets.symmetric(vertical: 40),
                        child: Center(
                          child: Text(
                            'Keranjang masih kosong',
                            style: TextStyle(color: AppColors.textSecondary),
                          ),
                        ),
                      )
                    else
                      ConstrainedBox(
                        constraints: BoxConstraints(
                          maxHeight: MediaQuery.of(context).size.height * 0.45,
                        ),
                        child: ListView.separated(
                          shrinkWrap: true,
                          itemCount: _cart.length,
                          separatorBuilder: (context, index) => const Divider(height: 1),
                          itemBuilder: (ctx, idx) {
                            final item = _cart[idx];
                            return Padding(
                              padding: const EdgeInsets.symmetric(vertical: 10),
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
                                        const SizedBox(height: 3),
                                        Text(
                                          CurrencyFormatter.format(item.product.price),
                                          style: AppTheme.dataMono(fontSize: 13, color: AppColors.primary),
                                        ),
                                      ],
                                    ),
                                  ),
                                  Container(
                                    decoration: BoxDecoration(
                                      color: AppColors.surfaceMuted,
                                      borderRadius: BorderRadius.circular(20),
                                      border: Border.all(color: AppColors.border),
                                    ),
                                    child: Row(
                                      children: [
                                        IconButton(
                                          icon: const Icon(Icons.remove, size: 18),
                                          onPressed: () {
                                            setState(() => _removeFromCart(item.product));
                                            setSheetState(() {});
                                          },
                                        ),
                                        Text(
                                          '${item.qty}',
                                          style: AppTheme.dataMono(fontSize: 14, fontWeight: FontWeight.w700),
                                        ),
                                        IconButton(
                                          icon: const Icon(Icons.add, size: 18, color: AppColors.primary),
                                          onPressed: () {
                                            if (item.qty < item.product.stock) {
                                              setState(() => _addToCart(item.product));
                                              setSheetState(() {});
                                            }
                                          },
                                        ),
                                      ],
                                    ),
                                  ),
                                  const SizedBox(width: 12),
                                  SizedBox(
                                    width: 75,
                                    child: Text(
                                      CurrencyFormatter.format(item.subtotal),
                                      textAlign: TextAlign.right,
                                      style: AppTheme.dataMono(fontSize: 14, fontWeight: FontWeight.w700),
                                    ),
                                  ),
                                ],
                              ),
                            );
                          },
                        ),
                      ),
                    const Divider(height: 24),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          'Total Pembayaran',
                          style: TextStyle(fontWeight: FontWeight.w600, fontSize: 15),
                        ),
                        Text(
                          CurrencyFormatter.format(_cartTotal),
                          style: AppTheme.dataMono(
                            fontSize: 20,
                            fontWeight: FontWeight.w800,
                            color: AppColors.primary,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 20),
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
          backgroundColor: Colors.white,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
          title: Text(
            'Metode Pembayaran',
            style: Theme.of(context).textTheme.titleLarge?.copyWith(fontSize: 18),
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              _paymentOptionTile(
                icon: Icons.money_rounded,
                title: 'Tunai',
                subtitle: 'Hitung kembalian cepat',
                color: AppColors.primary,
                onTap: () {
                  Navigator.pop(ctx);
                  _processTransaction('Tunai');
                },
              ),
              const SizedBox(height: 8),
              _paymentOptionTile(
                icon: Icons.qr_code_scanner_rounded,
                title: 'QRIS Toko',
                subtitle: 'Scan kode QRIS toko (Langsung lunas)',
                color: AppColors.teal,
                onTap: () {
                  Navigator.pop(ctx);
                  _processTransaction('QRIS');
                },
              ),
              const SizedBox(height: 8),
              _paymentOptionTile(
                icon: Icons.book_outlined,
                title: 'Kasbon / Hutang',
                subtitle: 'Pilih pelanggan & tanggal jatuh tempo',
                color: AppColors.amber,
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

  Widget _paymentOptionTile({
    required IconData icon,
    required String title,
    required String subtitle,
    required Color color,
    required VoidCallback onTap,
  }) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.background,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.border),
      ),
      child: ListTile(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        leading: Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.12),
            shape: BoxShape.circle,
          ),
          child: Icon(icon, color: color, size: 22),
        ),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
        subtitle: Text(subtitle, style: const TextStyle(fontSize: 12, color: AppColors.textSecondary)),
        onTap: onTap,
      ),
    );
  }

  void _processTransaction(String method) {
    setState(() => _cart.clear());
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('Transaksi $method senilai ${CurrencyFormatter.format(_cartTotal)} Berhasil!'),
        backgroundColor: AppColors.primary,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Kios BERKAH',
              style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18),
            ),
            Row(
              children: [
                Container(
                  width: 6,
                  height: 6,
                  decoration: const BoxDecoration(
                    color: AppColors.primary,
                    shape: BoxShape.circle,
                  ),
                ),
                const SizedBox(width: 5),
                const Text(
                  'Kasir Aktif',
                  style: TextStyle(fontSize: 11, color: AppColors.textSecondary),
                ),
              ],
            ),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'Keranjang',
            icon: Badge(
              isLabelVisible: _cartItemCount > 0,
              label: Text('$_cartItemCount'),
              backgroundColor: AppColors.primary,
              textColor: Colors.white,
              child: const Icon(Icons.shopping_bag_outlined),
            ),
            onPressed: _showCartSheet,
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: Column(
        children: [
          // Bar Pencarian Kapsul (Pill Search Putih Bersih)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
            child: Container(
              height: 50,
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(25),
                border: Border.all(color: AppColors.border),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.03),
                    blurRadius: 8,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Row(
                children: [
                  const SizedBox(width: 16),
                  const Icon(Icons.search_rounded, color: AppColors.textSecondary, size: 22),
                  const SizedBox(width: 10),
                  Expanded(
                    child: TextField(
                      controller: _searchController,
                      decoration: const InputDecoration(
                        hintText: 'Cari nama produk / barcode...',
                        border: InputBorder.none,
                        enabledBorder: InputBorder.none,
                        focusedBorder: InputBorder.none,
                        contentPadding: EdgeInsets.zero,
                        filled: false,
                      ),
                      onChanged: (_) => setState(() {}),
                    ),
                  ),
                  if (_searchController.text.isNotEmpty)
                    IconButton(
                      icon: const Icon(Icons.clear, size: 18),
                      onPressed: () {
                        _searchController.clear();
                        setState(() {});
                      },
                    )
                  else
                    IconButton(
                      icon: const Icon(Icons.qr_code_scanner_rounded, color: AppColors.primary, size: 22),
                      onPressed: () {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Membuka kamera scanner barcode...')),
                        );
                      },
                    ),
                  const SizedBox(width: 4),
                ],
              ),
            ),
          ),

          // Horizontal Category Pill Chips
          SizedBox(
            height: 38,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              itemCount: _categories.length,
              separatorBuilder: (context, index) => const SizedBox(width: 8),
              itemBuilder: (ctx, idx) {
                final cat = _categories[idx];
                final isSelected = (_selectedCategoryId ?? 0) == cat.id;
                return GestureDetector(
                  onTap: () {
                    setState(() {
                      _selectedCategoryId = isSelected ? null : cat.id;
                    });
                  },
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 180),
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                    decoration: BoxDecoration(
                      color: isSelected ? AppColors.primary : Colors.white,
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(
                        color: isSelected ? AppColors.primary : AppColors.border,
                      ),
                      boxShadow: isSelected
                          ? [
                              BoxShadow(
                                color: AppColors.primary.withValues(alpha: 0.25),
                                blurRadius: 8,
                                offset: const Offset(0, 2),
                              ),
                            ]
                          : null,
                    ),
                    alignment: Alignment.center,
                    child: Text(
                      cat.name,
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                        color: isSelected ? Colors.white : AppColors.textPrimary,
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
          const SizedBox(height: 12),

          // Daftar Produk (Card Putih Bersih, TANPA GAMBAR)
          Expanded(
            child: ListView.builder(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 130),
              itemCount: _products.length,
              itemBuilder: (ctx, idx) {
                final product = _products[idx];
                final cartQty = _getCartQty(product.id);
                final inCart = cartQty > 0;

                return Container(
                  margin: const EdgeInsets.only(bottom: 10),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(
                      color: inCart ? AppColors.primary : AppColors.border,
                      width: inCart ? 1.5 : 1,
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.03),
                        blurRadius: 6,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              if (product.categoryName != null) ...[
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                  decoration: BoxDecoration(
                                    color: AppColors.surfaceMuted,
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: Text(
                                    product.categoryName!,
                                    style: const TextStyle(
                                      fontSize: 10,
                                      fontWeight: FontWeight.w600,
                                      color: AppColors.textSecondary,
                                    ),
                                  ),
                                ),
                                const SizedBox(height: 4),
                              ],
                              Text(
                                product.name,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  fontSize: 15,
                                  fontWeight: FontWeight.w600,
                                  color: AppColors.textPrimary,
                                ),
                              ),
                              const SizedBox(height: 6),
                              Row(
                                children: [
                                  Text(
                                    CurrencyFormatter.format(product.price),
                                    style: AppTheme.dataMono(
                                      fontSize: 15,
                                      fontWeight: FontWeight.w700,
                                      color: AppColors.primary,
                                    ),
                                  ),
                                  const SizedBox(width: 10),
                                  StockBadge(stock: product.stock, threshold: product.minStock),
                                ],
                              ),
                            ],
                          ),
                        ),

                        // Tombol Tambah / Stepper Kuantitas
                        if (inCart)
                          Container(
                            decoration: BoxDecoration(
                              color: AppColors.primarySurface,
                              borderRadius: BorderRadius.circular(22),
                              border: Border.all(color: AppColors.primary.withValues(alpha: 0.4)),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                IconButton(
                                  icon: const Icon(Icons.remove, size: 16, color: AppColors.primary),
                                  constraints: const BoxConstraints(minWidth: 32, minHeight: 32),
                                  padding: EdgeInsets.zero,
                                  onPressed: () => _removeFromCart(product),
                                ),
                                Text(
                                  '$cartQty',
                                  style: AppTheme.dataMono(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w700,
                                    color: AppColors.primary,
                                  ),
                                ),
                                IconButton(
                                  icon: const Icon(Icons.add, size: 16, color: AppColors.primary),
                                  constraints: const BoxConstraints(minWidth: 32, minHeight: 32),
                                  padding: EdgeInsets.zero,
                                  onPressed: () => _addToCart(product),
                                ),
                              ],
                            ),
                          )
                        else
                          Material(
                            color: product.stock > 0 ? AppColors.primarySurface : AppColors.surfaceMuted,
                            borderRadius: BorderRadius.circular(12),
                            child: InkWell(
                              borderRadius: BorderRadius.circular(12),
                              onTap: product.stock > 0 ? () => _addToCart(product) : null,
                              child: Padding(
                                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                                child: Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    Icon(
                                      Icons.add,
                                      size: 16,
                                      color: product.stock > 0 ? AppColors.primary : AppColors.textMuted,
                                    ),
                                    const SizedBox(width: 4),
                                    Text(
                                      'Tambah',
                                      style: TextStyle(
                                        fontSize: 12,
                                        fontWeight: FontWeight.w700,
                                        color: product.stock > 0 ? AppColors.primary : AppColors.textMuted,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
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

      // Floating Cart Bar (Kapsul Melayang Putih Bersih)
      bottomSheet: _cart.isEmpty
          ? null
          : Container(
              color: Colors.transparent,
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 92),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(color: AppColors.border, width: 1.2),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.1),
                      blurRadius: 18,
                      offset: const Offset(0, 6),
                    ),
                  ],
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: GestureDetector(
                        behavior: HitTestBehavior.opaque,
                        onTap: _showCartSheet,
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              '$_cartItemCount barang terpilih',
                              style: const TextStyle(fontSize: 11, color: AppColors.textSecondary),
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
                    ElevatedButton.icon(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primary,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      ),
                      icon: const Icon(Icons.arrow_forward_rounded, size: 18),
                      label: const Text('Bayar', style: TextStyle(fontWeight: FontWeight.w800)),
                      onPressed: _showCartSheet,
                    ),
                  ],
                ),
              ),
            ),
    );
  }
}
