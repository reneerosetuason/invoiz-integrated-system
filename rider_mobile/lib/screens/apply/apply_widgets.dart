import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';

/// Shared presentational widgets for the guided "Apply as a Rider" flow.
///
/// All color/radius/type choices come from [AppColors] (the Invoiz design
/// system). These widgets are intentionally dumb: all state lives in the
/// wizard so navigating back/forward never loses entered information.

/// Human-readable file size (matches the backend `humanSize` format).
String formatBytes(int bytes) {
  if (bytes >= 1048576) return '${(bytes / 1048576).toStringAsFixed(1)} MB';
  if (bytes >= 1024) return '${(bytes / 1024).toStringAsFixed(1)} KB';
  return '$bytes B';
}

/// Step titles shown in the progress indicator.
const applyStepLabels = [
  'Personal',
  'Rider Type',
  'Vehicle',
  'Requirements',
  'Documents',
  'Review',
];

/// Mobile progress indicator: primary for the current step, success green
/// for completed steps, soft accent connectors.
class ApplyProgressIndicator extends StatelessWidget {
  const ApplyProgressIndicator({super.key, required this.step});

  final int step;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          for (int i = 0; i < applyStepLabels.length; i++) ...[
            _Node(
              index: i,
              label: applyStepLabels[i],
              state: i < step
                  ? _NodeState.done
                  : (i == step ? _NodeState.current : _NodeState.todo),
            ),
            if (i < applyStepLabels.length - 1)
              Container(
                width: 20,
                height: 2,
                margin: const EdgeInsets.only(bottom: 22, left: 4, right: 4),
                decoration: BoxDecoration(
                  color: i < step
                      ? AppColors.success
                      : AppColors.border,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
          ],
        ],
      ),
    );
  }
}

enum _NodeState { done, current, todo }

class _Node extends StatelessWidget {
  const _Node({required this.index, required this.label, required this.state});

  final int index;
  final String label;
  final _NodeState state;

  @override
  Widget build(BuildContext context) {
    final Color ring;
    final Color fill;
    final Color content;
    switch (state) {
      case _NodeState.done:
        ring = AppColors.success;
        fill = AppColors.success;
        content = Colors.white;
      case _NodeState.current:
        ring = AppColors.primary;
        fill = AppColors.primary;
        content = Colors.white;
      case _NodeState.todo:
        ring = AppColors.border;
        fill = AppColors.card;
        content = AppColors.textSecondary;
    }
    return SizedBox(
      width: 56,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 30,
            height: 30,
            decoration: BoxDecoration(
              color: fill,
              shape: BoxShape.circle,
              border: Border.all(color: ring, width: 2),
            ),
            child: Center(
              child: state == _NodeState.done
                  ? const Icon(Icons.check, size: 16, color: Colors.white)
                  : Text(
                      '${index + 1}',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                        color: content,
                      ),
                    ),
            ),
          ),
          const SizedBox(height: 4),
          Text(
            label,
            textAlign: TextAlign.center,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              fontSize: 11,
              fontWeight: state == _NodeState.current
                  ? FontWeight.w700
                  : FontWeight.w400,
              color: state == _NodeState.todo
                  ? AppColors.textSecondary
                  : AppColors.textPrimary,
            ),
          ),
        ],
      ),
    );
  }
}

/// Large title + subtitle shown at the top of each step.
class ApplyStepHeader extends StatelessWidget {
  const ApplyStepHeader({
    super.key,
    required this.title,
    required this.subtitle,
  });

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(
            fontSize: 24,
            fontWeight: FontWeight.w700,
            color: AppColors.textPrimary,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          subtitle,
          style: const TextStyle(
            fontSize: 14,
            color: AppColors.textSecondary,
            height: 1.5,
          ),
        ),
      ],
    );
  }
}

/// Selectable Invoiz card: white + hairline border unselected; soft accent
/// fill + primary border + check badge when selected.
class SelectableCard extends StatelessWidget {
  const SelectableCard({
    super.key,
    required this.selected,
    required this.onTap,
    required this.child,
  });

