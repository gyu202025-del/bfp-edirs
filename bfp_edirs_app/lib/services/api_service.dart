import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import '../models/incident_category.dart';

class ApiService {
  // Change this to your laptop's local IP (not 127.0.0.1) when testing on
  // a real phone over WiFi — e.g. 'http://192.168.1.5:8000/api'.
  // Use 'http://10.0.2.2:8000/api' if testing on the Android EMULATOR instead.
  static const String baseUrl = 'https://bfp-edirs-production.up.railway.app/api';

  static const Map<String, String> _jsonHeaders = {'Accept': 'application/json'};

  static Future<List<IncidentCategory>> fetchCategories() async {
    final res = await http
        .get(Uri.parse('$baseUrl/report/categories'), headers: _jsonHeaders)
        .timeout(const Duration(seconds: 15));
    if (res.statusCode != 200) throw Exception('Failed to load categories');
    final List data = jsonDecode(res.body);
    return data.map((e) => IncidentCategory.fromJson(e)).toList();
  }

  static Future<List<String>> fetchBarangays(String municipality) async {
    final res = await http
        .get(
          Uri.parse('$baseUrl/report/barangays').replace(queryParameters: {'municipality': municipality}),
          headers: _jsonHeaders,
        )
        .timeout(const Duration(seconds: 15));
    if (res.statusCode != 200) return [];
    return (jsonDecode(res.body) as List).map((e) => e.toString()).toList();
  }

  static Future<List<String>> fetchMunicipalities() async {
    final res = await http
        .get(Uri.parse('$baseUrl/report/municipalities'), headers: _jsonHeaders)
        .timeout(const Duration(seconds: 15));
    if (res.statusCode != 200) throw Exception('Failed to load municipalities');
    final List data = jsonDecode(res.body);
    return data.cast<String>();
  }

  static Future<Map<String, dynamic>> submitReport({
    required String category,
    required String municipality,
    required String barangay,
    required String description,
    required double latitude,
    required double longitude,
    String? landmark,
    String? reporterName,
    String? reporterContact,
    File? image,
  }) async {
    final uri = Uri.parse('$baseUrl/report');
    final request = http.MultipartRequest('POST', uri);
    request.headers['Accept'] = 'application/json';

    request.fields['category'] = category;
    request.fields['municipality'] = municipality;
    request.fields['barangay'] = barangay;
    request.fields['description'] = description;
    request.fields['latitude'] = latitude.toString();
    request.fields['longitude'] = longitude.toString();
    if (landmark != null) request.fields['landmark'] = landmark;
    if (reporterName != null) request.fields['reporter_name'] = reporterName;
    if (reporterContact != null) request.fields['reporter_contact'] = reporterContact;

    if (image != null) {
      request.files.add(await http.MultipartFile.fromPath('image', image.path));
    }

    final streamedResponse = await request.send().timeout(const Duration(seconds: 60));
    final response = await http.Response.fromStream(streamedResponse);

    if (response.statusCode == 201) {
      return jsonDecode(response.body);
    } else {
      throw Exception('Submit failed (${response.statusCode}): ${response.body}');
    }
  }
}