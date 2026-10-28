import '../widgets/buyer_page.dart';
import 'package:flutter/material.dart';
import '../config.dart';
import '../services/api_service.dart';
import '../theme.dart';
import '../widgets/auth_service_provider.dart';
import '../widgets/main_layout.dart';
import 'addresses_screen.dart';
import 'favorites_screen.dart';
import 'login_screen.dart';
import 'orders_screen.dart';
import 'profile_screen.dart';

class AccountScreen extends StatefulWidget {
  const AccountScreen({super.key});

  @override
  State<AccountScreen> createState() => _AccountScreenState();
}

class _AccountScreenState extends State<AccountScreen> {
  int _addressCount = 0;

  @override
  void initState() {
    super.initState();
    _loadAddressCount();
  }

  Future<void> _loadAddressCount() async {
    if (!mounted) return;
    try {
      final data = await ApiService().get('addresses');
      if (mounted) {
        setState(() => _addressCount = (data['addresses'] as List).length);
      }
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    final auth = AuthServiceProvider.of(context);
    final user = auth.user;

    return MainLayout(
      currentIndex: 4,
      showSettingsIcon: false,
      title: 'Account',
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 24, 20, 32),
        children: [
          const BuyerPageHeading(
            title: 'Your account',
            description: 'Your details, deliveries and saved finds.',
          ),
          Container(
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: AppColors.border, width: 0.5),
            ),
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                Container(
                  width: 56,
                  height: 56,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: AppColors.sandMid,
                  ),
                  child: ClipOval(
                    child:
                        user?.profilePicture != null &&
                            user!.profilePicture!.isNotEmpty
                        ? Image.network(
                            AppConfig.storageUrl(user.profilePicture),
                            fit: BoxFit.cover,
                            width: 56,
                            height: 56,
                            errorBuilder: (_, __, ___) => _initialAvatar(user),
                          )
                        : _initialAvatar(user),
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        user?.fullName ?? 'User',
                        style: TextStyle(
                          fontSize: 23,
                          fontWeight: FontWeight.w600,
                          fontFamily: 'Fraunces',
                          color: AppColors.textPrimary,
                        ),
                      ),
                      Text(
                        user?.email ?? '',
                        style: TextStyle(
                          fontSize: 12,
                          color: AppColors.textSecondary,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Member since ${_shortDate(user?.createdAt)}',
                        style: TextStyle(
                          fontSize: 12,
                          color: AppColors.textMuted,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          const BuyerPageHeading(title: 'Shopping & deliveries'),
          _menuTile(Icons.receipt_long_outlined, 'My Orders', () {
            Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const OrdersScreen()),
            );
          }),
          _menuTile(
            Icons.location_on_outlined,
            'My Addresses ($_addressCount)',
            () async {
              await Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const AddressesScreen()),
              );
              _loadAddressCount();
            },
          ),
          _menuTile(Icons.favorite_border, 'My Favorites', () async {
            await Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const FavoritesScreen()),
            );
          }),
          const SizedBox(height: 12),
          const BuyerPageHeading(title: 'Personal details'),
          _menuTile(Icons.person_outline, 'Edit Profile', () {
            Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const ProfileScreen()),
            );
          }),
          const SizedBox(height: 8),
          Container(
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: AppColors.border, width: 0.5),
            ),
            child: ListTile(
              leading: const Icon(Icons.logout, color: AppColors.danger),
              title: const Text(
                'Logout',
                style: TextStyle(
                  color: AppColors.danger,
                  fontWeight: FontWeight.w600,
                ),
              ),
              onTap: () {
                auth.logout();
                Navigator.pushAndRemoveUntil(
                  context,
                  MaterialPageRoute(builder: (_) => const LoginScreen()),
                  (route) => false,
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _menuTile(IconData icon, String title, VoidCallback onTap) {
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.border, width: 0.5),
      ),
      child: ListTile(
        leading: Container(
          width: 36,
          height: 36,
          decoration: BoxDecoration(
            color: AppColors.sandLight,
            borderRadius: BorderRadius.circular(10),
          ),
          child: Icon(icon, color: AppColors.primary, size: 20),
        ),
        title: Text(
          title,
          style: TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w500,
            color: AppColors.textPrimary,
            fontFamily: 'Inter',
          ),
        ),
        trailing: Icon(Icons.chevron_right, color: AppColors.textMuted),
        onTap: onTap,
      ),
    );
  }

  Widget _initialAvatar(dynamic user) {
    return Container(
      width: 56,
      height: 56,
      color: Colors.transparent,
      child: Center(
        child: Text(
          ((user?.firstName ?? '').isEmpty
                  ? '?'
                  : user.firstName.substring(0, 1))
              .toUpperCase(),
          style: TextStyle(
            color: AppColors.primary,
            fontSize: 24,
            fontWeight: FontWeight.w700,
          ),
        ),
      ),
    );
  }

  String _shortDate(String? iso) {
    if (iso == null || iso.isEmpty) return 'Invoiz';
    try {
      final d = DateTime.parse(iso);
      return '${d.year}';
    } catch (_) {
      return 'Invoiz';
    }
  }
}
