import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';

void main() {
  runApp(const ClubAgelaiApp());
}

/// Root widget for the Club Agelai Android app.
class ClubAgelaiApp extends StatelessWidget {
  const ClubAgelaiApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Club Agelai',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF0F4D7A)),
        useMaterial3: true,
      ),
      home: const SessionGate(),
    );
  }
}

/// Controls auth session state and displays either login or app dashboard.
class SessionGate extends StatefulWidget {
  const SessionGate({super.key});

  @override
  State<SessionGate> createState() => _SessionGateState();
}

class _SessionGateState extends State<SessionGate> {
  final ApiClient _apiClient = ApiClient();

  String? _token;
  Map<String, dynamic>? _user;
  List<dynamic> _activities = const <dynamic>[];
  List<dynamic> _reservations = const <dynamic>[];
  List<dynamic> _announcements = const <dynamic>[];

  bool _loading = false;
  String? _errorMessage;

  /// Performs local test login with pseudo Google identifier.
  Future<void> _login({
    required String googleId,
    required String email,
    required String fullName,
  }) async {
    setState(() {
      _loading = true;
      _errorMessage = null;
    });

    try {
      final Map<String, dynamic> result = await _apiClient.login(
        googleId: googleId,
        email: email,
        fullName: fullName,
      );

      final String token = result['token'] as String;
      final Map<String, dynamic> user = result['user'] as Map<String, dynamic>;

      _apiClient.setToken(token);
      setState(() {
        _token = token;
        _user = user;
      });

      await _reloadData();
    } on ApiException catch (error) {
      setState(() {
        _errorMessage = error.message;
      });
    } catch (_) {
      setState(() {
        _errorMessage = 'No se pudo iniciar sesion.';
      });
    } finally {
      setState(() {
        _loading = false;
      });
    }
  }

  /// Clears in-memory token and returns to login.
  void _logout() {
    _apiClient.setToken(null);
    setState(() {
      _token = null;
      _user = null;
      _activities = const <dynamic>[];
      _reservations = const <dynamic>[];
      _announcements = const <dynamic>[];
      _errorMessage = null;
    });
  }

  /// Reloads profile and app data from API endpoints.
  Future<void> _reloadData() async {
    if (_token == null) {
      return;
    }

    setState(() {
      _loading = true;
      _errorMessage = null;
    });

    try {
      final Map<String, dynamic> me = await _apiClient.me();
      final Map<String, dynamic> activities = await _apiClient.activities();
      final Map<String, dynamic> reservations = await _apiClient.reservations();
      final Map<String, dynamic> announcements = await _apiClient.announcements();

      setState(() {
        _user = me['user'] as Map<String, dynamic>;
        _activities = activities['activities'] as List<dynamic>;
        _reservations = reservations['reservations'] as List<dynamic>;
        _announcements = announcements['announcements'] as List<dynamic>;
      });
    } on ApiException catch (error) {
      setState(() {
        _errorMessage = error.message;
      });
    } catch (_) {
      setState(() {
        _errorMessage = 'Error al actualizar datos.';
      });
    } finally {
      setState(() {
        _loading = false;
      });
    }
  }

