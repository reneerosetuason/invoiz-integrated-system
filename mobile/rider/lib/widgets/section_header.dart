import 'package:flutter/material.dart';

import '../core/theme/app_theme.dart';

/// Consistent section heading with an optional trailing action.
class SectionHeader extends StatelessWidget {
  const SectionHeader({super.key, required this.title, this.action});

  final String title;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(title, style: AppText.section),
        ),
        ...[action].whereType<Widget>(),
      ],
    );
  }
}
