//import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:bfp_edirs_app/main.dart';

void main() {
  testWidgets('App loads the category selection screen', (WidgetTester tester) async {
    await tester.pumpWidget(const BfpEdirsApp());

    // Confirm the category-selection screen's heading actually renders.
    expect(find.text('What do you want to report?'), findsOneWidget);
  });
}