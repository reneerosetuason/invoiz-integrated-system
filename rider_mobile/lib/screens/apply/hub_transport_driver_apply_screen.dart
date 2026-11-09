import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/network/api_client.dart';
import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/input_validation.dart';
import '../../core/utils/name_validation.dart';
import '../../core/utils/vehicle_icons.dart';
import '../../services/address_service.dart';
import '../../services/application_service.dart';
import '../../services/vehicle_service.dart';
import '../../widgets/address_fields.dart';
import 'apply_screen.dart';
import 'apply_widgets.dart';

/// Dedicated Hub Transport Driver Application flow.
///
/// Steps: Personal → Vehicle Arrangement → License & Truck → Requirements → Documents → Review.
///
/// Vehicle Arrangement choice:
/// - Company-provided truck: No personal truck OR/CR documents required.
/// - Driver-owned truck: Collects truck details and requires vehicle registration (OR/CR).
class HubTransportDriverApplyScreen extends StatefulWidget {
  const HubTransportDriverApplyScreen({
    super.key,
    this.picker = const DocFilePicker(),
    this.apiClient,
  });

  final DocFilePicker picker;
  final ApiClient? apiClient;

  static const hubStepLabels = [
    'Personal',
    'Arrangement',
    'License & Truck',
    'Requirements',
    'Documents',
    'Review',
  ];

  @override
  State<HubTransportDriverApplyScreen> createState() =>
      _HubTransportDriverApplyScreenState();
}

class _DocSlot {
  const _DocSlot(this.key, this.title, this.subtitle, this.icon);
  final String key;
  final String title;
  final String subtitle;
  final IconData icon;
}

