import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:geolocator/geolocator.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';
import 'package:latlong2/latlong.dart';
import '../models/incident_category.dart';
import '../services/api_service.dart';
import 'confirmation_screen.dart';
import 'fullscreen_map_screen.dart';

class ReportFormScreen extends StatefulWidget {
  final IncidentCategory category;
  const ReportFormScreen({super.key, required this.category});

  @override
  State<ReportFormScreen> createState() => _ReportFormScreenState();
}

class _ReportFormScreenState extends State<ReportFormScreen> {
  static final LatLng _defaultCenter = LatLng(11.2444, 125.0064);
  // Region 8 (Eastern Visayas) bounds
  static final LatLngBounds _region8Bounds = LatLngBounds(LatLng(9.5, 123.8), LatLng(12.8, 125.9));

  final _mapController = MapController();
  bool _mapReady = false;
  LatLng? point;

  final _descriptionController = TextEditingController();
  final _landmarkController = TextEditingController();
  final _barangayController = TextEditingController();

  List<String> municipalities = [];
  String? selectedMunicipality;
  bool autoFilled = false;

  String locationStatus = 'Detecting your location...';
  File? image;
  bool submitting = false;

  @override
  void initState() {
    super.initState();
    _init();
  }

  Future<void> _init() async {
    await _loadMunicipalities();
    await _detectLocation();
  }

  Future<void> _loadMunicipalities() async {
    try {
      final result = await ApiService.fetchMunicipalities();
      if (mounted) setState(() => municipalities = result);
    } catch (_) {}
  }

