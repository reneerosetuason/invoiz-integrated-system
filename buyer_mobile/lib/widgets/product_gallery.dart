import 'buyer_controls.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import '../theme.dart';
import 'product_photo.dart';

class ProductGallery extends StatefulWidget {
  final List<String> images;
  final String label;
  const ProductGallery({super.key, required this.images, required this.label});
  @override
  State<ProductGallery> createState() => _ProductGalleryState();
}

class _ProductGalleryState extends State<ProductGallery> {
  final _pages = PageController();
  int _index = 0;
  @override
  void dispose() {
    _pages.dispose();
    super.dispose();
  }

  void _open() => Navigator.push(
    context,
    MaterialPageRoute(
      builder: (_) => ProductPhotoViewer(
        images: widget.images,
        initialIndex: _index,
        label: widget.label,
      ),
    ),
  );
  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final imageHeight = (constraints.maxWidth * .58)
          .clamp(164.0, 280.0)
          .toDouble();
      if (widget.images.isEmpty)
        return SizedBox(
          width: double.infinity,
          height: imageHeight,
          child: const ProductPhoto(path: null),
        );
      return Column(
        children: [
          SizedBox(
            width: double.infinity,
            height: imageHeight,
            child: Stack(
              children: [
                Positioned.fill(
                  child: PageView.builder(
                    controller: _pages,
                    itemCount: widget.images.length,
                    onPageChanged: (index) => setState(() => _index = index),
                    itemBuilder: (_, index) => Semantics(
                      button: true,
                      label: 'Open product photos',
                      child: InkWell(
                        onTap: _open,
                        child: ProductPhoto(
                          path: widget.images[index],
                          fit: BoxFit.contain,
                          label: widget.label,
                        ),
                      ),
                    ),
                  ),
                ),
                Positioned(
                  right: 12,
                  bottom: 12,
                  child: BuyerButton.secondary(
                    onPressed: _open,
                    icon: const Icon(
                      CupertinoIcons.arrow_up_left_arrow_down_right,
                      size: 20,
                    ),
                    label: Text('${_index + 1} / ${widget.images.length}'),
                  ),
                ),
              ],
            ),
          ),
          if (widget.images.length > 1)
            SizedBox(
              height: 64,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.all(8),
                itemCount: widget.images.length,
                separatorBuilder: (_, index) => const SizedBox(width: 8),
                itemBuilder: (_, index) => Semantics(
                  button: true,
                  selected: index == _index,
                  label: 'Show photo ${index + 1}',
                  child: InkWell(
                    onTap: () => _pages.animateToPage(
                      index,
                      duration: const Duration(milliseconds: 200),
                      curve: Curves.easeOut,
                    ),
                    child: Container(
                      width: 48,
                      padding: const EdgeInsets.all(2),
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(
                          color: index == _index
                              ? AppColors.primary
                              : AppColors.border,
                          width: 2,
                        ),
                      ),
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(5),
                        child: ProductPhoto(
                          path: widget.images[index],
                          showLabel: false,
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
        ],
      );
    },
  );
}

class ProductPhotoViewer extends StatefulWidget {
  final List<String> images;
  final int initialIndex;
  final String label;
  const ProductPhotoViewer({
    super.key,
    required this.images,
    this.initialIndex = 0,
    required this.label,
  });
  @override
  State<ProductPhotoViewer> createState() => _ProductPhotoViewerState();
}

class _ProductPhotoViewerState extends State<ProductPhotoViewer> {
  late final PageController _pages;
  late int _index;
  @override
  void initState() {
    super.initState();
    _index = widget.images.isEmpty
        ? 0
        : widget.initialIndex.clamp(0, widget.images.length - 1);
    _pages = PageController(initialPage: _index);
  }

  @override
  void dispose() {
    _pages.dispose();
    super.dispose();
  }

  void _move(int offset) => _pages.animateToPage(
    _index + offset,
    duration: const Duration(milliseconds: 200),
    curve: Curves.easeOut,
  );
  @override
  Widget build(BuildContext context) => CupertinoPageScaffold(
    backgroundColor: Colors.black,
    navigationBar: CupertinoNavigationBar(
      backgroundColor: Colors.black,
      transitionBetweenRoutes: false,
      border: null,
      leading: BuyerButton.icon(
        tooltip: 'Close photos',
        onPressed: () => Navigator.pop(context),
        icon: const Icon(CupertinoIcons.xmark, color: Colors.white),
      ),
      middle: Text(
        'Photo ${_index + 1} of ${widget.images.length}',
        style: const TextStyle(fontSize: 16, color: Colors.white),
      ),
    ),
    child: SafeArea(
      child: Column(
        children: [
          Expanded(
            child: widget.images.isEmpty
                ? const Center(
                    child: Text(
                      'No photos available',
                      style: TextStyle(color: Colors.white),
                    ),
                  )
                : PageView.builder(
                    controller: _pages,
                    itemCount: widget.images.length,
                    onPageChanged: (index) => setState(() => _index = index),
                    itemBuilder: (_, index) => InteractiveViewer(
                      minScale: 1,
                      maxScale: 5,
                      child: Center(
                        child: AspectRatio(
                          aspectRatio: 1,
                          child: ProductPhoto(
                            path: widget.images[index],
                            fit: BoxFit.contain,
                            label: widget.label,
                          ),
                        ),
                      ),
                    ),
                  ),
          ),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              child: Row(
                children: [
                  BuyerButton.icon(
                    tooltip: 'Previous photo',
                    onPressed: _index > 0 ? () => _move(-1) : null,
                    style: IconButton.styleFrom(
                      foregroundColor: Colors.white,
                      disabledForegroundColor: Colors.grey,
                    ),
                    icon: const Icon(CupertinoIcons.chevron_left),
                  ),
                  const Expanded(
                    child: Text(
                      'Pinch to zoom · Swipe for more',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: Colors.white, fontSize: 13),
                    ),
                  ),
                  BuyerButton.icon(
                    tooltip: 'Next photo',
                    onPressed: _index < widget.images.length - 1
                        ? () => _move(1)
                        : null,
                    style: IconButton.styleFrom(
                      foregroundColor: Colors.white,
                      disabledForegroundColor: Colors.grey,
                    ),
                    icon: const Icon(CupertinoIcons.chevron_right),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    ),
  );
}
