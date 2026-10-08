import 'package:flutter/material.dart';
import 'screens/agreement_screen.dart';

void main() {
  runApp(const BfpEdirsApp());
}

class BfpEdirsApp extends StatelessWidget {
  const BfpEdirsApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'ResQ Leyte',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(colorSchemeSeed: const Color(0xFFC62828), useMaterial3: true),
      home: const AgreementScreen(),
    );
  }
}