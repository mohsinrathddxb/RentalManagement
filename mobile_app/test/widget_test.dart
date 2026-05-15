import 'package:flutter_test/flutter_test.dart';
import 'package:flutter/material.dart';
import 'package:mobile_app/main.dart';

void main() {
  testWidgets('app shows Co-Living Space branding', (tester) async {
    await tester.pumpWidget(const MaterialApp(home: LandingScreen()));
    expect(find.text('Co-Living Space'), findsOneWidget);
  });
}
