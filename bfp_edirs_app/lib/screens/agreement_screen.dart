import 'package:flutter/material.dart';
import 'category_select_screen.dart';

class AgreementScreen extends StatefulWidget {
  const AgreementScreen({super.key});

  @override
  State<AgreementScreen> createState() => _AgreementScreenState();
}

class _AgreementScreenState extends State<AgreementScreen> {
  bool agreed = false;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF1B263B),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(20),
            child: Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Row(
                    children: [
                      Icon(Icons.warning_amber_rounded, color: Color(0xFFC62828)),
                      SizedBox(width: 8),
                      Text('Before You Report',
                          style: TextStyle(fontSize: 18, fontWeight: FontWeight.w600)),
                    ],
                  ),
                  const SizedBox(height: 14),
                  const Text(
                    'This app is for genuine emergencies only. Filing a false, malicious, or fake report is a punishable offense under Philippine law:',
                    style: TextStyle(fontSize: 13.5),
                  ),
                  const SizedBox(height: 12),
                  const _LawItem(
                    title: 'Republic Act No. 9514',
                    body: '(Fire Code of the Philippines), Rule 11: prohibits giving false or malicious fire alarms.',
                  ),
                  const SizedBox(height: 10),
                  const _LawItem(
                    title: 'Article 155, Revised Penal Code',
                    body: 'as amended by RA 11926: penalizes causing false public alarm, punishable by arresto menor (1 to 30 days) or a fine of up to ₱40,000.',
                  ),
                  const SizedBox(height: 12),
                  CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    controlAffinity: ListTileControlAffinity.leading,
                    value: agreed,
                    onChanged: (v) => setState(() => agreed = v ?? false),
                    title: const Text(
                      'I understand and agree that my report is truthful, and that filing a false report is punishable by law.',
                      style: TextStyle(fontSize: 12.5),
                    ),
                  ),
                  const SizedBox(height: 8),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: agreed
                          ? () => Navigator.pushReplacement(
                                context,
                                MaterialPageRoute(builder: (_) => const CategorySelectScreen()),
                              )
                          : null,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFFC62828),
                        padding: const EdgeInsets.symmetric(vertical: 14),
                      ),
                      child: const Text('Continue',
                          style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _LawItem extends StatelessWidget {
  final String title;
  final String body;
  const _LawItem({required this.title, required this.body});

  @override
  Widget build(BuildContext context) {
    return Text.rich(
      TextSpan(
        style: const TextStyle(fontSize: 12.5, color: Colors.black87),
        children: [
          TextSpan(text: '$title ', style: const TextStyle(fontWeight: FontWeight.bold)),
          TextSpan(text: body),
        ],
      ),
    );
  }
}