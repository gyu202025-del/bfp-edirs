class IncidentCategory {
  final String slug;
  final String label;
  final String agency;

  IncidentCategory({required this.slug, required this.label, required this.agency});

  factory IncidentCategory.fromJson(Map<String, dynamic> json) {
    return IncidentCategory(
      slug: json['slug'],
      label: json['label'],
      agency: json['agency'],
    );
  }

  static const Map<String, String> emojis = {
    'fire': '🔥',
    'medical': '🚑',
    'crime': '🚓',
    'accident': '⚠️',
  };

  String get emoji => emojis[slug] ?? '📍';
}