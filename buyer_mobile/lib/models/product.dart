import 'category.dart';

class ProductVariant {
  final int id;
  final String variantType;
  final String variantValue;
  final double priceAdjustment;
  final int stock;
  final String status;

  ProductVariant({
    required this.id,
    required this.variantType,
    required this.variantValue,
    this.priceAdjustment = 0,
    this.stock = 0,
    this.status = 'active',
  });

  factory ProductVariant.fromJson(Map<String, dynamic> json) => ProductVariant(
    id: json['id'] as int,
    variantType: json['variant_type'] as String,
    variantValue: json['variant_value'] as String,
    priceAdjustment: double.tryParse('${json['price_adjustment']}') ?? 0,
    stock: (json['stock'] as int?) ?? 0,
    status: json['status'] as String? ?? 'active',
  );

  bool get isAvailable =>
      stock > 0 && !['inactive', 'unavailable', 'deleted'].contains(status);

  String get label => '$variantType: $variantValue';
}

class ProductReview {
  final int id;
  final int rating;
  final String? comment;
  final String? buyerName;
  final String createdAt;

  ProductReview({
    required this.id,
    required this.rating,
    this.comment,
    this.buyerName,
    required this.createdAt,
  });

  factory ProductReview.fromJson(Map<String, dynamic> json) {
    final buyer = json['buyer'];
    return ProductReview(
      id: json['id'] as int,
      rating: json['rating'] as int,
      comment: json['comment'] as String?,
      buyerName: buyer is Map<String, dynamic>
          ? '${buyer['first_name'] ?? ''} ${buyer['last_name'] ?? ''}'
          : 'Anonymous',
      createdAt: json['created_at'] as String,
    );
  }
}

class SellerShop {
  final String? logo;
  final int sellerId;
  final String businessName;
  final String lineOfBusiness;
  final double rating;
  final int productCount;
  final int followers;

  SellerShop({
    this.logo,
    required this.sellerId,
    required this.businessName,
    this.lineOfBusiness = '',
    this.rating = 0,
    this.productCount = 0,
    this.followers = 0,
  });

  factory SellerShop.fromJson(
    Map<String, dynamic> json, {
    int? fallbackSellerId,
  }) => SellerShop(
    logo: json['logo'] as String?,
    sellerId: (json['seller_id'] as num?)?.toInt() ?? fallbackSellerId ?? 0,
    businessName:
        '${json['business_name'] ?? json['store_name'] ?? 'Invoiz Store'}',
    lineOfBusiness: json['line_of_business'] as String? ?? '',
    rating: json['rating'] != null
        ? double.tryParse('${json['rating']}') ?? 0
        : 0,
    productCount: (json['product_count'] as num?)?.toInt() ?? 0,
    followers: (json['followers'] as num?)?.toInt() ?? 0,
  );

  String get initial => businessName.trim().isEmpty
      ? 'S'
      : businessName.trim().substring(0, 1).toUpperCase();
}

class Product {
  final int id;
  final int? sellerId;
  final int? categoryId;
  final String name;
  final String? description;
  final String? brand;
  final String? model;
  final String? sku;
  final String? material;
  final String? dimensions;
  final String? weight;
  final String? warranty;
  final String? origin;
  final double price;
  final double? compareAtPrice;
  final int discountPercent;
  final int stock;
  final String? image;
  final double? rating;
  final String? status;
  final int sold;
  final List<String> gallery;
  final Category? category;
  final List<ProductVariant> variants;
  final List<ProductReview> reviews;
  final bool isFavorited;
  final SellerShop? shop;

  Product({
    required this.id,
    this.sellerId,
    this.categoryId,
    required this.name,
    this.description,
    this.brand,
    this.model,
    this.sku,
    this.material,
    this.dimensions,
    this.weight,
    this.warranty,
    this.origin,
    required this.price,
    this.compareAtPrice,
    this.discountPercent = 0,
    this.stock = 0,
    this.image,
    this.rating,
    this.status,
    this.sold = 0,
    this.gallery = const [],
    this.category,
    this.variants = const [],
    this.reviews = const [],
    this.isFavorited = false,
    this.shop,
  });

  factory Product.fromJson(Map<String, dynamic> json) {
    final cat = json['category'];
    final vars = json['variants'];
    final revs = json['reviews'];
    final shop = json['shop'];
    final gallery = json['gallery'];
    return Product(
      id: json['id'] as int,
      sellerId: json['seller_id'] as int?,
      categoryId: json['category_id'] as int?,
      name: json['name'] as String,
      description: json['description'] as String?,
      brand: json['brand'] as String?,
      model: json['model'] as String?,
      sku: json['sku'] as String?,
      material: json['material'] as String?,
      dimensions: json['dimensions'] as String?,
      weight: json['weight'] as String?,
      warranty: json['warranty'] as String?,
      origin: json['origin'] as String?,
      price:
          double.tryParse('${json['effective_price'] ?? json['price']}') ?? 0,
      compareAtPrice: json['compare_at_price'] != null
          ? double.tryParse('${json['compare_at_price']}')
          : null,
      discountPercent: (json['discount_percent'] as int?) ?? 0,
      stock: (json['stock'] as int?) ?? 0,
      image: json['image'] as String?,
      rating: json['rating'] != null
          ? double.tryParse('${json['rating']}')
          : null,
      status: json['status'] as String?,
      sold: (json['sold'] as int?) ?? 0,
      gallery: gallery is List
          ? gallery.whereType<String>().toList()
          : const [],
      category: cat is Map<String, dynamic> ? Category.fromJson(cat) : null,
      variants: vars is List
          ? vars
                .whereType<Map<String, dynamic>>()
                .map(ProductVariant.fromJson)
                .toList()
          : const [],
      reviews: revs is List
          ? revs
                .whereType<Map<String, dynamic>>()
                .map(ProductReview.fromJson)
                .toList()
          : const [],
      isFavorited: json['is_favorited'] as bool? ?? false,
      shop: shop is Map<String, dynamic>
          ? SellerShop.fromJson(
              shop,
              fallbackSellerId: (json['seller_id'] as num?)?.toInt(),
            )
          : null,
    );
  }

  int get availableStock {
    final allocated = variants.isEmpty
        ? stock
        : variants
              .where((v) => v.isAvailable)
              .fold(0, (sum, v) => sum + v.stock);
    return allocated.clamp(0, stock < 0 ? 0 : stock);
  }

  List<String> get displayImages => [if (image != null) image!, ...gallery]
      .map((path) => path.trim())
      .where((path) => path.isNotEmpty)
      .toSet()
      .toList();

  String get formattedPrice {
    final n = price.toStringAsFixed(2);
    final parts = n.split('.');
    final whole = parts[0].replaceAllMapped(
      RegExp(r'(\d)(?=(\d{3})+(?!\d))'),
      (m) => '${m[1]},',
    );
    return '₱$whole.${parts[1]}';
  }
}