  Future<void> _detectLocation() async {
    setState(() => locationStatus = 'Detecting your location...');
    try {
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.denied || permission == LocationPermission.deniedForever) {
        setState(() => locationStatus = 'Location permission denied. Tap the map to place your pin.');
        return;
      }
      if (!await Geolocator.isLocationServiceEnabled()) {
        setState(() => locationStatus = 'GPS is off. Turn it on, or tap the map to place your pin.');
        return;
      }

      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.high, timeLimit: Duration(seconds: 15)),
      );
      _setPoint(LatLng(pos.latitude, pos.longitude), moveMap: true, zoom: 16);
      setState(() => locationStatus = 'Location detected. Tap the map to adjust the pin.');
    } catch (_) {
      setState(() => locationStatus = 'Could not detect location. Tap the map to place your pin.');
    }
  }

  void _setPoint(LatLng p, {bool moveMap = false, double? zoom}) {
    setState(() => point = p);
    if (moveMap && _mapReady) {
      _mapController.move(p, zoom ?? _mapController.camera.zoom);
    }
    _reverseGeocode(p);
  }

  String? _matchMunicipality(List<dynamic> candidates) {
    String norm(String s) => s.toLowerCase().replaceAll('city of', '').replaceAll('city', '').trim();
    for (final c in candidates) {
      if (c == null) continue;
      final n = norm(c.toString());
      for (final m in municipalities) {
        if (norm(m) == n) return m;
      }
    }
    return null;
  }

  Future<void> _reverseGeocode(LatLng p) async {
    try {
      final res = await http.get(
        Uri.parse('https://nominatim.openstreetmap.org/reverse?format=json&lat=${p.latitude}&lon=${p.longitude}&zoom=16&addressdetails=1'),
        headers: {'User-Agent': 'BFPReportApp/1.0', 'Accept-Language': 'en'},
      ).timeout(const Duration(seconds: 8));
      if (res.statusCode != 200) return;

      final addr = (jsonDecode(res.body)['address'] as Map<String, dynamic>?) ?? {};
      final barangay = (addr['village'] ?? addr['suburb'] ?? addr['neighbourhood'] ?? addr['quarter'] ?? '').toString();
      final muni = _matchMunicipality([addr['city'], addr['town'], addr['municipality'], addr['county']]);

      if (!mounted) return;
      setState(() {
        if (barangay.isNotEmpty) _barangayController.text = barangay;
        if (muni != null) selectedMunicipality = muni;
        autoFilled = barangay.isNotEmpty || muni != null;
      });
    } catch (_) {
      // Leave the fields for manual entry
    }
  }

  Future<void> _pickImage() async {
    final picked = await ImagePicker().pickImage(source: ImageSource.camera, imageQuality: 70);
    if (picked != null) setState(() => image = File(picked.path));
  }

  Future<void> _submit() async {
    if (point == null) {
      _snack('Please tap the map to set your location.');
      return;
    }
    if (selectedMunicipality == null || _barangayController.text.trim().isEmpty || _descriptionController.text.trim().isEmpty) {
      _snack('Please fill in Municipality, Barangay, and Description.');
      return;
    }

    setState(() => submitting = true);
    try {
      final result = await ApiService.submitReport(
        category: widget.category.label,
        municipality: selectedMunicipality!,
        barangay: _barangayController.text.trim(),
        description: _descriptionController.text.trim(),
        latitude: point!.latitude,
        longitude: point!.longitude,
        landmark: _landmarkController.text.trim().isEmpty ? null : _landmarkController.text.trim(),
        image: image,
      );

      if (!mounted) return;
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => ConfirmationScreen(
            incidentNumber: (result['incident_number'] ?? result['id'] ?? '').toString(),
          ),
        ),
      );
    } catch (e) {
      if (mounted) setState(() => submitting = false);
      _snack('Failed to submit: $e');
    }
  }

  void _snack(String msg) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Report — ${widget.category.label}')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(point != null ? Icons.check_circle : Icons.hourglass_top,
                    color: point != null ? Colors.green : Colors.orange, size: 18),
                const SizedBox(width: 8),
                Expanded(child: Text(locationStatus, style: const TextStyle(fontSize: 12))),
              ],
            ),
            const SizedBox(height: 8),

            Stack(
              children: [
                SizedBox(
                  height: 260,
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(12),
                    child: FlutterMap(
                      mapController: _mapController,
                      options: MapOptions(
                        initialCenter: _defaultCenter,
                        initialZoom: 10,
                        minZoom: 8,
                        cameraConstraint: CameraConstraint.contain(bounds: _region8Bounds),
                        onMapReady: () {
                          _mapReady = true;
                          if (point != null) _mapController.move(point!, 16);
                        },
                        onTap: (tapPosition, latLng) => _setPoint(latLng),
                      ),
                      children: [
                        TileLayer(
                          urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                          userAgentPackageName: 'com.example.bfp_edirs_app',
                        ),
                        if (point != null)
                          MarkerLayer(
                            markers: [
                              Marker(
                                point: point!,
                                width: 28,
                                height: 28,
                                child: Container(
                                  decoration: BoxDecoration(
                                    color: const Color(0xFFC62828),
                                    shape: BoxShape.circle,
                                    border: Border.all(color: Colors.white, width: 3),
                                    boxShadow: const [BoxShadow(color: Colors.black38, blurRadius: 6)],
                                  ),
                                ),
                              ),
                            ],
                          ),
                      ],
                    ),
                  ),
                ),
                Positioned(
                  top: 8,
                  right: 8,
                  child: Material(
                    color: Colors.white,
                    shape: const CircleBorder(),
                    elevation: 3,
                    child: IconButton(
                      icon: const Icon(Icons.fullscreen, color: Color(0xFFC62828)),
                      onPressed: () async {
                        final result = await Navigator.push<LatLng>(
                          context,
                          MaterialPageRoute(
                            builder: (_) => FullscreenMapScreen(initialPoint: point ?? _defaultCenter),
                          ),
                        );
                        if (result != null) {
                          _setPoint(result, moveMap: true, zoom: 16);
                        }
                      },
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 6),
            Row(
              children: [
                TextButton.icon(
                  onPressed: _detectLocation,
                  icon: const Icon(Icons.my_location, size: 18),
                  label: const Text('Use my current location'),
                ),
              ],
            ),
            const Text(
              'Tip: drag outside the map to scroll the page.',
              style: TextStyle(fontSize: 11, color: Colors.grey),
            ),
            const SizedBox(height: 12),

            DropdownButtonFormField<String>(
              value: selectedMunicipality,
              decoration: const InputDecoration(labelText: 'Municipality / City *', border: OutlineInputBorder()),
              items: municipalities.map((m) => DropdownMenuItem(value: m, child: Text(m))).toList(),
              onChanged: (v) => setState(() => selectedMunicipality = v),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _barangayController,
              decoration: const InputDecoration(labelText: 'Barangay *', border: OutlineInputBorder()),
            ),
            if (autoFilled)
              const Padding(
                padding: EdgeInsets.only(top: 4),
                child: Text('Auto-filled from your pin. Please confirm it is correct.',
                    style: TextStyle(fontSize: 11, color: Colors.green)),
              ),
            const SizedBox(height: 12),
            TextField(
              controller: _descriptionController,
              maxLines: 3,
              decoration: const InputDecoration(labelText: "What's happening? *", border: OutlineInputBorder()),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _landmarkController,
              decoration: const InputDecoration(labelText: 'Nearby Landmark', border: OutlineInputBorder()),
            ),
            const SizedBox(height: 16),
            OutlinedButton.icon(
              onPressed: _pickImage,
              icon: const Icon(Icons.camera_alt),
              label: Text(image == null ? 'Add Photo' : 'Photo Attached ✓'),
            ),
            const SizedBox(height: 24),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: submitting ? null : _submit,
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFFC62828),
                  padding: const EdgeInsets.symmetric(vertical: 14),
                ),
                child: submitting
                    ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                    : const Text('Send Report', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
              ),
            ),
          ],
        ),
      ),
    );
  }
}