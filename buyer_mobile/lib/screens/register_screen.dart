import '../widgets/buyer_picker.dart';
import '../widgets/buyer_dialog.dart';
import '../widgets/buyer_controls.dart';
import '../widgets/buyer_field.dart';
import 'package:flutter/cupertino.dart';
import '../widgets/buyer_scaffold.dart';
import '../widgets/buyer_page.dart';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import '../config.dart';
import '../services/api_service.dart';
import '../theme.dart';
import '../widgets/entrance.dart';
import 'verify_email_screen.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _lastName = TextEditingController();
  final _firstName = TextEditingController();
  final _middleInitial = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _password = TextEditingController();
  final _passwordConfirm = TextEditingController();
  final _birthday = TextEditingController();
  final _street = TextEditingController();

  bool _manualAddress = false;
  final _manualProvince = TextEditingController();
  final _manualCity = TextEditingController();
  final _manualBarangay = TextEditingController();
  String? _sex;
  DateTime? _birthDate;

  List _provinces = [];
  List _municipalities = [];
  List _barangays = [];
  String? _provinceCode;
  String? _municipalityCode;
  String? _provinceName;
  String? _municipalityName;
  String? _barangayName;
  bool _loadingAddress = false;

  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _loadProvinces();
  }

  Future<void> _loadProvinces() async {
    setState(() => _loadingAddress = true);
    try {
      final res = await http
          .get(Uri.parse('${AppConfig.psgcBaseUrl}/provinces'))
          .timeout(const Duration(seconds: 12));
      if (!mounted) return;
      final data = jsonDecode(res.body) as List;
      setState(() {
        _provinces = data;
        _loadingAddress = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loadingAddress = false;
        _manualAddress = true;
      });
    }
  }

  Future<void> _loadMunicipalities() async {
    setState(() {
      _municipalities = [];
      _barangays = [];
      _municipalityCode = null;
      _municipalityName = null;
      _barangayName = null;
    });
    final provinceCode = _provinceCode;
    try {
      final res = await http
          .get(
            Uri.parse(
              '${AppConfig.psgcBaseUrl}/provinces/$provinceCode/municipalities',
            ),
          )
          .timeout(const Duration(seconds: 12));
      if (!mounted || provinceCode != _provinceCode) return;
      final data = jsonDecode(res.body) as List;
      setState(() => _municipalities = data);
    } catch (_) {}
  }

  Future<void> _loadBarangays() async {
    setState(() {
      _barangays = [];
      _barangayName = null;
    });
    final municipalityCode = _municipalityCode;
    try {
      final res = await http
          .get(
            Uri.parse(
              '${AppConfig.psgcBaseUrl}/municipalities/$municipalityCode/barangays',
            ),
          )
          .timeout(const Duration(seconds: 12));
      if (!mounted || municipalityCode != _municipalityCode) return;
      final data = jsonDecode(res.body) as List;
      setState(() => _barangays = data);
    } catch (_) {}
  }

  Future<void> _pickBirthday() async {
    final picked = await showBuyerDatePicker(
      context: context,
      initialDate: DateTime(2000, 1, 1),
      firstDate: DateTime(1940),
      lastDate: DateTime.now(),
    );
    if (picked != null) {
      setState(() {
        _birthDate = picked;
        _birthday.text =
            '${picked.year}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}';
      });
    }
  }

  int _calcAge(DateTime b) {
    final now = DateTime.now();
    var age = now.year - b.year;
    if (now.month < b.month || (now.month == b.month && now.day < b.day)) age--;
    return age;
  }

  Future<void> _submit() async {
    if (_busy || !_formKey.currentState!.validate()) return;
    if (_manualAddress) {
      _provinceName = _manualProvince.text.trim();
      _municipalityName = _manualCity.text.trim();
      _barangayName = _manualBarangay.text.trim();
    }
    if (_birthDate != null && _calcAge(_birthDate!) < 18) {
      _show('You must be at least 18 years old to register.');
      return;
    }
    if (_birthDate == null) {
      _show('Please select your birthday.');
      return;
    }
    if (_provinceName == null ||
        _municipalityName == null ||
        _barangayName == null) {
      _show('Please complete your address (Province, Municipality, Barangay).');
      return;
    }

    setState(() => _busy = true);
    try {
      final api = ApiService();

      await api.postForm('mobile/buyer/register', {
        'last_name': _lastName.text.trim(),
        'first_name': _firstName.text.trim(),
        'middle_initial': _middleInitial.text.trim(),
        'sex': _sex ?? '',
        'email': _email.text.trim(),
        'password': _password.text,
        'password_confirmation': _passwordConfirm.text,
        'phone': _phone.text.trim(),
        'birthday': _birthday.text,
        'province': _provinceName ?? '',
        'municipality': _municipalityName ?? '',
        'barangay': _barangayName ?? '',
        'address_line': _street.text.trim(),
      });

      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Text(
            'Verification code sent to your email.',
            style: TextStyle(color: AppColors.textPrimary),
          ),
          backgroundColor: AppColors.paleTeal,
        ),
      );
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => VerifyEmailScreen(email: _email.text.trim()),
        ),
      );
    } on ApiException catch (e) {
      _show(e.message);
    } catch (_) {
      _show('Unable to connect to server.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _show(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), backgroundColor: AppColors.danger),
    );
  }

  @override
  void dispose() {
    _lastName.dispose();
    _firstName.dispose();
    _middleInitial.dispose();
    _email.dispose();
    _phone.dispose();
    _password.dispose();
    _passwordConfirm.dispose();
    _birthday.dispose();
    _street.dispose();
    _manualProvince.dispose();
    _manualCity.dispose();
    _manualBarangay.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return BuyerScaffold(
      backgroundColor: AppColors.background,
      title: 'Create Account',
      child: Align(
        alignment: Alignment.topCenter,
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 640),
          child: Form(
            key: _formKey,
            child: ListView(
              padding: EdgeInsets.zero,
              children: [
                const Padding(
                  padding: EdgeInsets.fromLTRB(20, 20, 20, 4),
                  child: BuyerPageHeading(
                    title: 'Create your account',
                    description:
                        'Join Invoiz to discover curated finds, save favorites, and track every order.',
                  ),
                ),
                FadeSlideIn(
                  delayMs: 200,
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        BuyerPanel(
                          title: 'Personal Information',
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              _field(
                                'Last name*',
                                _lastName,
                                icon: CupertinoIcons.person,
                                required: true,
                              ),
                              _field(
                                'First name*',
                                _firstName,
                                icon: CupertinoIcons.person,
                                required: true,
                              ),
                              _field(
                                'Middle initial',
                                _middleInitial,
                                icon: CupertinoIcons.person_crop_circle,
                              ),
                              const SizedBox(height: 12),
                              Text('Sex*', style: _labelStyle()),
                              const SizedBox(height: 6),
                              BuyerPicker<String>(
                                value: _sex,
                                decoration: const InputDecoration(
                                  prefixIcon: Icon(Icons.wc_outlined),
                                ),
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
                                validator: (v) =>
                                    v == null ? 'Select sex' : null,
                              ),
                              const SizedBox(height: 12),
                              _field(
                                'E-mail address*',
                                _email,
                                icon: CupertinoIcons.mail,
                                required: true,
                                keyboard: TextInputType.emailAddress,
                              ),
                              _field(
                                'Mobile Number*',
                                _phone,
                                icon: CupertinoIcons.phone,
                                required: true,
                                keyboard: TextInputType.phone,
                              ),
                              const SizedBox(height: 12),
                              InkWell(
                                onTap: _pickBirthday,
                                borderRadius: BorderRadius.circular(14),
                                child: InputDecorator(
                                  decoration: const InputDecoration(
                                    labelText: 'Date of Birth*',
                                    prefixIcon: Icon(Icons.cake_outlined),
                                  ),
                                  child: Text(
                                    _birthday.text.isEmpty
                                        ? 'Select birthday (YYYY-MM-DD)'
                                        : _birthday.text,
                                    style: TextStyle(
                                      color: _birthday.text.isEmpty
                                          ? AppColors.textSecondary
                                          : AppColors.textPrimary,
                                      fontSize: 14,
                                    ),
                                  ),
                                ),
                              ),
                              if (_birthDate != null)
                                Padding(
                                  padding: const EdgeInsets.only(top: 6, left: 4),
                                  child: Wrap(
                                    crossAxisAlignment: WrapCrossAlignment.center,
                                    spacing: 5,
                                    children: [
                                      const Icon(CupertinoIcons.checkmark_circle_fill, size: 14, color: AppColors.success),
                                      Text(
                                        'Age (auto-computed): ${_calcAge(_birthDate!)} years old',
                                        style: const TextStyle(
                                          color: AppColors.success,
                                          fontSize: 12.5,
                                          fontWeight: FontWeight.w600,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              const SizedBox(height: 14),
                              _field(
                                'Password*',
                                _password,
                                icon: CupertinoIcons.lock,
                                obscure: true,
                                required: true,
                              ),
                              _field(
                                'Confirm Password*',
                                _passwordConfirm,
                                icon: CupertinoIcons.lock,
                                obscure: true,
                                required: true,
                                confirmWith: _password,
                              ),

                              const SizedBox(height: 12),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        BuyerPanel(
                          title: 'Default Delivery Address',
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              SwitchListTile.adaptive(
                                contentPadding: EdgeInsets.zero,
                                activeColor: AppColors.primary,
                                title: const Text(
                                  'Enter address manually',
                                  style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w600),
                                ),
                                subtitle: const Text(
                                  'Enable this if your location is not found in the online lookup.',
                                  style: TextStyle(fontSize: 12),
                                ),
                                value: _manualAddress,
                                onChanged: (value) =>
                                    setState(() => _manualAddress = value),
                              ),
                              const SizedBox(height: 8),
                              if (_manualAddress) ...[
                                _field(
                                  'Province / Metro Manila',
                                  _manualProvince,
                                  required: true,
                                ),
                                _field(
                                  'City / Municipality',
                                  _manualCity,
                                  required: true,
                                ),
                                _field(
                                  'Barangay',
                                  _manualBarangay,
                                  required: true,
                                ),
                              ] else ...[
                                _addressDropdown(
                                  'Province',
                                  _provinceName,
                                  _provinces,
                                  (v) {
                                    setState(() {
                                      _provinceCode = v;
                                      _provinceName =
                                          _provinces.firstWhere(
                                                (p) => p['code'] == v,
                                              )['name']
                                              as String;
                                      _loadMunicipalities();
                                    });
                                  },
                                ),
                                _addressDropdown(
                                  'Municipality',
                                  _municipalityName,
                                  _municipalities,
                                  (v) {
                                    setState(() {
                                      _municipalityCode = v;
                                      _municipalityName =
                                          _municipalities.firstWhere(
                                                (m) => m['code'] == v,
                                              )['name']
                                              as String;
                                      _loadBarangays();
                                    });
                                  },
                                ),
                                _addressDropdown(
                                  'Barangay',
                                  _barangayName,
                                  _barangays,
                                  (v) {
                                    setState(() {
                                      _barangayName =
                                          _barangays.firstWhere(
                                                (b) => b['code'] == v,
                                              )['name']
                                              as String;
                                    });
                                  },
                                ),
                              ],
                              _field(
                                'Street / House no. / etc.',
                                _street,
                                icon: CupertinoIcons.house,
                              ),
                              if (_loadingAddress)
                                const LinearProgressIndicator(),
                            ],
                          ),
                        ),
                        const SizedBox(height: 24),
                        BuyerButton.primary(
                          onPressed: _busy ? null : _submit,
                          style: ElevatedButton.styleFrom(
                            minimumSize: const Size.fromHeight(48),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14),
                            ),
                          ),
                          child: _busy
                              ? const SizedBox(
                                  width: 22,
                                  height: 22,
                                  child: CupertinoActivityIndicator(
                                    color: Colors.white,
                                  ),
                                )
                              : const Text(
                                  'Complete Registration',
                                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
                                ),
                        ),
                        const SizedBox(height: 14),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                          decoration: BoxDecoration(
                            color: AppColors.surfaceTint.withValues(alpha: 0.6),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: AppColors.border.withValues(alpha: 0.6), width: 0.8),
                          ),
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Icon(CupertinoIcons.info_circle, size: 16, color: AppColors.primary),
                              const SizedBox(width: 8),
                              Expanded(
                                child: Text(
                                  'After submitting, a verification code will be sent to your email. Account review notice will be delivered upon completion.',
                                  style: TextStyle(
                                    color: AppColors.textSecondary,
                                    fontSize: 12,
                                    height: 1.35,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 20),
                        Wrap(
                          alignment: WrapAlignment.center,
                          crossAxisAlignment: WrapCrossAlignment.center,
                          spacing: 6,
                          runSpacing: 4,
                          children: [
                            Text(
                              'Already registered?',
                              style: TextStyle(fontSize: 13.5, color: AppColors.textSecondary),
                            ),
                            GestureDetector(
                              onTap: () => Navigator.pop(context),
                              child: Text(
                                'Sign In instead',
                                style: TextStyle(
                                  fontSize: 13.5,
                                  fontWeight: FontWeight.w700,
                                  color: AppColors.primary,
                                  decoration: TextDecoration.underline,
                                  decorationColor: AppColors.primary,
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 28),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  TextStyle _labelStyle() => TextStyle(
    fontSize: 14,
    color: AppColors.textPrimary,
    fontWeight: FontWeight.w500,
  );

  Widget _field(
    String label,
    TextEditingController controller, {
    IconData? icon,
    bool required = false,
    bool obscure = false,
    TextInputType? keyboard,
    TextEditingController? confirmWith,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: BuyerField.form(
        controller: controller,
        obscureText: obscure,
        keyboardType: keyboard,
        decoration: InputDecoration(
          labelText: label,
          prefixIcon: icon != null ? Icon(icon) : null,
        ),
        validator: (v) {
          if (required && (v == null || v.trim().isEmpty)) return 'Required';
          if (confirmWith != null && v != confirmWith.text)
            return 'Passwords do not match';
          if (label.toLowerCase().contains('e-mail') &&
              v != null &&
              v.isNotEmpty) {
            if (!v.contains('@')) return 'Enter a valid email';
          }
          return null;
        },
      ),
    );
  }

  Widget _addressDropdown(
    String label,
    String? value,
    List items,
    ValueChanged<String> onChanged,
  ) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: BuyerPicker<String>(
        value: value != null
            ? items.firstWhere(
                (i) => i['name'] == value,
                orElse: () => {'code': ''},
              )['code']
            : null,
        hint: Text(label, style: const TextStyle(color: AppColors.textMuted)),
        decoration: const InputDecoration(
          prefixIcon: Icon(CupertinoIcons.location),
        ),
        items: items
            .map<DropdownMenuItem<String>>(
              (i) => DropdownMenuItem(
                value: i['code'] as String,
                child: Text(
                  i['name'] as String,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: AppColors.textPrimary),
                ),
              ),
            )
            .toList(),
        onChanged: (v) {
          if (v != null) onChanged(v);
        },
      ),
    );
  }
}
