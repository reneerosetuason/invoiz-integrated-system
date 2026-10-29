import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart' show ButtonStyle, Tooltip, WidgetState;
import '../theme.dart';

enum _ButtonKind { primary, secondary, plain, icon }

/// Native pressed feedback and one compact shape language across buyer flows.
class BuyerButton extends StatelessWidget {
  final VoidCallback? onPressed;
  final Widget? child, icon, label;
  final String? tooltip;
  final ButtonStyle? style;
  final BoxConstraints? constraints;
  final _ButtonKind _kind;
  final bool filled;

  const BuyerButton.primary({
    super.key,
    required this.onPressed,
    this.child,
    this.icon,
    this.label,
    this.style,
    this.tooltip,
  }) : _kind = _ButtonKind.primary,
       constraints = null,
       filled = true;
  const BuyerButton.secondary({
    super.key,
    required this.onPressed,
    this.child,
    this.icon,
    this.label,
    this.style,
    this.tooltip,
  }) : _kind = _ButtonKind.secondary,
       constraints = null,
       filled = true;
  const BuyerButton.plain({
    super.key,
    required this.onPressed,
    this.child,
    this.icon,
    this.label,
    this.style,
    this.tooltip,
  }) : _kind = _ButtonKind.plain,
       constraints = null,
       filled = false;
  const BuyerButton.icon({
    super.key,
    required this.onPressed,
    this.child,
    this.icon,
    this.label,
    this.style,
    this.tooltip,
    this.constraints,
    this.filled = false,
  }) : _kind = _ButtonKind.icon;

  @override
  Widget build(BuildContext context) {
    final states = <WidgetState>{if (onPressed == null) WidgetState.disabled};
    final primary = _kind == _ButtonKind.primary;
    final secondary = _kind == _ButtonKind.secondary;
    final iconOnly = _kind == _ButtonKind.icon;
    final foreground = onPressed == null
        ? AppColors.textMuted
        : style?.foregroundColor?.resolve(states) ??
              (primary ? CupertinoColors.white : AppColors.primaryDark);
    final background =
        style?.backgroundColor?.resolve(states) ??
        (primary
            ? AppColors.primaryDark
            : secondary
            ? AppColors.paper
            : filled
            ? AppColors.surface
            : null);
    final minimum = style?.minimumSize?.resolve(states) ?? const Size(44, 44);
    final content =
        child ??
        (label == null
            ? icon!
            : Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (icon != null) ...[icon!, const SizedBox(width: 7)],
                  Flexible(child: label!),
                ],
              ));
    final text = DefaultTextStyle.merge(
      style: TextStyle(
        fontFamily: 'Inter',
        fontSize: 14,
        height: 1.25,
        fontWeight: FontWeight.w600,
        color: foreground,
      ),
      textAlign: TextAlign.center,
      child: IconTheme.merge(
        data: IconThemeData(color: foreground, size: 20),
        child: content,
      ),
    );
    final padding =
        style?.padding?.resolve(states) ??
        (iconOnly
            ? EdgeInsets.zero
            : EdgeInsets.symmetric(
                horizontal: primary || secondary ? 18 : 8,
                vertical: 10,
              ));
    final button = primary
        ? CupertinoButton.filled(
            onPressed: onPressed,
            color: background,
            foregroundColor: foreground,
            disabledColor: AppColors.surfaceTint,
            pressedOpacity: .68,
            minimumSize: Size(
              minimum.width < 44 ? 44 : minimum.width,
              minimum.height < 44 ? 44 : minimum.height,
            ),
            padding: padding,
            borderRadius: BorderRadius.circular(999),
            child: text,
          )
        : CupertinoButton(
            onPressed: onPressed,
            color: background,
            foregroundColor: foreground,
            disabledColor: AppColors.surfaceTint,
            pressedOpacity: .55,
            minimumSize: Size(
              minimum.width < 44 ? 44 : minimum.width,
              minimum.height < 44 ? 44 : minimum.height,
            ),
            padding: padding,
            borderRadius: BorderRadius.circular(999),
            child: text,
          );
    Widget result = secondary
        ? DecoratedBox(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(999),
              border: Border.all(color: AppColors.border, width: .6),
            ),
            child: button,
          )
        : button;
    if (constraints != null) {
      result = ConstrainedBox(constraints: constraints!, child: result);
    }
    return tooltip == null ? result : Tooltip(message: tooltip!, child: result);
  }
}

/// A switch row keeps the original callback, including its disabled state.
class BuyerSwitchRow extends StatelessWidget {
  final bool value;
  final ValueChanged<bool?>? onChanged;
  final Widget title;
  const BuyerSwitchRow({
    super.key,
    required this.value,
    required this.onChanged,
    required this.title,
  });
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 4),
    child: Row(
      children: [
        Expanded(child: title),
        const SizedBox(width: 12),
        CupertinoSwitch(
          value: value,
          onChanged: onChanged,
          activeTrackColor: AppColors.primaryDark,
        ),
      ],
    ),
  );
}

class BuyerChoiceRow<T> extends StatelessWidget {
  final T value;
  final T? groupValue;
  final ValueChanged<T?>? onChanged;
  final Widget title;
  final Widget? subtitle;
  final Color activeColor;
  const BuyerChoiceRow({
    super.key,
    required this.value,
    required this.groupValue,
    required this.onChanged,
    required this.title,
    this.subtitle,
    this.activeColor = AppColors.primaryDark,
  });
  @override
  Widget build(BuildContext context) => Semantics(
    checked: value == groupValue,
    inMutuallyExclusiveGroup: true,
    child: CupertinoButton(
      padding: const EdgeInsets.symmetric(vertical: 8),
      minimumSize: const Size(44, 44),
      onPressed: onChanged == null
          ? null
          : () {
              if (value != groupValue) onChanged!(value);
            },
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 2),
            child: Icon(
              value == groupValue
                  ? CupertinoIcons.checkmark_circle_fill
                  : CupertinoIcons.circle,
              size: 22,
              color: value == groupValue ? activeColor : AppColors.textMuted,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: DefaultTextStyle(
              style: const TextStyle(
                fontFamily: 'Inter',
                fontSize: 14,
                height: 1.4,
                color: AppColors.textPrimary,
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  title,
                  if (subtitle != null) ...[
                    const SizedBox(height: 3),
                    subtitle!,
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    ),
  );
}
