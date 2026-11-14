import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/children_repository.dart';
import '../domain/child.dart';

final childrenProvider = FutureProvider.autoDispose<List<Child>>(
      (ref) => ref.watch(childrenRepositoryProvider).list(),
);