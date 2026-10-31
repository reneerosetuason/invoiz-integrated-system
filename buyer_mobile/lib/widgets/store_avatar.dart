import 'package:flutter/material.dart';
import '../config.dart';
import '../theme.dart';
import 'product_gallery.dart';

/// Opens a store logo full screen, pinch-to-zoom, like product photos.
Future<void> openStorePhoto(
  BuildContext context, {
  required String name,
  String? path,
}) async {
  if (AppConfig.storageUrl(path).isEmpty) return;
  await Navigator.push(
    context,
    MaterialPageRoute(
      builder: (_) => ProductPhotoViewer(
        images: [path!],
        label: '$name store profile picture',
      ),
    ),
  );
}

/// Uses the public store logo, with initials for missing or unavailable files.
class StoreAvatar extends StatelessWidget {
  final String name;
  final String? path;
  final double size;
  final VoidCallback? onTap;

  const StoreAvatar({
    super.key,
    required this.name,
    this.path,
    this.size = 48,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final initial = name.trim().isEmpty
        ? 'S'
        : name.trim().characters.first.toUpperCase();
    final fallback = ColoredBox(
      color: AppColors.paleTeal,
      child: Center(
        child: Text(
          initial,
          style: const TextStyle(
            color: AppColors.primaryDark,
            fontSize: 20,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
    );
    final url = AppConfig.storageUrl(path);
    final avatar = Semantics(
      image: true,
      button: onTap != null,
      label: '$name store profile picture',
      child: SizedBox.square(
        dimension: size,
        child: ClipOval(
          child: url.isEmpty
              ? fallback
              : Image.network(
                  url,
                  fit: BoxFit.cover,
                  excludeFromSemantics: true,
                  errorBuilder: (_, error, stack) => fallback,
                ),
        ),
      ),
    );
    if (onTap == null) return avatar;
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: avatar,
    );
  }
}