  final bool selected;
  final VoidCallback onTap;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Stack(
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: selected ? AppColors.accent : AppColors.card,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(
                color: selected
                    ? AppColors.primary
                    : AppColors.border,
                width: selected ? 2 : 1,
              ),
            ),
            child: child,
          ),
          if (selected)
            const Positioned(
              top: 10,
              right: 10,
              child: Icon(
                Icons.check_circle,
                size: 22,
                color: AppColors.primary,
              ),
            ),
        ],
      ),
    );
  }
}

/// Read-only requirement row used on the Requirements step.
class RequirementTile extends StatelessWidget {
  const RequirementTile({
    super.key,
    required this.icon,
    required this.title,
    required this.subtitle,
    this.staged = false,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final bool staged;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.border),
      ),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: AppColors.accent,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: AppColors.primary, size: 22),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: AppColors.textPrimary,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: const TextStyle(
                    fontSize: 12,
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
          if (staged)
            const Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.check,
                    color: AppColors.success, size: 18),
                SizedBox(width: 4),
                Text(
                  'Ready',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    color: AppColors.success,
                  ),
                ),
              ],
            ),
        ],
      ),
    );
  }
}

/// Document upload card: dashed pick state, staged state (file + size),
/// replace and remove actions. Files are staged locally and uploaded on
/// submit, so the card honestly reports "Selected" rather than "Uploaded".
class DocumentUploadCard extends StatelessWidget {
  const DocumentUploadCard({
    super.key,
    required this.title,
    required this.subtitle,
    required this.required,
    this.filename,
    this.fileSize,
    this.error,
    required this.onPick,
    this.onRemove,
  });

  final String title;
  final String subtitle;
  final bool required;
  final String? filename;
  final String? fileSize;
  final String? error;
  final VoidCallback onPick;
  final VoidCallback? onRemove;

  @override
  Widget build(BuildContext context) {
    final staged = filename != null;
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: error != null
              ? AppColors.warning
              : (staged ? AppColors.success : AppColors.border),
          width: staged || error != null ? 1.5 : 1,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: AppColors.accent,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(
                  staged
                      ? Icons.check_circle_outline
                      : Icons.upload_file_outlined,
                  color: staged
                      ? AppColors.success
                      : AppColors.primary,
                  size: 22,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            title,
                            style: const TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w600,
                              color: AppColors.textPrimary,
                            ),
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 8, vertical: 2),
                          decoration: BoxDecoration(
                            color: (required
                                    ? AppColors.primary
                                    : AppColors.textSecondary)
                                .withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            required ? 'Required' : 'Optional',
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w600,
                              color: required
                                  ? AppColors.primary
                                  : AppColors.textSecondary,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 2),
                    Text(
                      staged && fileSize != null
                          ? '$filename · $fileSize'
                          : subtitle,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        fontSize: 12,
                        color: staged
                            ? AppColors.success
                            : AppColors.textSecondary,
                        fontWeight:
                            staged ? FontWeight.w600 : FontWeight.w400,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (error != null) ...[
            const SizedBox(height: 8),
            Text(
              error!,
              style: const TextStyle(
                fontSize: 12,
                color: AppColors.warning,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
          const SizedBox(height: 12),
          if (!staged)
            SizedBox(
              width: double.infinity,
              child: DashedUploadWell(onTap: onPick),
            )
          else
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: onPick,
                    icon: const Icon(Icons.refresh_outlined, size: 18),
                    label: const Text('Replace'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppColors.primary,
                      minimumSize: const Size.fromHeight(44),
                      side:
                          const BorderSide(color: AppColors.primary),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                    ),
                  ),
                ),
                if (onRemove != null) ...[
                  const SizedBox(width: 8),
                  IconButton.outlined(
                    onPressed: onRemove,
                    tooltip: 'Remove',
                    style: IconButton.styleFrom(
                      foregroundColor: AppColors.warning,
                      side: BorderSide(
                        color: AppColors.warning.withValues(alpha: 0.4),
                      ),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                    ),
                    icon: const Icon(Icons.delete_outline, size: 20),
                  ),
                ],
              ],
            ),
          if (staged)
            const Padding(
              padding: EdgeInsets.only(top: 8),
              child: Text(
                'Selected — will be uploaded when you submit.',
                style: TextStyle(
                    fontSize: 11, color: AppColors.textSecondary),
              ),
            ),
        ],
      ),
    );
  }
}

