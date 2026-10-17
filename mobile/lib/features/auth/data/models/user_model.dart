class UserModel {
  final int id;
  final String name;
  final String email;
  final String role; // 'admin' | 'kasir'

  UserModel({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
  });

  bool get isAdmin => role == 'admin';

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: json['id'] as int? ?? 0,
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      role: json['role'] as String? ?? 'kasir',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'email': email,
      'role': role,
    };
  }
}
