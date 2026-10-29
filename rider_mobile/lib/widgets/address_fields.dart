import 'package:flutter/material.dart';

import '../core/network/api_client.dart';
import '../core/theme/app_theme.dart';
import '../services/address_service.dart';

/// Philippine Standard Geographic Code (PSGC) cascading address picker:
/// optionally Region → Province → City/Municipality → Barangay, plus
/// House No. / Street.
/// Reports a structured [AddressSelection] via [onChanged].
class AddressFields extends StatefulWidget {
  const AddressFields({
    super.key,
    this.onChanged,
    this.apiClient,
    this.showRegion = false,
  });

  final ValueChanged<AddressSelection>? onChanged;

  /// Shared API client (injectable in tests; defaults to a live client).
  final ApiClient? apiClient;

  /// Shows the first PSGC level for forms that collect rider addresses.
  final bool showRegion;

  @override
  State<AddressFields> createState() => _AddressFieldsState();
}

class _AddressFieldsState extends State<AddressFields> {
  late final AddressService _service;

  Map<String, String> _regions = {};
  List<PsgcOption> _provinces = [];
  List<PsgcOption> _municipalities = [];
  List<PsgcOption> _barangays = [];

  String? _selectedRegion;
  String? _provinceId;
  String? _municipalityId;
  String? _barangayId;

  bool _loadingRegions = false;
  bool _loadingProvinces = true;
  bool _loadingMunicipalities = false;
  bool _loadingBarangays = false;
  String? _error;

  final _houseNo = TextEditingController();
  final _street = TextEditingController();

  @override
  void initState() {
    super.initState();
    _service = AddressService(widget.apiClient ?? ApiClient());
    if (widget.showRegion) {
      _loadRegions();
    } else {
      _loadProvinces();
    }
    _houseNo.addListener(_report);
    _street.addListener(_report);
  }

  @override
  void dispose() {
    _houseNo.removeListener(_report);
    _street.removeListener(_report);
    _houseNo.dispose();
    _street.dispose();
    super.dispose();
  }

  Future<void> _loadProvinces() async {
    await _loadProvinceOptions();
  }

  Future<void> _loadRegions() async {
    setState(() {
      _loadingRegions = true;
      _error = null;
    });
    try {
      final regions = await _service.regions();
      if (!mounted) return;
      setState(() {
        _regions = regions;
        _loadingRegions = false;
        _loadingProvinces = false;
        if (regions.isEmpty) _error = 'No PSGC regions are available.';
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loadingRegions = false;
        _loadingProvinces = false;
        _error = 'Unable to load regions. Pull to retry.';
      });
    }
  }

  Future<void> _loadProvinceOptions({String? regionCode}) async {
    setState(() {
      _loadingProvinces = true;
      _error = null;
    });
    try {
      final list = await _service.provinces(regionCode: regionCode);
      if (!mounted) return;
      setState(() {
        _provinces = list;
        _loadingProvinces = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loadingProvinces = false;
        _error = 'Unable to load provinces. Pull to retry.';
      });
    }
  }

  Future<void> _loadMunicipalities(String provinceId) async {
    setState(() {
      _loadingMunicipalities = true;
      _municipalities = [];
      _barangays = [];
      _municipalityId = null;
      _barangayId = null;
    });
    _report();
    try {
      final list = await _service.municipalities(int.parse(provinceId));
      if (!mounted) return;
      setState(() {
        _municipalities = list;
        _loadingMunicipalities = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loadingMunicipalities = false;
        _error = 'Unable to load cities/municipalities.';
      });
    }
  }

  Future<void> _loadBarangays(String municipalityId) async {
    setState(() {
      _loadingBarangays = true;
      _barangays = [];
      _barangayId = null;
    });
    _report();
    try {
      final list = await _service.barangays(int.parse(municipalityId));
      if (!mounted) return;
      setState(() {
        _barangays = list;
        _loadingBarangays = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loadingBarangays = false;
        _error = 'Unable to load barangays.';
      });
    }
  }

  void _report() {
    widget.onChanged?.call(
      AddressSelection(
        province: _provinceId == null
            ? null
            : _provinces.where((p) => '${p.id}' == _provinceId).firstOrNull,
        municipality: _municipalityId == null
            ? null
            : _municipalities
                  .where((m) => '${m.id}' == _municipalityId)
                  .firstOrNull,
        barangay: _barangayId == null
            ? null
            : _barangays.where((b) => '${b.id}' == _barangayId).firstOrNull,
        region: _selectedRegion == null ? null : _regions[_selectedRegion],
        houseNumber: _houseNo.text,
        street: _street.text,
      ),
    );
  }

