import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:geolocator/geolocator.dart';
import 'package:http/http.dart' as http;
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
  static final LatLngBounds _region8Bounds = LatLngBounds(
    LatLng(9.5, 123.8),
    LatLng(12.8, 125.9),
  );

  final _mapController = MapController();
  final _descriptionController = TextEditingController();
  final _landmarkController = TextEditingController();
  final _locationSearchController = TextEditingController();

  bool _mapReady = false;
  LatLng? point;
  List<String> municipalities = [];
  List<String> barangays = [];
  String? selectedMunicipality;
  String? selectedBarangay;
  String? _municipalitiesError;
  String? _barangaysError;
  bool _barangaysLoading = false;
  bool autoFilled = false;

  String locationStatus = 'Detecting your location...';
  bool detectingLocation = false;
  bool _locationDetailsLoading = false;
  bool _locationSearchLoading = false;
  bool _locationSearchCompleted = false;
  String? _locationSearchError;
  List<_PlaceSuggestion> _locationSuggestions = [];
  Timer? _searchDebounce;
  int _placeSearchRequestId = 0;
  int _reverseGeocodeRequestId = 0;
  int _barangaysRequestId = 0;
  Future<void> _nominatimQueue = Future<void>.value();
  DateTime? _lastNominatimRequestAt;
  bool submitting = false;

  @override
  void initState() {
    super.initState();
    _init();
  }

  Future<void> _init() async {
    await Future.wait([_loadMunicipalities(), _detectLocation()]);
  }

  Future<void> _loadMunicipalities() async {
    try {
      final result = await ApiService.fetchMunicipalities();
      if (!mounted) return;
      setState(() {
        municipalities = result;
        _municipalitiesError = null;
      });
      final currentPoint = point;
      if (currentPoint != null) {
        _reverseGeocode(currentPoint, ++_reverseGeocodeRequestId);
      }
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _municipalitiesError =
            'Could not load municipalities. Check your connection and retry.';
      });
      debugPrint('Failed to load municipalities: $error');
    }
  }

  Future<void> _detectLocation() async {
    if (!mounted || detectingLocation) return;
    setState(() {
      detectingLocation = true;
      locationStatus = 'Detecting your location...';
    });
    try {
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) {
        if (mounted) {
          setState(() {
            locationStatus =
                'Location permission denied. Tap the map to place your pin.';
          });
        }
        return;
      }
      if (!await Geolocator.isLocationServiceEnabled()) {
        if (mounted) {
          setState(() {
            locationStatus =
                'GPS is off. Turn it on, or tap the map to place your pin.';
          });
        }
        return;
      }

      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.bestForNavigation,
          distanceFilter: 0,
          timeLimit: Duration(seconds: 30),
        ),
      );
      if (!mounted) return;
      _setPoint(LatLng(pos.latitude, pos.longitude), moveMap: true, zoom: 16);
    } catch (error) {
      if (mounted) {
        setState(() {
          locationStatus =
              'Could not detect location. Tap the map to place your pin.';
        });
      }
      debugPrint('Failed to detect location: $error');
    } finally {
      if (mounted) setState(() => detectingLocation = false);
    }
  }

  void _setPoint(
    LatLng newPoint, {
    bool moveMap = false,
    double? zoom,
    String? locationLabel,
  }) {
    final requestId = ++_reverseGeocodeRequestId;
    _barangaysRequestId++;
    _searchDebounce?.cancel();
    setState(() {
      point = newPoint;
      selectedMunicipality = null;
      selectedBarangay = null;
      barangays = [];
      _barangaysError = null;
      _barangaysLoading = false;
      autoFilled = false;
      _locationDetailsLoading = true;
      locationStatus = 'Identifying municipality and barangay...';
      _locationSuggestions = [];
      _locationSearchLoading = false;
      _locationSearchCompleted = false;
      _locationSearchError = null;
      _locationSearchController.text = locationLabel ?? '';
    });
    if (moveMap && _mapReady) {
      _mapController.move(newPoint, zoom ?? _mapController.camera.zoom);
    }
    _reverseGeocode(newPoint, requestId);
  }

  String _normalizePlace(String value) {
    return value
        .toLowerCase()
        .replaceAll(
          RegExp(r'\b(city of|city|municipality of|municipality)\b'),
          '',
        )
        .replaceAll(RegExp(r'^(barangay|brgy\.?)\s*'), '')
        .replaceAll(RegExp(r'\s*\(pob\.\)\s*$'), '')
        .replaceAll(RegExp(r'[^a-z0-9]'), '');
  }

  String? _matchMunicipality(Iterable<dynamic> candidates) {
    for (final candidate in candidates) {
      if (candidate == null) continue;
      final normalizedCandidate = _normalizePlace(candidate.toString());
      for (final municipality in municipalities) {
        if (_normalizePlace(municipality) == normalizedCandidate) {
          return municipality;
        }
      }
    }
    return null;
  }

  String? _matchBarangay(Iterable<String> candidates, List<String> available) {
    for (final candidate in candidates) {
      final normalizedCandidate = _normalizePlace(candidate);
      if (normalizedCandidate.isEmpty) continue;
      for (final barangay in available) {
        if (_normalizePlace(barangay) == normalizedCandidate) return barangay;
      }
    }
    return null;
  }

  Future<bool> _waitForNominatimSlot(bool Function() isCurrent) {
    final previousRequest = _nominatimQueue;
    final slotReady = Completer<void>();
    _nominatimQueue = slotReady.future;
    return previousRequest.then((_) async {
      if (!isCurrent()) {
        slotReady.complete();
        return false;
      }
      final lastRequest = _lastNominatimRequestAt;
      if (lastRequest != null) {
        final wait =
            const Duration(seconds: 1) - DateTime.now().difference(lastRequest);
        if (wait > Duration.zero) await Future<void>.delayed(wait);
      }
      if (!isCurrent()) {
        slotReady.complete();
        return false;
      }
      _lastNominatimRequestAt = DateTime.now();
      slotReady.complete();
      return true;
    });
  }

  Future<void> _reverseGeocode(LatLng location, int requestId) async {
    try {
      if (!await _waitForNominatimSlot(
        () => mounted && requestId == _reverseGeocodeRequestId,
      )) {
        return;
      }
      final response = await http
          .get(
            Uri.https('nominatim.openstreetmap.org', '/reverse', {
              'format': 'jsonv2',
              'lat': location.latitude.toString(),
              'lon': location.longitude.toString(),
              'zoom': '16',
              'addressdetails': '1',
            }),
            headers: {
              'User-Agent': 'BFPReportApp/1.0',
              'Accept-Language': 'en',
            },
          )
          .timeout(const Duration(seconds: 8));
      if (response.statusCode != 200) {
        throw Exception('Location lookup failed (${response.statusCode})');
      }

      final data = jsonDecode(response.body);
      if (data is! Map<String, dynamic> ||
          data['address'] is! Map<String, dynamic>) {
        throw const FormatException('Location lookup returned no address');
      }
      final address = data['address'] as Map<String, dynamic>;
      if (!mounted || requestId != _reverseGeocodeRequestId) return;

      final municipality = _matchMunicipality([
        address['city'],
        address['town'],
        address['municipality'],
        address['county'],
      ]);
      if (municipality == null) {
        setState(() {
          _locationDetailsLoading = false;
          locationStatus = 'Location selected. Choose the correct municipality and barangay.';
        });
        return;
      }

      final detectedBarangays = [
        address['barangay'],
        address['village'],
        address['suburb'],
        address['neighbourhood'],
        address['quarter'],
      ].whereType<String>().where((name) => name.trim().isNotEmpty);
      await _loadBarangays(
        municipality,
        detectedBarangays: detectedBarangays,
        reverseRequestId: requestId,
      );
    } catch (error) {
      if (!mounted || requestId != _reverseGeocodeRequestId) return;
      setState(() {
        _locationDetailsLoading = false;
        locationStatus =
            'Location selected. Choose the correct municipality and barangay.';
      });
      debugPrint('Failed to identify location details: $error');
    }
  }

  Future<void> _loadBarangays(
    String municipality, {
    Iterable<String> detectedBarangays = const [],
    int? reverseRequestId,
  }) async {
    final requestId = ++_barangaysRequestId;
    setState(() {
      selectedMunicipality = municipality;
      selectedBarangay = null;
      barangays = [];
      _barangaysError = null;
      _barangaysLoading = true;
    });
    try {
      final result = await ApiService.fetchBarangays(municipality);
      if (!mounted ||
          requestId != _barangaysRequestId ||
          (reverseRequestId != null &&
              reverseRequestId != _reverseGeocodeRequestId)) {
        return;
      }
      final matchedBarangay = _matchBarangay(detectedBarangays, result);
      setState(() {
        barangays = result;
        selectedBarangay = matchedBarangay;
        _barangaysLoading = false;
        autoFilled = matchedBarangay != null;
        if (reverseRequestId != null) {
          _locationDetailsLoading = false;
          locationStatus = matchedBarangay != null
              ? 'Location detected. Confirm the municipality and barangay.'
              : 'Municipality detected. Select the correct barangay.';
          if (_locationSearchController.text.isEmpty) {
            _locationSearchController.text = [
              ?matchedBarangay,
              municipality,
            ].join(', ');
          }
        }
      });
    } catch (error) {
      if (!mounted ||
          requestId != _barangaysRequestId ||
          (reverseRequestId != null &&
              reverseRequestId != _reverseGeocodeRequestId)) {
        return;
      }
      setState(() {
        _barangaysLoading = false;
        _barangaysError = 'Could not load barangays. Please retry or select another municipality.';
        if (reverseRequestId != null) {
          _locationDetailsLoading = false;
          locationStatus =
              'Municipality detected. Select the barangay manually.';
        }
      });
      debugPrint('Failed to load barangays for $municipality: $error');
    }
  }

  void _onMunicipalityChanged(String? municipality) {
    _reverseGeocodeRequestId++;
    setState(() {
      _locationDetailsLoading = false;
      selectedMunicipality = municipality;
      selectedBarangay = null;
      barangays = [];
      _barangaysError = null;
      _barangaysLoading = municipality != null;
      autoFilled = false;
      if (municipality != null) {
        locationStatus = 'Select the barangay for $municipality.';
      }
    });
    if (municipality != null) {
      _loadBarangays(municipality);
    } else {
      _barangaysRequestId++;
    }
  }

  void _onLocationSearchChanged(String query) {
    _searchDebounce?.cancel();
    final requestId = ++_placeSearchRequestId;
    final trimmedQuery = query.trim();
    setState(() {
      _locationSuggestions = [];
      _locationSearchError = null;
      _locationSearchCompleted = false;
      _locationSearchLoading = trimmedQuery.length >= 3;
    });
    if (trimmedQuery.length < 3) {
      setState(() => _locationSearchLoading = false);
      return;
    }
    _searchDebounce = Timer(const Duration(seconds: 1), () {
      _searchPlaces(trimmedQuery, requestId);
    });
  }

  Future<void> _searchPlaces(String query, int requestId) async {
    try {
      if (!await _waitForNominatimSlot(
        () => mounted && requestId == _placeSearchRequestId,
      )) {
        return;
      }
      final response = await http
          .get(
            Uri.https('nominatim.openstreetmap.org', '/search', {
              'format': 'jsonv2',
              'addressdetails': '1',
              'countrycodes': 'ph',
              'viewbox': '123.8,12.8,125.9,9.5',
              'bounded': '1',
              'limit': '5',
              'q': query,
            }),
            headers: {
              'User-Agent': 'BFPReportApp/1.0',
              'Accept-Language': 'en',
            },
          )
          .timeout(const Duration(seconds: 12));
      if (response.statusCode != 200) {
        throw Exception('Place search failed (${response.statusCode})');
      }
      final data = jsonDecode(response.body);
      if (data is! List) {
        throw const FormatException('Place search returned invalid results');
      }
      final suggestions = data
          .whereType<Map<String, dynamic>>()
          .where(
            (place) =>
                place['display_name'] is String &&
                double.tryParse(place['lat']?.toString() ?? '') != null &&
                double.tryParse(place['lon']?.toString() ?? '') != null,
          )
          .map(
            (place) => _PlaceSuggestion(
              displayName: place['display_name'] as String,
              location: LatLng(
                double.parse(place['lat'].toString()),
                double.parse(place['lon'].toString()),
              ),
            ),
          )
          .toList();
      if (!mounted || requestId != _placeSearchRequestId) return;
      setState(() {
        _locationSuggestions = suggestions;
        _locationSearchLoading = false;
        _locationSearchCompleted = true;
      });
    } catch (error) {
      if (!mounted || requestId != _placeSearchRequestId) return;
      setState(() {
        _locationSearchLoading = false;
        _locationSearchCompleted = true;
        _locationSearchError =
            'Could not search locations. Check your connection and try again.';
      });
      debugPrint('Failed to search locations: $error');
    }
  }

  void _selectPlace(_PlaceSuggestion suggestion) {
    FocusScope.of(context).unfocus();
    _setPoint(
      suggestion.location,
      moveMap: true,
      zoom: 16,
      locationLabel: suggestion.displayName,
    );
  }

  Future<void> _submit() async {
    if (point == null) {
      _snack('Please tap the map to set your location.');
      return;
    }
    if (selectedMunicipality == null || selectedBarangay == null) {
      _snack('Please select a Municipality and Barangay.');
      return;
    }

    setState(() => submitting = true);
    try {
      final result = await ApiService.submitReport(
        category: widget.category.label,
        municipality: selectedMunicipality!,
        barangay: selectedBarangay!,
        description: _descriptionController.text.trim(),
        latitude: point!.latitude,
        longitude: point!.longitude,
        landmark: _landmarkController.text.trim().isEmpty
            ? null
            : _landmarkController.text.trim(),
      );

      if (!mounted) return;
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => ConfirmationScreen(
            incidentNumber: (result['incident_number'] ?? result['id'] ?? '')
                .toString(),
          ),
        ),
      );
    } catch (error) {
      if (mounted) setState(() => submitting = false);
      _snack('Failed to submit: $error');
    }
  }

  void _snack(String message) {
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _descriptionController.dispose();
    _landmarkController.dispose();
    _locationSearchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      resizeToAvoidBottomInset: true,
      appBar: AppBar(title: Text('Report — ${widget.category.label}')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                if (detectingLocation || _locationDetailsLoading)
                  const SizedBox(
                    height: 18,
                    width: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                else
                  Icon(
                    point != null ? Icons.check_circle : Icons.hourglass_top,
                    color: point != null ? Colors.green : Colors.orange,
                    size: 18,
                  ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    locationStatus,
                    style: const TextStyle(fontSize: 12),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _locationSearchController,
              onChanged: _onLocationSearchChanged,
              onSubmitted: (_) {
                if (_locationSuggestions.isNotEmpty) {
                  _selectPlace(_locationSuggestions.first);
                }
              },
              textInputAction: TextInputAction.search,
              decoration: InputDecoration(
                labelText: 'Search for a place or address',
                hintText: 'Search within Leyte and Eastern Visayas',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: _locationSearchLoading
                    ? const Padding(
                        padding: EdgeInsets.all(14),
                        child: SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                      )
                    : null,
                border: const OutlineInputBorder(),
              ),
            ),
            if (_locationSuggestions.isNotEmpty)
              Card(
                margin: const EdgeInsets.only(top: 4),
                child: Column(
                  children: _locationSuggestions
                      .map(
                        (suggestion) => ListTile(
                          dense: true,
                          leading: const Icon(Icons.location_on_outlined),
                          title: Text(
                            suggestion.displayName,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                          ),
                          onTap: () => _selectPlace(suggestion),
                        ),
                      )
                      .toList(),
                ),
              ),
            if (_locationSearchError != null)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text(
                  _locationSearchError!,
                  style: const TextStyle(color: Colors.red, fontSize: 12),
                ),
              ),
            if (_locationSearchCompleted &&
                _locationSearchError == null &&
                _locationSuggestions.isEmpty)
              const Padding(
                padding: EdgeInsets.only(top: 8),
                child: Text(
                  'No matching places found.',
                  style: TextStyle(color: Colors.grey, fontSize: 12),
                ),
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
                        cameraConstraint: CameraConstraint.contain(
                          bounds: _region8Bounds,
                        ),
                        onMapReady: () {
                          _mapReady = true;
                          if (point != null) _mapController.move(point!, 16);
                        },
                        onTap: (tapPosition, latLng) => _setPoint(latLng),
                      ),
                      children: [
                        TileLayer(
                          urlTemplate:
                              'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
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
                                    border: Border.all(
                                      color: Colors.white,
                                      width: 3,
                                    ),
                                    boxShadow: const [
                                      BoxShadow(
                                        color: Colors.black38,
                                        blurRadius: 6,
                                      ),
                                    ],
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
                      icon: const Icon(
                        Icons.fullscreen,
                        color: Color(0xFFC62828),
                      ),
                      onPressed: () async {
                        final result = await Navigator.push<LatLng>(
                          context,
                          MaterialPageRoute(
                            builder: (_) => FullscreenMapScreen(
                              initialPoint: point ?? _defaultCenter,
                            ),
                          ),
                        );
                        if (result != null && mounted) {
                          _setPoint(result, moveMap: true, zoom: 16);
                        }
                      },
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 6),
            TextButton.icon(
              onPressed: detectingLocation ? null : _detectLocation,
              icon: const Icon(Icons.my_location, size: 18),
              label: const Text('Use my current location'),
            ),
            const Text(
              'Tip: drag outside the map to scroll the page.',
              style: TextStyle(fontSize: 11, color: Colors.grey),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              key: ValueKey(selectedMunicipality),
              initialValue: selectedMunicipality,
              decoration: InputDecoration(
                labelText: 'Municipality / City *',
                border: const OutlineInputBorder(),
                errorText: _municipalitiesError,
              ),
              items: municipalities
                  .map(
                    (municipality) => DropdownMenuItem(
                      value: municipality,
                      child: Text(municipality),
                    ),
                  )
                  .toList(),
              onChanged: municipalities.isEmpty ? null : _onMunicipalityChanged,
            ),
            if (_municipalitiesError != null)
              TextButton(
                onPressed: _loadMunicipalities,
                child: const Text('Retry loading municipalities'),
              ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              key: ValueKey('$selectedMunicipality-$selectedBarangay'),
              initialValue: selectedBarangay,
              decoration: InputDecoration(
                labelText: 'Barangay *',
                border: const OutlineInputBorder(),
                hintText: selectedMunicipality == null
                    ? 'Select a municipality first'
                    : _barangaysLoading
                    ? 'Loading barangays...'
                    : 'Select a barangay',
                errorText: _barangaysError,
              ),
              items: barangays
                  .map(
                    (barangay) => DropdownMenuItem(
                      value: barangay,
                      child: Text(barangay),
                    ),
                  )
                  .toList(),
              onChanged:
                  selectedMunicipality == null ||
                      _barangaysLoading ||
                      barangays.isEmpty
                  ? null
                  : (value) => setState(() {
                      selectedBarangay = value;
                      autoFilled = false;
                    }),
            ),
            if (_barangaysError != null)
              TextButton(
                onPressed: selectedMunicipality == null
                    ? null
                    : () => _loadBarangays(selectedMunicipality!),
                child: const Text('Retry loading barangays'),
              ),
            if (autoFilled)
              const Padding(
                padding: EdgeInsets.only(top: 4),
                child: Text(
                  'Auto-filled from the selected location. Please confirm it is correct.',
                  style: TextStyle(fontSize: 11, color: Colors.green),
                ),
              ),
            const SizedBox(height: 12),
            TextField(
              controller: _descriptionController,
              maxLines: 3,
              decoration: const InputDecoration(
                labelText: "What's happening? (optional)",
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _landmarkController,
              decoration: const InputDecoration(
                labelText: 'Nearby Landmark (optional)',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 16),
          ],
        ),
      ),
      bottomNavigationBar: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
          child: SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: submitting ? null : _submit,
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFFC62828),
                padding: const EdgeInsets.symmetric(vertical: 14),
              ),
              child: submitting
                  ? const SizedBox(
                      height: 20,
                      width: 20,
                      child: CircularProgressIndicator(
                        color: Colors.white,
                        strokeWidth: 2,
                      ),
                    )
                  : const Text(
                      'Send Report',
                      style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
            ),
          ),
        ),
      ),
    );
  }
}

class _PlaceSuggestion {
  final String displayName;
  final LatLng location;

  const _PlaceSuggestion({required this.displayName, required this.location});
}
