/// Wrapper respon standar API untuk mengisolasi penanganan status dan error.
class ApiResponse<T> {
  final bool isSuccess;
  final T? data;
  final String? message;
  final int? statusCode;

  ApiResponse.success(this.data, {this.message, this.statusCode = 200})
      : isSuccess = true;

  ApiResponse.error(this.message, {this.statusCode = 400, this.data})
      : isSuccess = false;
}
