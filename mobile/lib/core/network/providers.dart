import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../storage/session_storage.dart';
import 'api_client.dart';

final sessionStorageProvider = Provider<SessionStorage>((ref) => SessionStorage());

final apiClientProvider =
Provider<ApiClient>((ref) => ApiClient(ref.watch(sessionStorageProvider)));