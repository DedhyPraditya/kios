class CustomerModel {
  final int id;
  final String name;
  final String? phone;
  final String? address;
  final int? creditLimit;
  final bool isBlocked;
  final int outstandingDebt;
  final String? notes;

  CustomerModel({
    required this.id,
    required this.name,
    this.phone,
    this.address,
    this.creditLimit,
    this.isBlocked = false,
    this.outstandingDebt = 0,
    this.notes,
  });

  bool get canTakeCredit {
    if (isBlocked) return false;
    if (creditLimit == null) return true; // tanpa batas
    return outstandingDebt < creditLimit!;
  }

  factory CustomerModel.fromJson(Map<String, dynamic> json) {
    return CustomerModel(
      id: json['id'] as int? ?? 0,
      name: json['name'] as String? ?? '',
      phone: json['phone'] as String?,
      address: json['address'] as String?,
      creditLimit: json['credit_limit'] as int?,
      isBlocked: json['is_blocked'] == 1 || json['is_blocked'] == true,
      outstandingDebt: json['outstanding_debt'] as int? ?? (json['hutang'] as int? ?? 0),
      notes: json['notes'] as String?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'phone': phone,
      'address': address,
      'credit_limit': creditLimit,
      'is_blocked': isBlocked,
      'notes': notes,
    };
  }
}
