import 'dart:io';
import 'package:dio/dio.dart';
import 'package:dio/io.dart';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:kios_berkah_mobile/core/constants/api_endpoints.dart';
import 'package:kios_berkah_mobile/core/constants/app_colors.dart';
import 'package:kios_berkah_mobile/core/theme/app_theme.dart';
import 'package:kios_berkah_mobile/core/utils/currency_formatter.dart';
import 'package:kios_berkah_mobile/core/widgets/stock_badge.dart';
import 'package:kios_berkah_mobile/features/pos/data/models/cart_item_model.dart';
import 'package:kios_berkah_mobile/features/products/data/models/product_model.dart';
import 'package:kios_berkah_mobile/features/scanner/presentation/screens/barcode_scanner_screen.dart';
import 'package:kios_berkah_mobile/features/sales/data/models/sale_model.dart';
import 'package:kios_berkah_mobile/core/services/bluetooth_printer_service.dart';

class CustomerItem {
  final int id;
  final String name;
  final String? phone;
  final int? creditLimit;

  CustomerItem({
    required this.id,
    required this.name,
    this.phone,
    this.creditLimit,
  });

  factory CustomerItem.fromJson(Map<String, dynamic> json) {
    return CustomerItem(
      id: json['id'] as int? ?? 0,
      name: json['name'] as String? ?? '',
      phone: json['phone'] as String?,
      creditLimit: json['credit_limit'] as int?,
    );
  }
}

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
  List<CustomerItem> _customers = [];
  bool _isLoading = false;
  String? _fetchError;
  String _activeUrl = ApiEndpoints.defaultBaseUrl;

  @override
  void initState() {
    super.initState();
    fetchPosData();
  }

  Dio _createDio() {
    final dio = Dio(
      BaseOptions(
        connectTimeout: const Duration(seconds: 15),
        receiveTimeout: const Duration(seconds: 15),
        headers: {'Accept': 'application/json'},
      ),
    );
    (dio.httpClientAdapter as IOHttpClientAdapter).createHttpClient = () {
      final client = HttpClient();
      client.badCertificateCallback = (X509Certificate cert, String host, int port) => true;
      return client;
    };
    return dio;
  }

  Future<void> fetchPosData({bool showIndicator = true}) async {
    if (showIndicator) {
      setState(() {
        _isLoading = true;
        _fetchError = null;
      });
    }
    try {
      final prefs = await SharedPreferences.getInstance();
      var rawUrl = prefs.getString('kios_api_base_url')?.trim();
      if (rawUrl == null || rawUrl.isEmpty) {
        rawUrl = ApiEndpoints.defaultBaseUrl;
      }
      final cleanBaseUrl = rawUrl.endsWith('/') ? rawUrl.substring(0, rawUrl.length - 1) : rawUrl;
      _activeUrl = cleanBaseUrl;

      final dio = _createDio();
      final response = await dio.get('$cleanBaseUrl/pos');

      if (response.statusCode == 200 && response.data['success'] == true) {
        final productsRaw = response.data['products'] as List? ?? [];
        final categoriesRaw = response.data['categories'] as List? ?? [];
        final customersRaw = response.data['customers'] as List? ?? [];

        final loadedProducts = productsRaw.map((p) => ProductModel.fromJson(p)).toList();
        final loadedCategories = [
          CategoryModel(id: 0, name: 'Semua'),
          ...categoriesRaw.map((c) => CategoryModel.fromJson(c)),
        ];
        final loadedCustomers = customersRaw.map((c) => CustomerItem.fromJson(c)).toList();

        if (mounted) {
          setState(() {
            _products = loadedProducts;
            _categories = loadedCategories;
            _customers = loadedCustomers;
            _isLoading = false;
            _fetchError = null;
          });
        }
      } else {
        throw 'Respon server status ${response.statusCode}';
      }
    } catch (e) {
      debugPrint('Error fetch POS data: $e');
      if (mounted) {
        setState(() {
          _isLoading = false;
          _fetchError = e.toString();
        });
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Gagal sinkron dari $_activeUrl:\n$e', maxLines: 3),
            backgroundColor: AppColors.danger,
            behavior: SnackBarBehavior.floating,
            margin: const EdgeInsets.fromLTRB(16, 0, 16, 88),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
            duration: const Duration(seconds: 5),
          ),
        );
      }
    }
  }


  int get _cartTotal => _cart.fold(0, (sum, item) => sum + item.subtotal);
  int get _cartItemCount => _cart.fold(0, (sum, item) => sum + item.qty);

  int _getCartQty(int productId) {
    final index = _cart.indexWhere((item) => item.product.id == productId);
    return index >= 0 ? _cart[index].qty : 0;
  }

  bool _matchesBarcode(ProductModel p, String query) {
    final q = query.toLowerCase().trim();
    if (q.isEmpty) return false;

    // Cek barcode utama
    final pCode = p.barcode?.toLowerCase().trim() ?? '';
    if (pCode.isNotEmpty) {
      if (pCode == q) return true;
      final pStrip = pCode.replaceFirst(RegExp(r'^0+'), '');
      final qStrip = q.replaceFirst(RegExp(r'^0+'), '');
      if (pStrip.isNotEmpty && pStrip == qStrip) return true;
      if (pCode.contains(q) || q.contains(pCode)) return true;
    }

    // Cek barcode pada satuan jual (units)
    for (final u in p.units) {
      final uCode = u.barcode?.toLowerCase().trim() ?? '';
      if (uCode.isNotEmpty) {
        if (uCode == q) return true;
        final uStrip = uCode.replaceFirst(RegExp(r'^0+'), '');
        final qStrip = q.replaceFirst(RegExp(r'^0+'), '');
        if (uStrip.isNotEmpty && uStrip == qStrip) return true;
        if (uCode.contains(q) || q.contains(uCode)) return true;
      }
    }
    return false;
  }

  ProductModel? _findProductByBarcode(String barcode) {
    for (final p in _products) {
      if (_matchesBarcode(p, barcode)) {
        return p;
      }
    }
    return null;
  }

  Future<void> handleScannedBarcode(String barcode) async {
    final clean = barcode.trim();
    if (clean.isEmpty) return;

    // 1. Coba cari di katalog lokal
    ProductModel? matched = _findProductByBarcode(clean);

    // 2. Jika belum ketemu, coba sinkron data terbaru dari server
    if (matched == null) {
      _showToast('Memeriksa barcode $clean ke server...');
      await fetchPosData(showIndicator: false);
      matched = _findProductByBarcode(clean);
    }

    if (matched != null) {
      _addToCart(matched);
      _searchController.clear();
      setState(() {});
      _showToast('1x ${matched.name} dimasukkan ke keranjang!');
    } else {
      // Bersihkan search text agar katalog tidak terblokir kosong
      _searchController.clear();
      setState(() {});
      _showBarcodeNotFoundDialog(clean);
    }
  }

  void _showBarcodeNotFoundDialog(String barcode) {
    showDialog(
      context: context,
      builder: (ctx) {
        return AlertDialog(
          backgroundColor: Colors.white,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
          icon: Container(
            padding: const EdgeInsets.all(16),
            decoration: const BoxDecoration(
              color: AppColors.amberSurface,
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.qr_code_2_rounded, color: AppColors.amberText, size: 36),
          ),
          title: const Text(
            'Barcode Belum Terdaftar',
            style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                decoration: BoxDecoration(
                  color: AppColors.surfaceMuted,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: AppColors.border),
                ),
                child: Text(
                  barcode,
                  style: AppTheme.dataMono(fontWeight: FontWeight.w800, fontSize: 15, color: AppColors.primary),
                ),
              ),
              const SizedBox(height: 12),
              const Text(
                'Produk dengan kode barcode ini belum ada di data toko.\n\nSilakan daftarkan produk baru ini di menu Produk pada dashboard web Kios BERKAH, lalu tarik layar kasir ke bawah untuk sinkronisasi.',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 13, color: AppColors.textSecondary, height: 1.4),
              ),
            ],
          ),
          actions: [
            ElevatedButton(
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              onPressed: () => Navigator.pop(ctx),
              child: const Text('Mengerti / Tutup'),
            ),
          ],
        );
      },
    );
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
    String selectedPaymentMethod = 'Tunai'; // 'Tunai', 'QRIS', 'Kasbon'
    int? selectedCustomerId = _customers.isNotEmpty ? _customers.first.id : null;
    final paidController = TextEditingController();

    if (_cartTotal > 0) {
      paidController.text = _cartTotal.toString();
    }

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
            final bottomInset = MediaQuery.of(ctx).viewInsets.bottom;
            final currentPaid = int.tryParse(paidController.text.replaceAll(RegExp(r'[^0-9]'), '')) ?? 0;
            final change = currentPaid - _cartTotal;

            return SafeArea(
              child: Padding(
                padding: EdgeInsets.fromLTRB(20, 16, 20, 16 + bottomInset),
                child: SingleChildScrollView(
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
                      const SizedBox(height: 14),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Row(
                            children: [
                              const Icon(Icons.shopping_bag_outlined, color: AppColors.primary, size: 22),
                              const SizedBox(width: 8),
                              Text(
                                'Keranjang Kasir (${_cart.length} item)',
                                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                                      fontSize: 17,
                                      fontWeight: FontWeight.w700,
                                    ),
                              ),
                            ],
                          ),
                          if (_cart.isNotEmpty)
                            TextButton.icon(
                              style: TextButton.styleFrom(
                                foregroundColor: AppColors.danger,
                                padding: EdgeInsets.zero,
                              ),
                              icon: const Icon(Icons.delete_sweep_outlined, size: 18),
                              label: const Text('Kosongkan', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                              onPressed: () {
                                setState(() => _cart.clear());
                                setSheetState(() {});
                                Navigator.pop(ctx);
                              },
                            ),
                        ],
                      ),
                      const Divider(height: 20),
                      if (_cart.isEmpty)
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 36),
                          child: Column(
                            children: [
                              Container(
                                padding: const EdgeInsets.all(18),
                                decoration: const BoxDecoration(
                                  color: AppColors.surfaceMuted,
                                  shape: BoxShape.circle,
                                ),
                                child: const Icon(Icons.remove_shopping_cart_outlined, size: 40, color: AppColors.textSecondary),
                              ),
                              const SizedBox(height: 12),
                              const Text(
                                'Keranjang masih kosong',
                                style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600, color: AppColors.textPrimary),
                              ),
                              const SizedBox(height: 4),
                              const Text(
                                'Pilih produk dari katalog atau scan barcode',
                                style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
                              ),
                              const SizedBox(height: 18),
                              ElevatedButton(
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: AppColors.primary,
                                  foregroundColor: Colors.white,
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                ),
                                onPressed: () => Navigator.pop(ctx),
                                child: const Text('Mulai Pilih Produk'),
                              ),
                            ],
                          ),
                        )
                      else ...[
                        // Daftar Item Belanja
                        ConstrainedBox(
                          constraints: BoxConstraints(
                            maxHeight: MediaQuery.of(context).size.height * 0.30,
                          ),
                          child: ListView.separated(
                            shrinkWrap: true,
                            itemCount: _cart.length,
                            separatorBuilder: (context, index) => const Divider(height: 1),
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
                                            maxLines: 1,
                                            overflow: TextOverflow.ellipsis,
                                          ),
                                          const SizedBox(height: 2),
                                          Text(
                                            CurrencyFormatter.format(item.product.price),
                                            style: AppTheme.dataMono(fontSize: 12, color: AppColors.primary),
                                          ),
                                        ],
                                      ),
                                    ),
                                    Container(
                                      decoration: BoxDecoration(
                                        color: AppColors.surfaceMuted,
                                        borderRadius: BorderRadius.circular(18),
                                        border: Border.all(color: AppColors.border),
                                      ),
                                      child: Row(
                                        children: [
                                          IconButton(
                                            visualDensity: VisualDensity.compact,
                                            icon: const Icon(Icons.remove, size: 16),
                                            onPressed: () {
                                              setState(() => _removeFromCart(item.product));
                                              setSheetState(() {
                                                if (selectedPaymentMethod == 'Tunai') {
                                                  paidController.text = _cartTotal.toString();
                                                }
                                              });
                                            },
                                          ),
                                          Text(
                                            '${item.qty}',
                                            style: AppTheme.dataMono(fontSize: 13, fontWeight: FontWeight.w700),
                                          ),
                                          IconButton(
                                            visualDensity: VisualDensity.compact,
                                            icon: const Icon(Icons.add, size: 16, color: AppColors.primary),
                                            onPressed: () {
                                              if (item.qty < item.product.stock) {
                                                setState(() => _addToCart(item.product));
                                                setSheetState(() {
                                                  if (selectedPaymentMethod == 'Tunai') {
                                                    paidController.text = _cartTotal.toString();
                                                  }
                                                });
                                              } else {
                                                _showToast('Stok tersisa hanya ${item.product.stock} pcs!');
                                              }
                                            },
                                          ),
                                        ],
                                      ),
                                    ),
                                    const SizedBox(width: 10),
                                    SizedBox(
                                      width: 72,
                                      child: Text(
                                        CurrencyFormatter.format(item.subtotal),
                                        textAlign: TextAlign.right,
                                        style: AppTheme.dataMono(fontSize: 13, fontWeight: FontWeight.w700),
                                      ),
                                    ),
                                  ],
                                ),
                              );
                            },
                          ),
                        ),
                        const Divider(height: 18),

                        // Total Tagihan
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                          decoration: BoxDecoration(
                            color: AppColors.surfaceMuted,
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: AppColors.border),
                          ),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text(
                                'Total Tagihan',
                                style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
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
                        const SizedBox(height: 14),

                        // Pilihan Metode Pembayaran Langsung di Keranjang
                        const Text(
                          'Pilih Metode Pembayaran',
                          style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
                        ),
                        const SizedBox(height: 8),
                        Row(
                          children: [
                            Expanded(
                              child: _paymentChip(
                                label: 'Tunai',
                                icon: Icons.money_rounded,
                                isSelected: selectedPaymentMethod == 'Tunai',
                                color: AppColors.primary,
                                onTap: () => setSheetState(() {
                                  selectedPaymentMethod = 'Tunai';
                                  paidController.text = _cartTotal.toString();
                                }),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: _paymentChip(
                                label: 'QRIS',
                                icon: Icons.qr_code_rounded,
                                isSelected: selectedPaymentMethod == 'QRIS',
                                color: AppColors.teal,
                                onTap: () => setSheetState(() => selectedPaymentMethod = 'QRIS'),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: _paymentChip(
                                label: 'Kasbon',
                                icon: Icons.menu_book_rounded,
                                isSelected: selectedPaymentMethod == 'Kasbon',
                                color: AppColors.amberText,
                                onTap: () => setSheetState(() => selectedPaymentMethod = 'Kasbon'),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),

                        // Form input spesifik metode bayar
                        if (selectedPaymentMethod == 'Tunai') ...[
                          Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(color: AppColors.border),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    const Text('Uang Diterima: ', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                                    const Spacer(),
                                    Text(
                                      change >= 0
                                          ? 'Kembalian: ${CurrencyFormatter.format(change)}'
                                          : 'Kurang: ${CurrencyFormatter.format(change.abs())}',
                                      style: TextStyle(
                                        fontSize: 12,
                                        fontWeight: FontWeight.w700,
                                        color: change >= 0 ? AppColors.primary : AppColors.danger,
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 8),
                                TextField(
                                  controller: paidController,
                                  keyboardType: TextInputType.number,
                                  style: AppTheme.dataMono(fontSize: 16, fontWeight: FontWeight.w700),
                                  decoration: InputDecoration(
                                    prefixText: 'Rp ',
                                    isDense: true,
                                    contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                                  ),
                                  onChanged: (_) => setSheetState(() {}),
                                ),
                                const SizedBox(height: 8),
                                SingleChildScrollView(
                                  scrollDirection: Axis.horizontal,
                                  child: Row(
                                    children: [
                                      _quickCashButton('Uang Pas', _cartTotal, paidController, setSheetState),
                                      const SizedBox(width: 6),
                                      _quickCashButton('+10rb', (_cartTotal + 10000), paidController, setSheetState),
                                      const SizedBox(width: 6),
                                      _quickCashButton('+20rb', (_cartTotal + 20000), paidController, setSheetState),
                                      const SizedBox(width: 6),
                                      _quickCashButton('+50rb', (_cartTotal + 50000), paidController, setSheetState),
                                      const SizedBox(width: 6),
                                      _quickCashButton('100.000', 100000, paidController, setSheetState),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ] else if (selectedPaymentMethod == 'QRIS') ...[
                          Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: AppColors.tealSurface,
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(color: AppColors.teal.withValues(alpha: 0.3)),
                            ),
                            child: const Row(
                              children: [
                                Icon(Icons.qr_code_scanner_rounded, color: AppColors.teal, size: 24),
                                SizedBox(width: 10),
                                Expanded(
                                  child: Text(
                                    'Pelanggan scan QRIS Toko.\nTransaksi langsung lunas otomatis.',
                                    style: TextStyle(fontSize: 12, color: AppColors.textPrimary, height: 1.3),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ] else if (selectedPaymentMethod == 'Kasbon') ...[
                          Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: AppColors.amberSurface,
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(color: AppColors.amberText.withValues(alpha: 0.3)),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text('Pilih Pelanggan Kasbon:', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
                                const SizedBox(height: 6),
                                if (_customers.isNotEmpty)
                                  DropdownButtonFormField<int>(
                                    initialValue: selectedCustomerId,
                                    decoration: InputDecoration(
                                      isDense: true,
                                      filled: true,
                                      fillColor: Colors.white,
                                      contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                                    ),
                                    items: _customers.map((c) {
                                      final phoneText = (c.phone != null && c.phone!.isNotEmpty) ? c.phone! : 'Tanpa No';
                                      return DropdownMenuItem<int>(
                                        value: c.id,
                                        child: Text(
                                          '${c.name} ($phoneText)',
                                          style: const TextStyle(fontSize: 13),
                                        ),
                                      );
                                    }).toList(),
                                    onChanged: (val) => setSheetState(() => selectedCustomerId = val),
                                  )
                                else
                                  const Text('Belum ada data pelanggan di sistem.', style: TextStyle(fontSize: 12, color: AppColors.danger)),
                              ],
                            ),
                          ),
                        ],
                        const SizedBox(height: 16),

                        // Tombol Aksi Selesaikan Transaksi
                        ElevatedButton(
                          style: ElevatedButton.styleFrom(
                            backgroundColor: (selectedPaymentMethod == 'Tunai' && change < 0)
                                ? AppColors.textSecondary
                                : AppColors.primary,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 14),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                          ),
                          onPressed: (selectedPaymentMethod == 'Tunai' && change < 0)
                              ? null
                              : () {
                                  Navigator.pop(ctx);
                                  final paidAmount = selectedPaymentMethod == 'Tunai'
                                      ? currentPaid
                                      : (selectedPaymentMethod == 'QRIS' ? _cartTotal : 0);
                                  _processTransaction(
                                    selectedPaymentMethod,
                                    paid: paidAmount,
                                    customerId: selectedPaymentMethod == 'Kasbon' ? selectedCustomerId : null,
                                  );
                                },
                          child: Text(
                            selectedPaymentMethod == 'Tunai'
                                ? 'Bayar Tunai (${CurrencyFormatter.format(_cartTotal)})'
                                : selectedPaymentMethod == 'QRIS'
                                    ? 'Selesaikan QRIS (${CurrencyFormatter.format(_cartTotal)})'
                                    : 'Simpan Kasbon (${CurrencyFormatter.format(_cartTotal)})',
                            style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            );
          },
        );
      },
    );
  }

  Widget _paymentChip({
    required String label,
    required IconData icon,
    required bool isSelected,
    required Color color,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        padding: const EdgeInsets.symmetric(vertical: 10),
        decoration: BoxDecoration(
          color: isSelected ? color : AppColors.surface,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: isSelected ? color : AppColors.border,
            width: isSelected ? 1.5 : 1,
          ),
          boxShadow: isSelected
              ? [
                  BoxShadow(
                    color: color.withValues(alpha: 0.25),
                    blurRadius: 6,
                    offset: const Offset(0, 2),
                  ),
                ]
              : null,
        ),
        child: Column(
          children: [
            Icon(
              icon,
              size: 20,
              color: isSelected ? Colors.white : color,
            ),
            const SizedBox(height: 4),
            Text(
              label,
              style: TextStyle(
                fontSize: 12,
                fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
                color: isSelected ? Colors.white : AppColors.textPrimary,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _quickCashButton(
    String label,
    int amount,
    TextEditingController controller,
    StateSetter setSheetState,
  ) {
    return OutlinedButton(
      style: OutlinedButton.styleFrom(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        side: const BorderSide(color: AppColors.border),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        backgroundColor: AppColors.surfaceMuted,
        minimumSize: Size.zero,
        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
      ),
      onPressed: () {
        setSheetState(() {
          controller.text = amount.toString();
        });
      },
      child: Text(
        label,
        style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: AppColors.primary),
      ),
    );
  }

  Future<void> _processTransaction(
    String method, {
    int? paid,
    int? customerId,
    String? note,
  }) async {
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
      var rawUrl = prefs.getString('kios_api_base_url')?.trim();
      if (rawUrl == null || rawUrl.isEmpty) {
        rawUrl = ApiEndpoints.defaultBaseUrl;
      }
      final cleanBaseUrl = rawUrl.endsWith('/') ? rawUrl.substring(0, rawUrl.length - 1) : rawUrl;

      final payload = <String, dynamic>{
        'items': itemsPayload,
        'paid': paid ?? total,
        'payment_type': method.toLowerCase(),
      };
      if (customerId != null) payload['customer_id'] = customerId;
      if (note != null) payload['note'] = note;

      final dio = _createDio();
      final response = await dio.post('$cleanBaseUrl/pos', data: payload);

      if (response.statusCode == 200 && response.data['success'] == true) {
        _showToast('Transaksi $method senilai ${CurrencyFormatter.format(total)} Berhasil!');
        fetchPosData(showIndicator: false);

        if (response.data['sale'] is Map) {
          try {
            final createdSale = SaleModel.fromJson(response.data['sale']);
            BluetoothPrinterService.instance.printSaleReceipt(createdSale);
          } catch (_) {}
        }
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
                  final query = _searchController.text.trim();
                  final matchesSearch = query.isEmpty ||
                      p.name.toLowerCase().contains(query.toLowerCase()) ||
                      _matchesBarcode(p, query);
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
                                decoration: BoxDecoration(
                                  color: _fetchError != null ? AppColors.dangerSurface : AppColors.surfaceMuted,
                                  shape: BoxShape.circle,
                                ),
                                child: Icon(
                                  _fetchError != null ? Icons.cloud_off_rounded : Icons.inventory_2_outlined,
                                  size: 40,
                                  color: _fetchError != null ? AppColors.danger : AppColors.textSecondary,
                                ),
                              ),
                              const SizedBox(height: 14),
                              Text(
                                _fetchError != null
                                    ? 'Gagal Menghubungkan ke Server'
                                    : _searchController.text.isNotEmpty
                                        ? 'Tidak ada produk cocok dengan "${_searchController.text}"'
                                        : 'Belum ada produk untuk kategori ini',
                                textAlign: TextAlign.center,
                                style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
                              ),
                              const SizedBox(height: 6),
                              if (_fetchError != null) ...[
                                Padding(
                                  padding: const EdgeInsets.symmetric(horizontal: 24),
                                  child: Text(
                                    'URL: $_activeUrl\nError: $_fetchError',
                                    textAlign: TextAlign.center,
                                    style: TextStyle(fontSize: 11, color: AppColors.danger),
                                  ),
                                ),
                                const SizedBox(height: 14),
                                Wrap(
                                  spacing: 8,
                                  runSpacing: 8,
                                  alignment: WrapAlignment.center,
                                  children: [
                                    ElevatedButton.icon(
                                      style: ElevatedButton.styleFrom(
                                        backgroundColor: AppColors.primary,
                                        foregroundColor: Colors.white,
                                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                      ),
                                      icon: const Icon(Icons.sync_rounded, size: 16),
                                      label: const Text('Coba Lagi'),
                                      onPressed: () => fetchPosData(),
                                    ),
                                    OutlinedButton.icon(
                                      style: OutlinedButton.styleFrom(
                                        side: const BorderSide(color: AppColors.primary),
                                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                      ),
                                      icon: const Icon(Icons.restore_rounded, size: 16, color: AppColors.primary),
                                      label: const Text('Reset ke URL Produksi'),
                                      onPressed: () async {
                                        final prefs = await SharedPreferences.getInstance();
                                        await prefs.setString('kios_api_base_url', ApiEndpoints.defaultBaseUrl);
                                        fetchPosData();
                                      },
                                    ),
                                  ],
                                ),
                              ] else ...[
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
