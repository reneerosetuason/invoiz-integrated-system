import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../theme.dart';
import '../widgets/buyer_controls.dart';
import '../widgets/buyer_field.dart';
import '../widgets/buyer_page.dart';
import '../widgets/main_layout.dart';

class HelpFaqScreen extends StatefulWidget {
  final ApiService? api;
  const HelpFaqScreen({super.key, this.api});

  @override
  State<HelpFaqScreen> createState() => _HelpFaqScreenState();
}

class _HelpFaqScreenState extends State<HelpFaqScreen> {
  late final _api = widget.api ?? ApiService();
  final _searchController = TextEditingController();
  final _complaintForm = GlobalKey<FormState>();
  final _subjectController = TextEditingController();
  final _descriptionController = TextEditingController();

  bool _loading = true;
  String _searchQuery = '';
  String _email = 'invoizecommerce@gmail.com';
  String _phone = '+63 912 345 6789';
  String _hours = 'Monday to Saturday, 9:00 AM – 6:00 PM (PHT)';
  String _responseTime = 'within 24 hours on business days';

  int? _selectedOrderId;
  List<dynamic> _orders = [];
  bool _submittingComplaint = false;
  int _activeTab = 0; // 0: FAQs, 1: Report Problem

  List<Map<String, dynamic>> _faqGroups = [
    {
      'category': 'Orders & Checkout',
      'items': [
        {
          'q': 'How do I place an order?',
          'a':
              'Add items to your cart (or tap Buy Now on a product), go to Checkout, choose Cash on Delivery, then tap Place Order. You can order everything at once or per seller.',
        },
        {
          'q': 'What payment methods do you accept?',
          'a':
              'Cash on Delivery (COD) only: you pay in cash when your order arrives. No prepayment or e-wallet is needed.',
        },
        {
          'q': 'Where can I track my order?',
          'a':
              'Open My Orders to see live statuses: pending → confirmed → processing → ready for delivery → out for delivery → delivered. Open an order for its full timeline.',
        },
        {
          'q': 'Can I cancel my order?',
          'a':
              'Yes, while it is still pending or confirmed (before the seller prepares or ships it). Open the order and tap Cancel — reserved stock is returned automatically. Once it is being prepared or on its way, cancellation is no longer available.',
        },
      ],
    },
    {
      'category': 'Shipping & Delivery',
      'items': [
        {
          'q': 'How much is the shipping fee?',
          'a':
              'A flat ₱50 once per shop/seller in your order — not per item. Ordering 3 items from one shop costs ₱50 shipping; items from 2 different shops cost ₱100.',
        },
        {
          'q': 'Who delivers my order?',
          'a':
              'An INVOIZ rider picks your parcel up from the seller and delivers it to your address. The rider earns a flat ₱50 per delivery.',
        },
      ],
    },
    {
      'category': 'Vouchers & Discounts',
      'items': [
        {
          'q': 'How do I use a voucher code?',
          'a':
              'Enter the code in the Voucher field at checkout before placing your order. Fixed vouchers cut a peso amount off, percent vouchers cut a share (up to their cap), and free-shipping vouchers waive the ₱50/shop delivery fee.',
        },
        {
          'q': 'Who pays for voucher discounts?',
          'a':
              'The seller absorbs them: discounts come out of the seller\'s sales. Rider pay is never reduced by vouchers.',
        },
        {
          'q': 'Why was my voucher rejected?',
          'a':
              'Common reasons: the code is expired or inactive, you have not reached its minimum spend, it has reached its usage limit, or (for seller vouchers) none of its shop\'s items are in your order.',
        },
      ],
    },
    {
      'category': 'Account',
      'items': [
        {
          'q': 'How do I create an account?',
          'a':
              'Tap Register and sign up as a buyer — new registrations always start as buyer accounts. You can also continue with Google.',
        },
        {
          'q': 'I did not receive my verification code.',
          'a':
              'Check your spam folder, wait a minute, then use Resend on the verification page. Codes expire after 10 minutes.',
        },
      ],
    },
    {
      'category': 'Selling on Invoiz',
      'items': [
        {
          'q': 'How do I become a seller?',
          'a':
              'Create a buyer account first, then open the Sell page and submit your seller application with your business details. The administrator reviews it and the approval is sent to your email.',
        },
        {
          'q': 'How do low-stock alerts work?',
          'a':
              'When adding or editing a product, set a "Low Stock Alert At" level (for example 10). When stock drops to that level you get a notification; it clears automatically once you restock above it.',
        },
      ],
    },
    {
      'category': 'Returns, Refunds & Help',
      'items': [
        {
          'q': 'What if my item arrives damaged or wrong?',
          'a':
              'Inspect your order on delivery and keep photos of the item and packaging. You can tap "Request return" directly on delivered orders, or submit a report here.',
        },
        {
          'q': 'How do I contact customer service?',
          'a':
              'Use the contact card on this page (email, hotline, hours), message the seller directly from the store page, or file a problem report below for order issues.',
        },
        {
          'q': 'How do I message a seller?',
          'a':
              'Open the Messages page, or visit the seller\'s store and start a chat. Your conversation history stays in Messages.',
        },
      ],
    },
  ];

