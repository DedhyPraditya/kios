import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:kios_berkah_mobile/core/constants/app_colors.dart';
import 'package:kios_berkah_mobile/core/theme/app_theme.dart';
import 'package:kios_berkah_mobile/core/utils/currency_formatter.dart';
import 'package:kios_berkah_mobile/core/widgets/app_button.dart';
import 'package:kios_berkah_mobile/features/scanner/data/models/scan_result.dart';

/// Layar pemindai barcode kamera berkelanjutan (multi-scan) untuk kasir Kios Berkah.
/// Kamera tetap aktif dan tidak keluar sebelum kasir menekan tombol Close (X) atau Selesai.
class BarcodeScannerScreen extends StatefulWidget {
  final int initialCartCount;
  final int initialCartTotal;
  final Future<ScanResult> Function(String barcode)? onScanBarcode;

  const BarcodeScannerScreen({
    super.key,
    this.initialCartCount = 0,
    this.initialCartTotal = 0,
    this.onScanBarcode,
  });

  @override
  State<BarcodeScannerScreen> createState() => _BarcodeScannerScreenState();
}

class _BarcodeScannerScreenState extends State<BarcodeScannerScreen> {
  final MobileScannerController _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.normal,
    facing: CameraFacing.back,
    torchEnabled: false,
  );

  bool _isTorchOn = false;
  bool _isProcessing = false;
  String? _lastScannedCode;
  DateTime? _lastScannedTime;

  late int _cartCount;
  late int _cartTotal;
  ScanResult? _latestResult;
  Timer? _hideResultTimer;

  @override
  void initState() {
    super.initState();
    _cartCount = widget.initialCartCount;
    _cartTotal = widget.initialCartTotal;
  }

  @override
  void dispose() {
    _hideResultTimer?.cancel();
    _controller.dispose();
    super.dispose();
  }

  Future<void> _handleBarcode(String code) async {
    final clean = code.trim();
    if (clean.isEmpty) return;

    final now = DateTime.now();
    // Jeda 1.8 detik jika barcode yang sama persis terdeteksi berulang-ulang
    if (_lastScannedCode == clean &&
        _lastScannedTime != null &&
        now.difference(_lastScannedTime!).inMilliseconds < 1800) {
      return;
    }

    _isProcessing = true;
    _lastScannedCode = clean;
    _lastScannedTime = now;

    try {
      HapticFeedback.mediumImpact();
    } catch (_) {}

    if (widget.onScanBarcode != null) {
      final result = await widget.onScanBarcode!(clean);
      if (mounted) {
        setState(() {
          _latestResult = result;
          if (result.success) {
            _cartCount = result.cartItemCount;
            _cartTotal = result.cartTotal;
          }
        });

        _hideResultTimer?.cancel();
        _hideResultTimer = Timer(const Duration(seconds: 3), () {
          if (mounted) {
            setState(() => _latestResult = null);
          }
        });
      }
    }

    // Jeda kecil sebelum membaca frame berikutnya
    await Future.delayed(const Duration(milliseconds: 500));
    _isProcessing = false;
  }

  void _onDetect(BarcodeCapture capture) {
    if (_isProcessing) return;
    for (final barcode in capture.barcodes) {
      final code = barcode.rawValue;
      if (code != null && code.trim().isNotEmpty) {
        _handleBarcode(code.trim());
        break;
      }
    }
  }

  void _showManualInputDialog() {
    final textController = TextEditingController();
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) {
        return Padding(
          padding: EdgeInsets.fromLTRB(20, 20, 20, MediaQuery.of(ctx).viewInsets.bottom + 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'Ketik Barcode Manual',
                style: Theme.of(ctx).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 6),
              const Text(
                'Gunakan jika barcode produk tergores atau rusak',
                style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
              ),
              const SizedBox(height: 16),
              TextField(
                controller: textController,
                autofocus: true,
                keyboardType: TextInputType.text,
                decoration: InputDecoration(
                  hintText: 'Contoh: 899123456789',
                  filled: true,
                  fillColor: AppColors.surfaceMuted,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(14),
                    borderSide: BorderSide.none,
                  ),
                ),
                onSubmitted: (val) {
                  if (val.trim().isNotEmpty) {
                    Navigator.of(ctx).pop();
                    _handleBarcode(val.trim());
                  }
                },
              ),
              const SizedBox(height: 18),
              AppButton(
                label: 'Masukkan ke Keranjang',
                icon: Icons.add_shopping_cart_rounded,
                onPressed: () {
                  final code = textController.text.trim();
                  if (code.isNotEmpty) {
                    Navigator.of(ctx).pop();
                    _handleBarcode(code);
                  }
                },
              ),
            ],
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final screenWidth = MediaQuery.of(context).size.width;
    final isTablet = screenWidth >= 600;
    final boxSize = isTablet ? 320.0 : 270.0;

    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.close_rounded, size: 26),
          tooltip: 'Tutup Scanner',
          onPressed: () => Navigator.of(context).pop(false),
        ),
        title: const Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Scan Barcode Kasir',
              style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w800),
            ),
            Text(
              'Mode Multi-Scan (Kamera Terus Aktif)',
              style: TextStyle(color: AppColors.primaryBright, fontSize: 11, fontWeight: FontWeight.w600),
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: Icon(
              _isTorchOn ? Icons.flash_on_rounded : Icons.flash_off_rounded,
              color: _isTorchOn ? AppColors.amber : Colors.white,
            ),
            tooltip: 'Lampu Kilat',
            onPressed: () async {
              await _controller.toggleTorch();
              setState(() => _isTorchOn = !_isTorchOn);
            },
          ),
          IconButton(
            icon: const Icon(Icons.flip_camera_ios_rounded, color: Colors.white),
            tooltip: 'Ganti Kamera',
            onPressed: () => _controller.switchCamera(),
          ),
          IconButton(
            icon: const Icon(Icons.keyboard_outlined, color: Colors.white),
            tooltip: 'Input Manual',
            onPressed: _showManualInputDialog,
          ),
        ],
      ),
      body: Stack(
        alignment: Alignment.center,
        children: [
          // Live Camera Preview
          MobileScanner(
            controller: _controller,
            onDetect: _onDetect,
          ),

          // Scanning Viewfinder Overlay
          Container(
            width: boxSize,
            height: boxSize,
            decoration: BoxDecoration(
              border: Border.all(color: AppColors.primaryBright.withValues(alpha: 0.7), width: 2),
              borderRadius: BorderRadius.circular(24),
              boxShadow: [
                BoxShadow(
                  color: AppColors.primaryBright.withValues(alpha: 0.15),
                  blurRadius: 20,
                  spreadRadius: 2,
                ),
              ],
            ),
            child: Stack(
              children: [
                // Corner markers for scanner feel
                Align(
                  alignment: Alignment.topLeft,
                  child: Container(
                    width: 26,
                    height: 26,
                    decoration: const BoxDecoration(
                      border: Border(
                        top: BorderSide(color: AppColors.primaryBright, width: 4.5),
                        left: BorderSide(color: AppColors.primaryBright, width: 4.5),
                      ),
                      borderRadius: BorderRadius.only(topLeft: Radius.circular(20)),
                    ),
                  ),
                ),
                Align(
                  alignment: Alignment.topRight,
                  child: Container(
                    width: 26,
                    height: 26,
                    decoration: const BoxDecoration(
                      border: Border(
                        top: BorderSide(color: AppColors.primaryBright, width: 4.5),
                        right: BorderSide(color: AppColors.primaryBright, width: 4.5),
                      ),
                      borderRadius: BorderRadius.only(topRight: Radius.circular(20)),
                    ),
                  ),
                ),
                Align(
                  alignment: Alignment.bottomLeft,
                  child: Container(
                    width: 26,
                    height: 26,
                    decoration: const BoxDecoration(
                      border: Border(
                        bottom: BorderSide(color: AppColors.primaryBright, width: 4.5),
                        left: BorderSide(color: AppColors.primaryBright, width: 4.5),
                      ),
                      borderRadius: BorderRadius.only(bottomLeft: Radius.circular(20)),
                    ),
                  ),
                ),
                Align(
                  alignment: Alignment.bottomRight,
                  child: Container(
                    width: 26,
                    height: 26,
                    decoration: const BoxDecoration(
                      border: Border(
                        bottom: BorderSide(color: AppColors.primaryBright, width: 4.5),
                        right: BorderSide(color: AppColors.primaryBright, width: 4.5),
                      ),
                      borderRadius: BorderRadius.only(bottomRight: Radius.circular(20)),
                    ),
                  ),
                ),
              ],
            ),
          ),

          // Banner Notifikasi Hasil Scan (Tepat di atas Viewfinder)
          Positioned(
            top: 24,
            left: 20,
            right: 20,
            child: Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 480),
                child: AnimatedSwitcher(
                  duration: const Duration(milliseconds: 250),
                  child: _latestResult != null
                      ? Container(
                          key: ValueKey(_latestResult!.message + (_latestResult!.barcode ?? '')),
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                          decoration: BoxDecoration(
                            color: _latestResult!.success
                                ? const Color(0xFF064E3B).withValues(alpha: 0.95) // Deep emerald
                                : const Color(0xFF7F1D1D).withValues(alpha: 0.95), // Deep red
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(
                              color: _latestResult!.success ? AppColors.primaryBright : Colors.redAccent,
                              width: 1.5,
                            ),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withValues(alpha: 0.4),
                                blurRadius: 12,
                                offset: const Offset(0, 4),
                              ),
                            ],
                          ),
                          child: Row(
                            children: [
                              Icon(
                                _latestResult!.success ? Icons.check_circle_rounded : Icons.warning_amber_rounded,
                                color: _latestResult!.success ? AppColors.primaryBright : Colors.white,
                                size: 24,
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  mainAxisSize: MainAxisSize.min,
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      _latestResult!.productName ?? _latestResult!.message,
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: const TextStyle(
                                        color: Colors.white,
                                        fontSize: 14,
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                    if (_latestResult!.success && _latestResult!.productPrice != null)
                                      Row(
                                        children: [
                                          Text(
                                            CurrencyFormatter.format(_latestResult!.productPrice!),
                                            style: AppTheme.dataMono(
                                              fontSize: 12,
                                              fontWeight: FontWeight.w700,
                                              color: AppColors.primaryBright,
                                            ),
                                          ),
                                          const SizedBox(width: 8),
                                          Container(
                                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                                            decoration: BoxDecoration(
                                              color: Colors.white24,
                                              borderRadius: BorderRadius.circular(6),
                                            ),
                                            child: const Text(
                                              '+1 ke keranjang',
                                              style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w700),
                                            ),
                                          ),
                                        ],
                                      )
                                    else if (!_latestResult!.success)
                                      Text(
                                        _latestResult!.message,
                                        style: const TextStyle(color: Colors.white70, fontSize: 11),
                                      ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        )
                      : Container(
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                          decoration: BoxDecoration(
                            color: Colors.black.withValues(alpha: 0.6),
                            borderRadius: BorderRadius.circular(20),
                            border: Border.all(color: Colors.white24),
                          ),
                          child: const Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.qr_code_scanner_rounded, color: AppColors.primaryBright, size: 16),
                              SizedBox(width: 8),
                              Text(
                                'Arahkan kamera ke barcode produk',
                                style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600),
                              ),
                            ],
                          ),
                        ),
                ),
              ),
            ),
          ),

          // Dock Keranjang Melayang di Bagian Bawah Kamera
          Positioned(
            bottom: 24,
            left: 16,
            right: 16,
            child: Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 580),
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
                  decoration: BoxDecoration(
                    color: const Color(0xFF0F172A).withValues(alpha: 0.95), // Slate dark 900
                    borderRadius: BorderRadius.circular(22),
                    border: Border.all(color: AppColors.primary.withValues(alpha: 0.5), width: 1.5),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.45),
                        blurRadius: 18,
                        offset: const Offset(0, 6),
                      ),
                    ],
                  ),
                  child: Row(
                    children: [
                      // Info Total Keranjang Saat Ini
                      Expanded(
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                const Icon(Icons.shopping_cart_rounded, color: AppColors.primaryBright, size: 16),
                                const SizedBox(width: 6),
                                Text(
                                  '$_cartCount barang terpilih',
                                  style: const TextStyle(
                                    fontSize: 12,
                                    color: Colors.white70,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 3),
                            Text(
                              CurrencyFormatter.format(_cartTotal),
                              style: AppTheme.dataMono(
                                fontSize: 18,
                                fontWeight: FontWeight.w800,
                                color: Colors.white,
                              ),
                            ),
                          ],
                        ),
                      ),

                      // Tombol Selesai & Bayar
                      ElevatedButton.icon(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.primary,
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                          elevation: 3,
                        ),
                        icon: const Icon(Icons.arrow_forward_rounded, size: 18),
                        label: Text(
                          _cartCount > 0 ? 'Selesai & Bayar' : 'Selesai Scan',
                          style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13),
                        ),
                        onPressed: () {
                          // Return true jika ada barang di keranjang agar langsung membuka cart sheet
                          Navigator.of(context).pop(_cartCount > 0);
                        },
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
