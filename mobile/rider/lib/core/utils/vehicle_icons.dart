import 'package:flutter/material.dart';

/// Centralized vehicle-type → icon mapping for the Rider app.
///
/// Matches backend `vehicle_types.name` values (`GET /vehicle-types`),
/// common aliases, and human labels such as `Car / Sedan` via
/// case-insensitive substring matching (order matters: specific wheels
/// first). Unknown types fall back to a generic car icon — never blank.
/// Uses Material icons only, no extra dependencies.
IconData vehicleIconFor(String? type) {
  final t = (type ?? '').toLowerCase().trim();
  bool has(String s) => t.contains(s);

  if (has('moto')) return Icons.two_wheeler; // motorcycle, motorbike
  if (has('tricycle') || has('trike') || has('tuktuk')) {
    return Icons.pedal_bike;
  }
  if (has('bicycle') || has('bike')) return Icons.directions_bike;
  if (has('van') || has('minivan') || has('shuttle')) {
    return Icons.airport_shuttle;
  }
  if (has('truck') || has('lorry')) return Icons.local_shipping;
  if (has('sedan') || has('car')) return Icons.directions_car;
  return Icons.directions_car;
}