  @override
  void initState() {
    super.initState();
    _loadFaqs();
    _loadOrders();
  }

  @override
  void dispose() {
    _searchController.dispose();
    _subjectController.dispose();
    _descriptionController.dispose();
    super.dispose();
  }

  Future<void> _loadFaqs() async {
    try {
      final res = await _api.get('help/faq');
      if (res is Map<String, dynamic> && mounted) {
        setState(() {
          if (res['email'] != null && '${res['email']}'.isNotEmpty) {
            _email = '${res['email']}';
          }
          if (res['phone'] != null && '${res['phone']}'.isNotEmpty) {
            _phone = '${res['phone']}';
          }
          if (res['hours'] != null && '${res['hours']}'.isNotEmpty) {
            _hours = '${res['hours']}';
          }
          if (res['response_time'] != null &&
              '${res['response_time']}'.isNotEmpty) {
            _responseTime = '${res['response_time']}';
          }
          if (res['faqs'] is List && (res['faqs'] as List).isNotEmpty) {
            _faqGroups = (res['faqs'] as List)
                .map((g) => Map<String, dynamic>.from(g as Map))
                .toList();
          }
        });
      }
    } catch (_) {
      // Use bundled default FAQs
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _loadOrders() async {
    try {
      final res = await _api.get('orders');
      if (res is Map && res['orders'] is List && mounted) {
        setState(() {
          _orders = res['orders'] as List;
        });
      }
    } catch (_) {}
  }

  Future<void> _submitComplaint() async {
    if (_submittingComplaint || !_complaintForm.currentState!.validate()) return;
    FocusManager.instance.primaryFocus?.unfocus();
    setState(() => _submittingComplaint = true);

    try {
      await _api.post('help/complaint', {
        'subject': _subjectController.text.trim(),
        'description': _descriptionController.text.trim(),
        if (_selectedOrderId != null) 'order_id': _selectedOrderId,
      });

      if (!mounted) return;
      _subjectController.clear();
      _descriptionController.clear();
      setState(() => _selectedOrderId = null);

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Report submitted. Support will review and reply within 24 hours.',
          ),
          backgroundColor: AppColors.primaryDark,
        ),
      );
      setState(() => _activeTab = 0);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.toString()),
          backgroundColor: AppColors.danger,
        ),
      );
    } finally {
      if (mounted) setState(() => _submittingComplaint = false);
    }
  }

  List<Map<String, dynamic>> get _filteredGroups {
    final q = _searchQuery.trim().toLowerCase();
    if (q.isEmpty) return _faqGroups;

    final results = <Map<String, dynamic>>[];
    for (final group in _faqGroups) {
      final items = (group['items'] as List? ?? [])
          .map((i) => Map<String, dynamic>.from(i as Map))
          .where((i) {
            final question = (i['q'] ?? '').toString().toLowerCase();
            final answer = (i['a'] ?? '').toString().toLowerCase();
            return question.contains(q) || answer.contains(q);
          })
          .toList();

      if (items.isNotEmpty) {
        results.add({'category': group['category'], 'items': items});
      }
    }
    return results;
  }

  @override
  Widget build(BuildContext context) {
    return MainLayout(
      showBottomNav: false,
      title: 'Help Center',
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
        children: [
          const BuyerPageHeading(
            title: 'Help & FAQ',
            description: 'Answers, customer support, and order problem reports.',
          ),
          _contactCards(),
          const SizedBox(height: 18),
          _tabSelector(),
          const SizedBox(height: 16),
          if (_activeTab == 0) ..._buildFaqSection() else ..._buildReportSection(),
        ],
      ),
    );
  }

  Widget _tabSelector() {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.sandLight,
        borderRadius: BorderRadius.circular(10),
      ),
      padding: const EdgeInsets.all(4),
      child: Row(
        children: [
          Expanded(
            child: GestureDetector(
              onTap: () => setState(() => _activeTab = 0),
              child: Container(
                padding: const EdgeInsets.symmetric(vertical: 9),
                decoration: BoxDecoration(
                  color: _activeTab == 0 ? Colors.white : Colors.transparent,
                  borderRadius: BorderRadius.circular(8),
                  boxShadow: _activeTab == 0
                      ? [
                          BoxStyle.boxShadow ??
                              const BoxShadow(
                                color: Colors.black12,
                                blurRadius: 4,
                                offset: Offset(0, 1),
                              ),
                        ]
                      : null,
                ),
                alignment: Alignment.center,
                child: Text(
                  'Questions & Answers',
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight:
                        _activeTab == 0 ? FontWeight.w700 : FontWeight.w500,
                    color: _activeTab == 0
                        ? AppColors.primaryDark
                        : AppColors.textSecondary,
                  ),
                ),
              ),
            ),
          ),
          Expanded(
            child: GestureDetector(
              onTap: () => setState(() => _activeTab = 1),
              child: Container(
                padding: const EdgeInsets.symmetric(vertical: 9),
                decoration: BoxDecoration(
                  color: _activeTab == 1 ? Colors.white : Colors.transparent,
                  borderRadius: BorderRadius.circular(8),
                  boxShadow: _activeTab == 1
                      ? [
                          BoxStyle.boxShadow ??
                              const BoxShadow(
                                color: Colors.black12,
                                blurRadius: 4,
                                offset: Offset(0, 1),
                              ),
                        ]
                      : null,
                ),
                alignment: Alignment.center,
                child: Text(
                  'Report a Problem',
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight:
                        _activeTab == 1 ? FontWeight.w700 : FontWeight.w500,
                    color: _activeTab == 1
                        ? AppColors.primaryDark
                        : AppColors.textSecondary,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _contactCards() {
    return BuyerPanel(
      padding: const EdgeInsets.all(14),
      child: Column(
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(
                  Icons.mail_outline,
                  color: AppColors.primary,
                  size: 20,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Email Us',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 2),
                    SelectableText(
                      _email,
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: AppColors.primaryDark,
                      ),
                    ),
                    Text(
                      'Replies $_responseTime',
                      style: const TextStyle(
                        fontSize: 11,
                        color: AppColors.textMuted,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const Divider(height: 20),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(
                  Icons.phone_outlined,
                  color: AppColors.primary,
                  size: 20,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Support Hotline',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 2),
                    SelectableText(
                      _phone.isNotEmpty ? _phone : 'Hotline coming soon',
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: AppColors.primaryDark,
                      ),
                    ),
                    Text(
                      _hours,
                      style: const TextStyle(
                        fontSize: 11,
                        color: AppColors.textMuted,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  List<Widget> _buildFaqSection() {
    final filtered = _filteredGroups;

    return [
      TextField(
        controller: _searchController,
        onChanged: (v) => setState(() => _searchQuery = v),
        decoration: InputDecoration(
          hintText: 'Search answers… e.g. refund, shipping, voucher',
          hintStyle: const TextStyle(fontSize: 12, color: AppColors.textMuted),
          prefixIcon: const Icon(Icons.search, size: 20, color: AppColors.textSecondary),
          suffixIcon: _searchQuery.isNotEmpty
              ? IconButton(
                  icon: const Icon(Icons.clear, size: 18),
                  onPressed: () {
                    _searchController.clear();
                    setState(() => _searchQuery = '');
                  },
                )
              : null,
          filled: true,
          fillColor: Colors.white,
          contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(color: AppColors.border),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(color: AppColors.border),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(color: AppColors.primary),
          ),
        ),
      ),
      const SizedBox(height: 16),
      if (_loading)
        const Center(
          child: Padding(
            padding: EdgeInsets.all(24),
            child: CupertinoActivityIndicator(),
          ),
        )
      else if (filtered.isEmpty)
        Container(
          padding: const EdgeInsets.all(24),
          alignment: Alignment.center,
          child: const Text(
            'No matching answers found. Try different keywords or file a report.',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
          ),
        )
      else
        ...filtered.map((group) {
          final items = (group['items'] as List? ?? []);
          return Padding(
            padding: const EdgeInsets.only(bottom: 14),
            child: BuyerPanel(
              title: group['category'] as String?,
              padding: const EdgeInsets.all(12),
              child: Column(
                children: items.map<Widget>((item) {
                  final q = item['q'] as String? ?? '';
                  final a = item['a'] as String? ?? '';
                  return Container(
                    margin: const EdgeInsets.only(bottom: 8),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      border: Border.all(color: AppColors.border.withValues(alpha: 0.6)),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Theme(
                      data: Theme.of(context).copyWith(
                        dividerColor: Colors.transparent,
                      ),
                      child: ExpansionTile(
                        tilePadding: const EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 2,
                        ),
                        childrenPadding: const EdgeInsets.fromLTRB(14, 0, 14, 12),
                        leading: const Icon(
                          Icons.help_outline,
                          size: 18,
                          color: AppColors.primary,
                        ),
                        title: Text(
                          q,
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                            color: AppColors.textPrimary,
                          ),
                        ),
                        children: [
                          Align(
                            alignment: Alignment.centerLeft,
                            child: Text(
                              a,
                              style: const TextStyle(
                                fontSize: 13,
                                height: 1.45,
                                color: AppColors.textSecondary,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  );
                }).toList(),
              ),
            ),
          );
        }),
    ];
  }

  List<Widget> _buildReportSection() {
    return [
      BuyerPanel(
        title: 'Report an Order Problem',
        description:
            'Damaged, wrong, or missing item? Send us details and our team will review it.',
        child: Form(
          key: _complaintForm,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              BuyerField.form(
                controller: _subjectController,
                decoration: const InputDecoration(
                  labelText: 'Subject',
                  hintText: 'e.g. Damaged item in order',
                ),
                validator: (v) =>
                    (v == null || v.trim().isEmpty) ? 'Subject is required' : null,
              ),
              const SizedBox(height: 12),
              if (_orders.isNotEmpty) ...[
                DropdownButtonFormField<int?>(
                  initialValue: _selectedOrderId,
                  isExpanded: true,
                  decoration: const InputDecoration(
                    labelText: 'Related Order (optional)',
                  ),
                  items: [
                    const DropdownMenuItem<int?>(
                      value: null,
                      child: Text('No specific order'),
                    ),
                    ..._orders.map((o) {
                      final id = o['id'] as int;
                      final status = '${o['status'] ?? ''}'.replaceAll('_', ' ');
                      return DropdownMenuItem<int?>(
                        value: id,
                        child: Text(
                          'Order #$id — $status',
                          overflow: TextOverflow.ellipsis,
                        ),
                      );
                    }),
                  ],
                  onChanged: (val) => setState(() => _selectedOrderId = val),
                ),
                const SizedBox(height: 12),
              ],
              BuyerField.form(
                controller: _descriptionController,
                maxLines: 4,
                maxLength: 2000,
                decoration: const InputDecoration(
                  labelText: 'What happened?',
                  hintText:
                      'Describe the problem, including item names and what you received…',
                  alignLabelWithHint: true,
                ),
                validator: (v) => (v == null || v.trim().isEmpty)
                    ? 'Description is required'
                    : null,
              ),
              const SizedBox(height: 16),
              BuyerButton.primary(
                onPressed: _submittingComplaint ? null : _submitComplaint,
                style: ElevatedButton.styleFrom(
                  minimumSize: const Size.fromHeight(44),
                ),
                child: _submittingComplaint
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CupertinoActivityIndicator(color: Colors.white),
                      )
                    : const Text('Send Report'),
              ),
            ],
          ),
        ),
      ),
    ];
  }
}

class BoxStyle {
  static BoxShadow? get boxShadow => const BoxShadow(
        color: Color(0x10000000),
        blurRadius: 4,
        offset: Offset(0, 2),
      );
}
