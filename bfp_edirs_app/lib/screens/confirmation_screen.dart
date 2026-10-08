import 'package:flutter/material.dart';
import 'category_select_screen.dart';

class ConfirmationScreen extends StatelessWidget {
  final String incidentNumber;
  const ConfirmationScreen({super.key, required this.incidentNumber});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Text('✅', style: TextStyle(fontSize: 50)),
              const SizedBox(height: 12),
              const Text('Report Sent', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w600)),
              const SizedBox(height: 8),
              const Text('Your report has been received and is being reviewed.', textAlign: TextAlign.center),
              const SizedBox(height: 8),
              Text(incidentNumber, style: const TextStyle(fontFamily: 'monospace', color: Colors.grey)),
              const SizedBox(height: 24),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () => Navigator.pushAndRemoveUntil(
                    context,
                    MaterialPageRoute(builder: (_) => const CategorySelectScreen()),
                    (route) => false,
                  ),
                  child: const Text('Report Another'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}