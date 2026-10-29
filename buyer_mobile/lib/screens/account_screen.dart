import 'package:flutter/cupertino.dart';
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
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
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
                          fontSize: 20,
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
          const SizedBox(height: 20),
          Text(
            'Shopping & deliveries',
            style: Theme.of(context).textTheme.titleLarge,
          ),
          const SizedBox(height: 10),
          CupertinoListSection.insetGrouped(
            margin: EdgeInsets.zero,
            backgroundColor: Colors.transparent,
            children: [
              _menuTile(CupertinoIcons.doc_text, 'My Orders', () {
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const OrdersScreen()),
                );
              }),
              _menuTile(
                CupertinoIcons.location,
                'My Addresses ($_addressCount)',
                () async {
                  await Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => const AddressesScreen()),
                  );
                  _loadAddressCount();
                },
              ),
              _menuTile(CupertinoIcons.heart, 'My Favorites', () async {
                await Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const FavoritesScreen()),
                );
              }),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            'Personal details',
            style: Theme.of(context).textTheme.titleLarge,
          ),
          const SizedBox(height: 10),
          CupertinoListSection.insetGrouped(
            margin: EdgeInsets.zero,
            backgroundColor: Colors.transparent,
            children: [
              _menuTile(CupertinoIcons.person, 'Edit Profile', () {
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const ProfileScreen()),
                );
              }),
            ],
          ),
          const SizedBox(height: 8),
          Container(
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: AppColors.border, width: 0.5),
            ),
            child: CupertinoListTile.notched(
              leading: const Icon(
                CupertinoIcons.square_arrow_right,
                color: AppColors.danger,
              ),
              title: const Text(
                'Logout',
                style: TextStyle(
                  fontFamily: 'Inter',
                  fontSize: 14,
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
    return CupertinoListTile.notched(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      backgroundColor: AppColors.surface,
      leading: Icon(icon, color: AppColors.primary, size: 21),
      title: Text(
        title,
        maxLines: 2,
        style: TextStyle(
          fontSize: 14,
          fontWeight: FontWeight.w500,
          color: AppColors.textPrimary,
          fontFamily: 'Inter',
        ),
      ),
      trailing: const Icon(
        CupertinoIcons.chevron_right,
        size: 16,
        color: AppColors.textMuted,
      ),
      onTap: onTap,
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
