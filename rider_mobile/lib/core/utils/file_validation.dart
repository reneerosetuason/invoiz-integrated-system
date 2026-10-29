import 'dart:typed_data';

/// Content-level checks for rider-uploaded documents and delivery photos.
///
/// Extensions alone are not proof of type, so the first bytes are checked
/// against the signatures the backend accepts (`mimes:jpg,jpeg,png,webp,pdf`
/// for application documents). The backend still validates the upload; this
/// catches wrong or corrupted files before a large multipart request.
enum FileProblem { empty, tooLarge, unsupportedType, contentMismatch }

class FileValidationResult {
  const FileValidationResult._(this.problem, this.message);

  const FileValidationResult.valid() : problem = null, message = null;

  final FileProblem? problem;
  final String? message;

  bool get isValid => problem == null;
}

class FileContentValidation {
  FileContentValidation._();

  /// Mirrors the backend `max:5120` (5 MB) application-document rule.
  static const int maxDocumentBytes = 5 * 1024 * 1024;

  /// A16/Proof of delivery: JPG/PNG, max 5 MB. The backend accepts up to
  /// 8 MB, so the client is deliberately stricter so a captured photo cannot
  /// be accepted here and then rejected on upload.
  static const int maxPhotoBytes = 5 * 1024 * 1024;

  static const List<String> documentExtensions = ['pdf', 'jpg', 'jpeg', 'png'];

  static String _extension(String filename) {
    final dot = filename.lastIndexOf('.');
    if (dot < 0 || dot == filename.length - 1) return '';
    return filename.substring(dot + 1).toLowerCase();
  }

  static bool _startsWith(Uint8List bytes, List<int> signature) {
    if (bytes.length < signature.length) return false;
    for (var i = 0; i < signature.length; i++) {
      if (bytes[i] != signature[i]) return false;
    }
    return true;
  }

  static bool _isPdf(Uint8List bytes) => _startsWith(
    bytes,
    const [0x25, 0x50, 0x44, 0x46, 0x2D], // %PDF-
  );

  static bool _isJpeg(Uint8List bytes) =>
      _startsWith(bytes, const [0xFF, 0xD8, 0xFF]);

  static bool _isPng(Uint8List bytes) => _startsWith(bytes, const [
    0x89,
    0x50,
    0x4E,
    0x47,
    0x0D,
    0x0A,
    0x1A,
    0x0A,
  ]);

  static bool _isWebp(Uint8List bytes) {
    if (bytes.length < 12) return false;
    return _startsWith(bytes, const [0x52, 0x49, 0x46, 0x46]) &&
        bytes[8] == 0x57 && // W
        bytes[9] == 0x45 && // E
        bytes[10] == 0x42 && // B
        bytes[11] == 0x50; // P
  }

  /// Whether [bytes] look like the format implied by [filename].
  static bool matchesExtension(Uint8List bytes, String filename) {
    switch (_extension(filename)) {
      case 'pdf':
        return _isPdf(bytes);
      case 'jpg':
      case 'jpeg':
        return _isJpeg(bytes);
      case 'png':
        return _isPng(bytes);
      case 'webp':
        return _isWebp(bytes);
      default:
        return false;
    }
  }

  /// Validate an application document (PDF/JPG/PNG, max 5 MB).
  static FileValidationResult validateDocument({
    required String filename,
    required Uint8List bytes,
    int maxBytes = maxDocumentBytes,
  }) {
    if (!documentExtensions.contains(_extension(filename))) {
      return const FileValidationResult._(
        FileProblem.unsupportedType,
        'Unsupported file type. Please choose a PDF, JPG, or PNG file.',
      );
    }
    if (bytes.isEmpty) {
      return const FileValidationResult._(
        FileProblem.empty,
        'That file is empty. Please choose another file.',
      );
    }
    if (bytes.length > maxBytes) {
      return FileValidationResult._(
        FileProblem.tooLarge,
        'That file is over ${(maxBytes / (1024 * 1024)).round()} MB. '
        'Please choose a smaller file or take a new photo.',
      );
    }
    if (!matchesExtension(bytes, filename)) {
      return FileValidationResult._(
        FileProblem.contentMismatch,
        'That file does not look like a valid '
        '${_extension(filename).toUpperCase()} file. '
        'Please choose another file.',
      );
    }
    return const FileValidationResult.valid();
  }

  /// Validate a delivery proof photo (JPG/PNG/WebP, max 8 MB).
  static FileValidationResult validatePhoto({
    required String filename,
    required Uint8List bytes,
    int maxBytes = maxPhotoBytes,
  }) {
    if (bytes.isEmpty) {
      return const FileValidationResult._(
        FileProblem.empty,
        'The photo is empty. Please take another photo.',
      );
    }
    if (bytes.length > maxBytes) {
      return FileValidationResult._(
        FileProblem.tooLarge,
        'That photo is over ${(maxBytes / (1024 * 1024)).round()} MB. '
        'Please retake it.',
      );
    }
    if (!(_isJpeg(bytes) || _isPng(bytes) || _isWebp(bytes))) {
      return const FileValidationResult._(
        FileProblem.contentMismatch,
        'That photo format is not supported. Please take another photo.',
      );
    }
    return const FileValidationResult.valid();
  }
}
