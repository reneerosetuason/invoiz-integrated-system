import 'dart:typed_data';
import 'package:flutter/material.dart';
import '../config.dart';

/// One in-app viewer for QR codes, private proof bytes, and selected uploads.
Future<void> showBuyerImagePreview(
  BuildContext context, {
  required String title,
  Uint8List? bytes,
  String? url,
}) => showDialog<void>(
  context: context,
  useSafeArea: false,
  builder: (_) => BuyerImagePreview(title: title, bytes: bytes, url: url),
);

class BuyerImagePreview extends StatefulWidget {
  final String title;
  final Uint8List? bytes;
  final String? url;
  const BuyerImagePreview({
    super.key,
    required this.title,
    this.bytes,
    this.url,
  });

  @override
  State<BuyerImagePreview> createState() => _BuyerImagePreviewState();
}

class _BuyerImagePreviewState extends State<BuyerImagePreview> {
  final _transform = TransformationController();
  int _attempt = 0;
  @override
  void dispose() {
    _transform.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    Widget error(BuildContext _, Object error, StackTrace? stack) => Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Text(
            'This image could not be loaded.',
            style: TextStyle(color: Colors.white),
          ),
          TextButton(
            onPressed: () => setState(() => _attempt++),
            child: const Text('Retry', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
    final image = widget.bytes != null
        ? Image.memory(
            widget.bytes!,
            key: ValueKey(_attempt),
            fit: BoxFit.contain,
            semanticLabel: widget.title,
            errorBuilder: error,
          )
        : Image.network(
            AppConfig.storageUrl(widget.url),
            key: ValueKey(_attempt),
            fit: BoxFit.contain,
            semanticLabel: widget.title,
            errorBuilder: error,
          );
    return Dialog.fullscreen(
      backgroundColor: const Color(0xFF151C20),
      child: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
              child: Row(
                children: [
                  IconButton(
                    tooltip: 'Close image preview',
                    onPressed: () => Navigator.pop(context),
                    icon: const Icon(Icons.close, color: Colors.white),
                  ),
                  Expanded(
                    child: Text(
                      widget.title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                  TextButton(
                    onPressed: () => _transform.value = Matrix4.identity(),
                    child: const Text(
                      'Reset',
                      style: TextStyle(color: Colors.white),
                    ),
                  ),
                ],
              ),
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: LayoutBuilder(
                  builder: (_, constraints) => InteractiveViewer(
                    transformationController: _transform,
                    minScale: 1,
                    maxScale: 5,
                    child: SizedBox(
                      width: constraints.maxWidth,
                      height: constraints.maxHeight,
                      child: image,
                    ),
                  ),
                ),
              ),
            ),
            const Padding(
              padding: EdgeInsets.all(16),
              child: Text(
                'Pinch to zoom. Drag to explore.',
                style: TextStyle(color: Colors.white70, fontSize: 13),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
