import 'package:flutter/foundation.dart';

/// Base for screen view models. Async work often finishes after its screen
/// has closed, so notifications after [dispose] are dropped.
abstract class ViewModel extends ChangeNotifier {
  bool _disposed = false;

  @override
  void notifyListeners() {
    if (!_disposed) super.notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    super.dispose();
  }
}