/// Dashed "Upload file" well (JPG, PNG or PDF hint).
class DashedUploadWell extends StatelessWidget {
  const DashedUploadWell({super.key, required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 18),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: AppColors.primary.withValues(alpha: 0.4),
            width: 1.2,
          ),
        ),
        child: const Column(
          children: [
            Icon(Icons.cloud_upload_outlined,
                size: 28, color: AppColors.primary),
            SizedBox(height: 6),
            Text(
              'Upload file',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: AppColors.primary,
              ),
            ),
            SizedBox(height: 2),
            Text(
              'JPG, PNG or PDF',
              style: TextStyle(
                  fontSize: 11, color: AppColors.textSecondary),
            ),
          ],
        ),
      ),
    );
  }
}

/// Review section card with an [Edit] jump link.
class ReviewSection extends StatelessWidget {
  const ReviewSection({
    super.key,
    required this.title,
    required this.onEdit,
    required this.rows,
  });

  final String title;
  final VoidCallback onEdit;
  final List<MapEntry<String, String>> rows;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: AppColors.textPrimary,
                    letterSpacing: 0.5,
                  ),
                ),
              ),
              TextButton(
                onPressed: onEdit,
                style: TextButton.styleFrom(
                  minimumSize: Size.zero,
                  padding:
                      const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                ),
                child: const Text(
                  'Edit',
                  style: TextStyle(fontWeight: FontWeight.w600),
                ),
              ),
            ],
          ),
          const Divider(height: 16, color: AppColors.border),
          for (final row in rows)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SizedBox(
                    width: 130,
                    child: Text(
                      row.key,
                      style: const TextStyle(
                        fontSize: 13,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ),
                  Expanded(
                    child: Text(
                      row.value,
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: AppColors.textPrimary,
                      ),
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

/// Bottom Back / Continue bar shared by the wizard steps.
class ApplyNavBar extends StatelessWidget {
  const ApplyNavBar({
    super.key,
    required this.nextLabel,
    required this.onNext,
    this.onBack,
    this.nextLoading = false,
  });

  final String nextLabel;
  final VoidCallback? onNext;
  final VoidCallback? onBack;
  final bool nextLoading;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        if (onBack != null) ...[
          Expanded(
            child: OutlinedButton(
              onPressed: onBack,
              style: OutlinedButton.styleFrom(
                minimumSize: const Size.fromHeight(52),
                foregroundColor: AppColors.primary,
                side: const BorderSide(color: AppColors.primary),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                ),
              ),
              child: const Text('Back'),
            ),
          ),
          const SizedBox(width: 12),
        ],
        Expanded(
          flex: 2,
          child: SizedBox(
            height: 52,
            child: ElevatedButton(
              onPressed: nextLoading ? null : onNext,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary,
                foregroundColor: Colors.white,
                disabledBackgroundColor:
                    AppColors.primary.withValues(alpha: 0.5),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                ),
              ),
              child: nextLoading
                  ? const SizedBox(
                      width: 22,
                      height: 22,
                      child: CircularProgressIndicator(
                        strokeWidth: 2.5,
                        color: Colors.white,
                      ),
                    )
                  : Text(
                      nextLabel,
                      style: const TextStyle(fontWeight: FontWeight.w600),
                    ),
            ),
          ),
        ),
      ],
    );
  }
}
