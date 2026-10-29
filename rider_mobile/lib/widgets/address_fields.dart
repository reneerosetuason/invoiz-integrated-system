import 'package:flutter/material.dart';

import '../core/network/api_client.dart';
import '../core/theme/app_theme.dart';
import '../services/address_service.dart';

enum _Level { region, province, municipality, barangay }

/// Shared PSGC address entry for every rider-app application form.
class AddressFields extends StatefulWidget {
  const AddressFields({
    super.key,
    this.onChanged,
    this.apiClient,
    this.initialSelection,
  });

  final ValueChanged<AddressSelection>? onChanged;
  final ApiClient? apiClient;
  final AddressSelection? initialSelection;

  @override
  State<AddressFields> createState() => _AddressFieldsState();
}

class _AddressFieldsState extends State<AddressFields> {
  late final AddressService _service;
  late final TextEditingController _houseNo;
  late final TextEditingController _street;

  List<PsgcOption> _regions = [];
  List<PsgcOption> _provinces = [];
  List<PsgcOption> _municipalities = [];
  List<PsgcOption> _barangays = [];
  PsgcOption? _region, _province, _municipality, _barangay;
  bool _provinceNotApplicable = false;
  _Level? _loading;
  _Level? _failed;
  int _request = 0;

  @override
  void initState() {
    super.initState();
    _service = AddressService(widget.apiClient ?? ApiClient());
    _houseNo = TextEditingController(
      text: widget.initialSelection?.houseNumber,
    );
    _street = TextEditingController(text: widget.initialSelection?.street);
    _houseNo.addListener(_report);
    _street.addListener(_report);
    _load(_Level.region, restore: widget.initialSelection);
  }

  @override
  void dispose() {
    _houseNo.dispose();
    _street.dispose();
    super.dispose();
  }

  PsgcOption? _matching(List<PsgcOption> items, String? code) =>
      items.where((item) => item.code == code).firstOrNull;

  /// Each parent change invalidates all earlier requests, including errors.
  Future<void> _load(_Level level, {AddressSelection? restore}) async {
    final request = ++_request;
    setState(() {
      _loading = level;
      _failed = null;
    });
    try {
      final items = switch (level) {
        _Level.region => await _service.regions(),
        _Level.province => await _service.provinces(_region!.code),
        _Level.municipality =>
          _provinceNotApplicable
              ? await _service.regionCities(_region!.code)
              : await _service.municipalities(_province!.code),
        _Level.barangay => await _service.barangays(_municipality!.code),
      };
      if (!mounted || request != _request) return;
      setState(() {
        _loading = null;
        switch (level) {
          case _Level.region:
            _regions = items;
            _region = _matching(items, restore?.regionCode);
          case _Level.province:
            _provinces = items;
            _provinceNotApplicable = items.isEmpty;
            _province = _matching(items, restore?.province?.code);
          case _Level.municipality:
            _municipalities = items;
            _municipality = _matching(items, restore?.municipality?.code);
          case _Level.barangay:
            _barangays = items;
            _barangay = _matching(items, restore?.barangay?.code);
        }
      });
      _report();
      switch (level) {
        case _Level.region:
          if (_region != null) {
            await _load(_Level.province, restore: restore);
          }
        case _Level.province:
          if (_provinceNotApplicable || _province != null) {
            await _load(_Level.municipality, restore: restore);
          }
        case _Level.municipality:
          if (_municipality != null) {
            await _load(_Level.barangay, restore: restore);
          }
        case _Level.barangay:
          break;
      }
    } catch (_) {
      if (!mounted || request != _request) return;
      setState(() {
        _loading = null;
        _failed = level;
      });
      _report();
    }
  }

  void _select(_Level level, String code) {
    setState(() {
      _failed = null;
      switch (level) {
        case _Level.region:
          _region = _matching(_regions, code);
        case _Level.province:
          _province = _matching(_provinces, code);
        case _Level.municipality:
          _municipality = _matching(_municipalities, code);
        case _Level.barangay:
          _barangay = _matching(_barangays, code);
      }
      if (level.index < _Level.province.index) {
        _province = null;
        _provinces = [];
        _provinceNotApplicable = false;
      }
      if (level.index < _Level.municipality.index) {
        _municipality = null;
        _municipalities = [];
      }
      if (level.index < _Level.barangay.index) {
        _barangay = null;
        _barangays = [];
      }
    });
    _report();
    switch (level) {
      case _Level.region:
        _load(_Level.province);
      case _Level.province:
        _load(_Level.municipality);
      case _Level.municipality:
        _load(_Level.barangay);
      case _Level.barangay:
        break;
    }
  }

