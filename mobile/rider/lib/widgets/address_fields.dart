import 'package:flutter/material.dart';

import '../core/network/api_client.dart';
import '../core/theme/app_theme.dart';
import '../services/address_service.dart';

/// Philippine Standard Geographic Code (PSGC) cascading address picker:
/// Province → City/Municipality → Barangay, plus House No. / Street.
/// Reports a structured [AddressSelection] via [onChanged].
class AddressFields extends StatefulWidget {
  const AddressFields({super.key, this.onChanged, this.apiClient});

  final ValueChanged<AddressSelection>? onChanged;

  /// Shared API client (injectable in tests; defaults to a live client).
  final ApiClient? apiClient;

  @override
  State<AddressFields> createState() => _AddressFieldsState();
}

class _AddressFieldsState extends State<AddressFields> {
  late final AddressService _service;

  List<PsgcOption> _provinces = [];
  List<PsgcOption> _municipalities = [];
  List<PsgcOption> _barangays = [];

  String? _provinceId;
  String? _municipalityId;
  String? _barangayId;

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
    _loadProvinces();
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
    setState(() {
      _loadingProvinces = true;
      _error = null;
    });
    try {
      final list = await _service.provinces();
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
    widget.onChanged?.call(AddressSelection(
      province: _provinceId == null
          ? null
          : _provinces
              .where((p) => '${p.id}' == _provinceId)
              .firstOrNull,
      municipality: _municipalityId == null
          ? null
          : _municipalities
              .where((m) => '${m.id}' == _municipalityId)
              .firstOrNull,
      barangay: _barangayId == null
          ? null
          : _barangays.where((b) => '${b.id}' == _barangayId).firstOrNull,
      houseNumber: _houseNo.text,
      street: _street.text,
    ));
  }

  InputDecoration _decoration(String label, {IconData? icon}) {
    return InputDecoration(
      labelText: label,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: AppColors.border),
      ),
      prefixIcon: icon == null ? null : Icon(icon, size: 20),
      contentPadding:
          const EdgeInsets.symmetric(horizontal: 14, vertical: 16),
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
        _provinceField(),
        const SizedBox(height: 12),
        _municipalityField(),
        const SizedBox(height: 12),
        _barangayField(),
        const SizedBox(height: 12),
        Row(
          children: [
            const Icon(Icons.info_outline, size: 16, color: AppColors.textSecondary),
            const SizedBox(width: 8),
            const Expanded(
              child: Text(
                'Select the Province, City/Municipality and Barangay of your address.',
                style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
              ),
            ),
          ],
        ),
        if (_error != null)
          TextButton.icon(
            onPressed: _retry,
            icon: const Icon(Icons.refresh, size: 16),
            label: Text(_error!,
                style: const TextStyle(
                    fontSize: 13, color: AppColors.textSecondary)),
          ),
      ],
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
      onChanged: (v) {
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
      decoration: _decoration('City / Municipality *',
          icon: Icons.location_city_outlined),
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
    if (_provinces.isEmpty) {
      _loadProvinces();
    } else if (_municipalities.isEmpty) {
      _loadMunicipalities(_provinceId!);
    } else {
      _loadBarangays(_municipalityId!);
    }
  }
}