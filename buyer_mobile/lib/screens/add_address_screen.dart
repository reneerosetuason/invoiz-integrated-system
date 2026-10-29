import '../widgets/buyer_controls.dart';
import '../widgets/buyer_field.dart';
import '../widgets/buyer_picker.dart';
import 'package:flutter/cupertino.dart';
import '../widgets/buyer_page.dart';
import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../services/psgc_service.dart';
import '../models/address.dart';
import '../theme.dart';
import '../widgets/main_layout.dart';

class AddAddressScreen extends StatefulWidget {
  final Address? address;
  final ApiService? api;
  final PsgcService? psgc;
  const AddAddressScreen({super.key, this.address, this.api, this.psgc});

  @override
  State<AddAddressScreen> createState() => _AddAddressScreenState();
}

class _AddAddressScreenState extends State<AddAddressScreen> {
  final _formKey = GlobalKey<FormState>();
  final _recipient = TextEditingController();
  final _phone = TextEditingController();
  final _street = TextEditingController();
  final _postal = TextEditingController();

  // Used only when the PSGC lookup is unavailable, so saving still works.
  final _manualProvince = TextEditingController();
  final _manualCity = TextEditingController();
  final _manualBarangay = TextEditingController();

  late final PsgcService _psgc;

  List<PsgcItem> _regions = const [];
  List<PsgcItem> _provinceOptions = const [];
  List<PsgcItem> _cityOptions = const [];
  List<PsgcItem> _barangayOptions = const [];

  PsgcItem? _region;
  PsgcItem? _province;
  PsgcItem? _city;
  PsgcItem? _barangay;

  /// True for regions without provinces (e.g. NCR): the province picker lists
  /// cities and the city step reuses that choice.
  bool _provinceLevelIsCity = false;

  bool _manual = false;
  bool _loadingRegions = true;
  bool _loadingProvinces = false;
  bool _loadingCities = false;
  bool _loadingBarangays = false;

