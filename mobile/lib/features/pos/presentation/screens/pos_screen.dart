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
import 'package:kios_berkah_mobile/features/scanner/data/models/scan_result.dart';
import 'package:kios_berkah_mobile/features/sales/data/models/sale_model.dart';
import 'package:kios_berkah_mobile/core/services/bluetooth_printer_service.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:kios_berkah_mobile/core/utils/qris_helper.dart';

class CustomerItem {
  final int id;
  final String name;
  final String? phone;
  final int? creditLimit;
  final int outstanding;

  CustomerItem({
    required this.id,
    required this.name,
    this.phone,
    this.creditLimit,
    this.outstanding = 0,
  });

  factory CustomerItem.fromJson(Map<String, dynamic> json) {
    return CustomerItem(
      id: json['id'] as int? ?? 0,
      name: json['name'] as String? ?? '',
      phone: json['phone'] as String?,
      creditLimit: (json['credit_limit'] as num?)?.toInt(),
      outstanding: (json['outstanding'] as num?)?.toInt() ?? 0,
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
  Map<String, dynamic> _storeInfo = {};
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
            if (response.data['store'] is Map) {
              _storeInfo = Map<String, dynamic>.from(response.data['store']);
            }
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

  int get cartItemCount => _cartItemCount;
  int get cartTotal => _cartTotal;

  void showCartSheetExternal() {
    if (_cart.isNotEmpty) {
      _showCartSheet();
    }
  }

  Future<ScanResult> scanAndAddToCart(String barcode) async {
    final clean = barcode.trim();
    if (clean.isEmpty) {
      return ScanResult(
        success: false,
        message: 'Barcode kosong',
        cartItemCount: _cartItemCount,
        cartTotal: _cartTotal,
      );
    }

    ProductModel? matched = _findProductByBarcode(clean);
    if (matched == null) {
      await fetchPosData(showIndicator: false);
      matched = _findProductByBarcode(clean);
    }

    if (matched == null) {
      return ScanResult(
        success: false,
        barcode: clean,
        message: 'Barcode $clean tidak terdaftar',
        cartItemCount: _cartItemCount,
        cartTotal: _cartTotal,
      );
    }

    final usedBase = _cart.where((c) => c.product.id == matched!.id).fold(0, (s, c) => s + c.totalBaseQty);
    if (matched.stock - usedBase < 1) {
      return ScanResult(
        success: false,
        productName: matched.name,
        productPrice: matched.price,
        barcode: clean,
        message: 'Stok ${matched.name} sudah habis!',
        cartItemCount: _cartItemCount,
        cartTotal: _cartTotal,
      );
    }

    _addToCart(matched);
    _searchController.clear();
    setState(() {});

    return ScanResult(
      success: true,
      productName: matched.name,
      productPrice: matched.price,
      barcode: clean,
      cartItemCount: _cartItemCount,
      cartTotal: _cartTotal,
      message: '1x ${matched.name} dimasukkan',
    );
  }

  Future<void> openBarcodeScanner() async {
    final shouldOpenCart = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => BarcodeScannerScreen(
          initialCartCount: _cartItemCount,
          initialCartTotal: _cartTotal,
          onScanBarcode: scanAndAddToCart,
        ),
      ),
    );
    setState(() {});
    if (shouldOpenCart == true && _cart.isNotEmpty) {
      _showCartSheet();
    }
  }

  void _addToCart(ProductModel product, {ProductUnitModel? unit}) {
    final isi = unit?.isi ?? 1;
    final usedBase = _cart.where((c) => c.product.id == product.id).fold(0, (s, c) => s + c.totalBaseQty);
    if (product.stock - usedBase < isi) {
      _showToast('Stok ${product.name} tidak cukup${unit != null ? " untuk 1 ${unit.name}" : ""}!', isError: true);
      return;
    }

    setState(() {
      final key = '${product.id}-${unit?.id ?? 0}';
      final index = _cart.indexWhere((item) => item.key == key);
      if (index >= 0) {
        _cart[index].qty++;
      } else {
        _cart.add(CartItemModel(product: product, unit: unit, qty: 1));
      }
    });
  }

  void _removeFromCart(ProductModel product, {ProductUnitModel? unit}) {
    setState(() {
      final key = '${product.id}-${unit?.id ?? 0}';
      final index = _cart.indexWhere((item) => item.key == key);
      if (index >= 0) {
        if (_cart[index].qty > 1) {
          _cart[index].qty--;
        } else {
          _cart.removeAt(index);
        }
      }
    });
  }

