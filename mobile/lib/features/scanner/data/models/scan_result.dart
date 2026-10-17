class ScanResult {
  final bool success;
  final String message;
  final String? productName;
  final int? productPrice;
  final String? barcode;
  final int cartItemCount;
  final int cartTotal;

  ScanResult({
    required this.success,
    required this.message,
    this.productName,
    this.productPrice,
    this.barcode,
    this.cartItemCount = 0,
    this.cartTotal = 0,
  });
}
