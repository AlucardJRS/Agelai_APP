import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

void main() {
  runApp(const ClubAgelaiApp());
}

/// Root app widget with a modern blue/amber visual identity.
class ClubAgelaiApp extends StatelessWidget {
  const ClubAgelaiApp({super.key});

  @override
  Widget build(BuildContext context) {
    const Color seed = Color(0xFF0D5F9C);
    final ColorScheme scheme = ColorScheme.fromSeed(
      seedColor: seed,
      brightness: Brightness.light,
    );

    return MaterialApp(
      title: 'Club Agelai',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: scheme,
        useMaterial3: true,
        scaffoldBackgroundColor: const Color(0xFFF2F7FB),
        cardTheme: const CardThemeData(
          elevation: 2,
          margin: EdgeInsets.zero,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.all(Radius.circular(20)),
          ),
        ),
      ),
      home: const SessionGate(),
    );
  }
}

/// Manages login session and all data loaded from the backend.
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
  List<dynamic> _weekBoard = const <dynamic>[];
  List<dynamic> _reservations = const <dynamic>[];
  List<dynamic> _announcements = const <dynamic>[];
  Map<String, dynamic> _paymentMethods = const <String, dynamic>{};
  Map<String, dynamic> _locations = const <String, dynamic>{};
  bool _googleCalendarConnected = false;
  String? _googleCalendarEmail;

  bool _loading = false;
  String? _errorMessage;

  /// Executes local login with pseudo Google data.
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
      _apiClient.setToken(token);

      setState(() {
        _token = token;
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

  /// Logs out user and clears in-memory session data.
  void _logout() {
    _apiClient.setToken(null);
    setState(() {
      _token = null;
      _user = null;
      _activities = const <dynamic>[];
      _weekBoard = const <dynamic>[];
      _reservations = const <dynamic>[];
      _announcements = const <dynamic>[];
      _paymentMethods = const <String, dynamic>{};
      _locations = const <String, dynamic>{};
      _googleCalendarConnected = false;
      _googleCalendarEmail = null;
      _errorMessage = null;
    });
  }

  /// Loads all app sections from backend.
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
      final Map<String, dynamic> schedule = await _apiClient.schedule();
      final Map<String, dynamic> reservations = await _apiClient.reservations();
      final Map<String, dynamic> announcements =
          await _apiClient.announcements();
      final Map<String, dynamic> methods = Map<String, dynamic>.from(
        (activities['payment_methods'] ??
            me['payment_methods'] ??
            <String, dynamic>{}) as Map,
      );
      final Map<String, dynamic> locations = Map<String, dynamic>.from(
        (me['locations'] ?? <String, dynamic>{}) as Map,
      );
      final bool googleConnected =
          me['google_calendar_connected'] as bool? ?? false;
      final String? googleEmail = me['google_calendar_email'] as String?;

      setState(() {
        _user = Map<String, dynamic>.from(
          (me['user'] ?? <String, dynamic>{}) as Map,
        );
        _activities = activities['activities'] as List<dynamic>? ?? <dynamic>[];
        _weekBoard = schedule['week_board'] as List<dynamic>? ?? <dynamic>[];
        _reservations =
            reservations['reservations'] as List<dynamic>? ?? <dynamic>[];
        _announcements =
            announcements['announcements'] as List<dynamic>? ?? <dynamic>[];
        _paymentMethods = methods;
        _locations = locations;
        _googleCalendarConnected = googleConnected;
        _googleCalendarEmail = googleEmail;
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

  /// Starts Google OAuth linking flow and opens browser to grant Calendar access.
  Future<void> _startGoogleCalendarConnect() async {
    setState(() {
      _loading = true;
      _errorMessage = null;
    });

    try {
      final Map<String, dynamic> result = await _apiClient.googleConnectStart();
      final String authUrl = result['auth_url'] as String? ?? '';
      if (authUrl.isEmpty) {
        throw ApiException('No se recibio URL de autorizacion Google.');
      }

      final bool launched = await launchUrl(
        Uri.parse(authUrl),
        mode: LaunchMode.externalApplication,
      );
      if (!launched) {
        throw ApiException('No se pudo abrir el navegador para Google OAuth.');
      }

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'Completa la autorizacion en Google y vuelve a la app. Comprobaremos el enlace automaticamente.',
            ),
          ),
        );
      }

      unawaited(_pollGoogleCalendarConnection());
    } on ApiException catch (error) {
      setState(() {
        _errorMessage = error.message;
      });
    } catch (_) {
      setState(() {
        _errorMessage =
            'No se pudo iniciar la vinculacion con Google Calendar.';
      });
    } finally {
      setState(() {
        _loading = false;
      });
    }
  }

  /// Polls backend for a short period after OAuth start to detect successful linking.
  Future<void> _pollGoogleCalendarConnection() async {
    for (int attempt = 0; attempt < 12; attempt++) {
      await Future<void>.delayed(const Duration(seconds: 5));
      if (!mounted || _token == null) {
        return;
      }

      try {
        final Map<String, dynamic> status =
            await _apiClient.googleConnectStatus();
        final bool connected = status['connected'] as bool? ?? false;
        if (!connected) {
          continue;
        }

        setState(() {
          _googleCalendarConnected = true;
          _googleCalendarEmail = status['google_email'] as String?;
        });

        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Google Calendar vinculado correctamente.'),
            ),
          );
        }
        return;
      } catch (_) {
        // Silent retry until timeout.
      }
    }
  }

  /// Refreshes Google Calendar link status from backend.
  Future<void> _refreshGoogleCalendarStatus() async {
    setState(() {
      _loading = true;
      _errorMessage = null;
    });

    try {
      final Map<String, dynamic> status =
          await _apiClient.googleConnectStatus();
      final bool connected = status['connected'] as bool? ?? false;
      setState(() {
        _googleCalendarConnected = connected;
        _googleCalendarEmail = status['google_email'] as String?;
      });

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              connected
                  ? 'Cuenta Google vinculada: ${_googleCalendarEmail ?? 'sin email'}'
                  : 'La cuenta aun no esta vinculada.',
            ),
          ),
        );
      }
    } on ApiException catch (error) {
      setState(() {
        _errorMessage = error.message;
      });
    } catch (_) {
      setState(() {
        _errorMessage = 'No se pudo consultar el estado de Google Calendar.';
      });
    } finally {
      setState(() {
        _loading = false;
      });
    }
  }

  /// Removes linked Google account from backend.
  Future<void> _disconnectGoogleCalendar() async {
    setState(() {
      _loading = true;
      _errorMessage = null;
    });

    try {
      await _apiClient.googleDisconnect();
      setState(() {
        _googleCalendarConnected = false;
        _googleCalendarEmail = null;
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Cuenta Google desconectada.')),
        );
      }
    } on ApiException catch (error) {
      setState(() {
        _errorMessage = error.message;
      });
    } catch (_) {
      setState(() {
        _errorMessage = 'No se pudo desconectar la cuenta de Google.';
      });
    } finally {
      setState(() {
        _loading = false;
      });
    }
  }

  /// Creates a reservation after choosing payment method, then runs confirmation flow.
  Future<void> _reserveActivity(Map<String, dynamic> activity) async {
    final String? paymentMethod = await _choosePaymentMethod();
    if (paymentMethod == null) {
      return;
    }

    final int activityId = activity['id'] as int;
    setState(() {
      _loading = true;
      _errorMessage = null;
    });

    try {
      final Map<String, dynamic> reserveResult = await _apiClient.reserve(
        activityId: activityId,
        paymentMethod: paymentMethod,
      );
      final String status = reserveResult['status'] as String;

      if (status == 'waitlist') {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Sin plazas. Te hemos añadido a lista de espera.'),
            ),
          );
        }
      } else if (status == 'pending_user_confirm') {
        final int reservationId = reserveResult['reservation_id'] as int;
        final String localCode =
            reserveResult['local_confirmation_code'] as String? ?? '';
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

  /// Allows user to choose a payment method configured by admin/backend.
  Future<String?> _choosePaymentMethod() async {
    final Map<String, dynamic> methods = _paymentMethods;
    if (methods.isEmpty) {
      setState(() {
        _errorMessage = 'No hay metodos de pago configurados.';
      });
      return null;
    }

    String selected = methods.keys.first;
    return showDialog<String>(
      context: context,
      builder: (BuildContext context) {
        return StatefulBuilder(
          builder: (BuildContext context, StateSetter setLocalState) {
            return AlertDialog(
              title: const Text('Metodo de pago'),
              content: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  const Text('Selecciona como quieres realizar el pago.'),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<String>(
                    initialValue: selected,
                    decoration: const InputDecoration(
                      labelText: 'Metodo',
                      border: OutlineInputBorder(),
                    ),
                    items: methods.entries
                        .map(
                          (MapEntry<String, dynamic> entry) =>
                              DropdownMenuItem<String>(
                            value: entry.key,
                            child: Text(entry.value.toString()),
                          ),
                        )
                        .toList(),
                    onChanged: (String? value) {
                      if (value == null) {
                        return;
                      }
                      setLocalState(() {
                        selected = value;
                      });
                    },
                  ),
                ],
              ),
              actions: <Widget>[
                TextButton(
                  onPressed: () => Navigator.of(context).pop(null),
                  child: const Text('Cancelar'),
                ),
                FilledButton(
                  onPressed: () => Navigator.of(context).pop(selected),
                  child: const Text('Continuar'),
                ),
              ],
            );
          },
        );
      },
    );
  }

  /// Prompts for 6-digit confirmation code and confirms reservation.
  Future<void> _askAndConfirmReservation({
    required int reservationId,
    required String suggestedCode,
  }) async {
    final TextEditingController controller = TextEditingController(
      text: suggestedCode,
    );

    final bool? accepted = await showDialog<bool>(
      context: context,
      builder: (BuildContext context) {
        return AlertDialog(
          title: const Text('Confirmar reserva'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              const Text('Introduce el codigo de 6 digitos para continuar.'),
              const SizedBox(height: 8),
              TextField(
                controller: controller,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Codigo'),
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

    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Reserva confirmada por usuario. Pendiente aprobacion manual de admin.',
          ),
        ),
      );
    }
  }

  /// Cancels one reservation respecting business rules from backend.
  Future<void> _cancelReservation(int reservationId) async {
    setState(() {
      _loading = true;
      _errorMessage = null;
    });

    try {
      await _apiClient.cancelReservation(reservationId: reservationId);
      await _reloadData();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Reserva cancelada correctamente.')),
        );
      }
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

    final List<dynamic> modules =
        (_user?['modules'] as List<dynamic>?) ?? <dynamic>[];
    final bool pendingProfile =
        (_user?['status'] as String? ?? 'pending') != 'active' ||
            modules.isEmpty;

    return DefaultTabController(
      length: 5,
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
              tooltip: 'Salir',
              onPressed: _logout,
              icon: const Icon(Icons.logout),
            ),
          ],
          bottom: const TabBar(
            isScrollable: true,
            tabs: <Widget>[
              Tab(text: 'Perfil'),
              Tab(text: 'Horarios'),
              Tab(text: 'Actividades'),
              Tab(text: 'Reservas'),
              Tab(text: 'Avisos'),
            ],
          ),
        ),
        body: Stack(
          children: <Widget>[
            Container(
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: <Color>[Color(0xFFF8FCFF), Color(0xFFEAF3FB)],
                ),
              ),
              child: TabBarView(
                children: <Widget>[
                  _ProfileTab(
                    user: _user ?? const <String, dynamic>{},
                    pendingProfile: pendingProfile,
                    locations: _locations,
                    googleCalendarConnected: _googleCalendarConnected,
                    googleCalendarEmail: _googleCalendarEmail,
                    onConnectGoogle: _startGoogleCalendarConnect,
                    onRefreshGoogleStatus: _refreshGoogleCalendarStatus,
                    onDisconnectGoogle: _disconnectGoogleCalendar,
                  ),
                  _ScheduleTab(
                    weekBoard: _weekBoard,
                    pendingProfile: pendingProfile,
                  ),
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
                    borderRadius: BorderRadius.circular(14),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 10,
                      ),
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

/// Login screen with local pseudo Google ID registration.
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
      body: Container(
        width: double.infinity,
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: <Color>[
              Color(0xFF0D5F9C),
              Color(0xFF2E8BD1),
              Color(0xFFF5A93B),
            ],
          ),
        ),
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 470),
            child: Card(
              margin: const EdgeInsets.all(18),
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Form(
                  key: _formKey,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: <Widget>[
                      const Text(
                        'Club Agelai',
                        style: TextStyle(
                          fontSize: 28,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 6),
                      const Text(
                        'Acceso local con ID de Google (modo pruebas).',
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: _googleIdController,
                        decoration: const InputDecoration(
                          labelText: 'Google ID',
                        ),
                        validator: (String? value) {
                          if (value == null || value.trim().length < 4) {
                            return 'Introduce un Google ID valido.';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 10),
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
                      const SizedBox(height: 10),
                      TextFormField(
                        controller: _nameController,
                        decoration: const InputDecoration(
                          labelText: 'Nombre completo',
                        ),
                        validator: (String? value) {
                          if (value == null || value.trim().length < 2) {
                            return 'Introduce tu nombre.';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 14),
                      FilledButton.icon(
                        onPressed: widget.isLoading ? null : _submit,
                        icon: const Icon(Icons.login),
                        label: const Text('Entrar'),
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
      ),
    );
  }
}

/// Profile tab with access status and module list.
class _ProfileTab extends StatelessWidget {
  const _ProfileTab({
    required this.user,
    required this.pendingProfile,
    required this.locations,
    required this.googleCalendarConnected,
    required this.googleCalendarEmail,
    required this.onConnectGoogle,
    required this.onRefreshGoogleStatus,
    required this.onDisconnectGoogle,
  });

  final Map<String, dynamic> user;
  final bool pendingProfile;
  final Map<String, dynamic> locations;
  final bool googleCalendarConnected;
  final String? googleCalendarEmail;
  final Future<void> Function() onConnectGoogle;
  final Future<void> Function() onRefreshGoogleStatus;
  final Future<void> Function() onDisconnectGoogle;

  @override
  Widget build(BuildContext context) {
    final List<dynamic> modules =
        (user['modules'] as List<dynamic>?) ?? <dynamic>[];
    final String primaryLocation =
        locations['cala_dor'] as String? ?? "Cala d'Or";
    final String secondLocation =
        locations['cala_egos'] as String? ?? 'Cala Egos';

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
                const SizedBox(height: 5),
                Text('Email: ${user['email'] ?? ''}'),
                const SizedBox(height: 5),
                Text('Estado: ${user['status'] ?? ''}'),
                const SizedBox(height: 8),
                Text('Sedes: $primaryLocation (principal) / $secondLocation'),
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
                'Pendiente de asignar perfil. El administrador debe activar tus modulos.',
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
                  'Google Calendar',
                  style: TextStyle(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 8),
                Row(
                  children: <Widget>[
                    Icon(
                      googleCalendarConnected ? Icons.verified : Icons.link_off,
                      color: googleCalendarConnected
                          ? const Color(0xFF0F8A56)
                          : const Color(0xFF8A5B0F),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        googleCalendarConnected
                            ? 'Cuenta vinculada${googleCalendarEmail == null ? '' : ': $googleCalendarEmail'}'
                            : 'Cuenta no vinculada. Vincula Google para crear eventos al aprobar reservas.',
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: <Widget>[
                    FilledButton.icon(
                      onPressed: onRefreshGoogleStatus,
                      icon: const Icon(Icons.sync),
                      label: const Text('Comprobar estado'),
                    ),
                    if (!googleCalendarConnected)
                      OutlinedButton.icon(
                        onPressed: onConnectGoogle,
                        icon: const Icon(Icons.link),
                        label: const Text('Vincular Google'),
                      ),
                    if (googleCalendarConnected)
                      OutlinedButton.icon(
                        onPressed: onDisconnectGoogle,
                        icon: const Icon(Icons.link_off),
                        label: const Text('Desconectar'),
                      ),
                  ],
                ),
              ],
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
                  style: TextStyle(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 8),
                if (modules.isEmpty)
                  const Text('Sin modulos asignados.')
                else
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: modules.map((dynamic module) {
                      final Map<String, dynamic> moduleMap =
                          module as Map<String, dynamic>;
                      return Chip(
                        backgroundColor: const Color(0xFFE4F2FE),
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

/// Weekly schedule board tab for visual planning.
class _ScheduleTab extends StatelessWidget {
  const _ScheduleTab({required this.weekBoard, required this.pendingProfile});

  final List<dynamic> weekBoard;
  final bool pendingProfile;

  @override
  Widget build(BuildContext context) {
    if (pendingProfile) {
      return const Center(
        child: Text('Sin acceso a horarios hasta asignacion de perfil.'),
      );
    }

    if (weekBoard.isEmpty) {
      return const Center(
        child: Text('No hay horarios publicados por el momento.'),
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: weekBoard.length,
      itemBuilder: (BuildContext context, int index) {
        final Map<String, dynamic> week =
            weekBoard[index] as Map<String, dynamic>;
        final dynamic rawDays = week['days'];
        final List<dynamic> days = rawDays is List
            ? rawDays.cast<dynamic>()
            : rawDays is Map
                ? rawDays.values.toList()
                : <dynamic>[];

        return Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: Card(
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    week['week_label'] as String? ?? '',
                    style: const TextStyle(
                      fontSize: 17,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 10),
                  ...days.map((dynamic dayItem) {
                    final Map<String, dynamic> day =
                        dayItem as Map<String, dynamic>;
                    final List<dynamic> items =
                        day['items'] as List<dynamic>? ?? <dynamic>[];
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: Container(
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(color: const Color(0xFFD5E6F3)),
                          color: const Color(0xFFF9FCFF),
                        ),
                        child: Padding(
                          padding: const EdgeInsets.all(10),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: <Widget>[
                              Text(
                                '${day['day_label'] ?? ''} ${day['date_label'] ?? ''}',
                                style: const TextStyle(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              const SizedBox(height: 6),
                              if (items.isEmpty)
                                const Text('Sin actividades')
                              else
                                ...items.map((dynamic itemData) {
                                  final Map<String, dynamic> item =
                                      itemData as Map<String, dynamic>;
                                  return Padding(
                                    padding: const EdgeInsets.only(bottom: 8),
                                    child: Container(
                                      decoration: BoxDecoration(
                                        borderRadius: BorderRadius.circular(12),
                                        color: Colors.white,
                                        border: Border.all(
                                          color: const Color(0xFFCDE0EE),
                                        ),
                                      ),
                                      child: ListTile(
                                        contentPadding:
                                            const EdgeInsets.symmetric(
                                          horizontal: 10,
                                          vertical: 2,
                                        ),
                                        title: Text(
                                          item['title'] as String? ?? '',
                                          style: const TextStyle(
                                            fontWeight: FontWeight.w700,
                                          ),
                                        ),
                                        subtitle: Text(
                                          '${item['time_range'] ?? ''}\n${item['location_label'] ?? item['location'] ?? ''}',
                                        ),
                                        trailing: Text(
                                          '${item['remaining_slots'] ?? 0}/${item['capacity'] ?? 0}',
                                          style: const TextStyle(
                                            fontWeight: FontWeight.w700,
                                          ),
                                        ),
                                      ),
                                    ),
                                  );
                                }),
                            ],
                          ),
                        ),
                      ),
                    );
                  }),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}

/// Activities tab with reserve/queue actions.
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
        child: Text('Sin acceso a actividades hasta asignacion de perfil.'),
      );
    }

    if (activities.isEmpty) {
      return const Center(child: Text('No hay actividades disponibles.'));
    }

    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: activities.length,
      itemBuilder: (BuildContext context, int index) {
        final Map<String, dynamic> activity =
            activities[index] as Map<String, dynamic>;
        final int remaining = activity['remaining_slots'] as int? ?? 0;

        return Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: Card(
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    activity['title'] as String? ?? '',
                    style: const TextStyle(
                      fontSize: 17,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text('Modulo: ${activity['module_code'] ?? ''}'),
                  Text(
                    'Horario: ${activity['starts_at_local'] ?? activity['starts_at'] ?? ''}',
                  ),
                  Text(
                    'Lugar: ${activity['location_label'] ?? activity['location'] ?? ''}',
                  ),
                  Text('Plazas libres: $remaining'),
                  const SizedBox(height: 10),
                  FilledButton.icon(
                    onPressed: () => onReserve(activity),
                    icon: const Icon(Icons.event_available),
                    label: Text(
                      remaining > 0 ? 'Reservar' : 'Entrar en lista de espera',
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}

/// Reservations tab with payment details and cancellation option.
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
        final Map<String, dynamic> reservation =
            reservations[index] as Map<String, dynamic>;
        final Map<String, dynamic> activity =
            reservation['activity'] as Map<String, dynamic>;
        final String status = reservation['status'] as String? ?? '';
        final String calendarSyncStatus =
            reservation['calendar_sync_status'] as String? ?? 'not_linked';
        final String calendarEventId =
            reservation['google_calendar_event_id'] as String? ?? '';

        return Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: Card(
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    activity['title'] as String? ?? '',
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 4),
                  Text('Estado: $status'),
                  Text(
                    'Pago: ${reservation['payment_method_label'] ?? reservation['payment_method'] ?? ''}',
                  ),
                  Text('Estado pago: ${reservation['payment_status'] ?? ''}'),
                  Text('Google Calendar: $calendarSyncStatus'),
                  if (calendarEventId.isNotEmpty)
                    Text('Evento: $calendarEventId'),
                  Text('Modulo: ${activity['module_code'] ?? ''}'),
                  Text('Fecha: ${activity['starts_at'] ?? ''}'),
                  Text('Lugar: ${activity['location'] ?? ''}'),
                  const SizedBox(height: 10),
                  if (status != 'cancelled')
                    OutlinedButton.icon(
                      onPressed: () =>
                          onCancelReservation(reservation['id'] as int),
                      icon: const Icon(Icons.cancel_outlined),
                      label: const Text('Cancelar reserva'),
                    ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}

/// Announcements tab for promos, notices and gym schedules.
class _AnnouncementsTab extends StatelessWidget {
  const _AnnouncementsTab({required this.announcements});

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
        final Map<String, dynamic> announcement =
            announcements[index] as Map<String, dynamic>;
        final String moduleCode =
            announcement['module_code'] as String? ?? 'general';
        return Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: Card(
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    announcement['title'] as String? ?? '',
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 4),
                  Text('Modulo: $moduleCode'),
                  const SizedBox(height: 6),
                  Text(announcement['body'] as String? ?? ''),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}

/// Minimal HTTP API client.
class ApiClient {
  /// Android emulator uses 10.0.2.2 to reach host localhost.
  static const String _baseUrl = 'http://10.0.2.2:8000';

  String? _token;

  void setToken(String? token) {
    _token = token;
  }

  Future<Map<String, dynamic>> login({
    required String googleId,
    required String email,
    required String fullName,
  }) =>
      _request(
        method: 'POST',
        path: '/api/auth/google-login',
        body: <String, dynamic>{
          'google_id': googleId,
          'email': email,
          'full_name': fullName,
        },
        authRequired: false,
      );

  Future<Map<String, dynamic>> me() => _request(method: 'GET', path: '/api/me');

  Future<Map<String, dynamic>> googleConnectStart() =>
      _request(method: 'POST', path: '/api/google/connect/start');

  Future<Map<String, dynamic>> googleConnectStatus() =>
      _request(method: 'GET', path: '/api/google/connect/status');

  Future<Map<String, dynamic>> googleDisconnect() =>
      _request(method: 'POST', path: '/api/google/disconnect');

  Future<Map<String, dynamic>> activities() =>
      _request(method: 'GET', path: '/api/activities');

  Future<Map<String, dynamic>> schedule() =>
      _request(method: 'GET', path: '/api/schedule');

  Future<Map<String, dynamic>> reservations() =>
      _request(method: 'GET', path: '/api/reservations');

  Future<Map<String, dynamic>> announcements() =>
      _request(method: 'GET', path: '/api/announcements');

  Future<Map<String, dynamic>> reserve({
    required int activityId,
    required String paymentMethod,
  }) =>
      _request(
        method: 'POST',
        path: '/api/activities/$activityId/reserve',
        body: <String, dynamic>{'payment_method': paymentMethod},
      );

  Future<Map<String, dynamic>> confirmReservation({
    required int reservationId,
    required String confirmationCode,
  }) =>
      _request(
        method: 'POST',
        path: '/api/reservations/$reservationId/confirm',
        body: <String, dynamic>{'confirmation_code': confirmationCode},
      );

  Future<Map<String, dynamic>> cancelReservation({
    required int reservationId,
  }) =>
      _request(method: 'POST', path: '/api/reservations/$reservationId/cancel');

  /// Generic JSON request wrapper with API error normalization.
  Future<Map<String, dynamic>> _request({
    required String method,
    required String path,
    Map<String, dynamic>? body,
    bool authRequired = true,
  }) async {
    final HttpClient client = HttpClient()
      ..connectionTimeout = const Duration(seconds: 12);

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
      final dynamic decoded =
          responseBody.isEmpty ? <String, dynamic>{} : jsonDecode(responseBody);

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
        'No hay conexion con el backend. Verifica PHP en http://127.0.0.1:8000.',
      );
    } finally {
      client.close(force: true);
    }
  }
}

/// Controlled exception type for user-displayable API errors.
class ApiException implements Exception {
  ApiException(this.message);
  final String message;

  @override
  String toString() => message;
}