  /// Creates reservation and optionally confirms it with user code.
  Future<void> _reserveActivity(Map<String, dynamic> activity) async {
    final int activityId = activity['id'] as int;

    setState(() {
      _loading = true;
      _errorMessage = null;
    });

    try {
      final Map<String, dynamic> reserveResult = await _apiClient.reserve(activityId: activityId);
      final String status = reserveResult['status'] as String;

      if (status == 'waitlist') {
        if (!mounted) {
          return;
        }
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Te han añadido a la lista de espera.')),
        );
      } else if (status == 'pending_user_confirm') {
        final int reservationId = reserveResult['reservation_id'] as int;
        final String localCode = reserveResult['local_confirmation_code'] as String? ?? '';
        await _askAndConfirmReservation(
          reservationId: reservationId,
          suggestedCode: localCode,
        );
      }

      await _reloadData();
    } on ApiException catch (error) {
      setState(() {
        _errorMessage = error.message;
      });
    } catch (_) {
      setState(() {
        _errorMessage = 'No se pudo completar la reserva.';
      });
    } finally {
      setState(() {
        _loading = false;
      });
    }
  }

  /// Prompts for confirmation code and sends reservation confirmation to API.
  Future<void> _askAndConfirmReservation({
    required int reservationId,
    required String suggestedCode,
  }) async {
    final TextEditingController controller = TextEditingController(text: suggestedCode);

    final bool? accepted = await showDialog<bool>(
      context: context,
      builder: (BuildContext context) {
        return AlertDialog(
          title: const Text('Confirmar Reserva'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              const Text(
                'Introduce el codigo de confirmacion. En modo local se muestra directamente para pruebas.',
              ),
              const SizedBox(height: 8),
              TextField(
                controller: controller,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(
                  labelText: 'Codigo (6 digitos)',
                ),
              ),
            ],
          ),
          actions: <Widget>[
            TextButton(
              onPressed: () => Navigator.of(context).pop(false),
              child: const Text('Cancelar'),
            ),
            FilledButton(
              onPressed: () => Navigator.of(context).pop(true),
              child: const Text('Confirmar'),
            ),
          ],
        );
      },
    );

    if (accepted != true) {
      return;
    }

    await _apiClient.confirmReservation(
      reservationId: reservationId,
      confirmationCode: controller.text.trim(),
    );

    if (!mounted) {
      return;
    }
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Reserva confirmada por usuario. Pendiente validacion admin.')),
    );
  }

  /// Sends reservation cancellation request while respecting backend policy.
  Future<void> _cancelReservation(int reservationId) async {
    setState(() {
      _loading = true;
      _errorMessage = null;
    });

    try {
      await _apiClient.cancelReservation(reservationId: reservationId);
      await _reloadData();
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Reserva cancelada correctamente.')),
      );
    } on ApiException catch (error) {
      setState(() {
        _errorMessage = error.message;
      });
    } catch (_) {
      setState(() {
        _errorMessage = 'No se pudo cancelar la reserva.';
      });
    } finally {
      setState(() {
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_token == null) {
      return LoginScreen(
        isLoading: _loading,
        errorMessage: _errorMessage,
        onLogin: _login,
      );
    }

    final List<dynamic> modules = (_user?['modules'] as List<dynamic>?) ?? <dynamic>[];
    final bool pendingProfile = (_user?['status'] as String? ?? 'pending') != 'active' || modules.isEmpty;

    return DefaultTabController(
      length: 4,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Club Agelai'),
          actions: <Widget>[
            IconButton(
              tooltip: 'Actualizar',
              onPressed: _loading ? null : _reloadData,
              icon: const Icon(Icons.refresh),
            ),
            IconButton(
              tooltip: 'Cerrar sesion',
              onPressed: _logout,
              icon: const Icon(Icons.logout),
            ),
          ],
          bottom: const TabBar(
            isScrollable: true,
            tabs: <Widget>[
              Tab(text: 'Perfil'),
              Tab(text: 'Actividades'),
              Tab(text: 'Reservas'),
              Tab(text: 'Avisos'),
            ],
          ),
        ),
        body: Stack(
          children: <Widget>[
            TabBarView(
              children: <Widget>[
                _ProfileTab(user: _user ?? const <String, dynamic>{}, pendingProfile: pendingProfile),
                _ActivitiesTab(
                  activities: _activities,
                  pendingProfile: pendingProfile,
                  onReserve: _reserveActivity,
                ),
                _ReservationsTab(
                  reservations: _reservations,
                  onCancelReservation: _cancelReservation,
                ),
                _AnnouncementsTab(announcements: _announcements),
              ],
            ),
            if (_loading)
              const Align(
                alignment: Alignment.topCenter,
                child: LinearProgressIndicator(),
              ),
            if (_errorMessage != null)
              Align(
                alignment: Alignment.bottomCenter,
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Material(
                    color: Colors.red.shade100,
                    borderRadius: BorderRadius.circular(12),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                      child: Text(
                        _errorMessage!,
                        style: TextStyle(color: Colors.red.shade900),
                      ),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

/// Login screen with pseudo Google registration fields for local test flow.
class LoginScreen extends StatefulWidget {
  const LoginScreen({
    required this.isLoading,
    required this.errorMessage,
    required this.onLogin,
    super.key,
  });

  final bool isLoading;
  final String? errorMessage;
  final Future<void> Function({
    required String googleId,
    required String email,
    required String fullName,
  }) onLogin;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _googleIdController = TextEditingController();
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _nameController = TextEditingController();

  @override
  void dispose() {
    _googleIdController.dispose();
    _emailController.dispose();
    _nameController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    await widget.onLogin(
      googleId: _googleIdController.text.trim(),
      email: _emailController.text.trim(),
      fullName: _nameController.text.trim(),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 460),
          child: Card(
            margin: const EdgeInsets.all(16),
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Form(
                key: _formKey,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    const Text(
                      'Club Agelai',
                      style: TextStyle(fontSize: 24, fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: 6),
                    const Text('Registro/Login local con ID de Google (modo pruebas).'),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _googleIdController,
                      decoration: const InputDecoration(labelText: 'Google ID'),
                      validator: (String? value) {
                        if (value == null || value.trim().length < 4) {
                          return 'Introduce un Google ID valido.';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 8),
                    TextFormField(
                      controller: _emailController,
                      decoration: const InputDecoration(labelText: 'Email'),
                      validator: (String? value) {
                        if (value == null || !value.contains('@')) {
                          return 'Introduce un email valido.';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 8),
                    TextFormField(
                      controller: _nameController,
                      decoration: const InputDecoration(labelText: 'Nombre completo'),
                      validator: (String? value) {
                        if (value == null || value.trim().length < 2) {
                          return 'Introduce tu nombre.';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 12),
                    FilledButton(
                      onPressed: widget.isLoading ? null : _submit,
                      child: const Text('Entrar'),
                    ),
                    if (widget.errorMessage != null) ...<Widget>[
                      const SizedBox(height: 12),
                      Text(
                        widget.errorMessage!,
                        style: TextStyle(color: Colors.red.shade800),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Shows profile and granted modules, or pending profile warning.
class _ProfileTab extends StatelessWidget {
  const _ProfileTab({
    required this.user,
    required this.pendingProfile,
  });

  final Map<String, dynamic> user;
  final bool pendingProfile;

  @override
  Widget build(BuildContext context) {
    final List<dynamic> modules = (user['modules'] as List<dynamic>?) ?? <dynamic>[];
    return ListView(
      padding: const EdgeInsets.all(16),
      children: <Widget>[
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text('Nombre: ${user['full_name'] ?? ''}'),
                const SizedBox(height: 6),
                Text('Email: ${user['email'] ?? ''}'),
                const SizedBox(height: 6),
                Text('Estado: ${user['status'] ?? ''}'),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        if (pendingProfile)
          Card(
            color: Colors.amber.shade100,
            child: const Padding(
              padding: EdgeInsets.all(16),
              child: Text(
                'Pendiente de asignar perfil: un administrador debe activar tus modulos.',
              ),
            ),
          ),
        const SizedBox(height: 12),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                const Text(
                  'Modulos habilitados',
                  style: TextStyle(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 8),
                if (modules.isEmpty)
                  const Text('Sin modulos asignados.')
                else
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: modules.map((dynamic module) {
                      final Map<String, dynamic> moduleMap = module as Map<String, dynamic>;
                      return Chip(
                        label: Text(moduleMap['name'] as String? ?? ''),
                      );
                    }).toList(),
                  ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

/// Lists activities and enables reservation action when profile is active.
class _ActivitiesTab extends StatelessWidget {
  const _ActivitiesTab({
    required this.activities,
    required this.pendingProfile,
    required this.onReserve,
  });

  final List<dynamic> activities;
  final bool pendingProfile;
  final Future<void> Function(Map<String, dynamic>) onReserve;

  @override
  Widget build(BuildContext context) {
    if (pendingProfile) {
      return const Center(
        child: Padding(
          padding: EdgeInsets.all(16),
          child: Text('Sin acceso a actividades hasta asignacion de perfil.'),
        ),
      );
    }

    if (activities.isEmpty) {
      return const Center(child: Text('No hay actividades disponibles.'));
    }

    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: activities.length,
      itemBuilder: (BuildContext context, int index) {
        final Map<String, dynamic> activity = activities[index] as Map<String, dynamic>;
        final int remaining = activity['remaining_slots'] as int? ?? 0;
        return Card(
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  activity['title'] as String? ?? '',
                  style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 4),
                Text('Modulo: ${activity['module_code'] ?? ''}'),
                Text('Horario: ${activity['starts_at'] ?? ''}'),
                Text('Lugar: ${activity['location'] ?? ''}'),
                Text('Plazas libres: $remaining'),
                const SizedBox(height: 10),
                FilledButton(
                  onPressed: () => onReserve(activity),
                  child: Text(remaining > 0 ? 'Reservar' : 'Entrar en lista de espera'),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

/// Lists user reservations and allows cancellation when policy allows.
class _ReservationsTab extends StatelessWidget {
  const _ReservationsTab({
    required this.reservations,
    required this.onCancelReservation,
  });

  final List<dynamic> reservations;
  final Future<void> Function(int reservationId) onCancelReservation;

  @override
  Widget build(BuildContext context) {
    if (reservations.isEmpty) {
      return const Center(child: Text('No tienes reservas.'));
    }

    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: reservations.length,
      itemBuilder: (BuildContext context, int index) {
        final Map<String, dynamic> reservation = reservations[index] as Map<String, dynamic>;
        final Map<String, dynamic> activity = reservation['activity'] as Map<String, dynamic>;
        final String status = reservation['status'] as String? ?? '';

        return Card(
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  activity['title'] as String? ?? '',
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 4),
                Text('Estado: $status'),
                Text('Modulo: ${activity['module_code'] ?? ''}'),
                Text('Fecha: ${activity['starts_at'] ?? ''}'),
                Text('Lugar: ${activity['location'] ?? ''}'),
                const SizedBox(height: 10),
                if (status != 'cancelled')
                  OutlinedButton(
                    onPressed: () => onCancelReservation(reservation['id'] as int),
                    child: const Text('Cancelar reserva'),
                  ),
              ],
            ),
          ),
        );
      },
    );
  }
}

/// Displays active announcements published from web dashboard.
class _AnnouncementsTab extends StatelessWidget {
  const _AnnouncementsTab({
    required this.announcements,
  });

  final List<dynamic> announcements;

  @override
  Widget build(BuildContext context) {
    if (announcements.isEmpty) {
      return const Center(child: Text('Sin avisos activos.'));
    }

    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: announcements.length,
      itemBuilder: (BuildContext context, int index) {
        final Map<String, dynamic> announcement = announcements[index] as Map<String, dynamic>;
        final String moduleCode = announcement['module_code'] as String? ?? 'general';
        return Card(
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  announcement['title'] as String? ?? '',
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 4),
                Text('Modulo: $moduleCode'),
                const SizedBox(height: 6),
                Text(announcement['body'] as String? ?? ''),
              ],
            ),
          ),
        );
      },
    );
  }
}

/// Lightweight API client based on dart:io HttpClient to avoid extra dependencies.
class ApiClient {
  /// For Android emulator localhost mapping use 10.0.2.2.
  static const String _baseUrl = 'http://10.0.2.2:8000';

  String? _token;

  void setToken(String? token) {
    _token = token;
  }

  Future<Map<String, dynamic>> login({
    required String googleId,
    required String email,
    required String fullName,
  }) async {
    return _request(
      method: 'POST',
      path: '/api/auth/google-login',
      body: <String, dynamic>{
        'google_id': googleId,
        'email': email,
        'full_name': fullName,
      },
      authRequired: false,
    );
  }

  Future<Map<String, dynamic>> me() => _request(method: 'GET', path: '/api/me');

  Future<Map<String, dynamic>> activities() => _request(method: 'GET', path: '/api/activities');

  Future<Map<String, dynamic>> reservations() => _request(method: 'GET', path: '/api/reservations');

  Future<Map<String, dynamic>> announcements() => _request(method: 'GET', path: '/api/announcements');

  Future<Map<String, dynamic>> reserve({required int activityId}) => _request(
        method: 'POST',
        path: '/api/activities/$activityId/reserve',
      );

  Future<Map<String, dynamic>> confirmReservation({
    required int reservationId,
    required String confirmationCode,
  }) =>
      _request(
        method: 'POST',
        path: '/api/reservations/$reservationId/confirm',
        body: <String, dynamic>{
          'confirmation_code': confirmationCode,
        },
      );

  Future<Map<String, dynamic>> cancelReservation({required int reservationId}) => _request(
        method: 'POST',
        path: '/api/reservations/$reservationId/cancel',
      );

  /// Generic JSON request wrapper with secure defaults and rich error handling.
  Future<Map<String, dynamic>> _request({
    required String method,
    required String path,
    Map<String, dynamic>? body,
    bool authRequired = true,
  }) async {
    final HttpClient client = HttpClient();
    client.connectionTimeout = const Duration(seconds: 12);

    try {
      final Uri uri = Uri.parse('$_baseUrl$path');
      final HttpClientRequest request = await client.openUrl(method, uri);
      request.headers.set(HttpHeaders.contentTypeHeader, 'application/json');
      request.headers.set(HttpHeaders.acceptHeader, 'application/json');

      if (authRequired) {
        if (_token == null) {
          throw ApiException('Sesion no disponible.');
        }
        request.headers.set(HttpHeaders.authorizationHeader, 'Bearer $_token');
      }

      if (body != null) {
        request.write(jsonEncode(body));
      }

      final HttpClientResponse response = await request.close();
      final String responseBody = await response.transform(utf8.decoder).join();
      final dynamic decoded = responseBody.isEmpty ? <String, dynamic>{} : jsonDecode(responseBody);

      if (decoded is! Map<String, dynamic>) {
        throw ApiException('Respuesta del servidor no valida.');
      }

      final bool ok = decoded['ok'] as bool? ?? false;
      if (!ok || response.statusCode >= 400) {
        throw ApiException(decoded['message'] as String? ?? 'Error de API.');
      }

      return decoded;
    } on SocketException {
      throw ApiException(
        'No hay conexion con el backend. Verifica que PHP este en http://127.0.0.1:8000.',
      );
    } finally {
      client.close(force: true);
    }
  }
}

/// Controlled exception type for expected API failures.
class ApiException implements Exception {
  ApiException(this.message);

  final String message;

  @override
  String toString() => message;
}

