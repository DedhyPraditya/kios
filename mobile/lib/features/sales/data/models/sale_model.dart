class SaleItemModel {
  final int id;
  final String productName;
  final int price;
  final int qty;
  final int returnedQty;
  final int subtotal;

  SaleItemModel({
    required this.id,
    required this.productName,
    required this.price,
    required this.qty,
    this.returnedQty = 0,
    required this.subtotal,
  });

  factory SaleItemModel.fromJson(Map<String, dynamic> json) {
    return SaleItemModel(
      id: json['id'] as int? ?? 0,
      productName: json['product_name'] as String? ?? (json['product'] is Map ? json['product']['name'] : 'Barang'),
      price: json['price'] as int? ?? 0,
      qty: json['qty'] as int? ?? 0,
      returnedQty: json['returned_qty'] as int? ?? 0,
      subtotal: json['subtotal'] as int? ?? 0,
    );
  }
}

class SaleModel {
  final int id;
  final String invoiceNumber;
  final int cashierId;
  final String? cashierName;
  final int? customerId;
  final String? customerName;
  final String paymentMethod; // 'tunai', 'qris', 'kasbon'
  final bool isPaid;
  final int subtotal;
  final int discount;
  final int total;
  final int paid;
  final int change;
  final DateTime? dueDate;
  final DateTime? voidedAt;
  final String? voidReason;
  final String? notes;
  final DateTime createdAt;
  final List<SaleItemModel> items;

  SaleModel({
    required this.id,
    required this.invoiceNumber,
    required this.cashierId,
    this.cashierName,
    this.customerId,
    this.customerName,
    required this.paymentMethod,
    required this.isPaid,
    required this.subtotal,
    this.discount = 0,
    required this.total,
    required this.paid,
    this.change = 0,
    this.dueDate,
    this.voidedAt,
    this.voidReason,
    this.notes,
    required this.createdAt,
    this.items = const [],
  });

  bool get isVoided => voidedAt != null;
  bool get isKasbon => paymentMethod == 'kasbon';

  factory SaleModel.fromJson(Map<String, dynamic> json) {
    final rawItems = json['items'] as List<dynamic>? ?? [];
    return SaleModel(
      id: json['id'] as int? ?? 0,
      invoiceNumber: json['invoice_number'] as String? ?? (json['no_nota'] as String? ?? ''),
      cashierId: json['user_id'] as int? ?? 0,
      cashierName: json['user'] is Map ? json['user']['name'] : null,
      customerId: json['customer_id'] as int?,
      customerName: json['customer'] is Map ? json['customer']['name'] : null,
      paymentMethod: json['payment_method'] as String? ?? 'tunai',
      isPaid: json['is_paid'] == 1 || json['is_paid'] == true,
      subtotal: json['subtotal'] as int? ?? 0,
      discount: json['discount'] as int? ?? 0,
      total: json['total'] as int? ?? 0,
      paid: json['paid'] as int? ?? 0,
      change: json['change'] as int? ?? 0,
      dueDate: json['due_date'] != null ? DateTime.tryParse(json['due_date']) : null,
      voidedAt: json['voided_at'] != null ? DateTime.tryParse(json['voided_at']) : null,
      voidReason: json['void_reason'] as String?,
      notes: json['notes'] as String?,
      createdAt: DateTime.tryParse(json['created_at'] ?? '') ?? DateTime.now(),
      items: rawItems.map((e) => SaleItemModel.fromJson(e as Map<String, dynamic>)).toList(),
    );
  }
}
