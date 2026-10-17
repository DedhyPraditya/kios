class CashSessionModel {
  final int id;
  final int userId;
  final String? userName;
  final int openingCash;
  final DateTime openedAt;
  final DateTime? closedAt;
  final int? countedCash;
  final int? expectedCash;
  final int? difference;
  final int? deposit;
  final bool autoClosed;
  final String? notes;

  CashSessionModel({
    required this.id,
    required this.userId,
    this.userName,
    required this.openingCash,
    required this.openedAt,
    this.closedAt,
    this.countedCash,
    this.expectedCash,
    this.difference,
    this.deposit,
    this.autoClosed = false,
    this.notes,
  });

  bool get isOpen => closedAt == null;

  factory CashSessionModel.fromJson(Map<String, dynamic> json) {
    return CashSessionModel(
      id: json['id'] as int? ?? 0,
      userId: json['user_id'] as int? ?? 0,
      userName: json['user'] is Map ? json['user']['name'] : null,
      openingCash: json['opening_cash'] as int? ?? 0,
      openedAt: DateTime.tryParse(json['opened_at'] ?? '') ?? DateTime.now(),
      closedAt: json['closed_at'] != null ? DateTime.tryParse(json['closed_at']) : null,
      countedCash: json['counted_cash'] as int?,
      expectedCash: json['expected_cash'] as int?,
      difference: json['difference'] as int?,
      deposit: json['deposit'] as int?,
      autoClosed: json['auto_closed'] == 1 || json['auto_closed'] == true,
      notes: json['notes'] as String?,
    );
  }
}
