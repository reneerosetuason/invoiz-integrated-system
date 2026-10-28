import '../widgets/buyer_page.dart';
import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../models/address.dart';
import '../theme.dart';
import '../widgets/main_layout.dart';

class AddAddressScreen extends StatefulWidget {
  final Address? address;
  final ApiService? api;
  const AddAddressScreen({super.key, this.address, this.api});

  @override
  State<AddAddressScreen> createState() => _AddAddressScreenState();
}

class _AddAddressScreenState extends State<AddAddressScreen> {
  final _formKey = GlobalKey<FormState>();
  final _recipient = TextEditingController();
  final _phone = TextEditingController();
  final _street = TextEditingController();
  final _barangay = TextEditingController();
  final _city = TextEditingController();
  final _province = TextEditingController();
  final _postal = TextEditingController();
  bool _isDefault = false;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    final a = widget.address;
    if (a != null) {
      _recipient.text = a.recipientName;
      _phone.text = a.phone;
      _street.text = a.addressLine;
      _barangay.text = a.barangay;
      _city.text = a.city;
      _province.text = a.province;
      _postal.text = a.postalCode ?? '';
      _isDefault = a.isDefault;
    }
  }

  @override
  void dispose() {
    _recipient.dispose();
    _phone.dispose();
    _street.dispose();
    _barangay.dispose();
    _city.dispose();
    _province.dispose();
    _postal.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_busy || !_formKey.currentState!.validate()) return;
    setState(() => _busy = true);
    try {
      final api = widget.api ?? ApiService();
      final body = <String, dynamic>{
        'recipient_name': _recipient.text.trim(),
        'phone': _phone.text.trim(),
        'address_line': _street.text.trim(),
        'barangay': _barangay.text.trim(),
        'city': _city.text.trim(),
        'province': _province.text.trim(),
        'postal_code': _postal.text.trim(),
        'is_default': _isDefault,
      };
      if (widget.address == null) {
        await api.post('addresses', body);
      } else {
        await api.put('addresses/${widget.address!.id}', body);
      }
      if (!mounted) return;
      Navigator.pop(context);
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message), backgroundColor: AppColors.danger),
      );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Unable to save address.')));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return MainLayout(
      showBottomNav: false,
      title: widget.address == null ? 'Add address' : 'Edit address',
      child: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 24, 20, 32),
          children: [
            BuyerPageHeading(
              title: widget.address == null
                  ? 'A new delivery address'
                  : 'Edit delivery address',
              description: 'Add the details your rider needs to reach you.',
            ),
            BuyerPanel(
              title: 'Recipient & delivery details',
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  TextFormField(
                    controller: _recipient,
                    decoration: const InputDecoration(
                      labelText: 'Recipient Name',
                    ),
                    validator: (v) =>
                        (v == null || v.isEmpty) ? 'Required' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _phone,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(labelText: 'Phone'),
                    validator: (v) =>
                        (v == null || v.isEmpty) ? 'Required' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _street,
                    decoration: const InputDecoration(
                      labelText: 'Street / House No.',
                    ),
                    validator: (v) =>
                        (v == null || v.isEmpty) ? 'Required' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _barangay,
                    decoration: const InputDecoration(labelText: 'Barangay'),
                    validator: (v) =>
                        (v == null || v.isEmpty) ? 'Required' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _city,
                    decoration: const InputDecoration(
                      labelText: 'City / Municipality',
                    ),
                    validator: (v) =>
                        (v == null || v.isEmpty) ? 'Required' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _province,
                    decoration: const InputDecoration(labelText: 'Province'),
                    validator: (v) =>
                        (v == null || v.isEmpty) ? 'Required' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _postal,
                    decoration: const InputDecoration(
                      labelText: 'Postal Code (optional)',
                    ),
                  ),
                  const SizedBox(height: 8),
                  CheckboxListTile(
                    value: _isDefault,
                    onChanged: widget.address?.isDefault == true
                        ? null
                        : (v) => setState(() => _isDefault = v ?? false),
                    title: const Text(
                      'Set as default address',
                      style: TextStyle(fontSize: 14),
                    ),
                    controlAffinity: ListTileControlAffinity.leading,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),
            ElevatedButton(
              onPressed: _busy ? null : _save,
              style: ElevatedButton.styleFrom(
                minimumSize: const Size.fromHeight(46),
              ),
              child: _busy
                  ? const SizedBox(
                      width: 22,
                      height: 22,
                      child: CircularProgressIndicator(
                        color: Colors.white,
                        strokeWidth: 2,
                      ),
                    )
                  : const Text('Save Address'),
            ),
          ],
        ),
      ),
    );
  }
}
