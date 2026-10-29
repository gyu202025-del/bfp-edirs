import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:bfp_edirs_app/screens/category_select_screen.dart';

void main() {
  testWidgets('App loads the category selection screen', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(const MaterialApp(home: CategorySelectScreen()));

    expect(find.text('What do you want to report?'), findsOneWidget);
    expect(
      find.text(
        '© 2026 Emergency Disaster Incident Reporting System — Province of Leyte',
      ),
      findsOneWidget,
    );
  });
}