  void _report() => widget.onChanged?.call(
    AddressSelection(
      region: _region?.name,
      regionCode: _region?.code,
      province: _province,
      provinceNotApplicable: _provinceNotApplicable,
      municipality: _municipality,
      barangay: _barangay,
      houseNumber: _houseNo.text,
      street: _street.text,
    ),
  );

  InputDecoration _decoration(String label) => InputDecoration(
    labelText: label,
    border: OutlineInputBorder(
      borderRadius: BorderRadius.circular(12),
      borderSide: const BorderSide(color: AppColors.border),
    ),
    contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 16),
  );

  Widget _picker(
    _Level level,
    String label,
    List<PsgcOption> items,
    PsgcOption? selected, {
    required bool enabled,
    String? hint,
  }) => DropdownButtonFormField<String>(
    // FormField.initialValue is only read once; changing the key resets it
    // when a parent selection clears this level or a saved form is restored.
    key: ValueKey('${level.name}:${selected?.code}'),
    initialValue: selected?.code,
    isExpanded: true,
    itemHeight: null,
    menuMaxHeight: 320,
    decoration: _decoration(label).copyWith(
      suffixIcon: _loading == level
          ? const Padding(
              padding: EdgeInsets.all(16),
              child: SizedBox(
                width: 16,
                height: 16,
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
            )
          : null,
    ),
    hint: Text(
      hint ?? 'Select ${label.replaceAll(' *', '')}',
      maxLines: 1,
      overflow: TextOverflow.ellipsis,
    ),
    items: items
        .map(
          (item) => DropdownMenuItem(
            value: item.code,
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 10),
              child: Text(item.name),
            ),
          ),
        )
        .toList(),
    selectedItemBuilder: (_) => items
        .map(
          (item) => Tooltip(
            message: item.name,
            child: Align(
              alignment: Alignment.centerLeft,
              child: Text(
                item.name,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ),
        )
        .toList(),
    onChanged: enabled && _loading != level && items.isNotEmpty
        ? (code) {
            if (code != null) _select(level, code);
          }
        : null,
  );

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Row(
        children: [
          Expanded(
            flex: 2,
            child: TextField(
              controller: _houseNo,
              keyboardType: TextInputType.streetAddress,
              decoration: _decoration('House No.'),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            flex: 3,
            child: TextField(
              controller: _street,
              keyboardType: TextInputType.streetAddress,
              decoration: _decoration('Street'),
            ),
          ),
        ],
      ),
      const SizedBox(height: 12),
      _picker(_Level.region, 'Region *', _regions, _region, enabled: true),
      const SizedBox(height: 12),
      _picker(
        _Level.province,
        _provinceNotApplicable ? 'Province' : 'Province *',
        _provinces,
        _province,
        enabled: _region != null,
        hint: _provinceNotApplicable ? 'Not applicable in this region' : null,
      ),
      const SizedBox(height: 12),
      _picker(
        _Level.municipality,
        'City / Municipality *',
        _municipalities,
        _municipality,
        enabled: _province != null || _provinceNotApplicable,
      ),
      const SizedBox(height: 12),
      _picker(
        _Level.barangay,
        'Barangay *',
        _barangays,
        _barangay,
        enabled: _municipality != null,
      ),
      const SizedBox(height: 12),
      const Text(
        'Select your region, province (if applicable), city/municipality and barangay. '
        'Enter your house number and street above.',
        style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
      ),
      if (_failed != null) ...[
        const SizedBox(height: 8),
        Text(
          'Unable to load ${switch (_failed!) {
            _Level.region => 'regions',
            _Level.province => 'provinces',
            _Level.municipality => 'cities/municipalities',
            _Level.barangay => 'barangays',
          }}. Check your connection and retry.',
          style: const TextStyle(color: AppColors.textSecondary),
        ),
        TextButton.icon(
          onPressed: () => _load(_failed!, restore: widget.initialSelection),
          icon: const Icon(Icons.refresh, size: 18),
          label: const Text('Retry address lookup'),
        ),
      ],
    ],
  );
}
