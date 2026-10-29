import 'package:flutter/material.dart';
import '../config.dart';
import '../theme.dart';

/// Uses the public store logo, with initials for missing or unavailable files.
class StoreAvatar extends StatelessWidget {
  final String name;
  final String? path;
  final double size;

  const StoreAvatar({super.key, required this.name, this.path, this.size = 48});

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
    return Semantics(
      image: true,
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
  }
}
