import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:kios_berkah_mobile/core/constants/app_colors.dart';
import 'package:kios_berkah_mobile/core/widgets/app_button.dart';

/// Layar pemindai barcode kamera kilat untuk produk toko.
class BarcodeScannerScreen extends StatefulWidget {
  const BarcodeScannerScreen({super.key});

  @override
  State<BarcodeScannerScreen> createState() => _BarcodeScannerScreenState();
}

class _BarcodeScannerScreenState extends State<BarcodeScannerScreen> {
  final MobileScannerController _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
    facing: CameraFacing.back,
    torchEnabled: false,
  );

  bool _isTorchOn = false;
  bool _hasDetected = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _onDetect(BarcodeCapture capture) {
    if (_hasDetected) return;
    for (final barcode in capture.barcodes) {
      final code = barcode.rawValue;
      if (code != null && code.trim().isNotEmpty) {
        setState(() => _hasDetected = true);
        Navigator.of(context).pop(code.trim());
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
                keyboardType: TextInputType.number,
                decoration: InputDecoration(
                  hintText: 'Contoh: 899123456789',
                  filled: true,
                  fillColor: AppColors.surfaceMuted,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(14),
                    borderSide: BorderSide.none,
                  ),
                ),
              ),
              const SizedBox(height: 18),
              AppButton(
                label: 'Cari Produk',
                icon: Icons.search_rounded,
                onPressed: () {
                  final code = textController.text.trim();
                  if (code.isNotEmpty) {
                    Navigator.of(ctx).pop();
                    Navigator.of(context).pop(code);
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
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        elevation: 0,
        title: const Text(
          'Pindai Barcode Produk',
          style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w700),
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
            width: 280,
            height: 280,
            decoration: BoxDecoration(
              border: Border.all(color: AppColors.primaryBright, width: 2.5),
              borderRadius: BorderRadius.circular(24),
              boxShadow: [
                BoxShadow(
                  color: AppColors.primaryBright.withValues(alpha: 0.2),
                  blurRadius: 24,
                  spreadRadius: 4,
                ),
              ],
            ),
            child: Stack(
              children: [
                // Corner markers for scanner feel
                Align(
                  alignment: Alignment.topLeft,
                  child: Container(
                    width: 24,
                    height: 24,
                    decoration: const BoxDecoration(
                      border: Border(
                        top: BorderSide(color: AppColors.primaryBright, width: 4),
                        left: BorderSide(color: AppColors.primaryBright, width: 4),
                      ),
                      borderRadius: BorderRadius.only(topLeft: Radius.circular(20)),
                    ),
                  ),
                ),
                Align(
                  alignment: Alignment.topRight,
                  child: Container(
                    width: 24,
                    height: 24,
                    decoration: const BoxDecoration(
                      border: Border(
                        top: BorderSide(color: AppColors.primaryBright, width: 4),
                        right: BorderSide(color: AppColors.primaryBright, width: 4),
                      ),
                      borderRadius: BorderRadius.only(topRight: Radius.circular(20)),
                    ),
                  ),
                ),
                Align(
                  alignment: Alignment.bottomLeft,
                  child: Container(
                    width: 24,
                    height: 24,
                    decoration: const BoxDecoration(
                      border: Border(
                        bottom: BorderSide(color: AppColors.primaryBright, width: 4),
                        left: BorderSide(color: AppColors.primaryBright, width: 4),
                      ),
                      borderRadius: BorderRadius.only(bottomLeft: Radius.circular(20)),
                    ),
                  ),
                ),
                Align(
                  alignment: Alignment.bottomRight,
                  child: Container(
                    width: 24,
                    height: 24,
                    decoration: const BoxDecoration(
                      border: Border(
                        bottom: BorderSide(color: AppColors.primaryBright, width: 4),
                        right: BorderSide(color: AppColors.primaryBright, width: 4),
                      ),
                      borderRadius: BorderRadius.only(bottomRight: Radius.circular(20)),
                    ),
                  ),
                ),
              ],
            ),
          ),

          // Bottom Instruction Pill & Manual Input Button
          Positioned(
            bottom: 40,
            left: 24,
            right: 24,
            child: Column(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
                  decoration: BoxDecoration(
                    color: Colors.black.withValues(alpha: 0.75),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: Colors.white24),
                  ),
                  child: const Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.qr_code_scanner_rounded, color: AppColors.primaryBright, size: 20),
                      SizedBox(width: 8),
                      Text(
                        'Arahkan kamera ke barcode produk',
                        style: TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w600),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                TextButton.icon(
                  style: TextButton.styleFrom(
                    foregroundColor: Colors.white,
                    backgroundColor: Colors.white12,
                    padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                  ),
                  icon: const Icon(Icons.keyboard_outlined, size: 18),
                  label: const Text('Input Barcode Manual', style: TextStyle(fontSize: 13)),
                  onPressed: _showManualInputDialog,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
