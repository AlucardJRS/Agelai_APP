<?php
declare(strict_types=1);

/**
 * Main application router and controller for API + dashboard.
 */
final class App
{
    private Integrations $integrations;

    public function __construct(
        private readonly \PDO $pdo,
        private readonly array $config
    ) {
        $this->integrations = new Integrations($config);
    }

    public function handle(): void
    {
        Security::addSecurityHeaders();

        $this->applyRateLimit();

        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = rawurldecode((string) (parse_url($requestUri, PHP_URL_PATH) ?? '/'));
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        if ($path === '/assets/styles.css') {
            $this->serveStylesheet();
            return;
        }

        if ($method === 'GET' && $path === '/oauth/google/callback') {
            $this->handleGoogleOauthCallback();
            return;
        }

        if (str_starts_with($path, '/api/')) {
            $this->handleApiRequest($method, $path);
            return;
        }

        $this->handleDashboardRequest($method, $path);
    }

    /**
     * Baseline fixed-window rate limiter persisted in DB to resist brute-force abuse.
     */
    private function applyRateLimit(): void
    {
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string) (parse_url($requestUri, PHP_URL_PATH) ?? '/');
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $key = $ip . '|' . $method . '|' . $path;

        $limit = 120;
        $windowSeconds = 60;

        // Lower thresholds for authentication and reservation confirmation endpoints.
        if (
            ($path === '/api/auth/google-login' && $method === 'POST')
            || ($path === '/api/auth/google-login/start' && $method === 'POST')
            || ($path === '/dashboard/login' && $method === 'POST')
            || str_starts_with($path, '/api/reservations/')
        ) {
            $limit = 20;
        }

        $now = time();
        $statement = $this->pdo->prepare(
            'SELECT key_name, window_started_at, hit_count FROM rate_limits WHERE key_name = :key_name LIMIT 1'
        );
        $statement->execute([':key_name' => $key]);
        $row = $statement->fetch();

        if ($row === false) {
            $insert = $this->pdo->prepare(
                'INSERT INTO rate_limits (key_name, window_started_at, hit_count) VALUES (:key_name, :window_started_at, :hit_count)'
            );
            $insert->execute([
                ':key_name' => $key,
                ':window_started_at' => $now,
                ':hit_count' => 1,
            ]);
            return;
        }

        $windowStartedAt = (int) $row['window_started_at'];
        $hitCount = (int) $row['hit_count'];

        if (($now - $windowStartedAt) > $windowSeconds) {
            $reset = $this->pdo->prepare(
                'UPDATE rate_limits SET window_started_at = :window_started_at, hit_count = :hit_count WHERE key_name = :key_name'
            );
            $reset->execute([
                ':window_started_at' => $now,
                ':hit_count' => 1,
                ':key_name' => $key,
            ]);
            return;
        }

        if ($hitCount >= $limit) {
            http_response_code(429);
            if (str_starts_with($path, '/api/')) {
                $this->json([
                    'ok' => false,
                    'message' => 'Demasiadas solicitudes. Intenta de nuevo en un minuto.',
                ], 429);
            } else {
                echo 'Demasiadas solicitudes. Intenta de nuevo en un minuto.';
            }
            exit;
        }

