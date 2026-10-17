class CategoryModel {
  final int id;
  final String name;
  final int productsCount;

  CategoryModel({
    required this.id,
    required this.name,
    this.productsCount = 0,
  });

  factory CategoryModel.fromJson(Map<String, dynamic> json) {
    return CategoryModel(
      id: json['id'] as int? ?? 0,
      name: json['name'] as String? ?? '',
      productsCount: json['products_count'] as int? ?? 0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'products_count': productsCount,
    };
  }
}

class ProductUnitModel {
  final int id;
  final int productId;
  final String name;
  final int isi;
  final int price;
  final String? barcode;

  ProductUnitModel({
    required this.id,
    required this.productId,
    required this.name,
    required this.isi,
    required this.price,
    this.barcode,
  });

  factory ProductUnitModel.fromJson(Map<String, dynamic> json) {
    return ProductUnitModel(
      id: json['id'] as int? ?? 0,
      productId: json['product_id'] as int? ?? 0,
      name: json['name'] as String? ?? '',
      isi: json['isi'] as int? ?? 1,
      price: json['price'] as int? ?? 0,
      barcode: json['barcode'] as String?,
    );
  }
}

class ProductModel {
  final int id;
  final String? barcode;
  final String name;
  final int price;
  final int cost;
  final int stock;
  final int minStock;
  final bool isActive;
  final int? categoryId;
  final String? categoryName;
  final List<ProductUnitModel> units;

  ProductModel({
    required this.id,
    this.barcode,
    required this.name,
    required this.price,
    this.cost = 0,
    required this.stock,
    this.minStock = 5,
    this.isActive = true,
    this.categoryId,
    this.categoryName,
    this.units = const [],
  });

  bool get isLowStock => stock <= minStock;
  bool get isOutOfStock => stock <= 0;

  factory ProductModel.fromJson(Map<String, dynamic> json) {
    return ProductModel(
      id: json['id'] as int? ?? 0,
      barcode: json['barcode'] as String?,
      name: json['name'] as String? ?? '',
      price: json['price'] as int? ?? 0,
      cost: json['cost'] as int? ?? 0,
      stock: json['stock'] as int? ?? 0,
      minStock: (json['low_stock'] ?? json['min_stock']) as int? ?? 5,
      isActive: json['is_active'] == null || json['is_active'] == 1 || json['is_active'] == true,
      categoryId: json['category_id'] as int?,
      categoryName: json['category'] is Map ? json['category']['name'] : (json['category_name'] as String?),
      units: (json['units'] as List?)
              ?.map((u) => ProductUnitModel.fromJson(u as Map<String, dynamic>))
              .toList() ??
          const [],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'barcode': barcode,
      'name': name,
      'price': price,
      'cost': cost,
      'stock': stock,
      'min_stock': minStock,
      'is_active': isActive,
      'category_id': categoryId,
    };
  }
}

