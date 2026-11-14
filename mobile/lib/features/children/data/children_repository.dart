import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/providers.dart';
import '../domain/child.dart';

class ChildrenRepository {
  ChildrenRepository(this._api);

  final ApiClient _api;

  Future<List<Child>> list() async {
    final response = await _api.request('GET', '/children');
    final items = (response['children'] as List?) ?? const [];
    return items.map((e) => Child.fromJson(e as Map<String, dynamic>)).toList();
  }
}

final childrenRepositoryProvider = Provider<ChildrenRepository>(
      (ref) => ChildrenRepository(ref.watch(apiClientProvider)),
);