class _HubTransportDriverApplyScreenState
    extends State<HubTransportDriverApplyScreen> {
  int _step = 0;

  // Step 0 — Personal info
  final _firstName = TextEditingController();
  final _middleInitial = TextEditingController();
  final _lastName = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  AddressSelection? _addressSel;
  String? _sex;
  DateTime? _dob;

  // Email OTP verification
  final _otpCode = TextEditingController();
  String? _emailVerifiedFor;
  String? _codeSentTo;
  bool _sendingCode = false;
  bool _verifyingCode = false;
  int _resendIn = 0;
  Timer? _resendTimer;

  // Step 1 — Vehicle Arrangement (Required choice)
  // Options: 'company' (Company-provided truck) or 'driver_owned' (Driver-owned truck)
  String _vehicleArrangement = 'company';
  bool get _isDriverOwned => _vehicleArrangement == 'driver_owned';

  // Step 2 — Driver's License & Truck details
  final _licenseNo = TextEditingController();
  DateTime? _licenseExpiry;
  List<VehicleType> _vehicles = [];
  bool _loadingVehicles = true;
  String? _vehicleType;
  final _plate = TextEditingController();
  final _vehicleName = TextEditingController();
  final _vehicleModel = TextEditingController();
  final _parcelCapacity = TextEditingController();
  final _weightCapacity = TextEditingController();
  final _vehicleReg = TextEditingController();

  // Steps 4 — Staged documents
  final Map<String, ({Uint8List bytes, String filename})> _docs = {};

  // Step 5 — Review & submit
  bool _confirm = false;
  bool _submitting = false;
  bool _showErrors = false;

  static const _companyDocs = [
    _DocSlot(
      'drivers_license',
      "Driver's License",
      'Front and back, clear and readable',
      Icons.badge_outlined,
    ),
    _DocSlot(
      'valid_id',
      'Valid Government ID',
      'Government-issued ID with photo',
      Icons.credit_card_outlined,
    ),
    _DocSlot(
      'other',
      'Police or Barangay Clearance',
      'Valid clearance issued within the last 6 months',
      Icons.verified_outlined,
    ),
  ];

  static const _driverOwnedDocs = [
    _DocSlot(
      'drivers_license',
      "Driver's License",
      'Front and back, clear and readable',
      Icons.badge_outlined,
    ),
    _DocSlot(
      'valid_id',
      'Valid Government ID',
      'Government-issued ID with photo',
      Icons.credit_card_outlined,
    ),
    _DocSlot(
      'vehicle_registration',
      'OR/CR (Vehicle Registration)',
      'Official Receipt / Certificate of Registration',
      Icons.directions_car_outlined,
    ),
    _DocSlot(
      'other',
      'Police or Barangay Clearance',
      'Valid clearance issued within the last 6 months',
      Icons.verified_outlined,
    ),
  ];

  static final _emailRegex = RegExp(
    r'^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$',
  );

  @override
  void initState() {
    super.initState();
    _email.addListener(_onEmailChanged);
    _loadVehicles();
  }

  void _onEmailChanged() {
    if (_emailVerifiedFor != null &&
        _email.text.trim().toLowerCase() != _emailVerifiedFor!.toLowerCase()) {
      setState(() {
        _emailVerifiedFor = null;
        _codeSentTo = null;
        _otpCode.clear();
      });
    }
  }

  Future<void> _loadVehicles() async {
    setState(() => _loadingVehicles = true);
    try {
      final service = VehicleService(widget.apiClient ?? ApiClient());
      final list = await service.getActive(applicationType: 'hub_transport_driver');
      if (!mounted) return;
      setState(() {
        _vehicles = list.isEmpty ? _fallbackHubVehicles : list;
        _vehicleType = _vehicles.isNotEmpty ? _vehicles.first.name : 'truck';
        _loadingVehicles = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _vehicles = _fallbackHubVehicles;
        _vehicleType = _fallbackHubVehicles.first.name;
        _loadingVehicles = false;
      });
    }
  }

  List<VehicleType> get _fallbackHubVehicles => const [
        VehicleType(name: 'van', label: 'Van', capacityKg: 500),
        VehicleType(name: 'truck', label: 'Truck', capacityKg: 2000),
      ];

  int get _age => _dob == null ? 0 : AgeValidation.ageOn(_dob!);
  bool get _isEmailVerified =>
      _emailVerifiedFor != null &&
      _emailVerifiedFor!.toLowerCase() == _email.text.trim().toLowerCase();

  List<_DocSlot> get _requiredDocs =>
      _isDriverOwned ? _driverOwnedDocs : _companyDocs;

  void _pruneStaleDocs() {
    final validKeys = _requiredDocs.map((d) => d.key).toSet();
    _docs.removeWhere((key, _) => !validKeys.contains(key));
  }

  String? get _firstNameError {
    if (!_showErrors) return null;
    if (_firstName.text.trim().isEmpty) return 'Please enter your first name.';
    if (!NameValidation.isValidPerson(_firstName.text, maxLength: 50, minLength: 2)) {
      return 'Please enter a valid first name.';
    }
    return null;
  }

  String? get _lastNameError {
    if (!_showErrors) return null;
    if (_lastName.text.trim().isEmpty) return 'Please enter your last name.';
    if (!NameValidation.isValidPerson(_lastName.text, maxLength: 50, minLength: 2)) {
      return 'Please enter a valid last name.';
    }
    return null;
  }

  String? get _middleInitialError {
    if (!_showErrors || _middleInitial.text.trim().isEmpty) return null;
    return NameValidation.isValidPerson(_middleInitial.text, maxLength: 5)
        ? null
        : 'Please enter a valid middle initial.';
  }

  String? get _phoneError =>
      _showErrors ? PhilippineMobile.validate(_phone.text) : null;

  String? get _emailError {
    if (!_showErrors) return null;
    final email = _email.text.trim();
    if (email.isEmpty) return 'Please enter your email address.';
    if (!_emailRegex.hasMatch(email)) {
      return 'Please enter a valid email address.';
    }
    return null;
  }

  String? get _licenseError =>
      _showErrors ? LicenseNumber.validate(_licenseNo.text) : null;

  String? get _plateError =>
      _showErrors && _isDriverOwned ? LicensePlate.validate(_plate.text) : null;

  String? get _vehicleRegistrationError =>
      _showErrors && _isDriverOwned
          ? VehicleRegistration.validate(_vehicleReg.text)
          : null;

  void _snack(String msg) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
  }

  void _next() {
    setState(() => _showErrors = true);
    final error = _validateStep();
    if (error != null) {
      _snack(error);
      return;
    }
    FocusScope.of(context).unfocus();
    if (_step < 5) {
      setState(() {
        _step++;
        _showErrors = false;
      });
    }
  }

  void _back() {
    FocusScope.of(context).unfocus();
    if (_step > 0) {
      setState(() {
        _step--;
        _showErrors = false;
      });
    }
  }

  String? _validateStep() {
    switch (_step) {
      case 0:
        return _validatePersonal();
      case 1:
        return null; // Arrangement is always selected ('company' or 'driver_owned')
      case 2:
        return _validateLicenseAndTruck();
      case 4:
        return _validateDocs();
      default:
        return null;
    }
  }

  String? _validatePersonal() {
    if (_firstName.text.trim().isEmpty) return 'Please enter your first name.';
    if (!NameValidation.isValidPerson(_firstName.text, maxLength: 50, minLength: 2)) {
      return 'Please enter a valid first name.';
    }
    if (_middleInitial.text.trim().isNotEmpty &&
        !NameValidation.isValidPerson(_middleInitial.text, maxLength: 5)) {
      return 'Please enter a valid middle initial.';
    }
    if (_lastName.text.trim().isEmpty) return 'Please enter your last name.';
    if (!NameValidation.isValidPerson(_lastName.text, maxLength: 50, minLength: 2)) {
      return 'Please enter a valid last name.';
    }
    if (!_emailRegex.hasMatch(_email.text.trim())) {
      return 'Please enter a valid email address.';
    }
    if (!_isEmailVerified) {
      return 'Please verify your email address first.';
    }
    final phoneError = PhilippineMobile.validate(_phone.text);
    if (phoneError != null) return phoneError;
    if (_sex == null) return 'Please select your sex.';
    if (_dob == null) return 'Please select your date of birth.';
    if (_age < 18) return 'You must be at least 18 years old to apply.';
    if (_addressSel?.complete != true) {
      return 'Please complete your Region, Province (if applicable), City/Municipality and Barangay.';
    }
    return null;
  }

  String? _validateLicenseAndTruck() {
    final licenseError = LicenseNumber.validate(_licenseNo.text);
    if (licenseError != null) return licenseError;
    if (_licenseExpiry == null) {
      return 'Please select the license expiry date.';
    }
    if (_licenseExpiry!.isBefore(DateTime.now())) {
      return 'License expiry date must be today or in the future.';
    }

    if (_isDriverOwned) {
      if (_loadingVehicles || _vehicleType == null) {
        return 'Please wait while vehicle types load.';
      }
      final plateError = LicensePlate.validate(_plate.text);
      if (plateError != null) return plateError;
      final registrationError = VehicleRegistration.validate(_vehicleReg.text);
      if (registrationError != null) return registrationError;
      final parcelCapacity = int.tryParse(_parcelCapacity.text.trim());
      if (parcelCapacity == null || parcelCapacity < 1) {
        return 'Please enter the truck parcel capacity (at least 1).';
      }
      final weightCapacity = double.tryParse(_weightCapacity.text.trim());
      if (weightCapacity == null || weightCapacity <= 0) {
        return 'Please enter the truck weight capacity in kilograms.';
      }
    }

    return null;
  }

  String? _validateDocs() {
    final missing = _requiredDocs.where((d) => !_docs.containsKey(d.key)).toList();
    if (missing.isNotEmpty) {
      return 'Please upload: ${missing.map((d) => d.title).join(', ')}.';
    }
    return null;
  }

  ApplicationService get _appService =>
      ApplicationService(widget.apiClient ?? ApiClient());

  Future<void> _sendCode({bool resend = false}) async {
    final email = _email.text.trim();
    if (!_emailRegex.hasMatch(email)) {
      _snack('Please enter a valid email address first.');
      return;
    }
    if (_sendingCode) return;
    setState(() => _sendingCode = true);
    try {
      final name = '${_firstName.text.trim()} ${_lastName.text.trim()}'.trim();
      final message = resend
          ? await _appService.resendEmailCode(email: email)
          : await _appService.requestEmailCode(
              email: email,
              name: name.isEmpty ? null : name,
            );
      if (!mounted) return;
      setState(() {
        _codeSentTo = email;
        _otpCode.clear();
      });
      _startResendCooldown();
      _snack(message);
    } on ApiException catch (e) {
      if (mounted) _snack(e.message);
    } catch (_) {
      if (mounted) _snack('Unable to connect. Please try again.');
    } finally {
      if (mounted) setState(() => _sendingCode = false);
    }
  }

  void _startResendCooldown() {
    _resendTimer?.cancel();
    setState(() => _resendIn = 60);
    _resendTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) {
        timer.cancel();
        return;
      }
      if (_resendIn <= 1) {
        timer.cancel();
        setState(() => _resendIn = 0);
      } else {
        setState(() => _resendIn--);
      }
    });
  }

  Future<void> _verifyCode() async {
    final email = _email.text.trim();
    final code = _otpCode.text.trim();
    if (code.length != 6) {
      _snack('Please enter the 6-digit verification code.');
      return;
    }
    if (_verifyingCode) return;
    setState(() => _verifyingCode = true);
    try {
      await _appService.verifyEmailCode(email: email, code: code);
      if (!mounted) return;
      setState(() => _emailVerifiedFor = email);
      _snack('Email verified successfully.');
    } on ApiException catch (e) {
      if (mounted) _snack(e.message);
    } catch (_) {
      if (mounted) _snack('Unable to connect. Please try again.');
    } finally {
      if (mounted) setState(() => _verifyingCode = false);
    }
  }

  Future<void> _pickDoc(String key) async {
    try {
      final picked = await widget.picker.pickSingle(
        ApplyScreen.supportedDocExtensions,
      );
      if (picked == null || !mounted) return;
      final bytes = await picked.readBytes();
      if (bytes.length > ApplyScreen.maxDocBytes) {
        _snack('File too large. Maximum size is 5 MB.');
        return;
      }
      setState(() {
        _docs[key] = (bytes: bytes, filename: picked.name);
      });
    } catch (e) {
      _snack('Unable to open the file picker. Please try again.');
    }
  }

  Future<void> _pickDob() async {
    final now = DateTime.now();
    final initial = _dob ?? DateTime(now.year - 25, now.month, now.day);
    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(now.year - 80),
      lastDate: DateTime(now.year - 18, now.month, now.day),
    );
    if (picked != null && mounted) {
      setState(() => _dob = picked);
    }
  }

  Future<void> _pickLicenseExpiry() async {
    final now = DateTime.now();
    final initial = _licenseExpiry ?? DateTime(now.year + 2, now.month, now.day);
    final picked = await showDatePicker(
      context: context,
      initialDate: initial.isBefore(now) ? now : initial,
      firstDate: now,
      lastDate: DateTime(now.year + 10),
    );
    if (picked != null && mounted) {
      setState(() => _licenseExpiry = picked);
    }
  }

  Future<void> _submit() async {
    setState(() => _showErrors = true);
    if (!_confirm) {
      _snack('Please accept the Terms & Conditions before submitting.');
      return;
    }
    final error = _validateDocs();
    if (error != null) {
      _snack(error);
      return;
    }

    setState(() => _submitting = true);
    try {
      final middle = _middleInitial.text.trim();
      final name = middle.isEmpty
          ? '${_firstName.text.trim()} ${_lastName.text.trim()}'
          : '${_firstName.text.trim()} $middle. ${_lastName.text.trim()}';

      final result = await _appService.submit(
        name: name,
        email: _email.text.trim(),
        phone: PhilippineMobile.normalize(_phone.text),
        address: _addressSel?.composed ?? '',
        vehicleType: _isDriverOwned ? (_vehicleType ?? 'truck') : '',
        licensePlate: _isDriverOwned ? LicensePlate.normalize(_plate.text) : '',
        licenseNumber: LicenseNumber.normalize(_licenseNo.text),
        licenseExpiry: _licenseExpiry == null
            ? ''
            : '${_licenseExpiry!.year.toString().padLeft(4, '0')}-'
                  '${_licenseExpiry!.month.toString().padLeft(2, '0')}-'
                  '${_licenseExpiry!.day.toString().padLeft(2, '0')}',
        vehicleRegistration: _isDriverOwned
            ? VehicleRegistration.normalize(_vehicleReg.text)
            : '',
        applicationType: 'hub_transport_driver',
        vehicleName: _isDriverOwned ? _vehicleName.text.trim() : null,
        vehicleModel: _isDriverOwned ? _vehicleModel.text.trim() : null,
        parcelCapacity: _isDriverOwned && _parcelCapacity.text.trim().isNotEmpty
            ? int.tryParse(_parcelCapacity.text.trim())
            : null,
        capacityKg: _isDriverOwned && _weightCapacity.text.trim().isNotEmpty
            ? double.tryParse(_weightCapacity.text.trim())
            : null,
        riderType: 'full_time',
        vehicleOwnership: _isDriverOwned ? 'driver_owned' : 'company',
        acceptedTerms: _confirm,
        documents: _docs,
        middleInitial: _middleInitial.text.trim(),
        sex: _sex,
        birthday: _dob == null
            ? null
            : '${_dob!.year.toString().padLeft(4, '0')}-'
                  '${_dob!.month.toString().padLeft(2, '0')}-'
                  '${_dob!.day.toString().padLeft(2, '0')}',
        houseNumber: _addressSel?.houseNumber ?? '',
        street: _addressSel?.street ?? '',
        region: _addressSel?.region ?? '',
        regionCode: _addressSel?.regionCode ?? '',
        province: _addressSel?.province?.name ?? '',
        provinceCode: _addressSel?.province?.code ?? '',
        municipality: _addressSel?.municipality?.name ?? '',
        municipalityCode: _addressSel?.municipality?.code ?? '',
        barangay: _addressSel?.barangay?.name ?? '',
        barangayCode: _addressSel?.barangay?.code ?? '',
      );

      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => ApplySuccessScreen(
            result: result,
            email: _email.text.trim(),
          ),
        ),
      );
    } on ApiException catch (e) {
      if (mounted) _snack(e.message.isNotEmpty ? e.message : 'Submission failed.');
    } catch (_) {
      if (mounted) _snack('Submission failed. Please try again.');
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  void dispose() {
    _email.removeListener(_onEmailChanged);
    _resendTimer?.cancel();
    _firstName.dispose();
    _middleInitial.dispose();
    _lastName.dispose();
    _email.dispose();
    _phone.dispose();
    _otpCode.dispose();
    _licenseNo.dispose();
    _plate.dispose();
    _vehicleName.dispose();
    _vehicleModel.dispose();
    _parcelCapacity.dispose();
    _weightCapacity.dispose();
    _vehicleReg.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Apply as Hub Transport Driver'),
        backgroundColor: Colors.white,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
      ),
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 4),
              child: ApplyProgressIndicator(
                step: _step,
                labels: HubTransportDriverApplyScreen.hubStepLabels,
              ),
            ),
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
                child: AnimatedSwitcher(
                  duration: const Duration(milliseconds: 200),
                  child: _buildStep(),
                ),
              ),
            ),
            Container(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
              decoration: const BoxDecoration(
                color: Colors.white,
                border: Border(top: BorderSide(color: AppColors.border)),
              ),
              child: _buildNavBar(),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildStep() {
    switch (_step) {
      case 0:
        return _personalStep(key: const ValueKey(0));
      case 1:
        return _arrangementStep(key: const ValueKey(1));
      case 2:
        return _licenseAndTruckStep(key: const ValueKey(2));
      case 3:
        return _requirementsStep(key: const ValueKey(3));
      case 4:
        return _documentsStep(key: const ValueKey(4));
      default:
        return _reviewStep(key: const ValueKey(5));
    }
  }

  Widget _buildNavBar() {
    if (_step == 5) {
      return ApplyNavBar(
        nextLabel: 'Submit Application',
        onBack: _submitting ? null : _back,
        onNext: _submit,
        nextLoading: _submitting,
      );
    }
    return ApplyNavBar(
      nextLabel: 'Continue',
      onBack: _step == 0 ? null : _back,
      onNext: _next,
    );
  }

  // ── Step 0: Personal ────────────────────────────────────────────────
  Widget _personalStep({required Key key}) {
    return Column(
      key: key,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const ApplyStepHeader(
          title: 'Apply as Hub Transport Driver',
          subtitle: 'Tell us about yourself',
        ),
        const SizedBox(height: 20),
        Row(
          children: [
            Expanded(
              child: _field(
                _firstName,
                'First Name *',
                Icons.person_outline,
                maxLength: 50,
                errorText: _firstNameError,
                capitalization: TextCapitalization.words,
              ),
            ),
            const SizedBox(width: 12),
            SizedBox(
              width: 84,
              child: _field(
                _middleInitial,
                'M.I.',
                null,
                maxLength: 5,
                errorText: _middleInitialError,
                capitalization: TextCapitalization.characters,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _field(
                _lastName,
                'Last Name *',
                null,
                maxLength: 50,
                errorText: _lastNameError,
                capitalization: TextCapitalization.words,
              ),
            ),
          ],
        ),
        _field(
          _email,
          'Email Address *',
          Icons.email_outlined,
          keyboard: TextInputType.emailAddress,
          maxLength: 254,
          errorText: _emailError,
        ),
        _emailVerificationSection(),
        _field(
          _phone,
          'Phone Number *  (+639 / 09)',
          Icons.phone_outlined,
          keyboard: TextInputType.phone,
          maxLength: 16,
          errorText: _phoneError,
          hint: '09171234567',
        ),
        const Text(
          'Sex *',
          style: TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w600,
            color: AppColors.textPrimary,
          ),
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            _sexChip('male', 'Male'),
            const SizedBox(width: 8),
            _sexChip('female', 'Female'),
          ],
        ),
        const SizedBox(height: 16),
        _dobField(),
        const Text(
          'Address *',
          style: TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w600,
            color: AppColors.textPrimary,
          ),
        ),
        const SizedBox(height: 8),
        AddressFields(
          onChanged: (sel) => setState(() => _addressSel = sel),
          apiClient: widget.apiClient,
          initialSelection: _addressSel,
        ),
      ],
    );
  }

  Widget _emailVerificationSection() {
    final email = _email.text.trim();
    if (_isEmailVerified) {
      return Padding(
        padding: const EdgeInsets.only(bottom: 16),
        child: Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: AppColors.success.withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: AppColors.success.withValues(alpha: 0.4)),
          ),
          child: Row(
            children: [
              const Icon(Icons.check_circle, size: 20, color: AppColors.success),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  'Email verified ($_emailVerifiedFor)',
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: AppColors.success,
                  ),
                ),
              ),
            ],
          ),
        ),
      );
    }

    final codePending = _codeSentTo != null && _codeSentTo == email && email.isNotEmpty;

    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: AppColors.card,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: AppColors.border),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (codePending) ...[
              Text(
                'We sent a 6-digit verification code to:\n$email',
                style: const TextStyle(fontSize: 13, color: AppColors.textSecondary),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _otpCode,
                keyboardType: TextInputType.number,
                maxLength: 6,
                inputFormatters: [
                  FilteringTextInputFormatter.digitsOnly,
                  LengthLimitingTextInputFormatter(6),
                ],
                decoration: const InputDecoration(
                  labelText: 'Verification Code',
                  hintText: 'Enter 6-digit code',
                  counterText: '',
                ),
              ),
              const SizedBox(height: 10),
              SizedBox(
                height: 48,
                child: ElevatedButton(
                  onPressed: _verifyingCode ? null : _verifyCode,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primary,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                  child: _verifyingCode
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                        )
                      : const Text('Verify Email', style: TextStyle(fontWeight: FontWeight.w700)),
                ),
              ),
              const SizedBox(height: 8),
              Center(
                child: TextButton(
                  onPressed: _resendIn > 0 ? null : () => _sendCode(resend: true),
                  child: Text(_resendIn > 0 ? 'Resend in ${_resendIn}s' : 'Resend Code'),
                ),
              ),
            ] else ...[
              const Text(
                'Verify your email to continue your application.',
                style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
              ),
              const SizedBox(height: 12),
              SizedBox(
                height: 48,
                child: OutlinedButton.icon(
                  onPressed: _sendingCode ? null : _sendCode,
                  icon: const Icon(Icons.send_outlined, size: 18),
                  label: _sendingCode
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Text('Send Verification Code'),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _dobField() {
    final d = _dob;
    final text = d == null
        ? 'Select date'
        : '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')} (Age: $_age)';
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: InkWell(
        onTap: _pickDob,
        borderRadius: BorderRadius.circular(12),
        child: InputDecorator(
          decoration: const InputDecoration(
            labelText: 'Date of Birth *',
            prefixIcon: Icon(Icons.cake_outlined, size: 20),
          ),
          child: Text(text),
        ),
      ),
    );
  }

  Widget _sexChip(String value, String label) {
    final selected = _sex == value;
    return Expanded(
      child: InkWell(
        onTap: () => setState(() => _sex = value),
        borderRadius: BorderRadius.circular(10),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 12),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(10),
            color: selected ? AppTheme.primary.withValues(alpha: 0.1) : Colors.white,
            border: Border.all(
              color: selected ? AppTheme.primary : AppColors.border,
              width: selected ? 2 : 1,
            ),
          ),
          child: Center(
            child: Text(
              label,
              style: TextStyle(
                fontWeight: FontWeight.w700,
                color: selected ? AppTheme.primary : AppColors.textPrimary,
              ),
            ),
          ),
        ),
      ),
    );
  }

  // ── Step 1: Vehicle Arrangement (REQUIRED CHOICE) ───────────────────
  Widget _arrangementStep({required Key key}) {
    return Column(
      key: key,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const ApplyStepHeader(
          title: 'Vehicle Arrangement',
          subtitle: 'Choose your truck arrangement for Hub Transport',
        ),
        const SizedBox(height: 16),

        // Choice 1: Company-provided truck
        SelectableCard(
          selected: _vehicleArrangement == 'company',
          onTap: () {
            setState(() {
              _vehicleArrangement = 'company';
              _pruneStaleDocs();
            });
          },
          child: const Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.business_outlined, size: 30, color: Color(0xFF16697A)),
              SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Company-provided truck',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    SizedBox(height: 4),
                    Text(
                      'Operate company-owned 4-wheel fleet trucks. No personal truck OR/CR documents required. A company truck will be assigned upon admin approval.',
                      style: TextStyle(
                        fontSize: 13,
                        color: AppColors.textSecondary,
                        height: 1.4,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),

        const SizedBox(height: 14),

        // Choice 2: Driver-owned truck
        SelectableCard(
          selected: _vehicleArrangement == 'driver_owned',
          onTap: () {
            setState(() {
              _vehicleArrangement = 'driver_owned';
              _pruneStaleDocs();
            });
          },
          child: const Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.directions_car_filled_outlined, size: 30, color: Color(0xFF16697A)),
              SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Driver-owned truck',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    SizedBox(height: 4),
                    Text(
                      'Use your personal 4-wheel truck (van, L300, light truck). Collect truck details and required vehicle documents for admin review.',
                      style: TextStyle(
                        fontSize: 13,
                        color: AppColors.textSecondary,
                        height: 1.4,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  // ── Step 2: License & Truck Details ─────────────────────────────────
  Widget _licenseAndTruckStep({required Key key}) {
    return Column(
      key: key,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const ApplyStepHeader(
          title: "Driver's License & Truck Details",
          subtitle: 'Enter your professional license and vehicle specifications',
        ),
        const SizedBox(height: 16),

        _field(
          _licenseNo,
          'License Number *',
          Icons.badge_outlined,
          maxLength: 20,
          errorText: _licenseError,
          hint: 'N01-23-456789',
          capitalization: TextCapitalization.characters,
        ),
        _licenseExpiryField(),

        if (!_isDriverOwned) ...[
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: const Color(0xFF16697A).withValues(alpha: 0.08),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFF16697A).withValues(alpha: 0.2)),
            ),
            child: const Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.info_outline, size: 20, color: Color(0xFF16697A)),
                SizedBox(width: 10),
                Expanded(
                  child: Text(
                    'Company-provided truck selected: No personal truck details or OR/CR registration are required. An available truck will be assigned to you in Fleet Management upon approval.',
                    style: TextStyle(fontSize: 13, color: Color(0xFF16697A), height: 1.4),
                  ),
                ),
              ],
            ),
          ),
        ] else ...[
          const SizedBox(height: 8),
          const Text(
            'Four-Wheel Vehicle Type *',
            style: TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w600,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 8),
          if (_loadingVehicles)
            const LinearProgressIndicator()
          else
            ..._vehicles.map(
              (v) => Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: SelectableCard(
                  selected: _vehicleType == v.name,
                  onTap: () => setState(() => _vehicleType = v.name),
                  child: Row(
                    children: [
                      Icon(vehicleIconFor(v.name), size: 28, color: AppTheme.primary),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              v.label,
                              style: const TextStyle(
                                fontSize: 15,
                                fontWeight: FontWeight.w700,
                                color: AppColors.textPrimary,
                              ),
                            ),
                            Text(
                              '${v.capacityKg.toStringAsFixed(0)} kg capacity',
                              style: const TextStyle(
                                fontSize: 12,
                                color: AppColors.textSecondary,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          const SizedBox(height: 12),
          _field(
            _plate,
            'License Plate *',
            Icons.confirmation_number_outlined,
            maxLength: 20,
            errorText: _plateError,
            hint: 'ABC 1234',
            capitalization: TextCapitalization.characters,
          ),
          _field(
            _vehicleModel,
            'Brand / Model (optional)',
            Icons.directions_car_outlined,
            maxLength: 100,
            hint: 'e.g. Isuzu Elf / Mitsubishi L300',
          ),
          _field(
            _parcelCapacity,
            'Parcel Capacity *',
            Icons.inventory_2_outlined,
            keyboard: TextInputType.number,
            maxLength: 6,
            hint: 'e.g. 100',
          ),
          _field(
            _weightCapacity,
            'Weight Capacity (kg) *',
            Icons.scale_outlined,
            keyboard: const TextInputType.numberWithOptions(decimal: true),
            maxLength: 10,
            hint: 'e.g. 1500',
          ),
          _field(
            _vehicleReg,
            'Vehicle Registration (OR/CR Number) *',
            Icons.description_outlined,
            maxLength: 100,
            errorText: _vehicleRegistrationError,
            hint: 'Official Receipt / Certificate of Registration',
            capitalization: TextCapitalization.characters,
          ),
        ],
      ],
    );
  }

  Widget _licenseExpiryField() {
    final d = _licenseExpiry;
    final text = d == null
        ? 'Select date'
        : '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: InkWell(
        onTap: _pickLicenseExpiry,
        borderRadius: BorderRadius.circular(12),
        child: InputDecorator(
          decoration: const InputDecoration(
            labelText: 'License Expiry Date *',
            prefixIcon: Icon(Icons.event_outlined, size: 20),
          ),
          child: Text(text),
        ),
      ),
    );
  }

  // ── Step 3: Requirements ────────────────────────────────────────────
  Widget _requirementsStep({required Key key}) {
    return Column(
      key: key,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const ApplyStepHeader(
          title: 'Requirements for Hub Transport Drivers',
          subtitle: 'Ensure you have the required documents ready before proceeding',
        ),
        const SizedBox(height: 16),
        for (final doc in _requiredDocs) ...[
          Container(
            margin: const EdgeInsets.only(bottom: 12),
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: AppColors.border),
            ),
            child: Row(
              children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: const Color(0xFF16697A).withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Icon(doc.icon, color: const Color(0xFF16697A), size: 24),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        doc.title,
                        style: const TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                          color: AppColors.textPrimary,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        doc.subtitle,
                        style: const TextStyle(
                          fontSize: 12,
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ],
    );
  }

  // ── Step 4: Documents ───────────────────────────────────────────────
  Widget _documentsStep({required Key key}) {
    return Column(
      key: key,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const ApplyStepHeader(
          title: 'Upload Documents',
          subtitle: 'Upload clear photos or scans of the required documents',
        ),
        const SizedBox(height: 16),
        for (final doc in _requiredDocs) ...[
          _docUploadCard(doc),
          const SizedBox(height: 12),
        ],
      ],
    );
  }

  Widget _docUploadCard(_DocSlot slot) {
    final attached = _docs[slot.key];
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: attached != null ? AppColors.success : AppColors.border,
          width: attached != null ? 1.5 : 1,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                slot.icon,
                color: attached != null ? AppColors.success : AppColors.textSecondary,
                size: 22,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  slot.title,
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
              ),
              if (attached != null)
                const Icon(Icons.check_circle, color: AppColors.success, size: 20),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            slot.subtitle,
            style: const TextStyle(fontSize: 12, color: AppColors.textSecondary),
          ),
          const SizedBox(height: 12),
          if (attached != null) ...[
            Row(
              children: [
                Expanded(
                  child: Text(
                    attached.filename,
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: AppColors.textPrimary,
                    ),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                TextButton(
                  onPressed: () => _pickDoc(slot.key),
                  child: const Text('Change'),
                ),
                IconButton(
                  onPressed: () => setState(() => _docs.remove(slot.key)),
                  icon: const Icon(Icons.close, size: 18),
                  tooltip: 'Remove',
                ),
              ],
            ),
          ] else ...[
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: () => _pickDoc(slot.key),
                icon: const Icon(Icons.file_upload_outlined, size: 18),
                label: const Text('Upload Document'),
              ),
            ),
          ],
        ],
      ),
    );
  }

  // ── Step 5: Review ──────────────────────────────────────────────────
  Widget _reviewStep({required Key key}) {
    return Column(
      key: key,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const ApplyStepHeader(
          title: 'Review Application',
          subtitle: 'Please check your information before submitting',
        ),
        const SizedBox(height: 16),

        ReviewSection(
          title: 'ROLE & VEHICLE ARRANGEMENT',
          onEdit: () => setState(() => _step = 1),
          rows: [
            const MapEntry('Role', 'Hub Transport Driver'),
            MapEntry(
              'Arrangement',
              _isDriverOwned ? 'Driver-owned truck' : 'Company-provided truck',
            ),
          ],
        ),
        const SizedBox(height: 14),

        ReviewSection(
          title: 'PERSONAL DETAILS',
          onEdit: () => setState(() => _step = 0),
          rows: [
            MapEntry('Name', '${_firstName.text.trim()} ${_lastName.text.trim()}'),
            MapEntry('Email', _email.text.trim()),
            MapEntry('Phone', _phone.text.trim()),
            MapEntry('Sex', ucfirst(_sex ?? '-')),
            MapEntry('Address', _addressSel?.composed ?? '-'),
          ],
        ),
        const SizedBox(height: 14),

        ReviewSection(
          title: "DRIVER'S LICENSE & TRUCK",
          onEdit: () => setState(() => _step = 2),
          rows: [
            MapEntry('License Number', _licenseNo.text.trim()),
            MapEntry(
              'License Expiry',
              _licenseExpiry == null
                  ? '-'
                  : '${_licenseExpiry!.year}-${_licenseExpiry!.month.toString().padLeft(2, '0')}-${_licenseExpiry!.day.toString().padLeft(2, '0')}',
            ),
            if (_isDriverOwned) ...[
              MapEntry('Truck Type', ucfirst(_vehicleType ?? '-')),
              MapEntry('License Plate', _plate.text.trim()),
              if (_vehicleModel.text.trim().isNotEmpty)
                MapEntry('Brand / Model', _vehicleModel.text.trim()),
              MapEntry('Parcel Capacity', '${_parcelCapacity.text.trim()} parcels'),
              MapEntry('Weight Capacity', '${_weightCapacity.text.trim()} kg'),
              MapEntry('OR/CR Number', _vehicleReg.text.trim()),
            ] else ...[
              const MapEntry('Fleet Specification', 'Company Four-Wheel Truck (Assigned upon approval)'),
            ],
          ],
        ),
        const SizedBox(height: 14),

        ReviewSection(
          title: 'ATTACHED DOCUMENTS',
          onEdit: () => setState(() => _step = 4),
          rows: [
            for (final d in _requiredDocs)
              MapEntry(d.title, _docs.containsKey(d.key) ? 'Attached' : 'Missing'),
          ],
        ),
        const SizedBox(height: 20),

        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Checkbox(
              value: _confirm,
              onChanged: (v) => setState(() => _confirm = v ?? false),
            ),
            const Expanded(
              child: Padding(
                padding: EdgeInsets.only(top: 8),
                child: Text(
                  'I confirm that all information provided is accurate and all documents uploaded are authentic.',
                  style: TextStyle(fontSize: 13, height: 1.4),
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _field(
    TextEditingController controller,
    String label,
    IconData? icon, {
    int maxLength = 100,
    String? errorText,
    TextInputType keyboard = TextInputType.text,
    TextCapitalization capitalization = TextCapitalization.none,
    String? hint,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: TextField(
        controller: controller,
        maxLength: maxLength,
        keyboardType: keyboard,
        textCapitalization: capitalization,
        onChanged: (_) {
          if (_showErrors && mounted) setState(() {});
        },
        decoration: InputDecoration(
          labelText: label,
          hintText: hint,
          prefixIcon: icon != null ? Icon(icon, size: 20) : null,
          errorText: errorText,
          counterText: '',
        ),
      ),
    );
  }

  static String ucfirst(String s) {
    if (s.isEmpty) return s;
    return s[0].toUpperCase() + s.substring(1);
  }
}
