import 'package:kios_berkah_mobile/features/products/data/models/product_model.dart';

class CartItemModel {
  final ProductModel product;
  int qty;

  CartItemModel({
    required this.product,
    this.qty = 1,
  });

  int get subtotal => product.price * qty;

  Map<String, dynamic> toCheckoutItemJson() {
    return {
      'product_id': product.id,
      'qty': qty,
      'price': product.price,
    };
  }
}
