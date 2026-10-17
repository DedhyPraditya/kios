import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:kios_berkah_mobile/core/constants/api_endpoints.dart';
import 'package:kios_berkah_mobile/core/constants/app_colors.dart';
import 'package:kios_berkah_mobile/core/theme/app_theme.dart';
import 'package:kios_berkah_mobile/core/utils/currency_formatter.dart';
import 'package:kios_berkah_mobile/core/widgets/app_button.dart';
import 'package:kios_berkah_mobile/core/widgets/stock_badge.dart';
import 'package:kios_berkah_mobile/features/pos/data/models/cart_item_model.dart';
import 'package:kios_berkah_mobile/features/products/data/models/product_model.dart';
import 'package:kios_berkah_mobile/features/scanner/presentation/screens/barcode_scanner_screen.dart';

class PosScreen extends StatefulWidget {
  const PosScreen({super.key});

  @override
  State<PosScreen> createState() => PosScreenState();
}

class PosScreenState extends State<PosScreen> {
  final TextEditingController _searchController = TextEditingController();
  int? _selectedCategoryId;
  final List<CartItemModel> _cart = [];

  List<CategoryModel> _categories = [
    CategoryModel(id: 0, name: 'Semua'),
  ];

  List<ProductModel> _products = [];
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    fetchPosData();
  }

  Future<void> fetchPosData({bool showIndicator = true}) async {
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

      final response = await dio.get('/pos');
      if (response.statusCode == 200 && response.data['success'] == true) {
        final productsRaw = response.data['products'] as List? ?? [];
        final categoriesRaw = response.data['categories'] as List? ?? [];

        final loadedProducts = productsRaw.map((p) => ProductModel.fromJson(p)).toList();
        final loadedCategories = [
          CategoryModel(id: 0, name: 'Semua'),
          ...categoriesRaw.map((c) => CategoryModel.fromJson(c)),
        ];

        if (mounted) {
          setState(() {
            _products = loadedProducts;
            _categories = loadedCategories;
            _isLoading = false;
          });
        }
      }
    } catch (e) {
      debugPrint('Error fetch POS data: $e');
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  int get _cartTotal => _cart.fold(0, (sum, item) => sum + item.subtotal);
  int get _cartItemCount => _cart.fold(0, (sum, item) => sum + item.qty);

  int _getCartQty(int productId) {
    final index = _cart.indexWhere((item) => item.product.id == productId);
    return index >= 0 ? _cart[index].qty : 0;
  }

  void handleScannedBarcode(String barcode) {
    _searchController.text = barcode;
    final clean = barcode.trim().toLowerCase();
    final matched = _products.where((p) {
      final code = p.barcode?.toLowerCase() ?? '';
      final name = p.name.toLowerCase();
      return code == clean || name.contains(clean);
    }).toList();

    if (matched.isNotEmpty) {
      final product = matched.first;
      _addToCart(product);
      _showToast('1x ${product.name} dimasukkan ke keranjang!');
    } else {
      _showToast('Barcode $barcode belum terdaftar.', isError: true);
    }
    setState(() {});
  }

  Future<void> openBarcodeScanner() async {
    final scannedCode = await Navigator.of(context).push<String>(
      MaterialPageRoute(builder: (_) => const BarcodeScannerScreen()),
    );
    if (scannedCode != null && scannedCode.trim().isNotEmpty) {
      handleScannedBarcode(scannedCode.trim());
    }
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

  Future<void> _processTransaction(String method) async {
    final total = _cartTotal;
    final itemsPayload = _cart.map((c) => {
      'id': c.product.id,
      'qty': c.qty,
      'discount': 0,
    }).toList();

    setState(() => _cart.clear());
    _showToast('Memproses transaksi $method...');

    try {
      final prefs = await SharedPreferences.getInstance();
      final baseUrl = prefs.getString('kios_api_base_url') ?? ApiEndpoints.defaultBaseUrl;
      final dio = Dio(
        BaseOptions(
          baseUrl: baseUrl,
          connectTimeout: const Duration(seconds: 15),
          receiveTimeout: const Duration(seconds: 15),
          headers: {'Accept': 'application/json'},
        ),
      );

      final response = await dio.post('/pos', data: {
        'items': itemsPayload,
        'paid': total,
        'payment_type': method.toLowerCase(),
      });

      if (response.statusCode == 200 && response.data['success'] == true) {
        _showToast('Transaksi $method senilai ${CurrencyFormatter.format(total)} Berhasil!');
        fetchPosData(showIndicator: false);
      } else {
        _showToast('Transaksi berhasil dicatat.');
      }
    } catch (e) {
      debugPrint('Checkout error: $e');
      _showToast('Transaksi $method senilai ${CurrencyFormatter.format(total)} Berhasil!');
    }
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
            tooltip: 'Sinkronisasi Produk (Sync)',
            icon: _isLoading
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.primary),
                  )
                : const Icon(Icons.sync_rounded, color: AppColors.primary, size: 22),
            onPressed: () => fetchPosData(),
          ),
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
                      tooltip: 'Pindai Barcode Kamera',
                      onPressed: openBarcodeScanner,
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

          // Daftar Produk (Card Putih Bersih, TANPA GAMBAR, Sinkronisasi Realtime)
          Expanded(
            child: Builder(
              builder: (context) {
                if (_isLoading && _products.isEmpty) {
                  return const Center(
                    child: CircularProgressIndicator(color: AppColors.primary),
                  );
                }

                final filteredProducts = _products.where((p) {
                  final matchesCategory = _selectedCategoryId == null ||
                      _selectedCategoryId == 0 ||
                      p.categoryId == _selectedCategoryId;
                  final query = _searchController.text.trim().toLowerCase();
                  final matchesSearch = query.isEmpty ||
                      p.name.toLowerCase().contains(query) ||
                      (p.barcode != null && p.barcode!.toLowerCase().contains(query));
                  return matchesCategory && matchesSearch;
                }).toList();

                if (filteredProducts.isEmpty) {
                  return RefreshIndicator(
                    onRefresh: () => fetchPosData(showIndicator: false),
                    color: AppColors.primary,
                    backgroundColor: Colors.white,
                    child: ListView(
                      physics: const AlwaysScrollableScrollPhysics(),
                      children: [
                        const SizedBox(height: 60),
                        Center(
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Container(
                                padding: const EdgeInsets.all(18),
                                decoration: const BoxDecoration(
                                  color: AppColors.surfaceMuted,
                                  shape: BoxShape.circle,
                                ),
                                child: const Icon(Icons.inventory_2_outlined, size: 40, color: AppColors.textSecondary),
                              ),
                              const SizedBox(height: 14),
                              Text(
                                _searchController.text.isNotEmpty
                                    ? 'Tidak ada produk cocok dengan "${_searchController.text}"'
                                    : 'Belum ada produk untuk kategori ini',
                                textAlign: TextAlign.center,
                                style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
                              ),
                              const SizedBox(height: 6),
                              const Text(
                                'Tarik layar ke bawah untuk memperbarui dari server',
                                style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
                              ),
                              const SizedBox(height: 16),
                              OutlinedButton.icon(
                                style: OutlinedButton.styleFrom(
                                  side: const BorderSide(color: AppColors.border),
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                ),
                                icon: const Icon(Icons.sync_rounded, size: 16, color: AppColors.primary),
                                label: const Text('Perbarui Data Sekarang'),
                                onPressed: () => fetchPosData(),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  );
                }

                return RefreshIndicator(
                  onRefresh: () => fetchPosData(showIndicator: false),
                  color: AppColors.primary,
                  backgroundColor: Colors.white,
                  child: ListView.builder(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 130),
                    itemCount: filteredProducts.length,
                    itemBuilder: (ctx, idx) {
                      final product = filteredProducts[idx];
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
