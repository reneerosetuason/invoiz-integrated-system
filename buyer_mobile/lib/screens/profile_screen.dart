import '../widgets/buyer_picker.dart';
import '../widgets/buyer_controls.dart';
import '../widgets/buyer_field.dart';
import 'package:flutter/cupertino.dart';
import '../widgets/buyer_page.dart';
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:http/http.dart' as http;
import '../config.dart';
import '../services/api_service.dart';
import '../services/psgc_service.dart';
import '../theme.dart';
import '../widgets/auth_service_provider.dart';
import '../widgets/main_layout.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  final _formKey = GlobalKey<FormState>();
  final _lastName = TextEditingController();
  final _firstName = TextEditingController();
  final _middleInitial = TextEditingController();
  final _phone = TextEditingController();
  final _street = TextEditingController();
  final _province = TextEditingController();
  final _municipality = TextEditingController();
  final _barangay = TextEditingController();
  final _currentPassword = TextEditingController();
  final _password = TextEditingController();
  final _passwordConfirm = TextEditingController();
  final _bio = TextEditingController();

  String? _sex;
  XFile? _idImage;
  XFile? _profilePicture;
  bool _busy = false;
  bool _loaded = false;

  late final PsgcService _psgc;
  List<PsgcItem> _regions = const [];
  List<PsgcItem> _provinceOptions = const [];
  List<PsgcItem> _cityOptions = const [];
  List<PsgcItem> _barangayOptions = const [];

  PsgcItem? _region;
  PsgcItem? _selectedProvince;
  PsgcItem? _selectedCity;
  PsgcItem? _selectedBarangay;

  bool _provinceLevelIsCity = false;
  bool _manual = false;
  bool _loadingRegions = true;
  bool _loadingProvinces = false;
  bool _loadingCities = false;
  bool _loadingBarangays = false;

  @override
  void initState() {
    super.initState();
    _psgc = PsgcService();
    _loadRegions();
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

  Future<void> _restoreSaved() async {
    final user = AuthServiceProvider.of(context).user;
    if (user == null || _regions.isEmpty) return;
    final savedProv = user.province ?? _province.text.trim();
    final savedCity = user.municipality ?? _municipality.text.trim();
    final savedBarangay = user.barangay ?? _barangay.text.trim();
    if (savedProv.isEmpty && savedCity.isEmpty) return;

    var code = _codeOf(_regions, savedProv);
    if (code == null) {
      code = await _psgc.regionCodeFor(
        savedProv.isNotEmpty ? savedProv : savedCity,
      );
    }
    if (!mounted) return;
    final region = code == null ? null : _itemOf(_regions, code);
    if (region == null) return;
    await _selectRegion(
      region,
      restoreProvince: savedProv,
      restoreCity: savedCity,
      restoreBarangay: savedBarangay,
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
      _selectedProvince = null;
      _provinceOptions = const [];
      _selectedCity = null;
      _cityOptions = const [];
      _selectedBarangay = null;
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
      _selectedProvince = province;
      _selectedCity = null;
      _cityOptions = const [];
      _selectedBarangay = null;
      _barangayOptions = const [];
    });
    if (_provinceLevelIsCity) {
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
      _selectedCity = city;
      _selectedBarangay = null;
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
    if (match != null) setState(() => _selectedBarangay = match);
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

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_loaded) {
      _loaded = true;
      final user = AuthServiceProvider.of(context).user;
      if (user != null) {
        _lastName.text = user.lastName;
        _firstName.text = user.firstName;
        _middleInitial.text = user.middleInitial ?? '';
        _phone.text = user.phone ?? '';
        _street.text = user.addressLine ?? '';
        _province.text = user.province ?? '';
        _municipality.text = user.municipality ?? '';
        _barangay.text = user.barangay ?? '';
        _bio.text = user.bio ?? '';
        _sex = user.sex;
        _restoreSaved();
      }
    }
  }

  @override
  void dispose() {
    _lastName.dispose();
    _firstName.dispose();
    _middleInitial.dispose();
    _phone.dispose();
    _street.dispose();
    _province.dispose();
    _municipality.dispose();
    _barangay.dispose();
    _currentPassword.dispose();
    _password.dispose();
    _passwordConfirm.dispose();
    _bio.dispose();
    super.dispose();
  }

  Future<void> _pickProfilePicture() async {
    final picked = await ImagePicker().pickImage(
      source: ImageSource.gallery,
      maxWidth: 800,
    );
    if (picked != null && mounted) setState(() => _profilePicture = picked);
  }

  Future<void> _pickImage() async {
    final picked = await ImagePicker().pickImage(
      source: ImageSource.gallery,
      maxWidth: 1200,
    );
    if (picked != null && mounted) setState(() => _idImage = picked);
  }

  Future<void> _save() async {
    if (_busy || !_formKey.currentState!.validate()) return;
    if (_password.text.isNotEmpty && _password.text != _passwordConfirm.text) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Passwords do not match.'),
          backgroundColor: AppColors.danger,
        ),
      );
      return;
    }
    setState(() => _busy = true);
    try {
      final api = ApiService();
      final files = <http.MultipartFile>[];
      if (_profilePicture != null) {
        final bytes = await _profilePicture!.readAsBytes();
        files.add(
          http.MultipartFile.fromBytes(
            'profile_picture',
            bytes,
            filename: _profilePicture!.name,
          ),
        );
      }
      if (_idImage != null) {
        final bytes = await _idImage!.readAsBytes();
        files.add(
          http.MultipartFile.fromBytes(
            'id_image',
            bytes,
            filename: _idImage!.name,
          ),
        );
      }
      final provinceVal = _manual
          ? _province.text.trim()
          : (_selectedProvince?.name ?? _province.text.trim());
      final municipalityVal = _manual
          ? _municipality.text.trim()
          : (_selectedCity?.name ?? _municipality.text.trim());
      final barangayVal = _manual
          ? _barangay.text.trim()
          : (_selectedBarangay?.name ?? _barangay.text.trim());

      await api.postForm('profile', {
        'last_name': _lastName.text.trim(),
        'first_name': _firstName.text.trim(),
        'middle_initial': _middleInitial.text.trim(),
        'sex': _sex ?? '',
        'phone': _phone.text.trim(),
        'province': provinceVal,
        'municipality': municipalityVal,
        'barangay': barangayVal,
        'address_line': _street.text.trim(),
        'bio': _bio.text.trim(),
        if (_password.text.isNotEmpty)
          'current_password': _currentPassword.text,
        if (_password.text.isNotEmpty) 'password': _password.text,
        if (_password.text.isNotEmpty)
          'password_confirmation': _passwordConfirm.text,
      }, files: files);

      if (!mounted) return;
      await AuthServiceProvider.of(context).refreshUser();
      if (!mounted) return;
      _currentPassword.clear();
      _password.clear();
      _passwordConfirm.clear();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Profile updated.'),
          backgroundColor: AppColors.success,
        ),
      );
      Navigator.pop(context);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.toString()),
          backgroundColor: AppColors.danger,
        ),
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = AuthServiceProvider.of(context).user;
    final hasNetworkAvatar =
        user?.profilePicture != null && user!.profilePicture!.isNotEmpty;
    final hasLocalAvatar = _profilePicture != null;

    return MainLayout(
      showBottomNav: false,
      title: 'Edit Profile',
      child: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
          children: [
            const BuyerPageHeading(
              title: 'Your profile',
              description:
                  'Keep your personal and delivery details up to date.',
            ),
            Center(
              child: GestureDetector(
                onTap: _pickProfilePicture,
                child: Stack(
                  children: [
                    Container(
                      width: 72,
                      height: 72,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: Colors.transparent,
                        border: Border.all(
                          color: AppColors.primary.withValues(alpha: 0.3),
                          width: 2,
                        ),
                      ),
                      child: ClipOval(
                        child: hasLocalAvatar
                            ? (kIsWeb
                                  ? Image.network(
                                      _profilePicture!.path,
                                      fit: BoxFit.cover,
                                      width: 72,
                                      height: 72,
                                    )
                                  : Image.file(
                                      File(_profilePicture!.path),
                                      fit: BoxFit.cover,
                                      width: 72,
                                      height: 72,
                                    ))
                            : hasNetworkAvatar
                            ? Image.network(
                                AppConfig.storageUrl(user.profilePicture),
                                fit: BoxFit.cover,
                                width: 72,
                                height: 72,
                                errorBuilder: (_, __, ___) =>
                                    _avatarFallback(user),
                              )
                            : _avatarFallback(user),
                      ),
                    ),
                    Positioned(
                      right: 0,
                      bottom: 0,
                      child: Container(
                        padding: const EdgeInsets.all(5),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          shape: BoxShape.circle,
                          border: Border.all(color: AppColors.border),
                        ),
                        child: Icon(
                          Icons.camera_alt,
                          size: 16,
                          color: AppColors.primary,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 6),
            Center(
              child: Text(
                'Tap to change photo',
                style: TextStyle(fontSize: 11, color: AppColors.textSecondary),
              ),
            ),
            const SizedBox(height: 20),
            BuyerPanel(
              title: 'About you',
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  BuyerField.form(
                    controller: _bio,
                    maxLength: 200,
                    maxLines: 3,
                    decoration: const InputDecoration(
                      labelText: 'Tell us about yourself',
                      alignLabelWithHint: true,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            BuyerPanel(
              title: 'Personal information',
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  BuyerField.form(
                    controller: _lastName,
                    decoration: const InputDecoration(labelText: 'Last name*'),
                    validator: (v) =>
                        (v == null || v.isEmpty) ? 'Required' : null,
                  ),
                  const SizedBox(height: 12),
                  BuyerField.form(
                    controller: _firstName,
                    decoration: const InputDecoration(labelText: 'First name*'),
                    validator: (v) =>
                        (v == null || v.isEmpty) ? 'Required' : null,
                  ),
                  const SizedBox(height: 12),
                  BuyerField.form(
                    controller: _middleInitial,
                    decoration: const InputDecoration(
                      labelText: 'Middle initial',
                    ),
                  ),
                  const SizedBox(height: 12),
                  BuyerPicker<String>(
                    value: _sex,
                    decoration: const InputDecoration(labelText: 'Sex'),
                    items: ['male', 'female', 'other']
                        .map(
                          (s) => DropdownMenuItem(
                            value: s,
                            child: Text(
                              s.toUpperCase(),
                              style: const TextStyle(
                                color: AppColors.textPrimary,
                              ),
                            ),
                          ),
                        )
                        .toList(),
                    onChanged: (v) => setState(() => _sex = v),
                  ),
                  const SizedBox(height: 12),
                  BuyerField.form(
                    controller: _phone,
                    decoration: const InputDecoration(labelText: 'Contact No.'),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            BuyerPanel(
              title: 'Your address',
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (_manual) ...[
                    const Padding(
                      padding: EdgeInsets.only(bottom: 8),
                      child: Text(
                        'Address lookup is unavailable. Enter your province, city and barangay manually.',
                        style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
                      ),
                    ),
                    BuyerField.form(
                      controller: _province,
                      decoration: const InputDecoration(labelText: 'Province'),
                    ),
                    const SizedBox(height: 12),
                    BuyerField.form(
                      controller: _municipality,
                      decoration: const InputDecoration(
                        labelText: 'City / Municipality',
                      ),
                    ),
                    const SizedBox(height: 12),
                    BuyerField.form(
                      controller: _barangay,
                      decoration: const InputDecoration(labelText: 'Barangay'),
                    ),
                    const SizedBox(height: 12),
                  ] else ...[
                    _PsgcDropdownField(
                      key: const ValueKey('profile-region'),
                      label: 'Region',
                      value: _region,
                      items: _regions,
                      loading: _loadingRegions,
                      emptyHint: 'Select region…',
                      onChanged: (item) => _selectRegion(item),
                    ),
                    const SizedBox(height: 12),
                    _PsgcDropdownField(
                      key: const ValueKey('profile-province'),
                      label: 'Province',
                      value: _selectedProvince,
                      items: _provinceOptions,
                      loading: _loadingProvinces,
                      emptyHint: _region == null
                          ? 'Select region first'
                          : (_provinceLevelIsCity
                              ? 'Select city…'
                              : 'Select province…'),
                      onChanged: (item) => _selectProvince(item),
                    ),
                    const SizedBox(height: 12),
                    _PsgcDropdownField(
                      key: const ValueKey('profile-city'),
                      label: 'City / Municipality',
                      value: _selectedCity,
                      items: _cityOptions,
                      loading: _loadingCities,
                      emptyHint: _selectedProvince == null
                          ? 'Select province first'
                          : 'Select city / municipality…',
                      onChanged: (item) => _selectCity(item),
                    ),
                    const SizedBox(height: 12),
                    _PsgcDropdownField(
                      key: const ValueKey('profile-barangay'),
                      label: 'Barangay',
                      value: _selectedBarangay,
                      items: _barangayOptions,
                      loading: _loadingBarangays,
                      emptyHint: _selectedCity == null
                          ? 'Select city first'
                          : 'Select barangay…',
                      onChanged: (item) => setState(() => _selectedBarangay = item),
                    ),
                    const SizedBox(height: 12),
                  ],
                  BuyerField.form(
                    controller: _street,
                    decoration: const InputDecoration(
                      labelText: 'Street / House no.',
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            BuyerPanel(
              title: 'Password & security',
              description:
                  'Leave these fields blank to keep your current password.',
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  BuyerField.form(
                    controller: _currentPassword,
                    obscureText: true,
                    decoration: const InputDecoration(
                      labelText: 'Current password (for password changes)',
                    ),
                    validator: (value) =>
                        _password.text.isNotEmpty &&
                            (value == null || value.isEmpty)
                        ? 'Enter your current password.'
                        : null,
                  ),
                  const SizedBox(height: 12),
                  BuyerField.form(
                    controller: _password,
                    obscureText: true,
                    decoration: const InputDecoration(
                      labelText: 'New password (leave blank to keep)',
                    ),
                  ),
                  const SizedBox(height: 12),
                  BuyerField.form(
                    controller: _passwordConfirm,
                    obscureText: true,
                    decoration: const InputDecoration(
                      labelText: 'Confirm new password',
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            BuyerPanel(
              title: 'Identification',
              description: 'Upload a replacement document only when needed.',
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  InkWell(
                    onTap: _pickImage,
                    child: Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: AppColors.card,
                        border: Border.all(color: AppColors.border),
                        borderRadius: BorderRadius.circular(16),
                      ),
                      child: Row(
                        children: [
                          Icon(
                            _idImage == null
                                ? CupertinoIcons.doc_on_clipboard
                                : CupertinoIcons.checkmark_circle_fill,
                            color: _idImage == null
                                ? AppColors.textSecondary
                                : AppColors.success,
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              _idImage == null
                                  ? 'Upload new ID (optional)'
                                  : _idImage!.name,
                              style: const TextStyle(
                                fontSize: 14,
                                color: AppColors.textPrimary,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            const SizedBox(height: 24),
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
                  : const Text('Save Changes'),
            ),
            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }

  Widget _avatarFallback(dynamic user) {
    return Container(
      width: 72,
      height: 72,
      color: Colors.transparent,
      child: Center(
        child: Text(
          ((user?.firstName ?? '').isEmpty
                  ? '?'
                  : user.firstName.substring(0, 1))
              .toUpperCase(),
          style: TextStyle(
            color: AppColors.primary,
            fontSize: 26,
            fontWeight: FontWeight.w700,
          ),
        ),
      ),
    );
  }
}

class _PsgcDropdownField extends StatelessWidget {
  final String label;
  final PsgcItem? value;
  final List<PsgcItem> items;
  final bool loading;
  final String emptyHint;
  final ValueChanged<PsgcItem> onChanged;

  const _PsgcDropdownField({
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
        final item = _ProfileScreenState._itemOf(items, code ?? '');
        if (item != null) onChanged(item);
      },
    );
  }
}
