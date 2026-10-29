import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:invoize_rider/core/utils/vehicle_icons.dart';

/// Central vehicle icon mapping: every backend `vehicle_types.name`
/// resolves to an accurate icon, unknown types get a sane fallback.
void main() {
  test('motorcycle variants map to the motorcycle icon', () {
    expect(vehicleIconFor('motorcycle'), Icons.two_wheeler);
    expect(vehicleIconFor('motorbike'), Icons.two_wheeler);
  });

  test('car/sedan map to the car icon', () {
    expect(vehicleIconFor('car'), Icons.directions_car);
    expect(vehicleIconFor('sedan'), Icons.directions_car);
  });

  test('van maps to the van icon', () {
    expect(vehicleIconFor('van'), Icons.airport_shuttle);
  });

  test('truck maps to the truck icon', () {
    expect(vehicleIconFor('truck'), Icons.local_shipping);
  });

  test('labels and casing resolve like backend names', () {
    expect(vehicleIconFor('Motorcycle'), Icons.two_wheeler);
    expect(vehicleIconFor('Car/Sedan'), Icons.directions_car);
    expect(vehicleIconFor('Van'), Icons.airport_shuttle);
    expect(vehicleIconFor('Truck'), Icons.local_shipping);
  });

  test('unknown, empty and null types fall back instead of blank', () {
    expect(vehicleIconFor('spaceship'), Icons.directions_car);
    expect(vehicleIconFor(''), Icons.directions_car);
    expect(vehicleIconFor(null), Icons.directions_car);
  });
}