        $increment = $this->pdo->prepare(
            'UPDATE rate_limits SET hit_count = :hit_count WHERE key_name = :key_name'
        );
        $increment->execute([
            ':hit_count' => $hitCount + 1,
            ':key_name' => $key,
        ]);
    }

    private function serveStylesheet(): void
    {
        $cssFile = __DIR__ . '/../public/assets/styles.css';
        if (!is_file($cssFile)) {
            http_response_code(404);
            echo 'CSS no encontrado';
            return;
        }

        header('Content-Type: text/css; charset=utf-8');
        readfile($cssFile);
    }

    private function handleApiRequest(string $method, string $path): void
    {
        if ($method === 'POST' && $path === '/api/auth/google-login/start') {
            $this->apiGoogleMobileLoginStart();
            return;
        }

        if ($method === 'GET' && $path === '/api/auth/google-login/status') {
            $this->apiGoogleMobileLoginStatus();
            return;
        }

        if ($method === 'POST' && $path === '/api/auth/google-login') {
            $this->apiGoogleLogin();
            return;
        }

        $user = $this->authenticatedApiUser();
        if ($user === null) {
            $this->json(['ok' => false, 'message' => 'No autorizado'], 401);
            return;
        }

        if ($method === 'POST' && $path === '/api/google/connect/start') {
            $this->apiGoogleConnectStart($user);
            return;
        }

        if ($method === 'GET' && $path === '/api/google/connect/status') {
            $this->apiGoogleConnectStatus($user);
            return;
        }

        if ($method === 'POST' && $path === '/api/google/disconnect') {
            $this->apiGoogleDisconnect($user);
            return;
        }

        if ($method === 'GET' && $path === '/api/me') {
            $this->apiMe($user);
            return;
        }

        if ($method === 'GET' && $path === '/api/activities') {
            $this->apiActivities($user);
            return;
        }

        if ($method === 'GET' && $path === '/api/schedule') {
            $this->apiSchedule($user);
            return;
        }

        if ($method === 'GET' && $path === '/api/reservations') {
            $this->apiReservations($user);
            return;
        }

        if ($method === 'GET' && $path === '/api/announcements') {
            $this->apiAnnouncements($user);
            return;
        }

        if ($method === 'POST' && preg_match('#^/api/activities/(\d+)/reserve$#', $path, $matches) === 1) {
            $this->apiReserveActivity($user, (int) $matches[1]);
            return;
        }

        if ($method === 'POST' && preg_match('#^/api/reservations/(\d+)/confirm$#', $path, $matches) === 1) {
            $this->apiConfirmReservation($user, (int) $matches[1]);
            return;
        }

        if ($method === 'POST' && preg_match('#^/api/reservations/(\d+)/cancel$#', $path, $matches) === 1) {
            $this->apiCancelReservation($user, (int) $matches[1]);
            return;
        }

        $this->json(['ok' => false, 'message' => 'Ruta API no encontrada'], 404);
    }

    private function handleDashboardRequest(string $method, string $path): void
    {
        if ($path === '/') {
            header('Location: /dashboard');
            return;
        }

        if ($method === 'GET' && $path === '/dashboard/login') {
            $this->render('login', ['title' => 'Login Admin']);
            return;
        }

        if ($method === 'POST' && $path === '/dashboard/login') {
            $this->dashboardLogin();
            return;
        }

        if ($method === 'POST' && $path === '/dashboard/logout') {
            $this->dashboardLogout();
            return;
        }

        if (!$this->isAdminAuthenticated()) {
            header('Location: /dashboard/login');
            return;
        }

        if ($method === 'GET' && $path === '/dashboard') {
            $this->dashboardHome();
            return;
        }

        if ($method === 'GET' && $path === '/dashboard/users') {
            $this->dashboardUsers();
            return;
        }

        if ($method === 'POST' && $path === '/dashboard/users/update') {
            $this->dashboardUsersUpdate();
            return;
        }

        if ($method === 'POST' && $path === '/dashboard/users/create') {
            $this->dashboardUsersCreate();
            return;
        }

        if ($method === 'POST' && $path === '/dashboard/users/block') {
            $this->dashboardUsersBlock();
            return;
        }

        if ($method === 'GET' && $path === '/dashboard/activities') {
            $this->dashboardActivities();
            return;
        }

        if ($method === 'GET' && $path === '/dashboard/schedule') {
            $this->dashboardSchedule();
            return;
        }

        if ($method === 'POST' && $path === '/dashboard/activities/create') {
            $this->dashboardActivitiesCreate();
            return;
        }

        if ($method === 'POST' && $path === '/dashboard/activities/toggle') {
            $this->dashboardActivitiesToggle();
            return;
        }

        if ($method === 'GET' && $path === '/dashboard/reservations') {
            $this->dashboardReservations();
            return;
        }

        if ($method === 'POST' && $path === '/dashboard/reservations/approve') {
            $this->dashboardReservationApprove();
            return;
        }

        if ($method === 'POST' && $path === '/dashboard/reservations/reject') {
            $this->dashboardReservationReject();
            return;
        }

        if ($method === 'GET' && $path === '/dashboard/announcements') {
            $this->dashboardAnnouncements();
            return;
        }

        if ($method === 'POST' && $path === '/dashboard/announcements/create') {
            $this->dashboardAnnouncementCreate();
            return;
        }

        http_response_code(404);
        echo 'Ruta dashboard no encontrada';
    }

    private function dashboardLogin(): void
    {
        if (!Security::verifyCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->setFlash('error', 'Token CSRF invalido.');
            header('Location: /dashboard/login');
            return;
        }

        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $this->setFlash('error', 'Usuario y password son obligatorios.');
            header('Location: /dashboard/login');
            return;
        }

        $query = $this->pdo->prepare('SELECT id, username, password_hash FROM admins WHERE username = :username LIMIT 1');
        $query->execute([':username' => $username]);
        $admin = $query->fetch();

        if ($admin === false || !password_verify($password, (string) $admin['password_hash'])) {
            $this->setFlash('error', 'Credenciales invalidas.');
            header('Location: /dashboard/login');
            return;
        }

        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_username'] = (string) $admin['username'];

        $this->setFlash('success', 'Sesion iniciada correctamente.');
        header('Location: /dashboard');
    }

    private function dashboardLogout(): void
    {
        if (!Security::verifyCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->setFlash('error', 'Token CSRF invalido.');
            header('Location: /dashboard');
            return;
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();

        header('Location: /dashboard/login');
    }

    private function dashboardHome(): void
    {
        $stats = [
            'users_total' => $this->count('SELECT COUNT(*) FROM users'),
            'users_pending' => $this->count("SELECT COUNT(*) FROM users WHERE status = 'pending'"),
            'activities_active' => $this->count("SELECT COUNT(*) FROM activities WHERE status = 'active'"),
            'reservations_confirmed' => $this->count("SELECT COUNT(*) FROM reservations WHERE status = 'confirmed'"),
            'reservations_waitlist' => $this->count("SELECT COUNT(*) FROM reservations WHERE status = 'waitlist'"),
            'reservations_pending_admin' => $this->count("SELECT COUNT(*) FROM reservations WHERE status = 'pending_admin_approval'"),
        ];

        $this->render('dashboard', [
            'title' => 'Dashboard',
            'stats' => $stats,
        ]);
    }

    private function dashboardUsers(): void
    {
        $moduleRows = $this->pdo->query('SELECT id, code, name FROM modules ORDER BY name ASC')->fetchAll();
        $usersRaw = $this->pdo->query('SELECT id, full_name, email, google_id, status, created_at FROM users ORDER BY created_at DESC')->fetchAll();

        $moduleStmt = $this->pdo->prepare(
            'SELECT m.id, m.code, m.name
             FROM user_modules um
             INNER JOIN modules m ON m.id = um.module_id
             WHERE um.user_id = :user_id
             ORDER BY m.name ASC'
        );

        $users = [];
        foreach ($usersRaw as $userRow) {
            $moduleStmt->execute([':user_id' => (int) $userRow['id']]);
            $userModules = $moduleStmt->fetchAll();
            $userRow['modules'] = $userModules;
            $users[] = $userRow;
        }

        $this->render('users', [
            'title' => 'Usuarios y Permisos',
            'users' => $users,
            'modules' => $moduleRows,
        ]);
    }

    private function dashboardUsersUpdate(): void
    {
        if (!Security::verifyCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->setFlash('error', 'Token CSRF invalido.');
            header('Location: /dashboard/users');
            return;
        }

        $userId = (int) ($_POST['user_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? 'pending');
        $moduleIdsRaw = $_POST['module_ids'] ?? [];
        $allowedStatus = ['pending', 'active', 'blocked'];

        if ($userId <= 0 || !in_array($status, $allowedStatus, true)) {
            $this->setFlash('error', 'Datos de usuario invalidos.');
            header('Location: /dashboard/users');
            return;
        }

        $moduleIds = [];
        if (is_array($moduleIdsRaw)) {
            foreach ($moduleIdsRaw as $moduleIdRaw) {
                $moduleId = (int) $moduleIdRaw;
                if ($moduleId > 0) {
                    $moduleIds[] = $moduleId;
                }
            }
        }

        $this->pdo->beginTransaction();
        try {
            if ($status === 'active' && count($moduleIds) === 0) {
                // Active users without modules would violate the business rule.
                $status = 'pending';
            }

            $updateUser = $this->pdo->prepare('UPDATE users SET status = :status WHERE id = :id');
            $updateUser->execute([
                ':status' => $status,
                ':id' => $userId,
            ]);

            $deleteModules = $this->pdo->prepare('DELETE FROM user_modules WHERE user_id = :user_id');
            $deleteModules->execute([':user_id' => $userId]);

            if (count($moduleIds) > 0) {
                $insertModule = $this->pdo->prepare(
                    'INSERT IGNORE INTO user_modules (user_id, module_id) VALUES (:user_id, :module_id)'
                );
                foreach ($moduleIds as $moduleId) {
                    $insertModule->execute([
                        ':user_id' => $userId,
                        ':module_id' => $moduleId,
                    ]);
                }
            }

            if ($status === 'blocked') {
                $this->revokeUserApiTokens($userId);
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            $this->setFlash('error', 'No se pudo actualizar el usuario.');
            header('Location: /dashboard/users');
            return;
        }

        $this->setFlash('success', 'Usuario actualizado correctamente.');
        header('Location: /dashboard/users');
    }

    private function dashboardUsersCreate(): void
    {
        if (!Security::verifyCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->setFlash('error', 'Token CSRF invalido.');
            header('Location: /dashboard/users');
            return;
        }

        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $googleIdInput = trim((string) ($_POST['google_id'] ?? ''));
        $status = (string) ($_POST['status'] ?? 'pending');
        $allowedStatus = ['pending', 'active', 'blocked'];
        $moduleIdsRaw = $_POST['module_ids'] ?? [];

        if ($fullName === '' || mb_strlen($fullName) < 2 || mb_strlen($fullName) > 120) {
            $this->setFlash('error', 'Nombre invalido para nuevo usuario.');
            header('Location: /dashboard/users');
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->setFlash('error', 'Email invalido para nuevo usuario.');
            header('Location: /dashboard/users');
            return;
        }
        if (!in_array($status, $allowedStatus, true)) {
            $this->setFlash('error', 'Estado invalido para nuevo usuario.');
            header('Location: /dashboard/users');
            return;
        }

        $googleId = $googleIdInput;
        if ($googleId !== '' && !preg_match('/^[A-Za-z0-9._-]{4,128}$/', $googleId)) {
            $this->setFlash('error', 'Google ID invalido. Usa solo letras, numeros, punto, guion o guion bajo.');
            header('Location: /dashboard/users');
            return;
        }
        if ($googleId === '') {
            // Placeholder ID for manual creation; will be replaced when the user logs in with Google.
            $googleId = 'manual_local_' . bin2hex(random_bytes(8));
        }

        $moduleIds = [];
        if (is_array($moduleIdsRaw)) {
            foreach ($moduleIdsRaw as $moduleIdRaw) {
                $moduleId = (int) $moduleIdRaw;
                if ($moduleId > 0) {
                    $moduleIds[] = $moduleId;
                }
            }
        }
        $moduleIds = array_values(array_unique($moduleIds));

        if ($status === 'active' && count($moduleIds) === 0) {
            $status = 'pending';
        }

        $this->pdo->beginTransaction();
        try {
            $insertUser = $this->pdo->prepare(
                'INSERT INTO users (google_id, email, full_name, status, created_at)
                 VALUES (:google_id, :email, :full_name, :status, :created_at)'
            );
            $insertUser->execute([
                ':google_id' => $googleId,
                ':email' => $email,
                ':full_name' => $fullName,
                ':status' => $status,
                ':created_at' => gmdate('c'),
            ]);
            $userId = (int) $this->pdo->lastInsertId();

            if (count($moduleIds) > 0) {
                $insertModule = $this->pdo->prepare(
                    'INSERT IGNORE INTO user_modules (user_id, module_id) VALUES (:user_id, :module_id)'
                );
                foreach ($moduleIds as $moduleId) {
                    $insertModule->execute([
                        ':user_id' => $userId,
                        ':module_id' => $moduleId,
                    ]);
                }
            }

            if ($status === 'blocked') {
                $this->revokeUserApiTokens($userId);
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            $message = 'No se pudo crear el usuario.';
            if (str_contains(strtolower($exception->getMessage()), 'duplicate')) {
                $message = 'No se pudo crear: email o Google ID ya existen.';
            }
            $this->setFlash('error', $message);
            header('Location: /dashboard/users');
            return;
        }

        $this->setFlash('success', 'Usuario creado correctamente.');
        header('Location: /dashboard/users');
    }

    private function dashboardUsersBlock(): void
    {
        if (!Security::verifyCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->setFlash('error', 'Token CSRF invalido.');
            header('Location: /dashboard/users');
            return;
        }

        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            $this->setFlash('error', 'Usuario invalido para bloqueo.');
            header('Location: /dashboard/users');
            return;
        }

        $this->pdo->beginTransaction();
        try {
            $update = $this->pdo->prepare('UPDATE users SET status = :status WHERE id = :id');
            $update->execute([
                ':status' => 'blocked',
                ':id' => $userId,
            ]);

            $this->revokeUserApiTokens($userId);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            $this->setFlash('error', 'No se pudo bloquear el usuario.');
            header('Location: /dashboard/users');
            return;
        }

        $this->setFlash('success', 'Usuario bloqueado correctamente.');
        header('Location: /dashboard/users');
    }

    private function dashboardActivities(): void
    {
        $moduleRows = $this->pdo->query('SELECT code, name FROM modules ORDER BY name ASC')->fetchAll();
        $locations = (array) ($this->config['locations'] ?? []);

        $activities = $this->pdo->query(
            'SELECT a.*,
               (
                 SELECT COUNT(*)
                 FROM reservations r
                 WHERE r.activity_id = a.id
                 AND r.status IN (\'pending_user_confirm\', \'pending_admin_approval\', \'confirmed\')
               ) AS occupied_slots
             FROM activities a
             ORDER BY a.starts_at ASC'
        )->fetchAll();

        $this->render('activities', [
            'title' => 'Actividades',
            'modules' => $moduleRows,
            'locations' => $locations,
            'activities' => $activities,
        ]);
    }

    private function dashboardSchedule(): void
    {
        $activities = $this->fetchScheduleActivities(
            moduleCodes: [],
            includeAllModules: true,
            includeOnlyActive: true,
            daysAhead: 14
        );

        $this->render('schedule', [
            'title' => 'Horarios Semanales',
            'weekBoard' => $this->groupActivitiesByWeekAndDay($activities),
            'locationNames' => $this->locationNamesMap(),
        ]);
    }

    private function dashboardActivitiesCreate(): void
    {
        if (!Security::verifyCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->setFlash('error', 'Token CSRF invalido.');
            header('Location: /dashboard/activities');
            return;
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        $moduleCode = Security::normalizeModuleCode($_POST['module_code'] ?? null, (array) $this->config['allowed_modules']);
        $startsAt = trim((string) ($_POST['starts_at'] ?? ''));
        $endsAt = trim((string) ($_POST['ends_at'] ?? ''));
        $capacity = (int) ($_POST['capacity'] ?? 0);
        $location = trim((string) ($_POST['location'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $allowedLocationNames = array_values($this->locationNamesMap());

        if ($title === '' || mb_strlen($title) > 120 || $moduleCode === null) {
            $this->setFlash('error', 'Datos principales invalidos.');
            header('Location: /dashboard/activities');
            return;
        }

        if ($capacity < 1 || $capacity > 500) {
            $this->setFlash('error', 'El cupo debe estar entre 1 y 500.');
            header('Location: /dashboard/activities');
            return;
        }

        $startDate = date_create_immutable($startsAt);
        $endDate = date_create_immutable($endsAt);
        if ($startDate === false || $endDate === false || $endDate <= $startDate) {
            $this->setFlash('error', 'Fechas/horas invalidas.');
            header('Location: /dashboard/activities');
            return;
        }
        $utcZone = new \DateTimeZone('UTC');
        $startDateIso = $startDate->setTimezone($utcZone)->format('c');
        $endDateIso = $endDate->setTimezone($utcZone)->format('c');

        if ($location === '' || mb_strlen($location) > 120 || !in_array($location, $allowedLocationNames, true)) {
            $this->setFlash('error', 'Ubicacion invalida.');
            header('Location: /dashboard/activities');
            return;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO activities (title, module_code, starts_at, ends_at, capacity, location, notes, status, created_at)
             VALUES (:title, :module_code, :starts_at, :ends_at, :capacity, :location, :notes, :status, :created_at)'
        );
        $insert->execute([
            ':title' => $title,
            ':module_code' => $moduleCode,
            ':starts_at' => $startDateIso,
            ':ends_at' => $endDateIso,
            ':capacity' => $capacity,
            ':location' => $location,
            ':notes' => $notes,
            ':status' => 'active',
            ':created_at' => gmdate('c'),
        ]);

        $this->setFlash('success', 'Actividad creada correctamente.');
        header('Location: /dashboard/activities');
    }

    private function dashboardActivitiesToggle(): void
    {
        if (!Security::verifyCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->setFlash('error', 'Token CSRF invalido.');
            header('Location: /dashboard/activities');
            return;
        }

        $activityId = (int) ($_POST['activity_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');

        if ($activityId <= 0 || !in_array($status, ['active', 'inactive'], true)) {
            $this->setFlash('error', 'Datos de estado invalidos.');
            header('Location: /dashboard/activities');
            return;
        }

        $update = $this->pdo->prepare('UPDATE activities SET status = :status WHERE id = :id');
        $update->execute([
            ':status' => $status,
            ':id' => $activityId,
        ]);

        $this->setFlash('success', 'Estado de actividad actualizado.');
        header('Location: /dashboard/activities');
    }

    private function dashboardReservations(): void
    {
        $reservations = $this->pdo->query(
            'SELECT r.id, r.status, r.payment_status, r.payment_method, r.calendar_sync_status, r.google_calendar_event_id, r.confirmation_email_sent_at, r.created_at, r.updated_at,
                    u.full_name, u.email,
                    a.title, a.module_code, a.starts_at, a.ends_at, a.capacity, a.location,
                    (
                       SELECT COUNT(*)
                       FROM reservations rx
                       WHERE rx.activity_id = a.id
                       AND rx.status IN (\'pending_user_confirm\', \'pending_admin_approval\', \'confirmed\')
                    ) AS occupied_slots
             FROM reservations r
             INNER JOIN users u ON u.id = r.user_id
             INNER JOIN activities a ON a.id = r.activity_id
             ORDER BY r.created_at DESC'
        )->fetchAll();

        $this->render('reservations', [
            'title' => 'Reservas',
            'reservations' => $reservations,
            'paymentMethods' => $this->paymentMethodsMap(),
        ]);
    }

    private function dashboardReservationApprove(): void
    {
        if (!Security::verifyCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->setFlash('error', 'Token CSRF invalido.');
            header('Location: /dashboard/reservations');
            return;
        }

        $reservationId = (int) ($_POST['reservation_id'] ?? 0);
        if ($reservationId <= 0) {
            $this->setFlash('error', 'Reserva invalida.');
            header('Location: /dashboard/reservations');
            return;
        }

        $reservationForNotifications = null;

        $this->pdo->beginTransaction();
        try {
            $query = $this->pdo->prepare(
                'SELECT r.id, r.user_id, r.status, r.payment_method,
                        u.email, u.full_name,
                        a.title, a.module_code, a.starts_at, a.ends_at, a.location
                 FROM reservations r
                 INNER JOIN users u ON u.id = r.user_id
                 INNER JOIN activities a ON a.id = r.activity_id
                 WHERE r.id = :id
                 LIMIT 1'
            );
            $query->execute([':id' => $reservationId]);
            $reservation = $query->fetch();

            if ($reservation === false || (string) $reservation['status'] !== 'pending_admin_approval') {
                throw new \RuntimeException('Estado no aprobable.');
            }

            $paymentMethod = (string) ($reservation['payment_method'] ?? 'cash');
            $paymentStatusTarget = match ($paymentMethod) {
                'bizum' => 'pending_bizum_collection',
                'card' => 'pending_card_collection',
                default => 'pending_cash_collection',
            };

            $update = $this->pdo->prepare(
                'UPDATE reservations
                 SET status = :status, payment_status = :payment_status, updated_at = :updated_at
                 WHERE id = :id'
            );
            $update->execute([
                ':status' => 'confirmed',
                ':payment_status' => $paymentStatusTarget,
                ':updated_at' => gmdate('c'),
                ':id' => $reservationId,
            ]);

            $reservationForNotifications = $reservation;
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            $this->setFlash('error', 'No se pudo aprobar la reserva.');
            header('Location: /dashboard/reservations');
            return;
        }

        $notes = [];
        if (is_array($reservationForNotifications)) {
            $emailResult = $this->sendReservationApprovedEmail($reservationForNotifications);
            if ($emailResult['ok']) {
                $markEmail = $this->pdo->prepare(
                    'UPDATE reservations
                     SET confirmation_email_sent_at = :sent_at
                     WHERE id = :id'
                );
                $markEmail->execute([
                    ':sent_at' => gmdate('c'),
                    ':id' => (int) $reservationForNotifications['id'],
                ]);
                $notes[] = 'email enviado';
            } else {
                $notes[] = 'email pendiente';
            }

            $calendarResult = $this->syncReservationToGoogleCalendar($reservationForNotifications);
            if ($calendarResult['ok']) {
                $notes[] = 'calendar sincronizado';
            } else {
                $notes[] = 'calendar pendiente';
            }

            $this->logIntegration(
                (int) ($reservationForNotifications['user_id'] ?? 0),
                'reservation_approval',
                'success',
                (string) ($reservationForNotifications['email'] ?? ''),
                implode(', ', $notes)
            );
        }

        $this->setFlash(
            'success',
            'Reserva aprobada y confirmada.' . (count($notes) > 0 ? ' (' . implode(' | ', $notes) . ')' : '')
        );
        header('Location: /dashboard/reservations');
    }

    private function dashboardReservationReject(): void
    {
        if (!Security::verifyCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->setFlash('error', 'Token CSRF invalido.');
            header('Location: /dashboard/reservations');
            return;
        }

        $reservationId = (int) ($_POST['reservation_id'] ?? 0);
        if ($reservationId <= 0) {
            $this->setFlash('error', 'Reserva invalida.');
            header('Location: /dashboard/reservations');
            return;
        }

        $update = $this->pdo->prepare(
            'UPDATE reservations
             SET status = :status, updated_at = :updated_at
             WHERE id = :id'
        );
        $update->execute([
            ':status' => 'cancelled',
            ':updated_at' => gmdate('c'),
            ':id' => $reservationId,
        ]);

        $this->setFlash('success', 'Reserva rechazada.');
        header('Location: /dashboard/reservations');
    }

    private function dashboardAnnouncements(): void
    {
        $announcements = $this->pdo->query(
            'SELECT id, module_code, title, body, starts_at, ends_at, is_active, created_at
             FROM announcements
             ORDER BY created_at DESC'
        )->fetchAll();

        $this->render('announcements', [
            'title' => 'Anuncios',
            'announcements' => $announcements,
            'modules' => (array) $this->config['allowed_modules'],
        ]);
    }

    private function dashboardAnnouncementCreate(): void
    {
        if (!Security::verifyCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->setFlash('error', 'Token CSRF invalido.');
            header('Location: /dashboard/announcements');
            return;
        }

        $moduleCodeRaw = trim((string) ($_POST['module_code'] ?? ''));
        $moduleCode = $moduleCodeRaw === '' ? null : Security::normalizeModuleCode($moduleCodeRaw, (array) $this->config['allowed_modules']);
        $title = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        $startsAt = trim((string) ($_POST['starts_at'] ?? ''));
        $endsAt = trim((string) ($_POST['ends_at'] ?? ''));

        $startDate = date_create_immutable($startsAt);
        $endDate = date_create_immutable($endsAt);
        if ($moduleCodeRaw !== '' && $moduleCode === null) {
            $this->setFlash('error', 'Modulo invalido.');
            header('Location: /dashboard/announcements');
            return;
        }
        if ($title === '' || mb_strlen($title) > 120 || $body === '' || mb_strlen($body) > 3000) {
            $this->setFlash('error', 'Titulo o cuerpo invalidos.');
            header('Location: /dashboard/announcements');
            return;
        }
        if ($startDate === false || $endDate === false || $endDate <= $startDate) {
            $this->setFlash('error', 'Fechas invalidas.');
            header('Location: /dashboard/announcements');
            return;
        }
        $utcZone = new \DateTimeZone('UTC');
        $startDateIso = $startDate->setTimezone($utcZone)->format('c');
        $endDateIso = $endDate->setTimezone($utcZone)->format('c');

        $insert = $this->pdo->prepare(
            'INSERT INTO announcements (module_code, title, body, starts_at, ends_at, is_active, created_at)
             VALUES (:module_code, :title, :body, :starts_at, :ends_at, :is_active, :created_at)'
        );
        $insert->execute([
            ':module_code' => $moduleCode,
            ':title' => $title,
            ':body' => $body,
            ':starts_at' => $startDateIso,
            ':ends_at' => $endDateIso,
            ':is_active' => 1,
            ':created_at' => gmdate('c'),
        ]);

        $this->setFlash('success', 'Anuncio creado correctamente.');
        header('Location: /dashboard/announcements');
    }

    /**
     * Handles pseudo Google login for local testing.
     */
    private function apiGoogleLogin(): void
    {
        $payload = Security::jsonBody();
        $googleId = trim((string) ($payload['google_id'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $fullName = trim((string) ($payload['full_name'] ?? ''));

        if (!preg_match('/^[A-Za-z0-9._-]{4,128}$/', $googleId)) {
            $this->json(['ok' => false, 'message' => 'google_id invalido'], 422);
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['ok' => false, 'message' => 'email invalido'], 422);
            return;
        }
        if ($fullName === '' || mb_strlen($fullName) < 2 || mb_strlen($fullName) > 120) {
            $this->json(['ok' => false, 'message' => 'Nombre invalido'], 422);
            return;
        }

        $this->pdo->beginTransaction();
        try {
            $find = $this->pdo->prepare(
                'SELECT id FROM users WHERE google_id = :google_id OR email = :email LIMIT 1'
            );
            $find->execute([
                ':google_id' => $googleId,
                ':email' => $email,
            ]);
            $userRow = $find->fetch();

            if ($userRow === false) {
                $insert = $this->pdo->prepare(
                    'INSERT INTO users (google_id, email, full_name, status, created_at)
                     VALUES (:google_id, :email, :full_name, :status, :created_at)'
                );
                $insert->execute([
                    ':google_id' => $googleId,
                    ':email' => $email,
                    ':full_name' => $fullName,
                    ':status' => 'pending',
                    ':created_at' => gmdate('c'),
                ]);
                $userId = (int) $this->pdo->lastInsertId();
            } else {
                $userId = (int) $userRow['id'];
                $update = $this->pdo->prepare(
                    'UPDATE users SET google_id = :google_id, email = :email, full_name = :full_name WHERE id = :id'
                );
                $update->execute([
                    ':google_id' => $googleId,
                    ':email' => $email,
                    ':full_name' => $fullName,
                    ':id' => $userId,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            $this->json(['ok' => false, 'message' => 'No se pudo procesar el login'], 500);
            return;
        }

        $user = $this->getUserById($userId);
        if ($user === null) {
            $this->json(['ok' => false, 'message' => 'Usuario no encontrado'], 500);
            return;
        }
        if ((string) ($user['status'] ?? '') === 'blocked') {
            $this->json([
                'ok' => false,
                'message' => 'Tu cuenta esta bloqueada. Contacta con administracion.',
            ], 403);
            return;
        }

        $token = $this->issueToken($userId);

        $userModules = $this->getUserModules($userId);
        $this->json([
            'ok' => true,
            'token' => $token,
            'user' => $this->userPayload($user, $userModules),
            'message' => count($userModules) === 0 ? 'Perfil pendiente de asignacion por administrador.' : 'Login correcto.',
        ]);
    }

    /**
     * Starts real Google OAuth login flow for mobile/web app.
     */
    private function apiGoogleMobileLoginStart(): void
    {
        if (!$this->integrations->googleOauthConfigured()) {
            $this->json([
                'ok' => false,
                'message' => 'Google OAuth no esta configurado en el servidor.',
            ], 503);
            return;
        }

        $state = bin2hex(random_bytes(32));
        $expiresAt = gmdate('c', time() + 900);

        $insert = $this->pdo->prepare(
            'INSERT INTO google_mobile_login_states
                (state_token, status, expires_at, created_at, updated_at)
             VALUES
                (:state_token, :status, :expires_at, :created_at, :updated_at)'
        );
        $insert->execute([
            ':state_token' => $state,
            ':status' => 'pending',
            ':expires_at' => $expiresAt,
            ':created_at' => gmdate('c'),
            ':updated_at' => gmdate('c'),
        ]);

        $cleanup = $this->pdo->prepare(
            'DELETE FROM google_mobile_login_states
             WHERE expires_at < :now
                OR (status IN (\'completed\', \'error\') AND updated_at < :completed_cutoff)'
        );
        $cleanup->execute([
            ':now' => gmdate('c'),
            ':completed_cutoff' => gmdate('c', time() - 86400),
        ]);

        $authUrl = $this->integrations->buildGoogleAuthUrl(
            $state,
            ['openid', 'email', 'profile'],
            'select_account'
        );

        $this->json([
            'ok' => true,
            'state' => $state,
            'auth_url' => $authUrl,
            'expires_at' => $expiresAt,
            'message' => 'OAuth Google iniciado.',
        ]);
    }

    /**
     * Poll endpoint used by mobile app after opening Google OAuth browser.
     */
    private function apiGoogleMobileLoginStatus(): void
    {
        $state = trim((string) ($_GET['state'] ?? ''));
        if (!preg_match('/^[a-f0-9]{64}$/', $state)) {
            $this->json([
                'ok' => false,
                'message' => 'Parametro state invalido.',
            ], 422);
            return;
        }

        $query = $this->pdo->prepare(
            'SELECT state_token, user_id, google_email, api_token_enc, status, error_message, expires_at, completed_at, consumed_at
             FROM google_mobile_login_states
             WHERE state_token = :state_token
             LIMIT 1'
        );
        $query->execute([':state_token' => $state]);
        $row = $query->fetch();

        if ($row === false) {
            $this->json([
                'ok' => false,
                'message' => 'Estado de login no encontrado.',
            ], 404);
            return;
        }

        $status = (string) ($row['status'] ?? 'pending');
        $expiresAt = (string) ($row['expires_at'] ?? '');

        if ($expiresAt < gmdate('c') && $status === 'pending') {
            $expire = $this->pdo->prepare(
                'UPDATE google_mobile_login_states
                 SET status = :status, error_message = :error_message, updated_at = :updated_at
                 WHERE state_token = :state_token'
            );
            $expire->execute([
                ':status' => 'error',
                ':error_message' => 'Sesion de login Google expirada.',
                ':updated_at' => gmdate('c'),
                ':state_token' => $state,
            ]);

            $this->json([
                'ok' => true,
                'status' => 'error',
                'message' => 'Sesion de login Google expirada. Inicia de nuevo.',
            ]);
            return;
        }

        if ($status === 'pending') {
            $this->json([
                'ok' => true,
                'status' => 'pending',
                'message' => 'Esperando autorizacion Google...',
            ]);
            return;
        }

        if ($status === 'error') {
            $this->json([
                'ok' => true,
                'status' => 'error',
                'message' => (string) ($row['error_message'] ?? 'No se pudo completar login Google.'),
            ]);
            return;
        }

        if ((string) ($row['consumed_at'] ?? '') !== '') {
            $this->json([
                'ok' => true,
                'status' => 'consumed',
                'message' => 'Login ya consumido por la app.',
            ]);
            return;
        }

        $appSecret = (string) ($this->config['app_secret_key'] ?? '');
        $token = Security::decryptSecret((string) ($row['api_token_enc'] ?? ''), $appSecret);
        if ($token === null || $token === '') {
            $this->json([
                'ok' => true,
                'status' => 'error',
                'message' => 'No se pudo recuperar token de login.',
            ]);
            return;
        }

        $consume = $this->pdo->prepare(
            'UPDATE google_mobile_login_states
             SET consumed_at = :consumed_at, updated_at = :updated_at
             WHERE state_token = :state_token'
        );
        $consume->execute([
            ':consumed_at' => gmdate('c'),
            ':updated_at' => gmdate('c'),
            ':state_token' => $state,
        ]);

        $userId = (int) ($row['user_id'] ?? 0);
        $user = $this->getUserById($userId);
        $modules = $user === null ? [] : $this->getUserModules($userId);

        $this->json([
            'ok' => true,
            'status' => 'completed',
            'token' => $token,
            'user' => $user === null ? null : $this->userPayload($user, $modules),
            'google_email' => (string) ($row['google_email'] ?? ''),
            'message' => 'Login Google completado.',
        ]);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function apiMe(array $user): void
    {
        $modules = $this->getUserModules((int) $user['id']);
        $googleConnection = $this->getUserGoogleConnection((int) $user['id']);

        $this->json([
            'ok' => true,
            'user' => $this->userPayload($user, $modules),
            'pending_profile' => count($modules) === 0 || (string) $user['status'] !== 'active',
            'google_calendar_connected' => $googleConnection !== null,
            'google_calendar_email' => $googleConnection === null ? null : (string) ($googleConnection['google_email'] ?? ''),
            'payment_methods' => $this->paymentMethodsMap(),
            'locations' => $this->locationNamesMap(),
            'message' => count($modules) === 0 || (string) $user['status'] !== 'active'
                ? 'Pendiente de asignar perfil por parte del administrador.'
                : 'Perfil activo.',
        ]);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function apiGoogleConnectStart(array $user): void
    {
        if (!$this->integrations->googleOauthConfigured()) {
            $this->json([
                'ok' => false,
                'message' => 'Google OAuth no esta configurado en el servidor.',
            ], 503);
            return;
        }

        $state = bin2hex(random_bytes(32));
        $expiresAt = gmdate('c', time() + 900);

        $insert = $this->pdo->prepare(
            'INSERT INTO google_oauth_states (state_token, user_id, expires_at, used_at, created_at)
             VALUES (:state_token, :user_id, :expires_at, NULL, :created_at)'
        );
        $insert->execute([
            ':state_token' => $state,
            ':user_id' => (int) $user['id'],
            ':expires_at' => $expiresAt,
            ':created_at' => gmdate('c'),
        ]);

        $cleanup = $this->pdo->prepare('DELETE FROM google_oauth_states WHERE expires_at < :now');
        $cleanup->execute([':now' => gmdate('c')]);

        $this->json([
            'ok' => true,
            'auth_url' => $this->integrations->buildGoogleAuthUrl($state),
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function apiGoogleConnectStatus(array $user): void
    {
        $connection = $this->getUserGoogleConnection((int) $user['id']);
        $this->json([
            'ok' => true,
            'connected' => $connection !== null,
            'google_email' => $connection === null ? null : (string) ($connection['google_email'] ?? ''),
            'token_expires_at' => $connection === null ? null : (string) ($connection['token_expires_at'] ?? ''),
        ]);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function apiGoogleDisconnect(array $user): void
    {
        $delete = $this->pdo->prepare('DELETE FROM user_google_connections WHERE user_id = :user_id');
        $delete->execute([':user_id' => (int) $user['id']]);

        $this->json([
            'ok' => true,
            'message' => 'Cuenta Google desconectada.',
        ]);
    }

    private function handleGoogleOauthCallback(): void
    {
        Security::addSecurityHeaders();

        $error = trim((string) ($_GET['error'] ?? ''));
        if ($error !== '') {
            $this->renderOauthResultPage(false, 'Google devolvio error: ' . $error);
            return;
        }

        $state = trim((string) ($_GET['state'] ?? ''));
        $code = trim((string) ($_GET['code'] ?? ''));
        if (!preg_match('/^[a-f0-9]{64}$/', $state) || $code === '') {
            $this->renderOauthResultPage(false, 'Estado OAuth invalido.');
            return;
        }

        $mobileStateQuery = $this->pdo->prepare(
            'SELECT state_token, user_id, google_email, api_token_enc, status, error_message, expires_at, completed_at, consumed_at
             FROM google_mobile_login_states
             WHERE state_token = :state_token
             LIMIT 1'
        );
        $mobileStateQuery->execute([':state_token' => $state]);
        $mobileStateRow = $mobileStateQuery->fetch();
        if ($mobileStateRow !== false) {
            $this->handleGoogleMobileLoginCallback($mobileStateRow, $state, $code);
            return;
        }

        $connectStateQuery = $this->pdo->prepare(
            'SELECT state_token, user_id, expires_at, used_at
             FROM google_oauth_states
             WHERE state_token = :state_token
             LIMIT 1'
        );
        $connectStateQuery->execute([':state_token' => $state]);
        $connectStateRow = $connectStateQuery->fetch();
        if ($connectStateRow !== false) {
            $this->handleGoogleCalendarLinkCallback($connectStateRow, $state, $code);
            return;
        }

        $this->renderOauthResultPage(false, 'Estado OAuth no encontrado.');
    }

    /**
     * @param array<string, mixed> $stateRow
     */
    private function handleGoogleCalendarLinkCallback(array $stateRow, string $state, string $code): void
    {
        if ((string) ($stateRow['used_at'] ?? '') !== '') {
            $this->renderOauthResultPage(false, 'Estado OAuth ya utilizado.');
            return;
        }
        if ((string) $stateRow['expires_at'] < gmdate('c')) {
            $this->renderOauthResultPage(false, 'Sesion OAuth expirada. Inicia el enlace de nuevo.');
            return;
        }

        try {
            $tokenPayload = $this->integrations->exchangeGoogleAuthCode($code);
            $accessToken = trim((string) ($tokenPayload['access_token'] ?? ''));
            $refreshToken = trim((string) ($tokenPayload['refresh_token'] ?? ''));
            $expiresIn = (int) ($tokenPayload['expires_in'] ?? 3600);
            $scope = (string) ($tokenPayload['scope'] ?? '');

            if ($accessToken === '') {
                throw new \RuntimeException('No se recibio access_token de Google.');
            }

            $googleEmail = $this->integrations->fetchGoogleUserEmail($accessToken);
            $appSecret = (string) ($this->config['app_secret_key'] ?? '');
            $accessTokenEnc = Security::encryptSecret($accessToken, $appSecret);
            if ($accessTokenEnc === null) {
                throw new \RuntimeException('No se pudo cifrar access token.');
            }
            $refreshTokenEnc = $refreshToken === '' ? null : Security::encryptSecret($refreshToken, $appSecret);
            $expiresAt = gmdate('c', time() + max(300, $expiresIn - 60));

            $userId = (int) $stateRow['user_id'];
            $existingConnection = $this->getUserGoogleConnection($userId);
            if ($refreshTokenEnc === null && $existingConnection !== null) {
                $refreshTokenEnc = (string) ($existingConnection['refresh_token_enc'] ?? '') ?: null;
            }

            $upsert = $this->pdo->prepare(
                'INSERT INTO user_google_connections
                    (user_id, google_email, access_token_enc, refresh_token_enc, scope, token_expires_at, created_at, updated_at)
                 VALUES
                    (:user_id, :google_email, :access_token_enc, :refresh_token_enc, :scope, :token_expires_at, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    google_email = VALUES(google_email),
                    access_token_enc = VALUES(access_token_enc),
                    refresh_token_enc = VALUES(refresh_token_enc),
                    scope = VALUES(scope),
                    token_expires_at = VALUES(token_expires_at),
                    updated_at = VALUES(updated_at)'
            );
            $upsert->execute([
                ':user_id' => $userId,
                ':google_email' => $googleEmail,
                ':access_token_enc' => $accessTokenEnc,
                ':refresh_token_enc' => $refreshTokenEnc,
                ':scope' => $scope,
                ':token_expires_at' => $expiresAt,
                ':created_at' => gmdate('c'),
                ':updated_at' => gmdate('c'),
            ]);

            $markUsed = $this->pdo->prepare(
                'UPDATE google_oauth_states SET used_at = :used_at WHERE state_token = :state_token'
            );
            $markUsed->execute([
                ':used_at' => gmdate('c'),
                ':state_token' => $state,
            ]);

            $this->logIntegration($userId, 'google_oauth', 'success', (string) ($googleEmail ?? 'unknown'), 'Cuenta vinculada');
            $this->renderOauthResultPage(true, 'Cuenta Google vinculada correctamente. Ya puedes volver a la app.');
        } catch (\Throwable $exception) {
            $this->logIntegration(
                isset($stateRow['user_id']) ? (int) $stateRow['user_id'] : null,
                'google_oauth',
                'error',
                'oauth_callback',
                $exception->getMessage()
            );
            $this->renderOauthResultPage(false, 'No se pudo vincular Google: ' . $exception->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $stateRow
     */
    private function handleGoogleMobileLoginCallback(array $stateRow, string $state, string $code): void
    {
        $status = (string) ($stateRow['status'] ?? 'pending');
        if ($status !== 'pending') {
            $this->renderOauthResultPage(false, 'Este login Google ya se proceso. Vuelve a la app y reinicia el acceso.');
            return;
        }
        if ((string) ($stateRow['expires_at'] ?? '') < gmdate('c')) {
            $expired = $this->pdo->prepare(
                'UPDATE google_mobile_login_states
                 SET status = :status, error_message = :error_message, updated_at = :updated_at
                 WHERE state_token = :state_token'
            );
            $expired->execute([
                ':status' => 'error',
                ':error_message' => 'Sesion expirada en callback OAuth.',
                ':updated_at' => gmdate('c'),
                ':state_token' => $state,
            ]);
            $this->renderOauthResultPage(false, 'Sesion OAuth expirada. Inicia el login de nuevo desde la app.');
            return;
        }

        try {
            $tokenPayload = $this->integrations->exchangeGoogleAuthCode($code);
            $accessToken = trim((string) ($tokenPayload['access_token'] ?? ''));
            if ($accessToken === '') {
                throw new \RuntimeException('Google no devolvio access_token para login.');
            }

            $profile = $this->integrations->fetchGoogleUserProfile($accessToken);
            if ($profile === null) {
                throw new \RuntimeException('No se pudo leer perfil de Google.');
            }

            $googleId = trim((string) ($profile['id'] ?? ''));
            $email = trim((string) ($profile['email'] ?? ''));
            $fullName = trim((string) ($profile['name'] ?? ''));

            if (!preg_match('/^[A-Za-z0-9._-]{4,128}$/', $googleId)) {
                throw new \RuntimeException('Google ID invalido en respuesta OAuth.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Email de Google invalido.');
            }
            if ($fullName === '') {
                $fullName = $email;
            }
            $fullName = mb_substr($fullName, 0, 120);

            $appSecret = (string) ($this->config['app_secret_key'] ?? '');
            $this->pdo->beginTransaction();
            try {
                $find = $this->pdo->prepare(
                    'SELECT id FROM users WHERE google_id = :google_id OR email = :email LIMIT 1'
                );
                $find->execute([
                    ':google_id' => $googleId,
                    ':email' => $email,
                ]);
                $userRow = $find->fetch();

                if ($userRow === false) {
                    $insert = $this->pdo->prepare(
                        'INSERT INTO users (google_id, email, full_name, status, created_at)
                         VALUES (:google_id, :email, :full_name, :status, :created_at)'
                    );
                    $insert->execute([
                        ':google_id' => $googleId,
                        ':email' => $email,
                        ':full_name' => $fullName,
                        ':status' => 'pending',
                        ':created_at' => gmdate('c'),
                    ]);
                    $userId = (int) $this->pdo->lastInsertId();
                } else {
                    $userId = (int) $userRow['id'];
                    $updateUser = $this->pdo->prepare(
                        'UPDATE users SET google_id = :google_id, email = :email, full_name = :full_name WHERE id = :id'
                    );
                    $updateUser->execute([
                        ':google_id' => $googleId,
                        ':email' => $email,
                        ':full_name' => $fullName,
                        ':id' => $userId,
                    ]);
                }

                $statusQuery = $this->pdo->prepare('SELECT status FROM users WHERE id = :id LIMIT 1');
                $statusQuery->execute([':id' => $userId]);
                $statusRow = $statusQuery->fetch();
                if ($statusRow === false) {
                    throw new \RuntimeException('No se pudo leer el estado del usuario.');
                }
                if ((string) ($statusRow['status'] ?? '') === 'blocked') {
                    throw new \RuntimeException('Tu cuenta esta bloqueada. Contacta con administracion.');
                }

                $apiToken = $this->issueToken($userId);
                $apiTokenEnc = Security::encryptSecret($apiToken, $appSecret);
                if ($apiTokenEnc === null) {
                    throw new \RuntimeException('No se pudo cifrar token de app.');
                }

                $complete = $this->pdo->prepare(
                    'UPDATE google_mobile_login_states
                     SET user_id = :user_id,
                         google_email = :google_email,
                         api_token_enc = :api_token_enc,
                         status = :status,
                         error_message = NULL,
                         completed_at = :completed_at,
                         updated_at = :updated_at
                     WHERE state_token = :state_token'
                );
                $complete->execute([
                    ':user_id' => $userId,
                    ':google_email' => $email,
                    ':api_token_enc' => $apiTokenEnc,
                    ':status' => 'completed',
                    ':completed_at' => gmdate('c'),
                    ':updated_at' => gmdate('c'),
                    ':state_token' => $state,
                ]);

                $this->pdo->commit();

                $this->logIntegration(
                    $userId,
                    'google_mobile_login',
                    'success',
                    $email,
                    'Login Google completado para app movil'
                );
            } catch (\Throwable $inner) {
                $this->pdo->rollBack();
                throw $inner;
            }

            $this->renderOauthResultPage(
                true,
                'Login Google completado. Vuelve a la app y espera unos segundos para continuar.'
            );
        } catch (\Throwable $exception) {
            $markError = $this->pdo->prepare(
                'UPDATE google_mobile_login_states
                 SET status = :status, error_message = :error_message, updated_at = :updated_at
                 WHERE state_token = :state_token'
            );
            $markError->execute([
                ':status' => 'error',
                ':error_message' => mb_substr($exception->getMessage(), 0, 500),
                ':updated_at' => gmdate('c'),
                ':state_token' => $state,
            ]);

            $this->logIntegration(
                null,
                'google_mobile_login',
                'error',
                'oauth_callback',
                $exception->getMessage()
            );
            $this->renderOauthResultPage(false, 'No se pudo completar login Google: ' . $exception->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $user
     */
    private function apiActivities(array $user): void
    {
        if (!$this->userHasActiveAccess($user)) {
            $this->json([
                'ok' => true,
                'activities' => [],
                'message' => 'Tu perfil aun no tiene acceso a modulos.',
            ]);
            return;
        }

        $moduleCodes = array_map(
            static fn (array $module): string => (string) $module['code'],
            $this->getUserModules((int) $user['id'])
        );

        if (count($moduleCodes) === 0) {
            $this->json(['ok' => true, 'activities' => []]);
            return;
        }

        $activities = $this->fetchScheduleActivities(
            moduleCodes: $moduleCodes,
            includeAllModules: false,
            includeOnlyActive: true,
            daysAhead: 30
        );

        $this->json([
            'ok' => true,
            'activities' => $activities,
            'payment_methods' => $this->paymentMethodsMap(),
            'locations' => $this->locationNamesMap(),
        ]);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function apiSchedule(array $user): void
    {
        if (!$this->userHasActiveAccess($user)) {
            $this->json([
                'ok' => true,
                'schedule' => [],
                'week_board' => [],
                'message' => 'Tu perfil aun no tiene acceso a modulos.',
            ]);
            return;
        }

        $moduleCodes = array_map(
            static fn (array $module): string => (string) $module['code'],
            $this->getUserModules((int) $user['id'])
        );

        $activities = $this->fetchScheduleActivities(
            moduleCodes: $moduleCodes,
            includeAllModules: false,
            includeOnlyActive: true,
            daysAhead: 14
        );

        $this->json([
            'ok' => true,
            'schedule' => $activities,
            'week_board' => $this->groupActivitiesByWeekAndDay($activities),
            'location_names' => $this->locationNamesMap(),
        ]);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function apiReserveActivity(array $user, int $activityId): void
    {
        $payload = Security::jsonBody();
        $paymentMethodCode = $this->normalizePaymentMethod($payload['payment_method'] ?? null);
        if ($paymentMethodCode === null) {
            $this->json([
                'ok' => false,
                'message' => 'Metodo de pago invalido. Usa: efectivo, bizum o tarjeta.',
            ], 422);
            return;
        }

        if (!$this->userHasActiveAccess($user)) {
            $this->json(['ok' => false, 'message' => 'Tu perfil no tiene acceso a reservas.'], 403);
            return;
        }

        if ($activityId <= 0) {
            $this->json(['ok' => false, 'message' => 'Actividad invalida.'], 422);
            return;
        }

        $activity = $this->getActivityById($activityId);
        if ($activity === null || (string) $activity['status'] !== 'active') {
            $this->json(['ok' => false, 'message' => 'Actividad no disponible.'], 404);
            return;
        }

        $userModules = $this->getUserModules((int) $user['id']);
        $moduleCodes = array_map(static fn (array $module): string => (string) $module['code'], $userModules);
        if (!in_array((string) $activity['module_code'], $moduleCodes, true)) {
            $this->json(['ok' => false, 'message' => 'No tienes acceso a este modulo.'], 403);
            return;
        }

        $exists = $this->pdo->prepare(
            'SELECT id FROM reservations
             WHERE user_id = :user_id
             AND activity_id = :activity_id
             AND status IN (\'pending_user_confirm\', \'pending_admin_approval\', \'confirmed\', \'waitlist\')
             LIMIT 1'
        );
        $exists->execute([
            ':user_id' => (int) $user['id'],
            ':activity_id' => $activityId,
        ]);
        if ($exists->fetch() !== false) {
            $this->json(['ok' => false, 'message' => 'Ya tienes una reserva activa o en lista de espera.'], 409);
            return;
        }

        $now = gmdate('c');

        $this->pdo->beginTransaction();
        try {
            $occupied = $this->occupiedSlots($activityId);
            $capacity = (int) $activity['capacity'];

            $status = 'waitlist';
            $confirmationCode = null;
            $confirmationCodeHash = null;
            $confirmationDeadline = null;

            if ($occupied < $capacity) {
                $status = 'pending_user_confirm';
                $confirmationCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $confirmationCodeHash = hash('sha256', $confirmationCode);
                $confirmationDeadline = gmdate('c', time() + 1800);
            }

            $insert = $this->pdo->prepare(
                'INSERT INTO reservations (user_id, activity_id, status, confirmation_code_hash, confirmation_deadline, payment_status, payment_method, created_at, updated_at)
                 VALUES (:user_id, :activity_id, :status, :confirmation_code_hash, :confirmation_deadline, :payment_status, :payment_method, :created_at, :updated_at)'
            );
            $insert->execute([
                ':user_id' => (int) $user['id'],
                ':activity_id' => $activityId,
                ':status' => $status,
                ':confirmation_code_hash' => $confirmationCodeHash,
                ':confirmation_deadline' => $confirmationDeadline,
                ':payment_status' => $status === 'waitlist' ? 'pending_waitlist' : 'pending_user_confirmation',
                ':payment_method' => $paymentMethodCode,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);

            $reservationId = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            $this->json(['ok' => false, 'message' => 'No se pudo crear la reserva.'], 500);
            return;
        }

        if ($status === 'waitlist') {
            $this->json([
                'ok' => true,
                'reservation_id' => $reservationId,
                'status' => 'waitlist',
                'payment_method' => $paymentMethodCode,
                'payment_method_label' => $this->paymentMethodLabel($paymentMethodCode),
                'message' => 'Sin cupo disponible. Has entrado en lista de espera.',
            ]);
            return;
        }

        $emailSent = false;
        $emailError = '';
        if ($confirmationCode !== null) {
            $emailResult = $this->sendReservationCodeEmail($user, $activity, $confirmationCode, $paymentMethodCode);
            $emailSent = (bool) $emailResult['ok'];
            $emailError = (string) $emailResult['error'];
        }

        $response = [
            'ok' => true,
            'reservation_id' => $reservationId,
            'status' => 'pending_user_confirm',
            'payment_method' => $paymentMethodCode,
            'payment_method_label' => $this->paymentMethodLabel($paymentMethodCode),
            'email_sent' => $emailSent,
            'message' => 'Reserva creada. Confirma con el codigo recibido para pasar a validacion admin.',
        ];

        // In local/dev or SMTP failure, return code fallback to prevent blocking QA.
        if (!$emailSent && $confirmationCode !== null) {
            $response['local_confirmation_code'] = $confirmationCode;
            if ($emailError !== '') {
                $response['email_error'] = $emailError;
            }
        }

        $this->json([
            ...$response,
        ]);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function apiConfirmReservation(array $user, int $reservationId): void
    {
        $payload = Security::jsonBody();
        $confirmationCode = trim((string) ($payload['confirmation_code'] ?? ''));

        if (!preg_match('/^\d{6}$/', $confirmationCode)) {
            $this->json(['ok' => false, 'message' => 'Codigo de confirmacion invalido.'], 422);
            return;
        }

        $query = $this->pdo->prepare(
            'SELECT id, status, confirmation_code_hash, confirmation_deadline
             FROM reservations
             WHERE id = :id AND user_id = :user_id
             LIMIT 1'
        );
        $query->execute([
            ':id' => $reservationId,
            ':user_id' => (int) $user['id'],
        ]);
        $reservation = $query->fetch();

        if ($reservation === false || (string) $reservation['status'] !== 'pending_user_confirm') {
            $this->json(['ok' => false, 'message' => 'La reserva no requiere confirmacion o no existe.'], 404);
            return;
        }

        $deadlineRaw = (string) ($reservation['confirmation_deadline'] ?? '');
        $deadline = date_create_immutable($deadlineRaw);
        if ($deadline === false || $deadline < new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) {
            $this->json(['ok' => false, 'message' => 'El codigo ha expirado.'], 410);
            return;
        }

        $codeHash = hash('sha256', $confirmationCode);
        if (!hash_equals((string) $reservation['confirmation_code_hash'], $codeHash)) {
            $this->json(['ok' => false, 'message' => 'Codigo incorrecto.'], 422);
            return;
        }

        $update = $this->pdo->prepare(
            'UPDATE reservations
             SET status = :status,
                 payment_status = :payment_status,
                 confirmation_code_hash = NULL,
                 confirmation_deadline = NULL,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $update->execute([
            ':status' => 'pending_admin_approval',
            ':payment_status' => 'pending_admin_validation',
            ':updated_at' => gmdate('c'),
            ':id' => $reservationId,
        ]);

        $this->json([
            'ok' => true,
            'status' => 'pending_admin_approval',
            'message' => 'Reserva confirmada por usuario. Ahora queda pendiente de aprobacion manual del administrador.',
            'google_calendar' => 'Integracion real pendiente para entorno productivo.',
        ]);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function apiCancelReservation(array $user, int $reservationId): void
    {
        $query = $this->pdo->prepare(
            'SELECT r.id, r.activity_id, r.status, a.starts_at
             FROM reservations r
             INNER JOIN activities a ON a.id = r.activity_id
             WHERE r.id = :id AND r.user_id = :user_id
             LIMIT 1'
        );
        $query->execute([
            ':id' => $reservationId,
            ':user_id' => (int) $user['id'],
        ]);
        $reservation = $query->fetch();

        if ($reservation === false) {
            $this->json(['ok' => false, 'message' => 'Reserva no encontrada.'], 404);
            return;
        }

        if ((string) $reservation['status'] === 'cancelled') {
            $this->json(['ok' => false, 'message' => 'La reserva ya esta cancelada.'], 409);
            return;
        }

        $startDate = date_create_immutable((string) $reservation['starts_at']);
        if ($startDate === false) {
            $this->json(['ok' => false, 'message' => 'Error en fecha de actividad.'], 500);
            return;
        }

        $hoursUntilActivity = ((int) $startDate->format('U') - time()) / 3600;
        if ($hoursUntilActivity < (int) $this->config['reservation_cancellation_hours']) {
            $this->json([
                'ok' => false,
                'message' => 'No se puede cancelar con menos de 2 horas de antelacion.',
            ], 409);
            return;
        }

        $oldStatus = (string) $reservation['status'];
        $seatStatuses = ['pending_user_confirm', 'pending_admin_approval', 'confirmed'];
        $releasesSeat = in_array($oldStatus, $seatStatuses, true);

        $this->pdo->beginTransaction();
        try {
            $update = $this->pdo->prepare(
                'UPDATE reservations SET status = :status, updated_at = :updated_at WHERE id = :id'
            );
            $update->execute([
                ':status' => 'cancelled',
                ':updated_at' => gmdate('c'),
                ':id' => (int) $reservation['id'],
            ]);

            if ($releasesSeat) {
                $waitlist = $this->pdo->prepare(
                    'SELECT id FROM reservations
                     WHERE activity_id = :activity_id
                     AND status = :status
                     ORDER BY created_at ASC
                     LIMIT 1'
                );
                $waitlist->execute([
                    ':activity_id' => (int) $reservation['activity_id'],
                    ':status' => 'waitlist',
                ]);
                $next = $waitlist->fetch();

                if ($next !== false) {
                    $promote = $this->pdo->prepare(
                        'UPDATE reservations
                         SET status = :status, updated_at = :updated_at
                         WHERE id = :id'
                    );
                    $promote->execute([
                        ':status' => 'pending_admin_approval',
                        ':updated_at' => gmdate('c'),
                        ':id' => (int) $next['id'],
                    ]);
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            $this->json(['ok' => false, 'message' => 'No se pudo cancelar la reserva.'], 500);
            return;
        }

        $this->json([
            'ok' => true,
            'message' => 'Reserva cancelada correctamente.',
        ]);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function apiReservations(array $user): void
    {
        $query = $this->pdo->prepare(
            'SELECT r.id, r.status, r.payment_status, r.payment_method, r.google_calendar_event_id, r.calendar_sync_status, r.created_at, r.updated_at,
                    a.id AS activity_id, a.title, a.module_code, a.starts_at, a.ends_at, a.location
             FROM reservations r
             INNER JOIN activities a ON a.id = r.activity_id
             WHERE r.user_id = :user_id
             ORDER BY a.starts_at ASC'
        );
        $query->execute([':user_id' => (int) $user['id']]);

        $reservations = [];
        foreach ($query->fetchAll() as $row) {
            $reservations[] = [
                'id' => (int) $row['id'],
                'status' => (string) $row['status'],
                'payment_status' => (string) $row['payment_status'],
                'payment_method' => (string) $row['payment_method'],
                'payment_method_label' => $this->paymentMethodLabel((string) $row['payment_method']),
                'google_calendar_event_id' => (string) ($row['google_calendar_event_id'] ?? ''),
                'calendar_sync_status' => (string) ($row['calendar_sync_status'] ?? ''),
                'created_at' => (string) $row['created_at'],
                'updated_at' => (string) $row['updated_at'],
                'activity' => [
                    'id' => (int) $row['activity_id'],
                    'title' => (string) $row['title'],
                    'module_code' => (string) $row['module_code'],
                    'starts_at' => (string) $row['starts_at'],
                    'ends_at' => (string) $row['ends_at'],
                    'location' => (string) $row['location'],
                ],
            ];
        }

        $this->json([
            'ok' => true,
            'reservations' => $reservations,
        ]);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function apiAnnouncements(array $user): void
    {
        $userModules = $this->getUserModules((int) $user['id']);
        $moduleCodes = array_map(static fn (array $module): string => (string) $module['code'], $userModules);

        $now = gmdate('c');
        if (count($moduleCodes) === 0) {
            $query = $this->pdo->prepare(
                'SELECT id, module_code, title, body, starts_at, ends_at
                 FROM announcements
                 WHERE is_active = 1
                   AND starts_at <= :now
                   AND ends_at >= :now
                   AND module_code IS NULL
                 ORDER BY starts_at DESC'
            );
            $query->execute([':now' => $now]);
        } else {
            $placeholders = implode(', ', array_fill(0, count($moduleCodes), '?'));
            $query = $this->pdo->prepare(
                "SELECT id, module_code, title, body, starts_at, ends_at
                 FROM announcements
                 WHERE is_active = 1
                   AND starts_at <= ?
                   AND ends_at >= ?
                   AND (module_code IS NULL OR module_code IN ($placeholders))
                 ORDER BY starts_at DESC"
            );
            $params = [$now, $now, ...$moduleCodes];
            $query->execute($params);
        }

        $announcements = [];
        foreach ($query->fetchAll() as $row) {
            $announcements[] = [
                'id' => (int) $row['id'],
                'module_code' => $row['module_code'] === null ? null : (string) $row['module_code'],
                'title' => (string) $row['title'],
                'body' => (string) $row['body'],
                'starts_at' => (string) $row['starts_at'],
                'ends_at' => (string) $row['ends_at'],
            ];
        }

        $this->json([
            'ok' => true,
            'announcements' => $announcements,
        ]);
    }

    /**
     * @param array<int, string> $moduleCodes
     * @return array<int, array<string, mixed>>
     */
    private function fetchScheduleActivities(
        array $moduleCodes,
        bool $includeAllModules,
        bool $includeOnlyActive,
        int $daysAhead
    ): array {
        if (!$includeAllModules && count($moduleCodes) === 0) {
            return [];
        }

        $utc = new \DateTimeZone('UTC');
        $now = new \DateTimeImmutable('now', $utc);
        $limit = $now->modify('+' . max(1, $daysAhead) . ' days');

        $conditions = [];
        $params = [];

        if ($includeOnlyActive) {
            $conditions[] = "a.status = 'active'";
        }

        if (!$includeAllModules) {
            $placeholders = implode(', ', array_fill(0, count($moduleCodes), '?'));
            $conditions[] = "a.module_code IN ($placeholders)";
            $params = [...$params, ...$moduleCodes];
        }

        $conditions[] = 'a.ends_at >= ?';
        $params[] = $now->format('c');
        $conditions[] = 'a.starts_at <= ?';
        $params[] = $limit->format('c');

        $whereSql = implode(' AND ', $conditions);
        $query = $this->pdo->prepare(
            "SELECT a.*,
               (
                 SELECT COUNT(*)
                 FROM reservations r
                 WHERE r.activity_id = a.id
                 AND r.status IN ('pending_user_confirm', 'pending_admin_approval', 'confirmed')
               ) AS occupied_slots
             FROM activities a
             WHERE $whereSql
             ORDER BY a.starts_at ASC"
        );
        $query->execute($params);

        $locationNames = $this->locationNamesMap();
        $activities = [];
        foreach ($query->fetchAll() as $row) {
            $capacity = (int) $row['capacity'];
            $occupied = (int) $row['occupied_slots'];
            $remaining = max(0, $capacity - $occupied);
            $startLocal = $this->toLocalDate((string) $row['starts_at']);
            $endLocal = $this->toLocalDate((string) $row['ends_at']);

            $locationValue = (string) $row['location'];
            $locationLabel = in_array($locationValue, $locationNames, true) ? $locationValue : $locationValue;

            $activities[] = [
                'id' => (int) $row['id'],
                'title' => (string) $row['title'],
                'module_code' => (string) $row['module_code'],
                'starts_at' => (string) $row['starts_at'],
                'ends_at' => (string) $row['ends_at'],
                'starts_at_local' => $startLocal === null ? null : $startLocal->format('Y-m-d H:i'),
                'ends_at_local' => $endLocal === null ? null : $endLocal->format('Y-m-d H:i'),
                'weekday' => $startLocal === null ? null : $this->weekdayNameEs((int) $startLocal->format('N')),
                'date_label' => $startLocal === null ? null : $startLocal->format('d/m'),
                'time_range' => ($startLocal !== null && $endLocal !== null)
                    ? $startLocal->format('H:i') . ' - ' . $endLocal->format('H:i')
                    : null,
                'capacity' => $capacity,
                'occupied_slots' => $occupied,
                'remaining_slots' => $remaining,
                'location' => $locationValue,
                'location_label' => $locationLabel,
                'notes' => (string) $row['notes'],
            ];
        }

        return $activities;
    }

    /**
     * @param array<int, array<string, mixed>> $activities
     * @return array<int, array<string, mixed>>
     */
    private function groupActivitiesByWeekAndDay(array $activities): array
    {
        $board = [];
        foreach ($activities as $activity) {
            $startLocal = $this->toLocalDate((string) ($activity['starts_at'] ?? ''));
            if ($startLocal === null) {
                continue;
            }

            $isoWeek = $startLocal->format('o-\WW');
            $weekStart = $startLocal->modify('monday this week');
            $weekEnd = $weekStart->modify('+6 days');
            $weekLabel = 'Semana ' . $startLocal->format('W') . ' (' . $weekStart->format('d/m') . ' - ' . $weekEnd->format('d/m') . ')';

            $weekdayIndex = (int) $startLocal->format('N');
            $dayLabel = $this->weekdayNameEs($weekdayIndex);
            $dayDate = $startLocal->format('d/m');
            $dayKey = $weekdayIndex . '-' . $dayDate;

            if (!isset($board[$isoWeek])) {
                $board[$isoWeek] = [
                    'week_key' => $isoWeek,
                    'week_label' => $weekLabel,
                    'days' => [],
                ];
            }

            if (!isset($board[$isoWeek]['days'][$dayKey])) {
                $board[$isoWeek]['days'][$dayKey] = [
                    'weekday_index' => $weekdayIndex,
                    'day_label' => $dayLabel,
                    'date_label' => $dayDate,
                    'items' => [],
                ];
            }

            $board[$isoWeek]['days'][$dayKey]['items'][] = $activity;
        }

        foreach ($board as &$week) {
            uasort(
                $week['days'],
                static fn (array $a, array $b): int => ($a['weekday_index'] <=> $b['weekday_index'])
            );
        }
        unset($week);

        return array_values($board);
    }

    /**
     * @return array<string, string>
     */
    private function paymentMethodsMap(): array
    {
        $methods = (array) ($this->config['payment_methods'] ?? []);
        if ($methods === []) {
            return [
                'cash' => 'Efectivo',
                'bizum' => 'Bizum',
                'card' => 'Tarjeta',
            ];
        }

        $mapped = [];
        foreach ($methods as $code => $label) {
            if (!is_string($code) || !is_string($label)) {
                continue;
            }
            $mapped[$code] = $label;
        }

        return $mapped;
    }

    /**
     * @return array<string, string>
     */
    private function locationNamesMap(): array
    {
        $locations = (array) ($this->config['locations'] ?? []);
        $mapped = [];
        foreach ($locations as $code => $meta) {
            if (!is_string($code)) {
                continue;
            }

            if (is_array($meta) && isset($meta['name']) && is_string($meta['name'])) {
                $mapped[$code] = $meta['name'];
                continue;
            }
        }

        if ($mapped === []) {
            $mapped = [
                'cala_dor' => "Cala d'Or (rotonda Farash)",
                'cala_egos' => 'Cala Egos (delante del SYP)',
            ];
        }

        return $mapped;
    }

    private function isValidPaymentMethod(string $code): bool
    {
        return array_key_exists($code, $this->paymentMethodsMap());
    }

    private function paymentMethodLabel(string $code): string
    {
        return $this->paymentMethodsMap()[$code] ?? $code;
    }

    private function normalizePaymentMethod(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $raw = mb_strtolower(trim($value));
        $aliases = [
            'cash' => 'cash',
            'efectivo' => 'cash',
            'bizum' => 'bizum',
            'card' => 'card',
            'tarjeta' => 'card',
        ];

        if (!isset($aliases[$raw])) {
            return null;
        }

        $normalized = $aliases[$raw];
        return $this->isValidPaymentMethod($normalized) ? $normalized : null;
    }

    private function toLocalDate(string $isoDate): ?\DateTimeImmutable
    {
        $date = date_create_immutable($isoDate);
        if ($date === false) {
            return null;
        }

        $timezone = new \DateTimeZone((string) ($this->config['timezone'] ?? 'Europe/Madrid'));
        return $date->setTimezone($timezone);
    }

    private function weekdayNameEs(int $isoWeekday): string
    {
        return match ($isoWeekday) {
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miercoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sabado',
            7 => 'Domingo',
            default => 'Dia',
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getUserGoogleConnection(int $userId): ?array
    {
        $query = $this->pdo->prepare(
            'SELECT id, user_id, google_email, access_token_enc, refresh_token_enc, scope, token_expires_at, created_at, updated_at
             FROM user_google_connections
             WHERE user_id = :user_id
             LIMIT 1'
        );
        $query->execute([':user_id' => $userId]);
        $row = $query->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $activity
     * @return array{ok:bool,error:string}
     */
    private function sendReservationCodeEmail(array $user, array $activity, string $code, string $paymentMethod): array
    {
        if (!$this->integrations->smtpConfigured()) {
            return ['ok' => false, 'error' => 'SMTP no configurado'];
        }

        $email = (string) ($user['email'] ?? '');
        $name = (string) ($user['full_name'] ?? 'Usuario');
        $activityTitle = (string) ($activity['title'] ?? 'Actividad');
        $location = (string) ($activity['location'] ?? '');
        $startLocal = $this->toLocalDate((string) ($activity['starts_at'] ?? ''));
        $startLabel = $startLocal === null ? (string) ($activity['starts_at'] ?? '') : $startLocal->format('d/m/Y H:i');
        $paymentLabel = $this->paymentMethodLabel($paymentMethod);

        $subject = 'Codigo de confirmacion - Club Agelai';
        $body = "Hola {$name},\n\n"
            . "Tu pre-reserva para {$activityTitle} se ha creado correctamente.\n"
            . "Sede: {$location}\n"
            . "Fecha y hora: {$startLabel}\n"
            . "Metodo de pago elegido: {$paymentLabel}\n\n"
            . "Codigo de confirmacion: {$code}\n"
            . "Este codigo caduca en 30 minutos.\n\n"
            . "Equipo Club Agelai";

        $sendResult = $this->integrations->sendSmtpMail($email, $subject, $body);
        $this->logIntegration(
            isset($user['id']) ? (int) $user['id'] : null,
            'smtp_confirmation_code',
            $sendResult['ok'] ? 'success' : 'error',
            $email,
            $sendResult['ok'] ? 'Codigo enviado' : $sendResult['error']
        );

        return $sendResult;
    }

    /**
     * @param array<string, mixed> $reservation
     * @return array{ok:bool,error:string}
     */
    private function sendReservationApprovedEmail(array $reservation): array
    {
        if (!$this->integrations->smtpConfigured()) {
            return ['ok' => false, 'error' => 'SMTP no configurado'];
        }

        $email = (string) ($reservation['email'] ?? '');
        $name = (string) ($reservation['full_name'] ?? 'Usuario');
        $activityTitle = (string) ($reservation['title'] ?? 'Actividad');
        $location = (string) ($reservation['location'] ?? '');
        $startLocal = $this->toLocalDate((string) ($reservation['starts_at'] ?? ''));
        $startLabel = $startLocal === null ? (string) ($reservation['starts_at'] ?? '') : $startLocal->format('d/m/Y H:i');
        $paymentLabel = $this->paymentMethodLabel((string) ($reservation['payment_method'] ?? 'cash'));

        $subject = 'Reserva confirmada - Club Agelai';
        $body = "Hola {$name},\n\n"
            . "Tu reserva ha sido aprobada manualmente por el equipo de Club Agelai.\n\n"
            . "Actividad: {$activityTitle}\n"
            . "Sede: {$location}\n"
            . "Fecha y hora: {$startLabel}\n"
            . "Metodo de pago: {$paymentLabel}\n\n"
            . "Si no puedes asistir, recuerda cancelar con al menos 2 horas de antelacion.\n\n"
            . "Equipo Club Agelai";

        $sendResult = $this->integrations->sendSmtpMail($email, $subject, $body);
        $this->logIntegration(
            isset($reservation['user_id']) ? (int) $reservation['user_id'] : null,
            'smtp_reservation_approved',
            $sendResult['ok'] ? 'success' : 'error',
            $email,
            $sendResult['ok'] ? 'Confirmacion enviada' : $sendResult['error']
        );

        return $sendResult;
    }

    /**
     * @param array<string, mixed> $reservation
     * @return array{ok:bool,error:string,event_id:string}
     */
    private function syncReservationToGoogleCalendar(array $reservation): array
    {
        $userId = (int) ($reservation['user_id'] ?? 0);
        if ($userId <= 0) {
            return ['ok' => false, 'error' => 'Usuario invalido para calendar sync', 'event_id' => ''];
        }

        $connection = $this->getUserGoogleConnection($userId);
        if ($connection === null) {
            $this->markReservationCalendarSync((int) $reservation['id'], 'not_linked', null);
            return ['ok' => false, 'error' => 'Usuario sin Google vinculado', 'event_id' => ''];
        }

        try {
            $accessToken = $this->getValidGoogleAccessToken($connection);
            if ($accessToken === null) {
                $this->markReservationCalendarSync((int) $reservation['id'], 'token_error', null);
                return ['ok' => false, 'error' => 'No se pudo obtener token Google valido', 'event_id' => ''];
            }

            $startIso = (string) ($reservation['starts_at'] ?? '');
            $endIso = (string) ($reservation['ends_at'] ?? '');
            $eventPayload = [
                'summary' => 'Club Agelai - ' . (string) ($reservation['title'] ?? 'Actividad'),
                'location' => (string) ($reservation['location'] ?? ''),
                'description' => 'Reserva confirmada en Club Agelai. Modulo: '
                    . (string) ($reservation['module_code'] ?? '')
                    . '. Pago: ' . $this->paymentMethodLabel((string) ($reservation['payment_method'] ?? 'cash')),
                'start' => [
                    'dateTime' => $startIso,
                    'timeZone' => (string) ($this->config['timezone'] ?? 'Europe/Madrid'),
                ],
                'end' => [
                    'dateTime' => $endIso,
                    'timeZone' => (string) ($this->config['timezone'] ?? 'Europe/Madrid'),
                ],
            ];

            $eventId = $this->integrations->createGoogleCalendarEvent($accessToken, $eventPayload);
            if ($eventId === null) {
                $this->markReservationCalendarSync((int) $reservation['id'], 'event_error', null);
                return ['ok' => false, 'error' => 'Google no devolvio event id', 'event_id' => ''];
            }

            $this->markReservationCalendarSync((int) $reservation['id'], 'synced', $eventId);
            $this->logIntegration($userId, 'google_calendar', 'success', (string) ($connection['google_email'] ?? ''), 'Event ID: ' . $eventId);
            return ['ok' => true, 'error' => '', 'event_id' => $eventId];
        } catch (\Throwable $exception) {
            $this->markReservationCalendarSync((int) $reservation['id'], 'sync_error', null);
            $this->logIntegration($userId, 'google_calendar', 'error', (string) ($connection['google_email'] ?? ''), $exception->getMessage());
            return ['ok' => false, 'error' => $exception->getMessage(), 'event_id' => ''];
        }
    }

    /**
     * @param array<string, mixed> $connection
     */
    private function getValidGoogleAccessToken(array $connection): ?string
    {
        $appSecret = (string) ($this->config['app_secret_key'] ?? '');
        $accessTokenEnc = (string) ($connection['access_token_enc'] ?? '');
        $refreshTokenEnc = (string) ($connection['refresh_token_enc'] ?? '');
        $accessToken = Security::decryptSecret($accessTokenEnc, $appSecret);
        $expiresAt = (string) ($connection['token_expires_at'] ?? '');

        if ($accessToken !== null && $expiresAt > gmdate('c', time() + 60)) {
            return $accessToken;
        }

        $refreshToken = Security::decryptSecret($refreshTokenEnc, $appSecret);
        if ($refreshToken === null || $refreshToken === '') {
            return null;
        }

        $refreshPayload = $this->integrations->refreshGoogleAccessToken($refreshToken);
        $newAccessToken = (string) ($refreshPayload['access_token'] ?? '');
        if ($newAccessToken === '') {
            return null;
        }

        $expiresIn = (int) ($refreshPayload['expires_in'] ?? 3600);
        $newAccessTokenEnc = Security::encryptSecret($newAccessToken, $appSecret);
        if ($newAccessTokenEnc === null) {
            return null;
        }

        $newRefreshToken = (string) ($refreshPayload['refresh_token'] ?? '');
        $newRefreshTokenEnc = $newRefreshToken === '' ? $refreshTokenEnc : Security::encryptSecret($newRefreshToken, $appSecret);
        if ($newRefreshToken !== '' && $newRefreshTokenEnc === null) {
            $newRefreshTokenEnc = $refreshTokenEnc;
        }

        $update = $this->pdo->prepare(
            'UPDATE user_google_connections
             SET access_token_enc = :access_token_enc,
                 refresh_token_enc = :refresh_token_enc,
                 token_expires_at = :token_expires_at,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $update->execute([
            ':access_token_enc' => $newAccessTokenEnc,
            ':refresh_token_enc' => $newRefreshTokenEnc,
            ':token_expires_at' => gmdate('c', time() + max(300, $expiresIn - 60)),
            ':updated_at' => gmdate('c'),
            ':id' => (int) $connection['id'],
        ]);

        return $newAccessToken;
    }

    private function markReservationCalendarSync(int $reservationId, string $status, ?string $eventId): void
    {
        $update = $this->pdo->prepare(
            'UPDATE reservations
             SET calendar_sync_status = :status,
                 google_calendar_event_id = :event_id,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $update->execute([
            ':status' => $status,
            ':event_id' => $eventId,
            ':updated_at' => gmdate('c'),
            ':id' => $reservationId,
        ]);
    }

    private function logIntegration(?int $userId, string $channel, string $status, string $target, string $details): void
    {
        $insert = $this->pdo->prepare(
            'INSERT INTO integration_logs (user_id, channel, status, target, details, created_at)
             VALUES (:user_id, :channel, :status, :target, :details, :created_at)'
        );
        $insert->execute([
            ':user_id' => $userId,
            ':channel' => $channel,
            ':status' => $status,
            ':target' => mb_substr($target, 0, 220),
            ':details' => mb_substr($details, 0, 5000),
            ':created_at' => gmdate('c'),
        ]);
    }

    private function renderOauthResultPage(bool $ok, string $message): void
    {
        $title = $ok ? 'Google vinculado' : 'Error de vinculacion';
        $safeTitle = Security::e($title);
        $safeMessage = Security::e($message);
        $safeColor = $ok ? '#0f8a56' : '#b83849';

        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>' . $safeTitle . '</title></head><body style="font-family:Segoe UI,Arial,sans-serif;background:#f1f6fb;margin:0;">'
            . '<div style="max-width:680px;margin:40px auto;background:#fff;border:1px solid #d8e3ec;border-radius:16px;padding:24px;">'
            . '<h1 style="margin-top:0;color:' . $safeColor . ';">' . $safeTitle . '</h1>'
            . '<p style="color:#173349;font-size:16px;line-height:1.5;">' . $safeMessage . '</p>'
            . '<p style="color:#4b6273;font-size:14px;">Puedes cerrar esta ventana y volver a Club Agelai.</p>'
            . '</div></body></html>';
    }

    /**
     * Resolves and validates API bearer token.
     *
     * @return array<string, mixed>|null
     */
    private function authenticatedApiUser(): ?array
    {
        $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        if (!preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $header, $matches)) {
            return null;
        }

        $rawToken = strtolower($matches[1]);
        $tokenHash = hash('sha256', $rawToken);

        $query = $this->pdo->prepare(
            'SELECT u.id, u.google_id, u.email, u.full_name, u.status
             FROM api_tokens t
             INNER JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = :token_hash
               AND t.expires_at > :now
             LIMIT 1'
        );
        $query->execute([
            ':token_hash' => $tokenHash,
            ':now' => gmdate('c'),
        ]);
        $user = $query->fetch();
        if ($user === false) {
            return null;
        }

        $touch = $this->pdo->prepare(
            'UPDATE api_tokens SET last_used_at = :last_used_at WHERE token_hash = :token_hash'
        );
        $touch->execute([
            ':last_used_at' => gmdate('c'),
            ':token_hash' => $tokenHash,
        ]);

        return $user;
    }

    private function issueToken(int $userId): string
    {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $expiresAt = gmdate('c', time() + ((int) $this->config['token_ttl_hours'] * 3600));
        $insert = $this->pdo->prepare(
            'INSERT INTO api_tokens (user_id, token_hash, expires_at, created_at, last_used_at)
             VALUES (:user_id, :token_hash, :expires_at, :created_at, :last_used_at)'
        );
        $insert->execute([
            ':user_id' => $userId,
            ':token_hash' => $tokenHash,
            ':expires_at' => $expiresAt,
            ':created_at' => gmdate('c'),
            ':last_used_at' => gmdate('c'),
        ]);

        return $rawToken;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getUserById(int $userId): ?array
    {
        $query = $this->pdo->prepare(
            'SELECT id, google_id, email, full_name, status
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $query->execute([':id' => $userId]);
        $row = $query->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getUserModules(int $userId): array
    {
        $query = $this->pdo->prepare(
            'SELECT m.id, m.code, m.name
             FROM user_modules um
             INNER JOIN modules m ON m.id = um.module_id
             WHERE um.user_id = :user_id
             ORDER BY m.name ASC'
        );
        $query->execute([':user_id' => $userId]);
        return $query->fetchAll();
    }

    /**
     * @param array<string, mixed> $user
     * @param array<int, array<string, mixed>> $modules
     * @return array<string, mixed>
     */
    private function userPayload(array $user, array $modules): array
    {
        return [
            'id' => (int) $user['id'],
            'google_id' => (string) $user['google_id'],
            'email' => (string) $user['email'],
            'full_name' => (string) $user['full_name'],
            'status' => (string) $user['status'],
            'modules' => array_map(
                static fn (array $module): array => [
                    'id' => (int) $module['id'],
                    'code' => (string) $module['code'],
                    'name' => (string) $module['name'],
                ],
                $modules
            ),
        ];
    }

    /**
     * @param array<string, mixed> $user
     */
    private function userHasActiveAccess(array $user): bool
    {
        if ((string) $user['status'] !== 'active') {
            return false;
        }

        $modules = $this->getUserModules((int) $user['id']);
        return count($modules) > 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getActivityById(int $activityId): ?array
    {
        $query = $this->pdo->prepare(
            'SELECT id, title, module_code, starts_at, ends_at, capacity, location, notes, status
             FROM activities
             WHERE id = :id
             LIMIT 1'
        );
        $query->execute([':id' => $activityId]);
        $row = $query->fetch();
        return $row === false ? null : $row;
    }

    private function occupiedSlots(int $activityId): int
    {
        $query = $this->pdo->prepare(
            'SELECT COUNT(*) AS total
             FROM reservations
             WHERE activity_id = :activity_id
               AND status IN (\'pending_user_confirm\', \'pending_admin_approval\', \'confirmed\')'
        );
        $query->execute([':activity_id' => $activityId]);
        $row = $query->fetch();
        return $row === false ? 0 : (int) $row['total'];
    }

    private function isAdminAuthenticated(): bool
    {
        return isset($_SESSION['admin_id']) && (int) $_SESSION['admin_id'] > 0;
    }

    private function revokeUserApiTokens(int $userId): void
    {
        $delete = $this->pdo->prepare('DELETE FROM api_tokens WHERE user_id = :user_id');
        $delete->execute([':user_id' => $userId]);
    }

    private function count(string $sql): int
    {
        $result = $this->pdo->query($sql)->fetchColumn();
        return (int) $result;
    }

    /**
     * Simple flash messages persisted in session between redirects.
     */
    private function setFlash(string $type, string $message): void
    {
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function pullFlash(): ?array
    {
        if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
            return null;
        }

        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);

        return [
            'type' => (string) ($flash['type'] ?? 'info'),
            'message' => (string) ($flash['message'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function render(string $viewName, array $data = []): void
    {
        $viewFile = __DIR__ . '/../views/' . $viewName . '.php';
        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'Vista no encontrada: ' . Security::e($viewName);
            return;
        }

        $title = (string) ($data['title'] ?? $this->config['app_name']);
        $flash = $this->pullFlash();
        $csrfToken = Security::csrfToken();
        $adminUsername = (string) ($_SESSION['admin_username'] ?? '');
        $currentPath = rawurldecode((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/'));

        extract($data, EXTR_SKIP);
        include __DIR__ . '/../views/layout.php';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
