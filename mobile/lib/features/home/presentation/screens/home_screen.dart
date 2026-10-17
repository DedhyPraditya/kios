import 'dart:io';
import 'package:dio/dio.dart';
import 'package:dio/io.dart';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:kios_berkah_mobile/core/constants/api_endpoints.dart';
import 'package:kios_berkah_mobile/core/constants/app_colors.dart';
import 'package:kios_berkah_mobile/core/utils/currency_formatter.dart';
import 'package:kios_berkah_mobile/features/sales/data/models/sale_model.dart';

class HomeScreen extends StatefulWidget {
  final void Function(int tabIndex) onNavigateToTab;

  const HomeScreen({
    super.key,
    required this.onNavigateToTab,
  });

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  bool _isLoading = false;
  String? _errorMessage;

  String _storeName = 'Kios BERKAH';
  int _todayOmzet = 0;
  int _todayTrx = 0;
  int _totalProducts = 0;
  int _lowStockCount = 0;
  List<Map<String, dynamic>> _lowStockItems = [];
  List<SaleModel> _recentSales = [];

  String _activeUrl = ApiEndpoints.defaultBaseUrl;

  @override
  void initState() {
    super.initState();
    _fetchDashboardData();
  }

  Dio _createDio() {
    final dio = Dio(
      BaseOptions(
        connectTimeout: const Duration(seconds: 12),
        receiveTimeout: const Duration(seconds: 12),
        headers: {'Accept': 'application/json'},
      ),
    );

    (dio.httpClientAdapter as IOHttpClientAdapter).createHttpClient = () {
      final client = HttpClient();
      client.badCertificateCallback = (cert, host, port) => true;
      return client;
    };

    return dio;
  }