  bool _isDefault = false;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _psgc = widget.psgc ?? PsgcService();
    final a = widget.address;
    if (a != null) {
      _recipient.text = a.recipientName;
      _phone.text = a.phone;
      _street.text = a.addressLine;
      _postal.text = a.postalCode ?? '';
      _isDefault = a.isDefault;
      _manualProvince.text = a.province;
      _manualCity.text = a.city;
      _manualBarangay.text = a.barangay;
    }
    _loadRegions();
  }

  @override
  void dispose() {
    _recipient.dispose();
    _phone.dispose();
    _street.dispose();
    _postal.dispose();
    _manualProvince.dispose();
    _manualCity.dispose();
    _manualBarangay.dispose();
    super.dispose();
  }

  Future<void> _loadRegions() async {
    final regions = await _psgc.regions();
    if (!mounted) return;
    if (regions.isEmpty) {
      setState(() {
        _loadingRegions = false;
        _manual = true;
      });
      return;
    }
    setState(() {
      _regions = regions;
      _loadingRegions = false;
    });
    await _restoreSaved();
  }

  /// Rebuilds the Region -> Barangay chain for an address being edited.
  Future<void> _restoreSaved() async {
    final saved = widget.address;
    if (saved == null) return;
    var code = _codeOf(_regions, saved.region ?? '');
    if (code == null) {
      code = await _psgc.regionCodeFor(
        saved.province.isNotEmpty ? saved.province : saved.city,
      );
    }
    if (!mounted) return;
    final region = code == null ? null : _itemOf(_regions, code);
    if (region == null) return;
    await _selectRegion(
      region,
      restoreProvince: saved.province,
      restoreCity: saved.city,
      restoreBarangay: saved.barangay,
    );
  }

  Future<void> _selectRegion(
    PsgcItem region, {
    String? restoreProvince,
    String? restoreCity,
    String? restoreBarangay,
  }) async {
    setState(() {
      _region = region;
      _province = null;
      _provinceOptions = const [];
      _city = null;
      _cityOptions = const [];
      _barangay = null;
      _barangayOptions = const [];
      _provinceLevelIsCity = false;
      _loadingProvinces = true;
    });
    var options = await _psgc.provinces(region.code);
    if (!mounted) return;
    var isCityLevel = false;
    if (options.isEmpty) {
      options = await _psgc.regionCities(region.code);
      if (!mounted) return;
      isCityLevel = true;
    }
    setState(() {
      _provinceLevelIsCity = isCityLevel;
      _provinceOptions = options;
      _loadingProvinces = false;
    });
    final wanted = (restoreCity != null && restoreCity.isNotEmpty)
        ? restoreCity
        : (restoreProvince ?? '');
    final match = _itemOf(options, _codeOf(options, wanted) ?? '');
    if (match != null) {
      await _selectProvince(
        match,
        restoreCity: restoreCity,
        restoreBarangay: restoreBarangay,
      );
    }
  }

  Future<void> _selectProvince(
    PsgcItem province, {
    String? restoreCity,
    String? restoreBarangay,
  }) async {
    setState(() {
      _province = province;
      _city = null;
      _cityOptions = const [];
      _barangay = null;
      _barangayOptions = const [];
    });
    if (_provinceLevelIsCity) {
      // NCR-style region: this choice is already the city, so mirror it into
      // the city picker instead of leaving that step empty.
      setState(() => _cityOptions = [province]);
      await _selectCity(province, restoreBarangay: restoreBarangay);
      return;
    }
    setState(() => _loadingCities = true);
    final options = await _psgc.cities(province.code);
    if (!mounted) return;
    setState(() {
      _cityOptions = options;
      _loadingCities = false;
    });
    final match = _itemOf(options, _codeOf(options, restoreCity ?? '') ?? '');
    if (match != null) {
      await _selectCity(match, restoreBarangay: restoreBarangay);
    }
  }

  Future<void> _selectCity(PsgcItem city, {String? restoreBarangay}) async {
    setState(() {
      _city = city;
      _barangay = null;
      _barangayOptions = const [];
      _loadingBarangays = true;
    });
    final options = await _psgc.barangays(city.code);
    if (!mounted) return;
    setState(() {
      _barangayOptions = options;
      _loadingBarangays = false;
    });
    final match = _itemOf(
      options,
      _codeOf(options, restoreBarangay ?? '') ?? '',
    );
    if (match != null) setState(() => _barangay = match);
  }

  static String? _codeOf(List<PsgcItem> items, String name) {
    final wanted = name.trim().toLowerCase();
    if (wanted.isEmpty) return null;
    for (final item in items) {
      if (item.name.toLowerCase() == wanted) return item.code;
    }
    return null;
  }

  static PsgcItem? _itemOf(List<PsgcItem> items, String code) {
    if (code.isEmpty) return null;
    for (final item in items) {
      if (item.code == code) return item;
    }
    return null;
  }

  Future<void> _save() async {
    if (_busy || !_formKey.currentState!.validate()) return;
    final region = _manual ? '' : (_region?.name ?? '');
    final province = _manual
        ? _manualProvince.text.trim()
        : (_province?.name ?? '');
    final city = _manual ? _manualCity.text.trim() : (_city?.name ?? '');
    final barangay = _manual
        ? _manualBarangay.text.trim()
        : (_barangay?.name ?? '');
    setState(() => _busy = true);
    try {
      final api = widget.api ?? ApiService();
      final body = <String, dynamic>{
        'recipient_name': _recipient.text.trim(),
        'phone': _phone.text.trim(),
        'address_line': _street.text.trim(),
        'barangay': barangay,
        'city': city,
        'province': province,
        if (region.isNotEmpty) 'region': region,
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
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
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
                  BuyerField.form(
                    controller: _recipient,
                    decoration: const InputDecoration(
                      labelText: 'Recipient Name',
                    ),
                    validator: (v) =>
                        (v == null || v.isEmpty) ? 'Required' : null,
                  ),
                  const SizedBox(height: 12),
                  BuyerField.form(
                    controller: _phone,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(labelText: 'Phone'),
                    validator: (v) =>
                        (v == null || v.isEmpty) ? 'Required' : null,
                  ),
                  const SizedBox(height: 12),
                  BuyerField.form(
                    controller: _street,
                    decoration: const InputDecoration(
                      labelText: 'Street / House No.',
                    ),
                    validator: (v) =>
                        (v == null || v.isEmpty) ? 'Required' : null,
                  ),
                  const SizedBox(height: 12),
                  if (_manual) ..._manualAddressFields() else ..._pickers(),
                  BuyerField.form(
                    controller: _postal,
                    decoration: const InputDecoration(
                      labelText: 'Postal Code (optional)',
                    ),
                  ),
                  const SizedBox(height: 8),
                  BuyerSwitchRow(
                    value: _isDefault,
                    onChanged: widget.address?.isDefault == true
                        ? null
                        : (v) => setState(() => _isDefault = v ?? false),
                    title: const Text(
                      'Set as default address',
                      style: TextStyle(fontSize: 14),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),
            BuyerButton.primary(
              onPressed: _busy ? null : _save,
              style: ElevatedButton.styleFrom(
                minimumSize: const Size.fromHeight(46),
              ),
              child: _busy
                  ? const SizedBox(
                      width: 22,
                      height: 22,
                      child: CupertinoActivityIndicator(color: Colors.white),
                    )
                  : const Text('Save Address'),
            ),
          ],
        ),
      ),
    );
  }

  /// Region -> Province -> City/Municipality -> Barangay, mirroring the
  /// cascading dropdowns on the website.
  List<Widget> _pickers() => [
    _PickerField(
      key: const ValueKey('address-region'),
      label: 'Region',
      value: _region,
      items: _regions,
      loading: _loadingRegions,
      emptyHint: 'Select region…',
      onChanged: (item) => _selectRegion(item),
    ),
    const SizedBox(height: 12),
    _PickerField(
      key: const ValueKey('address-province'),
      label: 'Province',
      value: _province,
      items: _provinceOptions,
      loading: _loadingProvinces,
      emptyHint: _region == null
          ? 'Select region first'
          : (_provinceLevelIsCity ? 'Select city…' : 'Select province…'),
      onChanged: (item) => _selectProvince(item),
    ),
    const SizedBox(height: 12),
    _PickerField(
      key: const ValueKey('address-city'),
      label: 'City / Municipality',
      value: _city,
      items: _cityOptions,
      loading: _loadingCities,
      emptyHint: _province == null
          ? 'Select province first'
          : 'Select city / municipality…',
      onChanged: (item) => _selectCity(item),
    ),
    const SizedBox(height: 12),
    _PickerField(
      key: const ValueKey('address-barangay'),
      label: 'Barangay',
      value: _barangay,
      items: _barangayOptions,
      loading: _loadingBarangays,
      emptyHint: _city == null ? 'Select city first' : 'Select barangay…',
      onChanged: (item) => setState(() => _barangay = item),
    ),
    const SizedBox(height: 12),
  ];

  List<Widget> _manualAddressFields() => [
    const Padding(
      padding: EdgeInsets.only(bottom: 10),
      child: Text(
        'Address lookup is unavailable. Enter your province, city and '
        'barangay manually.',
        style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
      ),
    ),
    BuyerField.form(
      controller: _manualProvince,
      decoration: const InputDecoration(labelText: 'Province'),
      validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
    ),
    const SizedBox(height: 12),
    BuyerField.form(
      controller: _manualCity,
      decoration: const InputDecoration(labelText: 'City / Municipality'),
      validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
    ),
    const SizedBox(height: 12),
    BuyerField.form(
      controller: _manualBarangay,
      decoration: const InputDecoration(labelText: 'Barangay'),
      validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
    ),
    const SizedBox(height: 12),
  ];
}

class _PickerField extends StatelessWidget {
  final String label;
  final PsgcItem? value;
  final List<PsgcItem> items;
  final bool loading;
  final String emptyHint;
  final ValueChanged<PsgcItem> onChanged;

  const _PickerField({
    super.key,
    required this.label,
    required this.value,
    required this.items,
    required this.loading,
    required this.emptyHint,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    final hint = loading ? 'Loading…' : emptyHint;
    return BuyerPicker<String>(
      value: value?.code,
      hint: Text(hint, style: const TextStyle(color: AppColors.textMuted)),
      decoration: InputDecoration(labelText: label),
      items: items
          .map<DropdownMenuItem<String>>(
            (item) => DropdownMenuItem(
              value: item.code,
              child: Text(
                item.name,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: AppColors.textPrimary),
              ),
            ),
          )
          .toList(),
      onChanged: (code) {
        final item = _AddAddressScreenState._itemOf(items, code ?? '');
        if (item != null) onChanged(item);
      },
      validator: (_) => value == null ? 'Required' : null,
    );
  }
}
