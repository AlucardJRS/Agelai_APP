import 'package:flutter_test/flutter_test.dart';

import 'package:club_agelai_app/main.dart';

void main() {
  testWidgets('renderiza pantalla de acceso Club Agelai', (WidgetTester tester) async {
    await tester.pumpWidget(const ClubAgelaiApp());
    expect(find.text('Club Agelai'), findsWidgets);
  });
}