  void _showUnitPickerForProduct(ProductModel product) {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(
                  child: Container(
                    width: 40,
                    height: 4,
                    decoration: BoxDecoration(
                      color: AppColors.border,
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                Text(
                  product.name,
                  style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
                ),
                const SizedBox(height: 4),
                const Text(
                  'Pilih satuan barang yang ingin dimasukkan ke keranjang:',
                  style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
                ),
                const SizedBox(height: 14),
                // Satuan dasar
                Container(
                  decoration: BoxDecoration(
                    color: AppColors.surfaceMuted,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: AppColors.border),
                  ),
                  child: ListTile(
                    leading: const Icon(Icons.inventory_2_outlined, color: AppColors.primary),
                    title: const Text('Satuan Dasar (Pcs)', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
                    subtitle: Text(
                      CurrencyFormatter.format(product.price),
                      style: AppTheme.dataMono(fontSize: 13, color: AppColors.primary, fontWeight: FontWeight.w700),
                    ),
                    trailing: const Icon(Icons.add_shopping_cart_rounded, color: AppColors.primary, size: 20),
                    onTap: () {
                      Navigator.pop(ctx);
                      _addToCart(product);
                    },
                  ),
                ),
                const SizedBox(height: 8),
                ...product.units.map((u) {
                  return Container(
                    margin: const EdgeInsets.only(bottom: 8),
                    decoration: BoxDecoration(
                      color: AppColors.surfaceMuted,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: AppColors.border),
                    ),
                    child: ListTile(
                      leading: const Icon(Icons.layers_outlined, color: AppColors.teal),
                      title: Text('${u.name} (Isi ${u.isi})', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
                      subtitle: Text(
                        '${CurrencyFormatter.format(u.price)} / ${u.name}',
                        style: AppTheme.dataMono(fontSize: 13, color: AppColors.teal, fontWeight: FontWeight.w700),
                      ),
                      trailing: const Icon(Icons.add_shopping_cart_rounded, color: AppColors.teal, size: 20),
                      onTap: () {
                        Navigator.pop(ctx);
                        _addToCart(product, unit: u);
                      },
                    ),
                  );
                }),
              ],
            ),
          ),
        );
      },
    );
  }

  void _showSelectUnitModal(CartItemModel item, StateSetter setSheetState) {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (uCtx) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(18),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(
                  child: Container(
                    width: 40,
                    height: 4,
                    decoration: BoxDecoration(
                      color: AppColors.border,
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                ),
                const SizedBox(height: 14),
                Text(
                  'Ubah Satuan: ${item.product.name}',
                  style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
                ),
                const SizedBox(height: 12),
                ListTile(
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  tileColor: item.unit == null ? AppColors.primarySurface : AppColors.surfaceMuted,
                  leading: const Icon(Icons.inventory_2_outlined, color: AppColors.primary),
                  title: const Text('Satuan Dasar (Pcs)', style: TextStyle(fontWeight: FontWeight.w600)),
                  subtitle: Text(CurrencyFormatter.format(item.product.price)),
                  trailing: item.unit == null ? const Icon(Icons.check_circle_rounded, color: AppColors.primary) : null,
                  onTap: () {
                    setState(() => item.unit = null);
                    setSheetState(() {});
                    Navigator.pop(uCtx);
                  },
                ),
                const SizedBox(height: 8),
                ...item.product.units.map((u) {
                  final isSelected = item.unit?.id == u.id;
                  return Container(
                    margin: const EdgeInsets.only(bottom: 8),
                    child: ListTile(
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      tileColor: isSelected ? AppColors.tealSurface : AppColors.surfaceMuted,
                      leading: const Icon(Icons.layers_outlined, color: AppColors.teal),
                      title: Text('${u.name} (Isi ${u.isi})', style: const TextStyle(fontWeight: FontWeight.w600)),
                      subtitle: Text('${CurrencyFormatter.format(u.price)} / ${u.name}'),
                      trailing: isSelected ? const Icon(Icons.check_circle_rounded, color: AppColors.teal) : null,
                      onTap: () {
                        setState(() => item.unit = u);
                        setSheetState(() {});
                        Navigator.pop(uCtx);
                      },
                    ),
                  );
                }),
              ],
            ),
          ),
        );
      },
    );
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

  String? _getQrisPayload() {
    final payload = _storeInfo['qris_payload'] as String?;
    if (payload != null && payload.trim().isNotEmpty) {
      return payload.trim();
    }
    // Fallback QRIS payload Kios Berkah agar selalu menghasilkan QRIS Dinamis
    return '00020101021126590013ID.CO.BNI.WWW011893600009150471362202096111903340303UMI51440014ID.CO.QRIS.WWW0215ID10265451804280303UMI5204541153033605802ID5919KIOS SEMBAKO BERKAH6007BANGGAI61059476262070703A0163043EAB';
  }

  String? _getQrisImageUrl() {
    final raw = _storeInfo['qris_url'] as String?;
    if (raw == null || raw.isEmpty) return null;
    if (raw.startsWith('http')) {
      try {
        final uri = Uri.parse(raw);
        final baseUri = Uri.parse(_activeUrl);
        if (uri.host == 'localhost' || uri.host == '127.0.0.1') {
          return uri.replace(scheme: baseUri.scheme, host: baseUri.host, port: baseUri.port).toString();
        }
      } catch (_) {}
      return raw;
    }
    return '$_activeUrl/$raw';
  }

  void _showQrisFullscreenDialog(String? dynamicPayload, String? imageUrl, int amount, String merchantName) {
    showDialog(
      context: context,
      builder: (dCtx) {
        return Dialog(
          backgroundColor: Colors.white,
          insetPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: AppColors.tealSurface,
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: AppColors.teal.withValues(alpha: 0.3)),
                          ),
                          child: const Text(
                            'QRIS DINAMIS',
                            style: TextStyle(fontWeight: FontWeight.w900, fontSize: 13, color: AppColors.teal),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          merchantName.isNotEmpty ? merchantName : (_storeInfo['store_name'] ?? 'Kios BERKAH'),
                          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
                        ),
                      ],
                    ),
                    IconButton(
                      icon: const Icon(Icons.close_rounded, size: 20),
                      padding: EdgeInsets.zero,
                      constraints: const BoxConstraints(),
                      onPressed: () => Navigator.pop(dCtx),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: AppColors.border, width: 1.5),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.05),
                        blurRadius: 10,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: dynamicPayload != null && dynamicPayload.isNotEmpty
                      ? QrImageView(
                          data: dynamicPayload,
                          version: QrVersions.auto,
                          size: 240,
                          backgroundColor: Colors.white,
                          errorCorrectionLevel: QrErrorCorrectLevel.M,
                        )
                      : (imageUrl != null && imageUrl.isNotEmpty
                          ? Image.network(
                              imageUrl,
                              height: 240,
                              fit: BoxFit.contain,
                              errorBuilder: (context, error, stackTrace) => const Icon(Icons.broken_image, size: 64, color: AppColors.textMuted),
                            )
                          : const SizedBox(
                              height: 200,
                              child: Center(child: Text('QRIS belum diatur')),
                            )),
                ),
                const SizedBox(height: 16),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 14),
                  decoration: BoxDecoration(
                    color: AppColors.tealSurface,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: AppColors.teal.withValues(alpha: 0.2)),
                  ),
                  child: Column(
                    children: [
                      const Text(
                        'TOTAL PEMBAYARAN',
                        style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: AppColors.textSecondary, letterSpacing: 0.5),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        CurrencyFormatter.format(amount),
                        style: AppTheme.dataMono(
                          fontSize: 22,
                          fontWeight: FontWeight.w900,
                          color: AppColors.teal,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 10),
                if (dynamicPayload != null && dynamicPayload.isNotEmpty)
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.check_circle_rounded, size: 14, color: AppColors.primary),
                      const SizedBox(width: 4),
                      Text(
                        'Nominal ${CurrencyFormatter.format(amount)} sudah terisi otomatis',
                        style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: AppColors.primary),
                      ),
                    ],
                  )
                else
                  const Text(
                    'QRIS Statis — Pembeli perlu mengetik nominal manual.',
                    style: TextStyle(fontSize: 11, color: AppColors.amberText, fontWeight: FontWeight.w600),
                  ),
                const SizedBox(height: 14),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.primary,
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      padding: const EdgeInsets.symmetric(vertical: 12),
                    ),
                    onPressed: () => Navigator.pop(dCtx),
                    child: const Text('Tutup Pratinjau', style: TextStyle(fontWeight: FontWeight.w700)),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  void _showCartSheet() {
    String selectedPaymentMethod = 'Tunai'; // 'Tunai', 'QRIS', 'Kasbon'
    int? selectedCustomerId = _customers.isNotEmpty ? _customers.first.id : null;
    final paidController = TextEditingController();
    final dpController = TextEditingController(text: '0');
    final discountController = TextEditingController(text: '0');
    final noteController = TextEditingController();
    bool isDiscountPercent = false;
    DateTime dueDate = DateTime.now().add(const Duration(days: 7));

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
            final subtotal = _cartTotal;

            // Kalkulasi Diskon Nota
            final rawDisc = int.tryParse(discountController.text.replaceAll(RegExp(r'[^0-9]'), '')) ?? 0;
            final discValue = isDiscountPercent
                ? ((subtotal * rawDisc) / 100).round()
                : rawDisc.clamp(0, subtotal);
            final finalTotal = (subtotal - discValue).clamp(0, double.infinity).toInt();

            // Tunai
            final currentPaid = int.tryParse(paidController.text.replaceAll(RegExp(r'[^0-9]'), '')) ?? 0;
            final change = currentPaid - finalTotal;

            // Kasbon & DP
            final selectedCustomer = _customers.firstWhere(
              (c) => c.id == selectedCustomerId,
              orElse: () => _customers.isNotEmpty ? _customers.first : CustomerItem(id: 0, name: 'Umum'),
            );
            final rawDp = int.tryParse(dpController.text.replaceAll(RegExp(r'[^0-9]'), '')) ?? 0;
            final dpAmount = rawDp.clamp(0, finalTotal);
            final sisaKasbon = finalTotal - dpAmount;
            final projectedDebt = selectedCustomer.outstanding + sisaKasbon;
            final isOverLimit = selectedCustomer.creditLimit != null && projectedDebt > selectedCustomer.creditLimit!;

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
                        // Daftar Item Belanja (Dengan Satuan & Badge Grosir)
                        ConstrainedBox(
                          constraints: BoxConstraints(
                            maxHeight: MediaQuery.of(context).size.height * 0.28,
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
                                          Row(
                                            children: [
                                              Expanded(
                                                child: Text(
                                                  item.product.name,
                                                  style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
                                                  maxLines: 1,
                                                  overflow: TextOverflow.ellipsis,
                                                ),
                                              ),
                                              if (item.isGrosir)
                                                Container(
                                                  margin: const EdgeInsets.only(left: 6),
                                                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                                  decoration: BoxDecoration(
                                                    color: AppColors.primarySurface,
                                                    borderRadius: BorderRadius.circular(6),
                                                  ),
                                                  child: const Text(
                                                    'GROSIR',
                                                    style: TextStyle(fontSize: 9, fontWeight: FontWeight.w800, color: AppColors.primary),
                                                  ),
                                                ),
                                            ],
                                          ),
                                          const SizedBox(height: 3),
                                          Row(
                                            children: [
                                              Text(
                                                CurrencyFormatter.format(item.unitPrice),
                                                style: AppTheme.dataMono(fontSize: 12, color: AppColors.primary),
                                              ),
                                              if (item.product.units.isNotEmpty) ...[
                                                const SizedBox(width: 8),
                                                GestureDetector(
                                                  onTap: () => _showSelectUnitModal(item, setSheetState),
                                                  child: Container(
                                                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                                    decoration: BoxDecoration(
                                                      color: AppColors.surfaceMuted,
                                                      borderRadius: BorderRadius.circular(6),
                                                      border: Border.all(color: AppColors.border),
                                                    ),
                                                    child: Row(
                                                      mainAxisSize: MainAxisSize.min,
                                                      children: [
                                                        Text(
                                                          item.unit?.name ?? 'Pcs',
                                                          style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w700),
                                                        ),
                                                        const Icon(Icons.arrow_drop_down, size: 14),
                                                      ],
                                                    ),
                                                  ),
                                                ),
                                              ],
                                            ],
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
                                              _removeFromCart(item.product, unit: item.unit);
                                              setSheetState(() {
                                                if (selectedPaymentMethod == 'Tunai') {
                                                  paidController.text = finalTotal.toString();
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
                                              if ((item.totalBaseQty + item.isi) <= item.product.stock) {
                                                _addToCart(item.product, unit: item.unit);
                                                setSheetState(() {
                                                  if (selectedPaymentMethod == 'Tunai') {
                                                    paidController.text = finalTotal.toString();
                                                  }
                                                });
                                              } else {
                                                _showToast('Stok tersisa hanya ${item.product.stock}!');
                                              }
                                            },
                                          ),
                                        ],
                                      ),
                                    ),
                                    const SizedBox(width: 8),
                                    SizedBox(
                                      width: 70,
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
                        const Divider(height: 16),

                        // Form Diskon Nota (Opsional)
                        Row(
                          children: [
                            const Text('Diskon Nota: ', style: TextStyle(fontSize: 12, color: AppColors.textSecondary, fontWeight: FontWeight.w600)),
                            const Spacer(),
                            SizedBox(
                              width: 95,
                              height: 32,
                              child: TextField(
                                controller: discountController,
                                keyboardType: TextInputType.number,
                                textAlign: TextAlign.right,
                                style: AppTheme.dataMono(fontSize: 12, fontWeight: FontWeight.w700),
                                decoration: InputDecoration(
                                  isDense: true,
                                  prefixText: isDiscountPercent ? '' : 'Rp ',
                                  suffixText: isDiscountPercent ? '%' : '',
                                  contentPadding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
                                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                                ),
                                onChanged: (_) => setSheetState(() {
                                  if (selectedPaymentMethod == 'Tunai') {
                                    paidController.text = finalTotal.toString();
                                  }
                                }),
                              ),
                            ),
                            const SizedBox(width: 6),
                            InkWell(
                              onTap: () => setSheetState(() {
                                isDiscountPercent = !isDiscountPercent;
                                if (selectedPaymentMethod == 'Tunai') {
                                  paidController.text = finalTotal.toString();
                                }
                              }),
                              borderRadius: BorderRadius.circular(8),
                              child: Container(
                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
                                decoration: BoxDecoration(
                                  color: AppColors.surfaceMuted,
                                  borderRadius: BorderRadius.circular(8),
                                  border: Border.all(color: AppColors.border),
                                ),
                                child: Text(
                                  isDiscountPercent ? '%' : 'Rp',
                                  style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700),
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 10),

                        // Total Tagihan Card
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
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Text(
                                    'Total Tagihan',
                                    style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                                  ),
                                  if (discValue > 0)
                                    Text(
                                      'Diskon: -${CurrencyFormatter.format(discValue)}',
                                      style: const TextStyle(fontSize: 10, color: AppColors.danger, fontWeight: FontWeight.w600),
                                    ),
                                ],
                              ),
                              Text(
                                CurrencyFormatter.format(finalTotal),
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

                        // Pilihan Metode Pembayaran
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
                                  paidController.text = finalTotal.toString();
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
                                      _quickCashButton('Uang Pas', finalTotal, paidController, setSheetState),
                                      const SizedBox(width: 6),
                                      _quickCashButton('+10rb', (finalTotal + 10000), paidController, setSheetState),
                                      const SizedBox(width: 6),
                                      _quickCashButton('+20rb', (finalTotal + 20000), paidController, setSheetState),
                                      const SizedBox(width: 6),
                                      _quickCashButton('+50rb', (finalTotal + 50000), paidController, setSheetState),
                                      const SizedBox(width: 6),
                                      _quickCashButton('100.000', 100000, paidController, setSheetState),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ] else if (selectedPaymentMethod == 'QRIS') ...[
                          Builder(
                            builder: (context) {
                              final rawPayload = _getQrisPayload();
                              final qrisUrl = _getQrisImageUrl();
                              final dynamicPayload = (rawPayload != null && rawPayload.isNotEmpty)
                                  ? QrisHelper.toDynamic(rawPayload, finalTotal)
                                  : null;
                              final merchant = rawPayload != null ? QrisHelper.getMerchantName(rawPayload) : '';
                              final hasQris = (dynamicPayload != null && dynamicPayload.isNotEmpty) || (qrisUrl != null && qrisUrl.isNotEmpty);

                              return Container(
                                padding: const EdgeInsets.all(14),
                                decoration: BoxDecoration(
                                  color: Colors.white,
                                  borderRadius: BorderRadius.circular(16),
                                  border: Border.all(color: AppColors.border),
                                  boxShadow: [
                                    BoxShadow(
                                      color: Colors.black.withValues(alpha: 0.03),
                                      blurRadius: 8,
                                      offset: const Offset(0, 2),
                                    ),
                                  ],
                                ),
                                child: Column(
                                  children: [
                                    Row(
                                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                      children: [
                                        Row(
                                          children: [
                                            Container(
                                              padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                                              decoration: BoxDecoration(
                                                color: AppColors.tealSurface,
                                                borderRadius: BorderRadius.circular(6),
                                                border: Border.all(color: AppColors.teal.withValues(alpha: 0.3)),
                                              ),
                                              child: const Text(
                                                'QRIS DINAMIS',
                                                style: TextStyle(fontSize: 11, fontWeight: FontWeight.w900, color: AppColors.teal),
                                              ),
                                            ),
                                            const SizedBox(width: 8),
                                            Text(
                                              merchant.isNotEmpty ? merchant : (_storeInfo['store_name'] ?? 'Kode QRIS Toko'),
                                              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
                                            ),
                                          ],
                                        ),
                                        if (hasQris)
                                          InkWell(
                                            borderRadius: BorderRadius.circular(8),
                                            onTap: () => _showQrisFullscreenDialog(dynamicPayload, qrisUrl, finalTotal, merchant),
                                            child: const Padding(
                                              padding: EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                              child: Row(
                                                children: [
                                                  Icon(Icons.fullscreen_rounded, size: 16, color: AppColors.primary),
                                                  SizedBox(width: 2),
                                                  Text(
                                                    'Perbesar',
                                                    style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: AppColors.primary),
                                                  ),
                                                ],
                                              ),
                                            ),
                                          ),
                                      ],
                                    ),
                                    const SizedBox(height: 12),
                                    if (dynamicPayload != null && dynamicPayload.isNotEmpty) ...[
                                      GestureDetector(
                                        onTap: () => _showQrisFullscreenDialog(dynamicPayload, qrisUrl, finalTotal, merchant),
                                        child: Container(
                                          padding: const EdgeInsets.all(10),
                                          decoration: BoxDecoration(
                                            color: Colors.white,
                                            borderRadius: BorderRadius.circular(14),
                                            border: Border.all(color: AppColors.border),
                                          ),
                                          child: QrImageView(
                                            data: dynamicPayload,
                                            version: QrVersions.auto,
                                            size: 170,
                                            backgroundColor: Colors.white,
                                            errorCorrectionLevel: QrErrorCorrectLevel.M,
                                          ),
                                        ),
                                      ),
                                      const SizedBox(height: 8),
                                      Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                        decoration: BoxDecoration(
                                          color: AppColors.primarySurface,
                                          borderRadius: BorderRadius.circular(20),
                                        ),
                                        child: Row(
                                          mainAxisSize: MainAxisSize.min,
                                          children: [
                                            const Icon(Icons.check_circle_rounded, size: 13, color: AppColors.primary),
                                            const SizedBox(width: 5),
                                            Text(
                                              'Nominal terisi otomatis (${CurrencyFormatter.format(finalTotal)})',
                                              style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: AppColors.primary),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ] else if (qrisUrl != null && qrisUrl.isNotEmpty) ...[
                                      GestureDetector(
                                        onTap: () => _showQrisFullscreenDialog(null, qrisUrl, finalTotal, merchant),
                                        child: Container(
                                          padding: const EdgeInsets.all(10),
                                          decoration: BoxDecoration(
                                            color: Colors.white,
                                            borderRadius: BorderRadius.circular(14),
                                            border: Border.all(color: AppColors.border),
                                          ),
                                          child: Image.network(
                                            qrisUrl,
                                            height: 170,
                                            fit: BoxFit.contain,
                                            errorBuilder: (context, error, stackTrace) => const Icon(Icons.qr_code, size: 60, color: AppColors.textSecondary),
                                          ),
                                        ),
                                      ),
                                      const SizedBox(height: 8),
                                      const Text(
                                        'QRIS Statis — Pembeli perlu mengetik nominal manual.',
                                        style: TextStyle(fontSize: 11, color: AppColors.amberText, fontWeight: FontWeight.w600),
                                      ),
                                    ] else ...[
                                      Container(
                                        padding: const EdgeInsets.symmetric(vertical: 24, horizontal: 16),
                                        child: const Column(
                                          children: [
                                            Icon(Icons.qr_code_2_rounded, size: 48, color: AppColors.textMuted),
                                            SizedBox(height: 8),
                                            Text(
                                              'Belum Ada QRIS Toko',
                                              style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
                                            ),
                                            SizedBox(height: 4),
                                            Text(
                                              'Unggah gambar QRIS di menu Pengaturan pada web dashboard toko.',
                                              textAlign: TextAlign.center,
                                              style: TextStyle(fontSize: 11, color: AppColors.textSecondary),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ],
                                    const SizedBox(height: 10),
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                                      decoration: BoxDecoration(
                                        color: AppColors.surfaceMuted,
                                        borderRadius: BorderRadius.circular(10),
                                      ),
                                      child: Row(
                                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                        children: [
                                          const Text(
                                            'Total Tagihan QRIS:',
                                            style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
                                          ),
                                          Text(
                                            CurrencyFormatter.format(finalTotal),
                                            style: AppTheme.dataMono(fontSize: 14, fontWeight: FontWeight.w800, color: AppColors.primary),
                                          ),
                                        ],
                                      ),
                                    ),
                                    const SizedBox(height: 6),
                                    const Text(
                                      'Mendukung BCA, Mandiri Livin, BRImo, GoPay, OVO, ShopeePay, Dana, dll.\nPastikan pembeli telah selesai scan & bayar sebelum menekan tombol di bawah.',
                                      textAlign: TextAlign.center,
                                      style: TextStyle(fontSize: 10, color: AppColors.textSecondary, height: 1.3),
                                    ),
                                  ],
                                ),
                              );
                            },
                          ),
                        ] else if (selectedPaymentMethod == 'Kasbon') ...[
                          Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: AppColors.amberSurface,
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(color: AppColors.amberText.withValues(alpha: 0.3)),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    const Icon(Icons.person_pin_rounded, color: AppColors.amberText, size: 20),
                                    const SizedBox(width: 6),
                                    const Text('Pelanggan Kasbon (Hutang):', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
                                  ],
                                ),
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
                                          style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                                        ),
                                      );
                                    }).toList(),
                                    onChanged: (val) => setSheetState(() => selectedCustomerId = val),
                                  )
                                else
                                  const Text('Belum ada data pelanggan di sistem.', style: TextStyle(fontSize: 12, color: AppColors.danger)),

                                const SizedBox(height: 8),

                                // Kartu Info Hutang Berjalan & Batas Kredit
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                                  decoration: BoxDecoration(
                                    color: Colors.white,
                                    borderRadius: BorderRadius.circular(10),
                                    border: Border.all(color: AppColors.border),
                                  ),
                                  child: Row(
                                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                    children: [
                                      Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          const Text('Hutang Berjalan', style: TextStyle(fontSize: 10, color: AppColors.textSecondary)),
                                          Text(
                                            CurrencyFormatter.format(selectedCustomer.outstanding),
                                            style: AppTheme.dataMono(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.amberText),
                                          ),
                                        ],
                                      ),
                                      Column(
                                        crossAxisAlignment: CrossAxisAlignment.end,
                                        children: [
                                          const Text('Batas Kredit', style: TextStyle(fontSize: 10, color: AppColors.textSecondary)),
                                          Text(
                                            selectedCustomer.creditLimit != null
                                                ? CurrencyFormatter.format(selectedCustomer.creditLimit!)
                                                : 'Tanpa Batas',
                                            style: AppTheme.dataMono(fontSize: 12, fontWeight: FontWeight.w700),
                                          ),
                                        ],
                                      ),
                                    ],
                                  ),
                                ),
                                const Divider(height: 18),

                                // Bagian Bayar DP (Uang Muka)
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    const Text('Bayar DP / Uang Muka:', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
                                    Text(
                                      dpAmount > 0 ? 'DP: ${CurrencyFormatter.format(dpAmount)}' : 'Tanpa DP',
                                      style: TextStyle(
                                        fontSize: 12,
                                        fontWeight: FontWeight.w700,
                                        color: dpAmount > 0 ? AppColors.primary : AppColors.textSecondary,
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 6),
                                TextField(
                                  controller: dpController,
                                  keyboardType: TextInputType.number,
                                  style: AppTheme.dataMono(fontSize: 15, fontWeight: FontWeight.w700),
                                  decoration: InputDecoration(
                                    prefixText: 'Rp ',
                                    isDense: true,
                                    filled: true,
                                    fillColor: Colors.white,
                                    contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                                  ),
                                  onChanged: (_) => setSheetState(() {}),
                                ),
                                const SizedBox(height: 6),
                                SingleChildScrollView(
                                  scrollDirection: Axis.horizontal,
                                  child: Row(
                                    children: [
                                      _quickCashButton('Tanpa DP', 0, dpController, setSheetState),
                                      const SizedBox(width: 6),
                                      _quickCashButton('DP 25%', (finalTotal * 0.25).round(), dpController, setSheetState),
                                      const SizedBox(width: 6),
                                      _quickCashButton('DP 50%', (finalTotal * 0.5).round(), dpController, setSheetState),
                                      const SizedBox(width: 6),
                                      _quickCashButton('Lunas (100%)', finalTotal, dpController, setSheetState),
                                    ],
                                  ),
                                ),
                                const Divider(height: 18),

                                // Bagian Tanggal Jatuh Tempo
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    const Text('Jatuh Tempo Kasbon:', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
                                    InkWell(
                                      onTap: () async {
                                        final picked = await showDatePicker(
                                          context: context,
                                          initialDate: dueDate,
                                          firstDate: DateTime.now(),
                                          lastDate: DateTime.now().add(const Duration(days: 365)),
                                        );
                                        if (picked != null) {
                                          setSheetState(() => dueDate = picked);
                                        }
                                      },
                                      child: Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                        decoration: BoxDecoration(
                                          color: Colors.white,
                                          borderRadius: BorderRadius.circular(8),
                                          border: Border.all(color: AppColors.primary),
                                        ),
                                        child: Row(
                                          children: [
                                            const Icon(Icons.calendar_month_rounded, size: 14, color: AppColors.primary),
                                            const SizedBox(width: 4),
                                            Text(
                                              '${dueDate.day.toString().padLeft(2, '0')}/${dueDate.month.toString().padLeft(2, '0')}/${dueDate.year}',
                                              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.primary),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 6),
                                Row(
                                  children: [
                                    _quickDueButton('+7 Hari', 7, () => setSheetState(() => dueDate = DateTime.now().add(const Duration(days: 7)))),
                                    const SizedBox(width: 6),
                                    _quickDueButton('+14 Hari', 14, () => setSheetState(() => dueDate = DateTime.now().add(const Duration(days: 14)))),
                                    const SizedBox(width: 6),
                                    _quickDueButton('+30 Hari', 30, () => setSheetState(() => dueDate = DateTime.now().add(const Duration(days: 30)))),
                                  ],
                                ),
                                const Divider(height: 18),

                                // Ringkasan Kasbon & Peringatan Limit
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    const Text('Sisa Hutang Transaksi:', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                                    Text(
                                      CurrencyFormatter.format(sisaKasbon),
                                      style: AppTheme.dataMono(fontSize: 14, fontWeight: FontWeight.w800, color: AppColors.amberText),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 4),
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    const Text('Proyeksi Total Hutang:', style: TextStyle(fontSize: 11, color: AppColors.textSecondary)),
                                    Text(
                                      CurrencyFormatter.format(projectedDebt),
                                      style: TextStyle(
                                        fontSize: 11,
                                        fontWeight: FontWeight.w700,
                                        color: isOverLimit ? AppColors.danger : AppColors.textPrimary,
                                      ),
                                    ),
                                  ],
                                ),
                                if (isOverLimit) ...[
                                  const SizedBox(height: 8),
                                  Container(
                                    padding: const EdgeInsets.all(8),
                                    decoration: BoxDecoration(
                                      color: AppColors.dangerSurface,
                                      borderRadius: BorderRadius.circular(8),
                                      border: Border.all(color: AppColors.danger.withValues(alpha: 0.3)),
                                    ),
                                    child: Row(
                                      children: [
                                        const Icon(Icons.warning_amber_rounded, color: AppColors.danger, size: 16),
                                        const SizedBox(width: 6),
                                        Expanded(
                                          child: Text(
                                            'Melebihi batas kredit pelanggan (Batas: ${CurrencyFormatter.format(selectedCustomer.creditLimit!)})',
                                            style: const TextStyle(fontSize: 11, color: AppColors.danger, fontWeight: FontWeight.w600),
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ],
                              ],
                            ),
                          ),
                        ],
                        const SizedBox(height: 12),

                        // Catatan Nota (Opsional)
                        TextField(
                          controller: noteController,
                          decoration: InputDecoration(
                            hintText: 'Catatan nota (opsional)...',
                            prefixIcon: const Icon(Icons.edit_note_rounded, size: 20),
                            isDense: true,
                            contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                            border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                          ),
                        ),
                        const SizedBox(height: 16),

                        // Tombol Aksi Selesaikan Transaksi
                        ElevatedButton(
                          style: ElevatedButton.styleFrom(
                            backgroundColor: (selectedPaymentMethod == 'Tunai' && change < 0) ||
                                    (selectedPaymentMethod == 'Kasbon' && (isOverLimit || selectedCustomerId == null))
                                ? AppColors.textSecondary
                                : AppColors.primary,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 14),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                          ),
                          onPressed: (selectedPaymentMethod == 'Tunai' && change < 0) ||
                                  (selectedPaymentMethod == 'Kasbon' && (isOverLimit || selectedCustomerId == null))
                              ? null
                              : () {
                                  Navigator.pop(ctx);
                                  final paidAmount = selectedPaymentMethod == 'Tunai'
                                      ? currentPaid
                                      : (selectedPaymentMethod == 'QRIS' ? finalTotal : dpAmount);
                                  final dueDateStr = selectedPaymentMethod == 'Kasbon'
                                      ? '${dueDate.year}-${dueDate.month.toString().padLeft(2, '0')}-${dueDate.day.toString().padLeft(2, '0')}'
                                      : null;
                                  _processTransaction(
                                    selectedPaymentMethod,
                                    paid: paidAmount,
                                    customerId: selectedPaymentMethod == 'Kasbon' ? selectedCustomerId : null,
                                    dueDate: dueDateStr,
                                    discount: discValue,
                                    note: noteController.text.trim().isNotEmpty ? noteController.text.trim() : null,
                                  );
                                },
                          child: Text(
                            selectedPaymentMethod == 'Tunai'
                                ? 'Bayar Tunai (${CurrencyFormatter.format(finalTotal)})'
                                : selectedPaymentMethod == 'QRIS'
                                    ? 'Selesaikan QRIS (${CurrencyFormatter.format(finalTotal)})'
                                    : 'Simpan Kasbon (Sisa: ${CurrencyFormatter.format(sisaKasbon)})',
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

  Widget _quickDueButton(String label, int days, VoidCallback onTap) {
    return OutlinedButton(
      style: OutlinedButton.styleFrom(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        side: const BorderSide(color: AppColors.border),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        backgroundColor: Colors.white,
        minimumSize: Size.zero,
        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
      ),
      onPressed: onTap,
      child: Text(
        label,
        style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: AppColors.primary),
      ),
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

  void _showReceiptDialog(SaleModel sale, {int changeAmount = 0}) {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (dCtx) {
        bool isPrinting = false;
        String? printStatus;
        bool? printSuccess;

        return StatefulBuilder(
          builder: (context, setDialogState) {
            final printerName = BluetoothPrinterService.instance.connectedName;
            final isPrinterConfigured = printerName != null && printerName.isNotEmpty;

            Future<void> doPrint() async {
              setDialogState(() {
                isPrinting = true;
                printStatus = 'Mengirim data ke printer thermal...';
                printSuccess = null;
              });

              final success = await BluetoothPrinterService.instance.printSaleReceipt(
                sale,
                storeName: _storeInfo['store_name'] ?? 'KIOS BERKAH',
              );

              setDialogState(() {
                isPrinting = false;
                printSuccess = success;
                printStatus = success
                    ? 'Struk berhasil dicetak!'
                    : 'Gagal mencetak. Pastikan printer hidup & terhubung di menu Setelan.';
              });
            }

            return Dialog(
              backgroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
              insetPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 420),
                child: Padding(
                  padding: const EdgeInsets.all(22),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      // Lingkaran Sukses
                      Container(
                        width: 60,
                        height: 60,
                        decoration: const BoxDecoration(
                          color: AppColors.primarySurface,
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(
                          Icons.check_circle_rounded,
                          color: AppColors.primary,
                          size: 38,
                        ),
                      ),
                      const SizedBox(height: 12),
                      const Text(
                        'Pembayaran Selesai!',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                          color: AppColors.textPrimary,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        sale.invoiceNumber.isNotEmpty ? sale.invoiceNumber : 'Nota #${sale.id}',
                        style: AppTheme.dataMono(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: AppColors.primary,
                        ),
                      ),
                      const SizedBox(height: 14),

                      // Ringkasan Transaksi
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: AppColors.surfaceMuted,
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: AppColors.border),
                        ),
                        child: Column(
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                const Text('Metode', style: TextStyle(fontSize: 12, color: AppColors.textSecondary)),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                  decoration: BoxDecoration(
                                    color: sale.paymentMethod == 'tunai'
                                        ? AppColors.primarySurface
                                        : (sale.paymentMethod == 'qris' ? AppColors.tealSurface : AppColors.amberSurface),
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: Text(
                                    sale.paymentMethod.toUpperCase(),
                                    style: TextStyle(
                                      fontSize: 11,
                                      fontWeight: FontWeight.w700,
                                      color: sale.paymentMethod == 'tunai'
                                          ? AppColors.primary
                                          : (sale.paymentMethod == 'qris' ? AppColors.teal : AppColors.amberText),
                                    ),
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 6),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                const Text('Total Tagihan', style: TextStyle(fontSize: 12, color: AppColors.textSecondary)),
                                Text(
                                  CurrencyFormatter.format(sale.total),
                                  style: AppTheme.dataMono(
                                    fontSize: 15,
                                    fontWeight: FontWeight.w800,
                                    color: AppColors.textPrimary,
                                  ),
                                ),
                              ],
                            ),
                            if (sale.paymentMethod == 'tunai' && changeAmount > 0) ...[
                              const SizedBox(height: 6),
                              const Divider(height: 1, color: AppColors.border),
                              const SizedBox(height: 6),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  const Text('Kembalian', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppColors.primary)),
                                  Text(
                                    CurrencyFormatter.format(changeAmount),
                                    style: AppTheme.dataMono(
                                      fontSize: 16,
                                      fontWeight: FontWeight.w900,
                                      color: AppColors.primary,
                                    ),
                                  ),
                                ],
                              ),
                            ],
                            if (sale.isKasbon) ...[
                              const SizedBox(height: 6),
                              const Divider(height: 1, color: AppColors.border),
                              const SizedBox(height: 6),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  const Text('Uang Muka (DP)', style: TextStyle(fontSize: 12, color: AppColors.textSecondary)),
                                  Text(
                                    CurrencyFormatter.format(sale.paid),
                                    style: AppTheme.dataMono(fontSize: 13, fontWeight: FontWeight.w700),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 4),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  const Text('Sisa Piutang', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.amberText)),
                                  Text(
                                    CurrencyFormatter.format(sale.total - sale.paid),
                                    style: AppTheme.dataMono(fontSize: 14, fontWeight: FontWeight.w800, color: AppColors.amberText),
                                  ),
                                ],
                              ),
                            ],
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),

                      // Status Printer Bluetooth
                      Row(
                        children: [
                          Icon(
                            isPrinterConfigured ? Icons.print_rounded : Icons.print_disabled_rounded,
                            size: 16,
                            color: isPrinterConfigured ? AppColors.primary : AppColors.textSecondary,
                          ),
                          const SizedBox(width: 6),
                          Expanded(
                            child: Text(
                              isPrinterConfigured
                                  ? 'Printer: $printerName'
                                  : 'Printer belum diatur (Menu Setelan)',
                              style: TextStyle(
                                fontSize: 11,
                                fontWeight: isPrinterConfigured ? FontWeight.w600 : FontWeight.w500,
                                color: isPrinterConfigured ? AppColors.primary : AppColors.textSecondary,
                              ),
                            ),
                          ),
                        ],
                      ),

                      if (printStatus != null) ...[
                        const SizedBox(height: 10),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                          decoration: BoxDecoration(
                            color: printSuccess == true
                                ? AppColors.primarySurface
                                : (printSuccess == false ? Colors.red.shade50 : AppColors.surfaceMuted),
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(
                              color: printSuccess == true
                                  ? AppColors.primary.withValues(alpha: 0.3)
                                  : (printSuccess == false ? Colors.red.shade200 : AppColors.border),
                            ),
                          ),
                          child: Row(
                            children: [
                              if (isPrinting)
                                const SizedBox(
                                  width: 14,
                                  height: 14,
                                  child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.primary),
                                )
                              else
                                Icon(
                                  printSuccess == true ? Icons.check_circle_rounded : Icons.error_outline_rounded,
                                  size: 16,
                                  color: printSuccess == true ? AppColors.primary : Colors.red,
                                ),
                              const SizedBox(width: 8),
                              Expanded(
                                child: Text(
                                  printStatus!,
                                  style: TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.w600,
                                    color: printSuccess == true
                                        ? AppColors.primary
                                        : (printSuccess == false ? Colors.red.shade800 : AppColors.textPrimary),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],

                      const SizedBox(height: 18),

                      // Pertanyaan & Tombol Aksi
                      const Text(
                        'Ingin mencetak struk untuk pelanggan?',
                        style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppColors.textSecondary),
                      ),
                      const SizedBox(height: 12),

                      Row(
                        children: [
                          Expanded(
                            child: OutlinedButton(
                              style: OutlinedButton.styleFrom(
                                padding: const EdgeInsets.symmetric(vertical: 12),
                                side: const BorderSide(color: AppColors.border),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                              ),
                              onPressed: () => Navigator.pop(dCtx),
                              child: const Text(
                                'Tanpa Struk',
                                style: TextStyle(
                                  fontWeight: FontWeight.w700,
                                  fontSize: 13,
                                  color: AppColors.textSecondary,
                                ),
                              ),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            flex: 2,
                            child: ElevatedButton.icon(
                              style: ElevatedButton.styleFrom(
                                backgroundColor: AppColors.primary,
                                foregroundColor: Colors.white,
                                padding: const EdgeInsets.symmetric(vertical: 12),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                              ),
                              icon: isPrinting
                                  ? const SizedBox(
                                      width: 16,
                                      height: 16,
                                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                    )
                                  : const Icon(Icons.print_rounded, size: 18),
                              label: Text(
                                isPrinting
                                    ? 'Mencetak...'
                                    : (printSuccess == true ? 'Cetak Ulang' : 'Cetak Struk'),
                                style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13),
                              ),
                              onPressed: isPrinting ? null : doPrint,
                            ),
                          ),
                        ],
                      ),
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

  Future<void> _processTransaction(
    String method, {
    int? paid,
    int? customerId,
    String? dueDate,
    int? discount,
    String? note,
  }) async {
    final total = (_cartTotal - (discount ?? 0)).clamp(0, double.infinity).toInt();
    final itemsPayload = _cart.map((c) => c.toApiItemJson()).toList();

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
      if (dueDate != null && dueDate.isNotEmpty) payload['due_date'] = dueDate;
      if (discount != null && discount > 0) payload['discount'] = discount;
      if (note != null && note.isNotEmpty) payload['note'] = note;

      final dio = _createDio();
      final response = await dio.post('$cleanBaseUrl/pos', data: payload);

      if (response.statusCode == 200 && response.data['success'] == true) {
        fetchPosData(showIndicator: false);

        SaleModel? createdSale;
        if (response.data['sale'] is Map) {
          try {
            createdSale = SaleModel.fromJson(response.data['sale'] as Map<String, dynamic>);
          } catch (e) {
            debugPrint('Gagal parsing sale dari server: $e');
          }
        }

        createdSale ??= SaleModel(
          id: DateTime.now().millisecondsSinceEpoch,
          invoiceNumber: response.data['invoice_no'] as String? ?? 'INV-${DateTime.now().millisecondsSinceEpoch}',
          cashierId: 1,
          paymentMethod: method.toLowerCase(),
          isPaid: method.toLowerCase() != 'kasbon' || (paid != null && paid >= total),
          subtotal: total,
          discount: discount ?? 0,
          total: total,
          paid: paid ?? total,
          change: (paid != null && paid > total) ? paid - total : 0,
          createdAt: DateTime.now(),
          items: itemsPayload.map((it) {
            return SaleItemModel(
              id: it['id'] as int? ?? 0,
              productName: it['product_name'] as String? ?? 'Barang',
              price: it['price'] as int? ?? 0,
              qty: it['qty'] as int? ?? 1,
              subtotal: ((it['price'] as int? ?? 0) * (it['qty'] as int? ?? 1)),
            );
          }).toList(),
        );

        final changeAmount = (paid != null && paid > total) ? paid - total : 0;
        _showReceiptDialog(createdSale, changeAmount: changeAmount);
      } else {
        final msg = response.data['message'] ?? 'Transaksi gagal diproses oleh server.';
        _showToast(msg.toString(), isError: true);
      }
    } catch (e) {
      debugPrint('Checkout error: $e');
      String errorMsg = 'Gagal memproses transaksi.';
      if (e is DioException) {
        if (e.response?.data is Map && e.response?.data['message'] != null) {
          errorMsg = e.response!.data['message'].toString();
        } else if (e.response?.statusCode == 500) {
          errorMsg = 'Server mengembalikan error 500. Silakan jalankan git pull di server produksi.';
        } else if (e.type == DioExceptionType.connectionTimeout || e.type == DioExceptionType.receiveTimeout) {
          errorMsg = 'Koneksi ke server timeout. Periksa jaringan internet Anda.';
        } else {
          errorMsg = 'Gagal terhubung ke server: ${e.message}';
        }
      } else {
        errorMsg = e.toString();
      }
      _showToast(errorMsg, isError: true);
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
            tooltip: 'Pindai Barcode (Kamera)',
            icon: const Icon(Icons.qr_code_scanner_rounded, color: AppColors.primary, size: 22),
            onPressed: openBarcodeScanner,
          ),
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

                final screenWidth = MediaQuery.of(context).size.width;
                final isTablet = screenWidth >= 600;
                final isWideLandscape = screenWidth >= 960;
                final listBottomPadding = _cart.isNotEmpty ? 84.0 : 20.0;

                return RefreshIndicator(
                  onRefresh: () => fetchPosData(showIndicator: false),
                  color: AppColors.primary,
                  backgroundColor: Colors.white,
                  child: isTablet
                      ? GridView.builder(
                          physics: const AlwaysScrollableScrollPhysics(),
                          padding: EdgeInsets.fromLTRB(16, 0, 16, listBottomPadding),
                          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                            crossAxisCount: isWideLandscape ? 3 : 2,
                            crossAxisSpacing: 10,
                            mainAxisSpacing: 10,
                            mainAxisExtent: 136,
                          ),
                          itemCount: filteredProducts.length,
                          itemBuilder: (ctx, idx) {
                            final product = filteredProducts[idx];
                            final cartQty = _getCartQty(product.id);
                            return _buildProductCard(product, cartQty, isGrid: true);
                          },
                        )
                      : ListView.builder(
                          physics: const AlwaysScrollableScrollPhysics(),
                          padding: EdgeInsets.fromLTRB(16, 0, 16, listBottomPadding),
                          itemCount: filteredProducts.length,
                          itemBuilder: (ctx, idx) {
                            final product = filteredProducts[idx];
                            final cartQty = _getCartQty(product.id);
                            return _buildProductCard(product, cartQty, isGrid: false);
                          },
                        ),
                );
              },
            ),
          ),
        ],
      ),

      // Cart Bar (Menempel rapi di atas bottom navigation bar tanpa tumpang tindih)
      bottomSheet: _cart.isEmpty
          ? null
          : Container(
              decoration: BoxDecoration(
                color: Colors.white,
                border: const Border(top: BorderSide(color: AppColors.border, width: 1)),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.08),
                    blurRadius: 10,
                    offset: const Offset(0, -3),
                  ),
                ],
              ),
              child: SafeArea(
                top: false,
                bottom: false,
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  child: Align(
                    alignment: Alignment.center,
                    heightFactor: 1.0,
                    child: ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 600),
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
                                    style: const TextStyle(
                                      fontSize: 11,
                                      color: AppColors.textSecondary,
                                      fontWeight: FontWeight.w500,
                                    ),
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
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                            ),
                            icon: const Icon(Icons.arrow_forward_rounded, size: 18),
                            label: const Text('Bayar', style: TextStyle(fontWeight: FontWeight.w800)),
                            onPressed: _showCartSheet,
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),
    );
  }

  Widget _buildProductCard(ProductModel product, int cartQty, {bool isGrid = false}) {
    final inCart = cartQty > 0;

    return Container(
      margin: EdgeInsets.only(bottom: isGrid ? 0 : 10),
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
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: product.stock > 0
            ? () {
                if (product.units.isNotEmpty) {
                  _showUnitPickerForProduct(product);
                } else {
                  _addToCart(product);
                }
              }
            : null,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Wrap(
                      spacing: 4,
                      runSpacing: 2,
                      children: [
                        if (product.categoryName != null)
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: AppColors.surfaceMuted,
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              product.categoryName!,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.w600,
                                color: AppColors.textSecondary,
                              ),
                            ),
                          ),
                        if (product.units.isNotEmpty)
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: AppColors.tealSurface,
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              '+${product.units.length} Satuan',
                              style: const TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.w700,
                                color: AppColors.teal,
                              ),
                            ),
                          ),
                        if (product.wholesalePrices.isNotEmpty)
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: AppColors.amberSurface,
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: const Text(
                              'Grosir',
                              style: TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.w700,
                                color: AppColors.amberText,
                              ),
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      product.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: AppColors.textPrimary,
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
                        const SizedBox(width: 8),
                        StockBadge(stock: product.stock, threshold: product.minStock),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),

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
                        constraints: const BoxConstraints(minWidth: 30, minHeight: 30),
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
                        constraints: const BoxConstraints(minWidth: 30, minHeight: 30),
                        padding: EdgeInsets.zero,
                        onPressed: () {
                          if (product.units.isNotEmpty) {
                            _showUnitPickerForProduct(product);
                          } else {
                            _addToCart(product);
                          }
                        },
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
                    onTap: product.stock > 0
                        ? () {
                            if (product.units.isNotEmpty) {
                              _showUnitPickerForProduct(product);
                            } else {
                              _addToCart(product);
                            }
                          }
                        : null,
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
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
                            product.units.isNotEmpty ? 'Satuan' : 'Tambah',
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
      ),
    );
  }
}
