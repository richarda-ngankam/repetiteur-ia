import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_theme.dart';
import '../application/auth_controller.dart';
import '../data/auth_repository.dart';

enum _Mode { login, register }

enum _Step { phone, code }

class AuthScreen extends ConsumerStatefulWidget {
  const AuthScreen({super.key});

  @override
  ConsumerState<AuthScreen> createState() => _AuthScreenState();
}

class _AuthScreenState extends ConsumerState<AuthScreen> {
  final _name = TextEditingController();
  final _number = TextEditingController();
  final _code = TextEditingController();

  _Mode _mode = _Mode.login;
  _Step _step = _Step.phone;
  bool _terms = false;
  bool _privacy = false;
  bool _busy = false;
  String? _error;

  String get _digits => _number.text.replaceAll(RegExp(r'\D'), '');
  String get _destination => '+237$_digits';
  String get _purpose => _mode == _Mode.login ? 'login' : 'register';

  @override
  void dispose() {
    _name.dispose();
    _number.dispose();
    _code.dispose();
    super.dispose();
  }

  void _setMode(_Mode mode) {
    setState(() {
      _mode = mode;
      _step = _Step.phone;
      _error = null;
      _code.clear();
    });
  }

  void _fail(String message) => setState(() => _error = message);

  Future<void> _sendCode() async {
    if (!RegExp(r'^6\d{8}$').hasMatch(_digits)) {
      return _fail('Saisis un numéro camerounais à 9 chiffres (ex. 6XXXXXXXX).');
    }
    if (_mode == _Mode.register) {
      if (_name.text.trim().length < 2) return _fail('Saisis ton nom.');
      if (!_terms || !_privacy) {
        return _fail(
            "Accepte les conditions d'utilisation et la politique de confidentialité.");
      }
    }

    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref
          .read(authRepositoryProvider)
          .sendCode(purpose: _purpose, destination: _destination);
      if (mounted) setState(() => _step = _Step.code);
    } on ApiException catch (e) {
      _fail(e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _verify() async {
    if (!RegExp(r'^\d{6}$').hasMatch(_code.text)) {
      return _fail('Le code contient 6 chiffres.');
    }

    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final repository = ref.read(authRepositoryProvider);
      final token = _mode == _Mode.login
          ? await repository.login(destination: _destination, code: _code.text)
          : await repository.register(
        destination: _destination,
        code: _code.text,
        name: _name.text.trim(),
      );
      // Le routeur redirige automatiquement vers l'accueil.
      await ref.read(authControllerProvider.notifier).setSession(token);
    } on ApiException catch (e) {
      _fail(e.status == 409
          ? 'Ce numéro a déjà un compte. Utilise « Se connecter ».'
          : e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(18),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 430),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _brand(),
                  const SizedBox(height: 22),
                  SegmentedButton<_Mode>(
                    showSelectedIcon: false,
                    segments: const [
                      ButtonSegment(value: _Mode.login, label: Text('Se connecter')),
                      ButtonSegment(value: _Mode.register, label: Text('Créer un compte')),
                    ],
                    selected: {_mode},
                    onSelectionChanged: (s) => _setMode(s.first),
                  ),
                  const SizedBox(height: 14),
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(17),
                      border: Border.all(color: AppColors.border),
                    ),
                    child: _step == _Step.phone ? _phoneStep() : _codeStep(),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _brand() {
    return Row(
      children: [
        Container(
          width: 46,
          height: 46,
          decoration: BoxDecoration(
            color: AppColors.teal,
            borderRadius: BorderRadius.circular(15),
          ),
          alignment: Alignment.center,
          child: const Text('✦', style: TextStyle(color: Colors.white, fontSize: 24)),
        ),
        const SizedBox(width: 12),
        const Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Répétiteur IA',
                  style: TextStyle(
                      fontSize: 23, fontWeight: FontWeight.w800, color: AppColors.navy)),
              Text('Apprendre, comprendre, progresser.',
                  style: TextStyle(fontSize: 12, color: AppColors.muted)),
            ],
          ),
        ),
      ],
    );
  }

  Widget _label(String text) => Padding(
    padding: const EdgeInsets.only(top: 14, bottom: 6),
    child: Text(text,
        style: const TextStyle(
            fontSize: 11, fontWeight: FontWeight.w800, color: Color(0xFF45566C))),
  );

  Widget _errorBox() => _error == null
      ? const SizedBox.shrink()
      : Container(
    margin: const EdgeInsets.only(top: 12),
    padding: const EdgeInsets.all(10),
    decoration: BoxDecoration(
      color: const Color(0xFFFDEAEA),
      borderRadius: BorderRadius.circular(10),
    ),
    child: Text(_error!,
        style: const TextStyle(
            color: Color(0xFFA52E2E), fontSize: 12, fontWeight: FontWeight.w700)),
  );

  Widget _phoneStep() {
    final register = _mode == _Mode.register;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(register ? 'Inscription parent' : 'Connexion parent',
            style: const TextStyle(
                fontSize: 15, fontWeight: FontWeight.w800, color: AppColors.navy)),
        const Text('Un code à 6 chiffres sera envoyé par SMS.',
            style: TextStyle(fontSize: 12, color: AppColors.muted)),
        if (register) ...[
          _label('Ton nom'),
          TextField(
            controller: _name,
            textCapitalization: TextCapitalization.words,
            decoration: const InputDecoration(hintText: 'Ex. Marie Ngono'),
          ),
        ],
        _label('Numéro de téléphone'),
        Row(
          children: [
            Container(
              height: 50,
              padding: const EdgeInsets.symmetric(horizontal: 12),
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: const Color(0xFFEDF2F7),
                borderRadius: BorderRadius.circular(11),
                border: Border.all(color: const Color(0xFFD7E0E9)),
              ),
              child: const Text('🇨🇲 +237', style: TextStyle(fontWeight: FontWeight.w800)),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: TextField(
                controller: _number,
                keyboardType: TextInputType.phone,
                inputFormatters: [
                  FilteringTextInputFormatter.digitsOnly,
                  LengthLimitingTextInputFormatter(9),
                ],
                decoration: const InputDecoration(hintText: '6XXXXXXXX'),
              ),
            ),
          ],
        ),
        if (register) ...[
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            controlAffinity: ListTileControlAffinity.leading,
            dense: true,
            value: _terms,
            onChanged: (v) => setState(() => _terms = v ?? false),
            title: const Text("J'accepte les conditions d'utilisation.",
                style: TextStyle(fontSize: 12)),
          ),
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            controlAffinity: ListTileControlAffinity.leading,
            dense: true,
            value: _privacy,
            onChanged: (v) => setState(() => _privacy = v ?? false),
            title: const Text("J'accepte la politique de confidentialité.",
                style: TextStyle(fontSize: 12)),
          ),
        ],
        _errorBox(),
        const SizedBox(height: 14),
        FilledButton(
          onPressed: _busy ? null : _sendCode,
          child: Text(_busy ? 'Envoi…' : 'Recevoir mon code'),
        ),
      ],
    );
  }

  Widget _codeStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Saisis ton code',
            style: TextStyle(
                fontSize: 15, fontWeight: FontWeight.w800, color: AppColors.navy)),
        Text('Envoyé au $_destination',
            style: const TextStyle(fontSize: 12, color: AppColors.muted)),
        _label('Code à 6 chiffres'),
        TextField(
          controller: _code,
          autofocus: true,
          keyboardType: TextInputType.number,
          inputFormatters: [
            FilteringTextInputFormatter.digitsOnly,
            LengthLimitingTextInputFormatter(6),
          ],
          decoration: const InputDecoration(hintText: '••••••'),
        ),
        if (kDebugMode)
          Container(
            margin: const EdgeInsets.only(top: 12),
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: const Color(0xFFFFF7E8),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Text(
              'Mode test : le code est dans backend\\storage\\logs\\laravel.log '
                  '(dernière ligne « Code $_purpose pour… »).',
              style: const TextStyle(fontSize: 11, color: Color(0xFF77501C)),
            ),
          ),
        _errorBox(),
        const SizedBox(height: 14),
        FilledButton(
          onPressed: _busy ? null : _verify,
          child: Text(_busy ? 'Vérification…' : 'Valider'),
        ),
        TextButton(
          onPressed: () => setState(() {
            _step = _Step.phone;
            _error = null;
          }),
          child: const Text('← Changer de numéro'),
        ),
      ],
    );
  }
}