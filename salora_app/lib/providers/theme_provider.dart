import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

class ThemeProvider extends ChangeNotifier {
  static const _storageKey = 'salora_theme_mode';

  ThemeMode _themeMode = ThemeMode.dark;
  bool _initialized = false;

  ThemeProvider() {
    _load();
  }

  ThemeMode get themeMode => _themeMode;
  bool get isDark => _themeMode == ThemeMode.dark;
  bool get isInitialized => _initialized;

  Future<void> _load() async {
    final prefs = await SharedPreferences.getInstance();
    final stored = prefs.getString(_storageKey);
    switch (stored) {
      case 'light':
        _themeMode = ThemeMode.light;
        break;
      case 'dark':
      default:
        _themeMode = ThemeMode.dark;
        break;
    }
    _initialized = true;
    notifyListeners();
  }

  Future<void> setThemeMode(ThemeMode mode) async {
    if (_themeMode == mode && _initialized) return;
    _themeMode = mode;
    _initialized = true;
    notifyListeners();
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_storageKey, mode == ThemeMode.light ? 'light' : 'dark');
  }

  Future<void> toggleTheme() => setThemeMode(isDark ? ThemeMode.light : ThemeMode.dark);
}
