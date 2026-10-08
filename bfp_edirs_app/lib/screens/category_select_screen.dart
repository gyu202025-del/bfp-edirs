import 'package:flutter/material.dart';
import '../models/incident_category.dart';
import '../services/api_service.dart';
import 'report_form_screen.dart';

class CategorySelectScreen extends StatefulWidget {
  const CategorySelectScreen({super.key});

  @override
  State<CategorySelectScreen> createState() => _CategorySelectScreenState();
}

class _CategorySelectScreenState extends State<CategorySelectScreen> {
  List<IncidentCategory> categories = [];
  bool loading = true;
  String? error;

  @override
  void initState() {
    super.initState();
    _loadCategories();
  }

  Future<void> _loadCategories() async {
    try {
      final result = await ApiService.fetchCategories();
      setState(() {
        categories = result;
        loading = false;
      });
    } catch (e) {
      setState(() {
        error = 'Could not connect to the server. Check your internet connection.';
        loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF1B263B),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Text('🚨', style: TextStyle(fontSize: 40)),
              const SizedBox(height: 12),
              const Text(
                'What do you want to report?',
                textAlign: TextAlign.center,
                style: TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 8),
              const Text(
                'Select a category — we\'ll capture your location automatically.',
                textAlign: TextAlign.center,
                style: TextStyle(color: Colors.white70, fontSize: 13),
              ),
              const SizedBox(height: 32),
              if (loading) const CircularProgressIndicator(color: Colors.white),
              if (error != null) ...[
                Text(error!, style: const TextStyle(color: Colors.redAccent), textAlign: TextAlign.center),
                const SizedBox(height: 12),
                ElevatedButton(onPressed: () { setState(() { loading = true; error = null; }); _loadCategories(); }, child: const Text('Retry')),
              ],
              if (!loading && error == null)
                GridView.count(
                  crossAxisCount: 2,
                  shrinkWrap: true,
                  crossAxisSpacing: 12,
                  mainAxisSpacing: 12,
                  children: categories.map((c) => _CategoryCard(category: c)).toList(),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CategoryCard extends StatelessWidget {
  final IncidentCategory category;
  const _CategoryCard({required this.category});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(16),
      onTap: () {
        Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => ReportFormScreen(category: category)),
        );
      },
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white.withOpacity(0.08),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: Colors.white.withOpacity(0.15)),
        ),
        padding: const EdgeInsets.all(16),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(category.emoji, style: const TextStyle(fontSize: 34)),
            const SizedBox(height: 8),
            Text(category.label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
            Text(category.agency, style: const TextStyle(color: Colors.white54, fontSize: 11)),
          ],
        ),
      ),
    );
  }
}