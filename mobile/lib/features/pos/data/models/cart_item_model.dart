import 'package:kios_berkah_mobile/features/products/data/models/product_model.dart';

class CartItemModel {
  final ProductModel product;
  ProductUnitModel? unit;
  int qty;
  int discount;

  CartItemModel({
    required this.product,
    this.unit,
    this.qty = 1,
    this.discount = 0,
  });

  String get key => '${product.id}-${unit?.id ?? 0}';

  int get isi => unit?.isi ?? 1;

  int get totalBaseQty => qty * isi;

  int get unitPrice {
    if (unit != null) return unit!.price;
    return product.priceFor(qty);
  }

  bool get isGrosir {
    if (unit != null) return false;
    return unitPrice < product.price;
  }

  int get grossSubtotal => unitPrice * qty;

  int get subtotal => (grossSubtotal - discount).clamp(0, double.infinity).toInt();

  Map<String, dynamic> toApiItemJson() {
    return {
      'id': product.id,
      'unit_id': unit?.id,
      'qty': qty,
      'discount': discount,
    };
  }
}
