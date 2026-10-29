import 'package:app_links/app_links.dart';

/// Restored deep-link handling for the rider app.
///
/// Accepted links use the custom scheme `invoizrider` with host `login`
/// (e.g. `invoizrider://login`) and open the login screen.
abstract class DeepLinkService {
  static const scheme = 'invoizrider';
  static const loginHost = 'login';
  static const loginRouteName = 'deep_link_login';

  /// Password-reset links mailed to riders:
  /// `invoizrider://reset-password?token=…&email=…`.
  static const resetHost = 'reset-password';
  static const resetRouteName = 'deep_link_reset';

  /// The link the app was launched from, if it is a login link.
  Future<Uri?> get initialLink;

  /// Live login links received while the app is running.
  Stream<Uri> get loginLinks;

  /// The link the app was launched from, if it is a reset link.
  Future<Uri?> get initialResetLink;

  /// Live reset links received while the app is running.
  Stream<Uri> get resetLinks;

  static bool isLoginLink(Uri? uri) =>
      uri != null && uri.scheme == scheme && uri.host == loginHost;

  static bool isResetLink(Uri? uri) =>
      uri != null && uri.scheme == scheme && uri.host == resetHost;

  /// Email + token carried by a reset link, or null when incomplete.
  static ({String email, String token})? resetCredentials(Uri? uri) {
    if (!isResetLink(uri)) return null;
    final email = uri!.queryParameters['email']?.trim() ?? '';
    final token = uri.queryParameters['token'] ?? '';
    if (email.isEmpty || token.isEmpty) return null;
    return (email: email, token: token);
  }
}

/// [DeepLinkService] backed by the `app_links` plugin.
class AppLinksDeepLinkService implements DeepLinkService {
  AppLinksDeepLinkService({AppLinks? links}) : _links = links ?? AppLinks();

  final AppLinks _links;

  @override
  Future<Uri?> get initialLink async {
    try {
      final uri = await _links.getInitialLink();
      return DeepLinkService.isLoginLink(uri) ? uri : null;
    } catch (_) {
      return null;
    }
  }

  @override
  Stream<Uri> get loginLinks async* {
    await for (final uri in _links.uriLinkStream) {
      if (DeepLinkService.isLoginLink(uri)) yield uri;
    }
  }

  @override
  Future<Uri?> get initialResetLink async {
    try {
      final uri = await _links.getInitialLink();
      return DeepLinkService.isResetLink(uri) ? uri : null;
    } catch (_) {
      return null;
    }
  }

  @override
  Stream<Uri> get resetLinks async* {
    await for (final uri in _links.uriLinkStream) {
      if (DeepLinkService.isResetLink(uri)) yield uri;
    }
  }
}