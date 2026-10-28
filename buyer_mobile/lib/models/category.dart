class Category {
  final int id;
  final String name;
  final String? description;
  final String? image;
  final int productsCount;

  Category({
    required this.id,
    required this.name,
    this.description,
    this.image,
    this.productsCount = 0,
  });

  factory Category.fromJson(Map<String, dynamic> json) => Category(
    id: json['id'] as int,
    name: json['name'] as String,
    description: json['description'] as String?,
    image: json['image'] as String?,
    productsCount: (json['products_count'] as int?) ?? 0,
  );
}
