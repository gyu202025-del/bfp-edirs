import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

class FullscreenMapScreen extends StatefulWidget {
  final LatLng initialPoint;
  const FullscreenMapScreen({super.key, required this.initialPoint});

  @override
  State<FullscreenMapScreen> createState() => _FullscreenMapScreenState();
}

class _FullscreenMapScreenState extends State<FullscreenMapScreen> {
  // Region 8 (Eastern Visayas) bounds — same as the inline map
  static final LatLngBounds _region8Bounds = LatLngBounds(LatLng(9.5, 123.8), LatLng(12.8, 125.9));

  late LatLng point;

  @override
  void initState() {
    super.initState();
    point = widget.initialPoint;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Set Your Location'),
        backgroundColor: const Color(0xFFC62828),
        foregroundColor: Colors.white,
      ),
      body: Stack(
        children: [
          FlutterMap(
            options: MapOptions(
              initialCenter: point,
              initialZoom: 16,
              minZoom: 8,
              cameraConstraint: CameraConstraint.contain(bounds: _region8Bounds),
              onTap: (tapPosition, latLng) => setState(() => point = latLng),
            ),
            children: [
              TileLayer(
                urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                userAgentPackageName: 'com.example.bfp_edirs_app',
              ),
              MarkerLayer(
                markers: [
                  Marker(
                    point: point,
                    width: 32,
                    height: 32,
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
          Positioned(
            top: 12,
            left: 12,
            right: 12,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(10),
                boxShadow: const [BoxShadow(color: Colors.black26, blurRadius: 6)],
              ),
              child: const Text(
                'Tap anywhere on the map to place your pin',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 12.5),
              ),
            ),
          ),
        ],
      ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: () => Navigator.pop(context, point),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFFC62828),
                padding: const EdgeInsets.symmetric(vertical: 14),
              ),
              child: const Text('Confirm Location', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
            ),
          ),
        ),
      ),
    );
  }
}