  InputDecoration _decoration(String label, {IconData? icon}) {
    return InputDecoration(
      labelText: label,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: AppColors.border),
      ),
      prefixIcon: icon == null ? null : Icon(icon, size: 20),
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 16),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            SizedBox(
              width: 120,
              child: TextField(
                controller: _houseNo,
                keyboardType: TextInputType.streetAddress,
                decoration: _decoration('House No.'),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: TextField(
                controller: _street,
                decoration: _decoration('Street'),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        if (widget.showRegion) ...[_regionField(), const SizedBox(height: 12)],
        _provinceField(),
        const SizedBox(height: 12),
        _municipalityField(),
        const SizedBox(height: 12),
        _barangayField(),
        const SizedBox(height: 12),
        Row(
          children: [
            const Icon(
              Icons.info_outline,
              size: 16,
              color: AppColors.textSecondary,
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                widget.showRegion
                    ? 'Select the Region, Province, City/Municipality and Barangay of your address.'
                    : 'Select the Province, City/Municipality and Barangay of your address.',
                style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
              ),
            ),
          ],
        ),
        if (_error != null)
          TextButton.icon(
            onPressed: _retry,
            icon: const Icon(Icons.refresh, size: 16),
            label: Text(
              _error!,
              style: const TextStyle(
                fontSize: 13,
                color: AppColors.textSecondary,
              ),
            ),
          ),
      ],
    );
  }

  Widget _regionField() {
    if (_loadingRegions) {
      return const Center(
        child: Padding(
          padding: EdgeInsets.all(16),
          child: SizedBox(
            width: 20,
            height: 20,
            child: CircularProgressIndicator(strokeWidth: 2),
          ),
        ),
      );
    }
    return DropdownButtonFormField<String>(
      initialValue: _selectedRegion,
      isExpanded: true,
      decoration: _decoration('Region *', icon: Icons.map_outlined),
      items: _regions.entries
          .map(
            (region) =>
                DropdownMenuItem(value: region.key, child: Text(region.value)),
          )
          .toList(),
      onChanged: (code) {
        if (code == null) return;
        setState(() {
          _selectedRegion = code;
          _provinceId = null;
          _municipalityId = null;
          _barangayId = null;
          _provinces = [];
          _municipalities = [];
          _barangays = [];
        });
        _report();
        _loadProvinceOptions(regionCode: code);
      },
    );
  }

  Widget _provinceField() {
    if (_loadingProvinces) {
      return const Center(
        child: Padding(
          padding: EdgeInsets.all(16),
          child: SizedBox(
            width: 20,
            height: 20,
            child: CircularProgressIndicator(strokeWidth: 2),
          ),
        ),
      );
    }
    return DropdownButtonFormField<String>(
      initialValue: _provinceId,
      isExpanded: true,
      decoration: _decoration('Province *', icon: Icons.map_outlined),
      items: _provinces
          .map((p) => DropdownMenuItem(value: '${p.id}', child: Text(p.name)))
          .toList(),
      onChanged: widget.showRegion && _selectedRegion == null
          ? null
          : (v) {
              if (v == null) return;
              setState(() => _provinceId = v);
              _loadMunicipalities(v);
            },
    );
  }

  Widget _municipalityField() {
    if (_loadingMunicipalities) {
      return const Center(
        child: Padding(
          padding: EdgeInsets.all(12),
          child: SizedBox(
            width: 20,
            height: 20,
            child: CircularProgressIndicator(strokeWidth: 2),
          ),
        ),
      );
    }
    return DropdownButtonFormField<String>(
      initialValue: _municipalityId,
      isExpanded: true,
      decoration: _decoration(
        'City / Municipality *',
        icon: Icons.location_city_outlined,
      ),
      items: _municipalities
          .map((m) => DropdownMenuItem(value: '${m.id}', child: Text(m.name)))
          .toList(),
      onChanged: _provinceId == null
          ? null
          : (v) {
              if (v == null) return;
              setState(() => _municipalityId = v);
              _loadBarangays(v);
            },
    );
  }

  Widget _barangayField() {
    if (_loadingBarangays) {
      return const Center(
        child: Padding(
          padding: EdgeInsets.all(12),
          child: SizedBox(
            width: 20,
            height: 20,
            child: CircularProgressIndicator(strokeWidth: 2),
          ),
        ),
      );
    }
    return DropdownButtonFormField<String>(
      initialValue: _barangayId,
      isExpanded: true,
      decoration: _decoration('Barangay *', icon: Icons.place_outlined),
      items: _barangays
          .map((b) => DropdownMenuItem(value: '${b.id}', child: Text(b.name)))
          .toList(),
      onChanged: _municipalityId == null
          ? null
          : (v) {
              if (v == null) return;
              setState(() => _barangayId = v);
              _report();
            },
    );
  }

  void _retry() {
    _error = null;
    if (widget.showRegion && _regions.isEmpty) {
      _loadRegions();
    } else if (_provinces.isEmpty && widget.showRegion) {
      if (_selectedRegion != null) {
        _loadProvinceOptions(regionCode: _selectedRegion);
      }
    } else if (_provinces.isEmpty) {
      _loadProvinces();
    } else if (_municipalities.isEmpty) {
      _loadMunicipalities(_provinceId!);
    } else {
      _loadBarangays(_municipalityId!);
    }
  }
}
