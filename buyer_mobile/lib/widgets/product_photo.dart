import 'package:flutter/cupertino.dart';
import 'buyer_controls.dart';
import 'package:flutter/material.dart';
import '../config.dart';
import '../theme.dart';

class ProductPhoto extends StatefulWidget {
  final String? path;
  final BoxFit fit;
  final String label;
  final bool showLabel;
  const ProductPhoto({
    super.key,
    required this.path,
    this.fit = BoxFit.cover,
    this.label = 'Product photo',
    this.showLabel = true,
  });
  @override
  State<ProductPhoto> createState() => _ProductPhotoState();
}

class _ProductPhotoState extends State<ProductPhoto> {
  int _attempt = 0;
  @override
  Widget build(BuildContext context) {
    final url = AppConfig.storageUrl(widget.path);
    if (url.isEmpty) return _fallback();
    return Image.network(
      url,
      key: ValueKey('$url:$_attempt'),
      fit: widget.fit,
      width: double.infinity,
      height: double.infinity,
      semanticLabel: widget.label,
      loadingBuilder: (_, child, progress) => progress == null
          ? child
          : ColoredBox(
              color: AppColors.cream,
              child: Center(
                child: SizedBox(
                  width: 22,
                  height: 22,
                  child: progress.expectedTotalBytes == null
                      ? const CupertinoActivityIndicator(radius: 10)
                      : CupertinoActivityIndicator.partiallyRevealed(
                          radius: 10,
                          progress:
                              progress.cumulativeBytesLoaded /
                              progress.expectedTotalBytes!,
                        ),
                ),
              ),
            ),
      errorBuilder: (_, error, stack) => _fallback(
        retry: () async {
          await NetworkImage(url).evict();
          if (mounted) setState(() => _attempt++);
        },
      ),
    );
  }

  Widget _fallback({VoidCallback? retry}) => LayoutBuilder(
    builder: (context, constraints) {
      final compact =
          !widget.showLabel ||
          constraints.maxHeight < 110 ||
          constraints.maxWidth < 100;
      return ColoredBox(
        color: AppColors.cream,
        child: Center(
          child: compact
              ? (retry == null
                    ? const Icon(
                        CupertinoIcons.photo,
                        color: AppColors.textMuted,
                        size: 24,
                      )
                    : BuyerButton.icon(
                        tooltip: 'Retry photo',
                        onPressed: retry,
                        icon: const Icon(CupertinoIcons.refresh, size: 22),
                      ))
              : Padding(
                  padding: const EdgeInsets.all(6),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(
                        CupertinoIcons.photo,
                        color: AppColors.textMuted,
                        size: 28,
                      ),
                      if (retry == null)
                        const Padding(
                          padding: EdgeInsets.only(top: 5),
                          child: Text(
                            'Photo unavailable',
                            style: TextStyle(fontSize: 12),
                          ),
                        ),
                      if (retry != null)
                        BuyerButton.plain(
                          onPressed: retry,
                          icon: const Icon(CupertinoIcons.refresh, size: 16),
                          label: const Text(
                            'Retry photo',
                            style: TextStyle(fontSize: 12),
                          ),
                        ),
                    ],
                  ),
                ),
        ),
      );
    },
  );
}
