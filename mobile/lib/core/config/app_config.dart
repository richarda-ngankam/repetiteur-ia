class AppConfig {
  // Émulateur Android : 10.0.2.2 désigne le PC. Vrai téléphone :
  // flutter run --dart-define=API_URL=http://IP_DU_PC:8000/api/v1
  static const apiBaseUrl = String.fromEnvironment(
    'API_URL',
    defaultValue: 'http://10.0.2.2:8000/api/v1',
  );
}