  Future<void> _fetchDashboardData({bool showIndicator = true}) async {
    if (showIndicator) {
      setState(() {
        _isLoading = true;
        _errorMessage = null;
      });
    }

    try {
      final prefs = await SharedPreferences.getInstance();
      _activeUrl = prefs.getString('kios_api_base_url') ?? ApiEndpoints.defaultBaseUrl;

      final dio = _createDio();
      final url = '$_activeUrl/dashboard';
      final res = await dio.get(url);

      if (res.statusCode == 200 && res.data is Map) {
        final data = res.data as Map<String, dynamic>;
        final stats = data['stats'] as Map<String, dynamic>? ?? {};
        final rawLow = data['low_stock_items'] as List<dynamic>? ?? [];
        final rawRecent = data['recent_sales'] as List<dynamic>? ?? [];

        if (mounted) {
          setState(() {
            _storeName = (data['store_name'] as String?)?.trim().isNotEmpty == true
                ? data['store_name'] as String
                : 'Kios BERKAH';
            _todayOmzet = (stats['today_omzet'] as num?)?.toInt() ?? 0;
            _todayTrx = (stats['today_trx'] as num?)?.toInt() ?? 0;
            _totalProducts = (stats['total_products'] as num?)?.toInt() ?? 0;
            _lowStockCount = (stats['low_stock_count'] as num?)?.toInt() ?? 0;

            _lowStockItems = rawLow.map((e) => Map<String, dynamic>.from(e as Map)).toList();
            _recentSales = rawRecent.map((e) => SaleModel.fromJson(e as Map<String, dynamic>)).toList();
            _isLoading = false;
            _errorMessage = null;
          });
        }
      } else {
        throw Exception('Status ${res.statusCode}');
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoading = false;
          _errorMessage = e.toString();
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final screenWidth = MediaQuery.of(context).size.width;
    final isTablet = screenWidth >= 600;

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              _storeName,
              style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18),
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
                  'Beranda Toko',
                  style: TextStyle(fontSize: 11, color: AppColors.textSecondary),
                ),
              ],
            ),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'Segarkan Dashboard',
            icon: _isLoading
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.primary),
                  )
                : const Icon(Icons.refresh_rounded, color: AppColors.primary),
            onPressed: () => _fetchDashboardData(),
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () => _fetchDashboardData(showIndicator: false),
        color: AppColors.primary,
        backgroundColor: Colors.white,
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: EdgeInsets.fromLTRB(16, 12, 16, isTablet ? 32 : 24),
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 900),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Hero Banner Selamat Datang
                  _buildWelcomeBanner(isTablet),
                  if (_errorMessage != null) ...[
                    const SizedBox(height: 12),
                    _buildErrorBanner(),
                  ],
                  const SizedBox(height: 16),

                  // Statistik Hari Ini (Grid)
                  _buildStatsSection(isTablet),
                  const SizedBox(height: 20),

                  // Menu Pintasan Cepat
                  _buildQuickActions(isTablet),
                  const SizedBox(height: 24),

                  if (isTablet) ...[
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          flex: 1,
                          child: _buildLowStockSection(),
                        ),
                        const SizedBox(width: 16),
                        Expanded(
                          flex: 1,
                          child: _buildRecentSalesSection(),
                        ),
                      ],
                    ),
                  ] else ...[
                    _buildLowStockSection(),
                    const SizedBox(height: 20),
                    _buildRecentSalesSection(),
                  ],
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildWelcomeBanner(bool isTablet) {
    return Container(
      width: double.infinity,
      padding: EdgeInsets.all(isTablet ? 20 : 16),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [AppColors.primary, AppColors.primaryBright],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.25),
            blurRadius: 14,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.2),
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.storefront_rounded,
              color: Colors.white,
              size: 28,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Halo, Selamat Datang di $_storeName',
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                    fontSize: 15,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  'Kelola transaksi kasir, pantau omzet, dan cek stok secara praktis.',
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.9),
                    fontSize: 12,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildErrorBanner() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: AppColors.dangerSurface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.danger.withValues(alpha: 0.3)),
      ),
      child: Row(
        children: [
          const Icon(Icons.info_outline_rounded, color: AppColors.danger, size: 20),
          const SizedBox(width: 10),
          const Expanded(
            child: Text(
              'Gagal memperbarui data dari server. Menampilkan data lokal terakhir.',
              style: TextStyle(fontSize: 12, color: AppColors.danger, fontWeight: FontWeight.w600),
            ),
          ),
          TextButton(
            onPressed: () => _fetchDashboardData(),
            style: TextButton.styleFrom(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              minimumSize: Size.zero,
              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
            ),
            child: const Text(
              'Coba Lagi',
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.danger),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStatsSection(bool isTablet) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Ringkasan Hari Ini',
          style: TextStyle(
            fontWeight: FontWeight.w800,
            fontSize: 16,
            color: AppColors.textPrimary,
          ),
        ),
        const SizedBox(height: 10),
        GridView.count(
          crossAxisCount: isTablet ? 4 : 2,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          crossAxisSpacing: 10,
          mainAxisSpacing: 10,
          childAspectRatio: isTablet ? 1.4 : 1.35,
          children: [
            _buildStatCard(
              title: 'Omzet Hari Ini',
              value: CurrencyFormatter.format(_todayOmzet),
              icon: Icons.payments_rounded,
              iconColor: AppColors.primary,
              bgColor: AppColors.primarySurface,
            ),
            _buildStatCard(
              title: 'Transaksi Hari Ini',
              value: '$_todayTrx Nota',
              icon: Icons.receipt_long_rounded,
              iconColor: AppColors.teal,
              bgColor: AppColors.tealSurface,
            ),
            _buildStatCard(
              title: 'Total Produk',
              value: '$_totalProducts Barang',
              icon: Icons.inventory_2_rounded,
              iconColor: const Color(0xFF2563EB),
              bgColor: const Color(0xFFEFF6FF),
            ),
            _buildStatCard(
              title: 'Stok Menipis',
              value: '$_lowStockCount Item',
              icon: Icons.warning_amber_rounded,
              iconColor: _lowStockCount > 0 ? AppColors.danger : AppColors.amberText,
              bgColor: _lowStockCount > 0 ? AppColors.dangerSurface : AppColors.amberSurface,
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildStatCard({
    required String title,
    required String value,
    required IconData icon,
    required Color iconColor,
    required Color bgColor,
  }) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.border),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                title,
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                  color: AppColors.textSecondary,
                ),
              ),
              Container(
                padding: const EdgeInsets.all(6),
                decoration: BoxDecoration(
                  color: bgColor,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Icon(icon, size: 16, color: iconColor),
              ),
            ],
          ),
          Text(
            value,
            style: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w800,
              color: AppColors.textPrimary,
            ),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    );
  }

  Widget _buildQuickActions(bool isTablet) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Akses Menu Cepat',
          style: TextStyle(
            fontWeight: FontWeight.w800,
            fontSize: 16,
            color: AppColors.textPrimary,
          ),
        ),
        const SizedBox(height: 10),
        Row(
          children: [
            Expanded(
              child: _buildQuickActionBtn(
                title: 'Buka Kasir',
                subtitle: 'Mulai Transaksi',
                icon: Icons.point_of_sale_rounded,
                color: AppColors.primary,
                onTap: () => widget.onNavigateToTab(1),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: _buildQuickActionBtn(
                title: 'Riwayat Nota',
                subtitle: 'Cek Penjualan',
                icon: Icons.history_rounded,
                color: AppColors.teal,
                onTap: () => widget.onNavigateToTab(2),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: _buildQuickActionBtn(
                title: 'Arang Kiloan',
                subtitle: 'Hitung Timbangan',
                icon: Icons.local_fire_department_rounded,
                color: const Color(0xFFD97706),
                onTap: () => widget.onNavigateToTab(3),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: _buildQuickActionBtn(
                title: 'Setelan',
                subtitle: 'Printer & URL',
                icon: Icons.tune_rounded,
                color: const Color(0xFF6366F1),
                onTap: () => widget.onNavigateToTab(4),
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildQuickActionBtn({
    required String title,
    required String subtitle,
    required IconData icon,
    required Color color,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: AppColors.border),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.02),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Column(
          children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.12),
                shape: BoxShape.circle,
              ),
              child: Icon(icon, color: color, size: 22),
            ),
            const SizedBox(height: 8),
            Text(
              title,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontWeight: FontWeight.w700,
                fontSize: 12,
                color: AppColors.textPrimary,
              ),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
            const SizedBox(height: 2),
            Text(
              subtitle,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 10,
                color: AppColors.textSecondary,
              ),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildLowStockSection() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.border),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(6),
                    decoration: BoxDecoration(
                      color: _lowStockCount > 0 ? AppColors.dangerSurface : AppColors.primarySurface,
                      shape: BoxShape.circle,
                    ),
                    child: Icon(
                      _lowStockCount > 0 ? Icons.warning_rounded : Icons.check_circle_rounded,
                      color: _lowStockCount > 0 ? AppColors.danger : AppColors.primary,
                      size: 16,
                    ),
                  ),
                  const SizedBox(width: 8),
                  const Text(
                    'Peringatan Stok',
                    style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14),
                  ),
                ],
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: _lowStockCount > 0 ? AppColors.dangerSurface : AppColors.tealSurface,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(
                  _lowStockCount > 0 ? '$_lowStockCount Item Menipis' : 'Stok Aman',
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    color: _lowStockCount > 0 ? AppColors.danger : AppColors.teal,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (_lowStockItems.isEmpty)
            Container(
              padding: const EdgeInsets.symmetric(vertical: 18),
              alignment: Alignment.center,
              child: const Text(
                'Semua stok barang dalam kondisi mencukupi.',
                style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
              ),
            )
          else
            ListView.separated(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: _lowStockItems.length,
              separatorBuilder: (context, index) => const Divider(height: 1, color: AppColors.border),
              itemBuilder: (context, index) {
                final item = _lowStockItems[index];
                final name = item['name'] as String? ?? 'Barang';
                final stock = item['stock'] ?? 0;
                final lowStock = item['low_stock'] ?? 5;

                return Padding(
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  child: Row(
                    children: [
                      Expanded(
                        child: Text(
                          name,
                          style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: AppColors.dangerSurface,
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: AppColors.danger.withValues(alpha: 0.3)),
                        ),
                        child: Text(
                          'Sisa $stock (Min $lowStock)',
                          style: const TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                            color: AppColors.danger,
                          ),
                        ),
                      ),
                    ],
                  ),
                );
              },
            ),
        ],
      ),
    );
  }

  Widget _buildRecentSalesSection() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.border),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Row(
                children: [
                  Icon(Icons.history_edu_rounded, color: AppColors.primary, size: 20),
                  SizedBox(width: 8),
                  Text(
                    'Transaksi Terakhir',
                    style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14),
                  ),
                ],
              ),
              TextButton(
                onPressed: () => widget.onNavigateToTab(2),
                style: TextButton.styleFrom(
                  padding: EdgeInsets.zero,
                  minimumSize: Size.zero,
                  tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                ),
                child: const Text(
                  'Lihat Semua',
                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.primary),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (_recentSales.isEmpty)
            Container(
              padding: const EdgeInsets.symmetric(vertical: 18),
              alignment: Alignment.center,
              child: const Text(
                'Belum ada transaksi hari ini.',
                style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
              ),
            )
          else
            ListView.separated(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: _recentSales.length,
              separatorBuilder: (context, index) => const Divider(height: 1, color: AppColors.border),
              itemBuilder: (context, index) {
                final sale = _recentSales[index];
                return Padding(
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  child: Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: sale.paymentMethod == 'kasbon'
                              ? AppColors.amberSurface
                              : (sale.paymentMethod == 'qris' ? AppColors.tealSurface : AppColors.primarySurface),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Icon(
                          sale.paymentMethod == 'kasbon'
                              ? Icons.pending_actions_rounded
                              : (sale.paymentMethod == 'qris' ? Icons.qr_code_rounded : Icons.payments_rounded),
                          size: 16,
                          color: sale.paymentMethod == 'kasbon'
                              ? AppColors.amberText
                              : (sale.paymentMethod == 'qris' ? AppColors.teal : AppColors.primary),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              sale.invoiceNumber,
                              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
                            ),
                            Text(
                              sale.customerName ?? (sale.cashierName ?? 'Umum'),
                              style: const TextStyle(fontSize: 11, color: AppColors.textSecondary),
                            ),
                          ],
                        ),
                      ),
                      Text(
                        CurrencyFormatter.format(sale.total),
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 13,
                          color: AppColors.textPrimary,
                        ),
                      ),
                    ],
                  ),
                );
              },
            ),
        ],
      ),
    );
  }
}
