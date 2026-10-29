import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart'
    show FormField, FormFieldState, InputDecoration;
import 'package:flutter/services.dart';
import '../theme.dart';

/// Cupertino editing with the existing controllers, Form validation and saves.
class BuyerField extends StatefulWidget {
  final TextEditingController? controller;
  final String? initialValue;
  final InputDecoration decoration;
  final TextInputType? keyboardType;
  final TextInputAction? textInputAction;
  final TextStyle? style;
  final TextAlign textAlign;
  final bool obscureText, readOnly, enabled, autofocus;
  final int? maxLines, maxLength, minLines;
  final Iterable<String>? autofillHints;
  final ValueChanged<String>? onChanged, onSubmitted;
  final FormFieldSetter<String>? onSaved;
  final FormFieldValidator<String>? validator;
  final GestureTapCallback? onTap;
  final List<TextInputFormatter>? inputFormatters;
  final bool _form;
  const BuyerField({
    super.key,
    this.controller,
    this.initialValue,
    this.decoration = const InputDecoration(),
    this.keyboardType,
    this.textInputAction,
    this.style,
    this.textAlign = TextAlign.start,
    this.obscureText = false,
    this.readOnly = false,
    this.enabled = true,
    this.autofocus = false,
    this.maxLines = 1,
    this.minLines,
    this.maxLength,
    this.autofillHints,
    this.onChanged,
    this.onSubmitted,
    this.onSaved,
    this.validator,
    this.onTap,
    this.inputFormatters,
  }) : _form = false;
  const BuyerField.form({
    super.key,
    this.controller,
    this.initialValue,
    this.decoration = const InputDecoration(),
    this.keyboardType,
    this.textInputAction,
    this.style,
    this.textAlign = TextAlign.start,
    this.obscureText = false,
    this.readOnly = false,
    this.enabled = true,
    this.autofocus = false,
    this.maxLines = 1,
    this.minLines,
    this.maxLength,
    this.autofillHints,
    this.onChanged,
    this.onSubmitted,
    this.onSaved,
    this.validator,
    this.onTap,
    this.inputFormatters,
  }) : _form = true;
  @override
  State<BuyerField> createState() => _BuyerFieldState();
}

class _BuyerFieldState extends State<BuyerField> {
  final _form = GlobalKey<FormFieldState<String>>();
  TextEditingController? _owned;
  bool _focused = false;
  late String _initialText;
  TextEditingController get _controller => widget.controller ?? _owned!;
  @override
  void initState() {
    super.initState();
    if (widget.controller == null) {
      _owned = TextEditingController(text: widget.initialValue);
    }
    _initialText = _controller.text;
    _controller.addListener(_sync);
  }

  void _sync() {
    final field = _form.currentState;
    if (field != null && field.value != _controller.text) {
      field.didChange(_controller.text);
    }
  }

  @override
  void didUpdateWidget(BuyerField oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.controller != widget.controller) {
      final previous = oldWidget.controller ?? _owned!;
      final value = previous.value;
      previous.removeListener(_sync);
      _owned?.dispose();
      _owned = widget.controller == null
          ? TextEditingController.fromValue(value)
          : null;
      _controller.addListener(_sync);
    }
  }

  @override
  void dispose() {
    _controller.removeListener(_sync);
    _owned?.dispose();
    super.dispose();
  }

  Widget _input(String? error) {
    final label = widget.decoration.labelText;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (label != null) ...[
          Text(
            label,
            style: const TextStyle(
              fontFamily: 'Inter',
              fontSize: 12,
              fontWeight: FontWeight.w500,
              color: AppColors.textSecondary,
            ),
          ),
          const SizedBox(height: 6),
        ],
        Focus(
          onFocusChange: (value) => setState(() => _focused = value),
          child: ConstrainedBox(
            constraints: const BoxConstraints(minHeight: 46),
            child: CupertinoTextField(
              controller: _controller,
              textAlign: widget.textAlign,
              keyboardType: widget.keyboardType,
              textInputAction: widget.textInputAction,
              obscureText: widget.obscureText,
              readOnly: widget.readOnly,
              enabled: widget.enabled,
              autofocus: widget.autofocus,
              maxLines: widget.maxLines,
              minLines: widget.minLines,
              maxLength: widget.maxLength,
              autofillHints: widget.autofillHints,
              onChanged: widget.onChanged,
              onSubmitted: widget.onSubmitted,
              onTap: widget.onTap,
              inputFormatters: widget.inputFormatters,
              cursorColor: AppColors.primaryDark,
              style: const TextStyle(
                fontFamily: 'Inter',
                fontSize: 14,
                height: 1.4,
                color: AppColors.textPrimary,
              ).merge(widget.style),
              placeholder: widget.decoration.hintText,
              placeholderStyle: const TextStyle(
                fontFamily: 'Inter',
                fontSize: 14,
                color: AppColors.textMuted,
              ),
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
              prefix: widget.decoration.prefixIcon == null
                  ? null
                  : Padding(
                      padding: const EdgeInsets.only(left: 12),
                      child: IconTheme(
                        data: const IconThemeData(
                          size: 18,
                          color: AppColors.textSecondary,
                        ),
                        child: widget.decoration.prefixIcon!,
                      ),
                    ),
              suffix: widget.decoration.suffixIcon,
              decoration: BoxDecoration(
                color: AppColors.paper,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(
                  width: _focused ? 1 : .5,
                  color: error != null
                      ? AppColors.danger
                      : _focused
                      ? AppColors.primaryDark
                      : AppColors.border,
                ),
              ),
            ),
          ),
        ),
        if (error != null)
          Padding(
            padding: const EdgeInsets.only(top: 5),
            child: Text(
              error,
              style: const TextStyle(
                fontFamily: 'Inter',
                fontSize: 12,
                color: AppColors.danger,
              ),
            ),
          ),
        if (widget.maxLength != null && widget.decoration.counterText != '')
          ValueListenableBuilder<TextEditingValue>(
            valueListenable: _controller,
            builder: (_, value, __) => Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text(
                '${value.text.characters.length}/${widget.maxLength}',
                textAlign: TextAlign.end,
                style: const TextStyle(
                  fontFamily: 'Inter',
                  fontSize: 11,
                  color: AppColors.textMuted,
                ),
              ),
            ),
          ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) => widget._form
      ? _BuyerFormValue(
          key: _form,
          text: _controller.text,
          enabled: widget.enabled,
          validator: widget.validator,
          onSaved: widget.onSaved,
          onReset: () => _controller.text = widget.initialValue ?? _initialText,
          builder: (field) => _input(field.errorText),
        )
      : _input(null);
}

class _BuyerFormValue extends FormField<String> {
  final String text;
  const _BuyerFormValue({
    super.key,
    required this.text,
    required super.builder,
    super.enabled,
    super.validator,
    super.onSaved,
    super.onReset,
  }) : super(initialValue: text);
  @override
  FormFieldState<String> createState() => _BuyerFormValueState();
}

class _BuyerFormValueState extends FormFieldState<String> {
  @override
  void didUpdateWidget(covariant _BuyerFormValue oldWidget) {
    super.didUpdateWidget(oldWidget);
    final current = widget as _BuyerFormValue;
    if (current.text != oldWidget.text) setValue(current.text);
  }
}
