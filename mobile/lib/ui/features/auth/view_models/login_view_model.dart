import '../../../../data/repositories/auth_repository.dart';
import '../../../../data/services/api_exception.dart';
import '../../../core/view_model.dart';

class LoginViewModel extends ViewModel {
  LoginViewModel({required AuthRepository authRepository})
    : _auth = authRepository;

  final AuthRepository _auth;

  bool _isSubmitting = false;
  String? _error;

  bool get isSubmitting => _isSubmitting;
  String? get error => _error;

  /// On success the router leaves the login screen by itself.
  Future<void> login(String email, String password) async {
    if (_isSubmitting) return;
    if (email.trim().isEmpty || password.isEmpty) {
      _error = 'Email va parolni kiriting.';
      notifyListeners();
      return;
    }

    _isSubmitting = true;
    _error = null;
    notifyListeners();
    try {
      await _auth.login(email: email, password: password);
    } on ApiException catch (e) {
      _error = e.message;
    } finally {
      _isSubmitting = false;
      notifyListeners();
    }
  }
}
