import 'package:flutter_test/flutter_test.dart';
import 'package:repetiteur_ia/features/children/domain/child.dart';

void main() {
  group('Child.fromJson', () {
    test('désérialise correctement un enfant valide', () {
      final json = {
        'id': '123e4567-e89b-12d3-a456-426614174000',
        'first_name': 'Aïcha',
        'age': 15,
        'level_code': '3eme',
        'track_code': 'Générale',
        'my_role': 'payer',
      };

      final child = Child.fromJson(json);

      expect(child.id, '123e4567-e89b-12d3-a456-426614174000');
      expect(child.firstName, 'Aïcha');
      expect(child.age, 15);
      expect(child.levelCode, '3eme');
      expect(child.trackCode, 'Générale');
      expect(child.myRole, 'payer');
    });

    test('gère un track_code null', () {
      final json = {
        'id': 'abc',
        'first_name': 'Samuel',
        'age': 10,
        'level_code': '6eme',
        'track_code': null,
        'my_role': 'follower',
      };

      final child = Child.fromJson(json);

      expect(child.trackCode, isNull);
      expect(child.myRole, 'follower');
    });
  });
}
