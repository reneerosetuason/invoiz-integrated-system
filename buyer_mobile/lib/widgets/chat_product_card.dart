import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import '../models/chat.dart';
import '../screens/product_detail_screen.dart';
import '../services/api_service.dart';
import '../theme.dart';
import 'product_photo.dart';
import 'shopping_ui.dart';

class ChatProductCard extends StatelessWidget {
  final ChatProduct card;
  final ApiService? api;
  const ChatProductCard({super.key, required this.card, this.api});

  @override
  Widget build(BuildContext context) {
    final largeText = MediaQuery.textScalerOf(context).scale(14) > 18;
    final option = card.option == null
        ? null
        : Text(
            card.option!,
            style: const TextStyle(
              color: AppColors.textSecondary,
              fontSize: 12,
            ),
          );
    final price = ShoppingPrice(shoppingMoney(card.price), size: 14);
    return Semantics(
      label: 'View product: ${card.name}',
      button: true,
      child: CupertinoButton(
        padding: EdgeInsets.zero,
        onPressed: () => Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => ProductDetailScreen(productId: card.id, api: api),
          ),
        ),
        child: Container(
          key: ValueKey('chat-product-${card.id}'),
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
            color: AppColors.paper,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: SizedBox(
                      width: 48,
                      height: 58,
                      child: ProductPhoto(
                        path: card.image,
                        showLabel: false,
                        label: card.name,
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          card.name,
                          style: const TextStyle(
                            color: AppColors.textPrimary,
                            fontSize: 13,
                            height: 1.3,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        if (!largeText && option != null)
                          Padding(
                            padding: const EdgeInsets.only(top: 3),
                            child: option,
                          ),
                        if (!largeText) ...[const SizedBox(height: 4), price],
                      ],
                    ),
                  ),
                ],
              ),
              if (largeText) ...[
                if (option != null) ...[const SizedBox(height: 6), option],
                const SizedBox(height: 6),
                price,
              ],
            ],
          ),
        ),
      ),
    );
  }
}
