class ApiConfig {
  /// Physical Android device through:
  /// adb reverse tcp:8000 tcp:8000
  ///
  /// Emulator override:
  /// flutter run --dart-define=SALORA_API_URL=http://10.0.2.2:8000/api
  ///
  /// Wi-Fi override:
  /// flutter run --dart-define=SALORA_API_URL=http://YOUR_PC_IP:8000/api
  static const String baseUrl = String.fromEnvironment(
    'SALORA_API_URL',
    defaultValue: 'http://127.0.0.1:8000/api',
  );

  static const Duration requestTimeout = Duration(seconds: 15);

  static Uri get _apiUri => Uri.parse(baseUrl);

  static String get origin {
    final uri = _apiUri;
    return Uri(
      scheme: uri.scheme,
      userInfo: uri.userInfo,
      host: uri.host,
      port: uri.hasPort ? uri.port : null,
    ).toString().replaceFirst(RegExp(r'/$'), '');
  }

  static bool _isLocalDevelopmentHost(String host) {
    final normalized = host.toLowerCase();
    return normalized == 'localhost' ||
        normalized == '127.0.0.1' ||
        normalized == '10.0.2.2';
  }

  static String _publicMediaUrl(String path) {
    final normalized = path
        .replaceAll(r'\', '/')
        .replaceFirst(RegExp(r'^/?storage/app/public/'), '')
        .replaceFirst(RegExp(r'^/?public/storage/'), '')
        .replaceFirst(RegExp(r'^/?storage/'), '')
        .replaceFirst(RegExp(r'^(storage/)+'), '')
        .replaceFirst(RegExp(r'^/+'), '');

    return '$baseUrl/media/public-file'
        '?path=${Uri.encodeQueryComponent(normalized)}';
  }

  /// Converts every API-returned image/video value to a URL reachable from
  /// the same host currently used by the API.
  static String? resolveAssetUrl(String? value) {
    final raw = value?.trim();
    if (raw == null || raw.isEmpty) return null;

    if (raw.startsWith('data:') || raw.startsWith('blob:')) {
      return raw;
    }

    final normalized = raw.replaceAll(r'\', '/');

    final absolute = Uri.tryParse(normalized);
    if (absolute != null &&
        absolute.hasScheme &&
        (absolute.scheme == 'http' || absolute.scheme == 'https')) {
      if (absolute.path.startsWith('/storage/')) {
        final storagePath = absolute.path.substring('/storage/'.length);
        if (_isLocalDevelopmentHost(absolute.host)) {
          return _publicMediaUrl(storagePath);
        }
      }

      // Laravel may serialize APP_URL as localhost/10.0.2.2 while Flutter
      // currently reaches it through another development host.
      if (_isLocalDevelopmentHost(absolute.host)) {
        final api = _apiUri;
        return Uri(
          scheme: api.scheme,
          userInfo: api.userInfo,
          host: api.host,
          port: api.hasPort ? api.port : null,
          path: absolute.path,
          query: absolute.hasQuery ? absolute.query : null,
          fragment: absolute.hasFragment ? absolute.fragment : null,
        ).toString();
      }

      return normalized;
    }

    final storagePath = normalized
        .replaceFirst(RegExp(r'^/?storage/app/public/'), '')
        .replaceFirst(RegExp(r'^/?public/storage/'), '')
        .replaceFirst(RegExp(r'^/?storage/'), '')
        .replaceFirst(RegExp(r'^(storage/)+'), '')
        .replaceFirst(RegExp(r'^/+'), '');

    const publicPrefixes = [
      'avatars/',
      'venues/',
      'services/',
      'service-categories/',
      'payment-methods/',
      'offers/',
      'providers/',
      'provider-portfolios/',
      'service-media/',
    ];

    if (normalized.startsWith('/storage/') ||
        normalized.startsWith('storage/') ||
        normalized.startsWith('storage/app/public/') ||
        publicPrefixes.any(storagePath.startsWith)) {
      return _publicMediaUrl(storagePath);
    }

    final path = normalized.startsWith('/') ? normalized : '/$normalized';
    return '$origin$path';
  }
}
