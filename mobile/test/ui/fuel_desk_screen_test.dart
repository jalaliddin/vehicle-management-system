import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:provider/provider.dart';
import 'package:utg_transport/data/services/api_exception.dart';
import 'package:utg_transport/domain/models/fuel_station.dart';
import 'package:utg_transport/ui/features/fuel/view_models/fuel_desk_view_model.dart';
import 'package:utg_transport/ui/features/fuel/views/fuel_desk_screen.dart';

import '../helpers/fixtures.dart';

void main() {
  testWidgets('a failed shift load offers a retry, not a closed shift', (
    tester,
  ) async {
    final fuel = MockFuelRepository();
    when(() => fuel.stations())
        .thenAnswer((_) async => [FuelStation.fromJson(fuelStationJson())]);
    when(() => fuel.currentShift(1))
        .thenThrow(const ApiException('Server bilan aloqa yo\'q'));

    await tester.pumpWidget(
      ChangeNotifierProvider(
        create: (_) => FuelDeskViewModel(fuelRepository: fuel)..load(),
        child: const MaterialApp(home: FuelDeskScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Smena yopiq'), findsNothing);
    expect(find.text('Smenani ochish'), findsNothing);
    expect(find.text('Qayta urinish'), findsOneWidget);
  });
}
