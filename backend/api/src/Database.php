<?php
declare(strict_types=1);

/**
 * Database bootstrapper and schema manager.
 */
final class Database
{
    private \PDO $pdo;

    /**
     * @param array<string, mixed> $dbConfig
     */
    public function __construct(private readonly array $dbConfig)
    {
        $host = (string) ($this->dbConfig['host'] ?? '127.0.0.1');
        $port = (int) ($this->dbConfig['port'] ?? 3306);
        $database = (string) ($this->dbConfig['database'] ?? 'agelai_dietas');
        $username = (string) ($this->dbConfig['username'] ?? 'root');
        $password = (string) ($this->dbConfig['password'] ?? '');
        $charset = (string) ($this->dbConfig['charset'] ?? 'utf8mb4');

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $host,
            $port,
            $database,
            $charset
        );

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if (defined('\PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
            $options[\PDO::MYSQL_ATTR_MULTI_STATEMENTS] = false;
        }

        try {
            $this->pdo = new \PDO($dsn, $username, $password, $options);
        } catch (\PDOException $exception) {
            $message = $exception->getMessage();
            $unknownDb = str_contains($message, 'Unknown database') || str_contains($message, '[1049]');

            if ($unknownDb) {
                if (!preg_match('/^[A-Za-z0-9_]+$/', $database)) {
                    throw new \RuntimeException(
                        'Nombre de base de datos invalido para creacion automatica segura.',
                        0,
                        $exception
                    );
                }

                try {
                    $serverDsn = sprintf('mysql:host=%s;port=%d;charset=%s', $host, $port, $charset);
                    $bootstrapPdo = new \PDO($serverDsn, $username, $password, $options);
                    $bootstrapPdo->exec(
                        'CREATE DATABASE IF NOT EXISTS `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
                    );
                    $this->pdo = new \PDO($dsn, $username, $password, $options);
                } catch (\Throwable $createDbException) {
                    throw new \RuntimeException(
                        'No se pudo crear/conectar a la base MariaDB. Revisa permisos del usuario.',
                        0,
                        $createDbException
                    );
                }
            } else {
                throw new \RuntimeException(
                    'No se pudo conectar a MariaDB. Revisa AGELAI_DB_HOST/PORT/NAME/USER/PASS y que la BD exista.',
                    0,
                    $exception
                );
            }
        }

        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        // Keep UTC in database layer for deterministic comparisons and scheduling logic.
        $this->pdo->exec("SET time_zone = '+00:00'");
    }

    public function pdo(): \PDO
    {
        return $this->pdo;
    }

    /**
     * Creates tables and seed data idempotently.
     *
     * @param array<string, mixed> $config
     */
    public function ensureSchema(array $config): void
    {
        try {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS admins (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    username VARCHAR(60) NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    created_at VARCHAR(35) NOT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_admins_username (username)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS users (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    google_id VARCHAR(128) NOT NULL,
                    email VARCHAR(180) NOT NULL,
                    full_name VARCHAR(120) NOT NULL,
                    first_name VARCHAR(80) NOT NULL DEFAULT \'\',
                    last_name VARCHAR(120) NOT NULL DEFAULT \'\',
                    phone VARCHAR(30) NOT NULL DEFAULT \'\',
                    address VARCHAR(220) NOT NULL DEFAULT \'\',
                    username VARCHAR(60) DEFAULT NULL,
                    password_hash VARCHAR(255) DEFAULT NULL,
                    auth_provider VARCHAR(20) NOT NULL DEFAULT \'google\',
                    status VARCHAR(20) NOT NULL DEFAULT \'pending\',
                    created_at VARCHAR(35) NOT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_users_google_id (google_id),
                    UNIQUE KEY uniq_users_email (email),
                    UNIQUE KEY uniq_users_username (username),
                    KEY idx_users_phone (phone),
                    KEY idx_users_name (first_name, last_name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS modules (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    code VARCHAR(60) NOT NULL,
                    name VARCHAR(120) NOT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_modules_code (code)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS user_modules (
                    user_id BIGINT UNSIGNED NOT NULL,
                    module_id BIGINT UNSIGNED NOT NULL,
                    PRIMARY KEY (user_id, module_id),
                    CONSTRAINT fk_user_modules_user
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    CONSTRAINT fk_user_modules_module
                        FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS activities (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    title VARCHAR(120) NOT NULL,
                    module_code VARCHAR(60) NOT NULL,
                    starts_at VARCHAR(35) NOT NULL,
                    ends_at VARCHAR(35) NOT NULL,
                    capacity INT UNSIGNED NOT NULL,
                    location VARCHAR(120) NOT NULL,
                    notes VARCHAR(500) NOT NULL DEFAULT \'\',
                    status VARCHAR(20) NOT NULL DEFAULT \'active\',
                    created_at VARCHAR(35) NOT NULL,
                    PRIMARY KEY (id),
                    KEY idx_activities_module_code (module_code),
                    KEY idx_activities_starts_at (starts_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS reservations (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    user_id BIGINT UNSIGNED NOT NULL,
                    activity_id BIGINT UNSIGNED NOT NULL,
                    status VARCHAR(40) NOT NULL,
                    confirmation_code_hash CHAR(64) DEFAULT NULL,
                    confirmation_deadline VARCHAR(35) DEFAULT NULL,
                    payment_status VARCHAR(40) NOT NULL DEFAULT \'pending\',
                    payment_method VARCHAR(20) NOT NULL DEFAULT \'cash\',
                    google_calendar_event_id VARCHAR(190) DEFAULT NULL,
                    calendar_sync_status VARCHAR(30) NOT NULL DEFAULT \'not_linked\',
                    confirmation_email_sent_at VARCHAR(35) DEFAULT NULL,
                    created_at VARCHAR(35) NOT NULL,
                    updated_at VARCHAR(35) NOT NULL,
                    PRIMARY KEY (id),
                    KEY idx_reservations_user (user_id),
                    KEY idx_reservations_activity_status (activity_id, status),
                    KEY idx_reservations_payment_method (payment_method),
                    KEY idx_reservations_calendar_sync_status (calendar_sync_status),
                    CONSTRAINT fk_reservations_user
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    CONSTRAINT fk_reservations_activity
                        FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            // Backward-compatible migration for already-created local databases.
            if (!$this->columnExists('reservations', 'payment_method')) {
                $this->pdo->exec(
                    'ALTER TABLE reservations
                     ADD COLUMN payment_method VARCHAR(20) NOT NULL DEFAULT \'cash\' AFTER payment_status'
                );
            }
            if (!$this->columnExists('reservations', 'google_calendar_event_id')) {
                $this->pdo->exec(
                    'ALTER TABLE reservations
                     ADD COLUMN google_calendar_event_id VARCHAR(190) DEFAULT NULL AFTER payment_method'
                );
            }
            if (!$this->columnExists('reservations', 'calendar_sync_status')) {
                $this->pdo->exec(
                    'ALTER TABLE reservations
                     ADD COLUMN calendar_sync_status VARCHAR(30) NOT NULL DEFAULT \'not_linked\' AFTER google_calendar_event_id'
                );
            }
            if (!$this->columnExists('reservations', 'confirmation_email_sent_at')) {
                $this->pdo->exec(
                    'ALTER TABLE reservations
                     ADD COLUMN confirmation_email_sent_at VARCHAR(35) DEFAULT NULL AFTER calendar_sync_status'
                );
            }

            // Backward-compatible migration for local credentials on users table.
            if (!$this->columnExists('users', 'username')) {
                $this->pdo->exec(
                    'ALTER TABLE users
                     ADD COLUMN username VARCHAR(60) DEFAULT NULL AFTER full_name'
                );
            }
            if (!$this->columnExists('users', 'first_name')) {
                $this->pdo->exec(
                    'ALTER TABLE users
                     ADD COLUMN first_name VARCHAR(80) NOT NULL DEFAULT \'\' AFTER full_name'
                );
            }
            if (!$this->columnExists('users', 'last_name')) {
                $this->pdo->exec(
                    'ALTER TABLE users
                     ADD COLUMN last_name VARCHAR(120) NOT NULL DEFAULT \'\' AFTER first_name'
                );
            }
            if (!$this->columnExists('users', 'phone')) {
                $this->pdo->exec(
                    'ALTER TABLE users
                     ADD COLUMN phone VARCHAR(30) NOT NULL DEFAULT \'\' AFTER last_name'
                );
            }
            if (!$this->columnExists('users', 'address')) {
                $this->pdo->exec(
                    'ALTER TABLE users
                     ADD COLUMN address VARCHAR(220) NOT NULL DEFAULT \'\' AFTER phone'
                );
            }
            if (!$this->columnExists('users', 'password_hash')) {
                $this->pdo->exec(
                    'ALTER TABLE users
                     ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL AFTER username'
                );
            }
            if (!$this->columnExists('users', 'auth_provider')) {
                $this->pdo->exec(
                    'ALTER TABLE users
                     ADD COLUMN auth_provider VARCHAR(20) NOT NULL DEFAULT \'google\' AFTER password_hash'
                );
            }
            if (!$this->indexExists('users', 'uniq_users_username')) {
                $this->pdo->exec(
                    'ALTER TABLE users
                     ADD UNIQUE KEY uniq_users_username (username)'
                );
            }
            if (!$this->indexExists('users', 'idx_users_phone')) {
                $this->pdo->exec(
                    'ALTER TABLE users
                     ADD KEY idx_users_phone (phone)'
                );
            }
            if (!$this->indexExists('users', 'idx_users_name')) {
                $this->pdo->exec(
                    'ALTER TABLE users
                     ADD KEY idx_users_name (first_name, last_name)'
                );
            }

            // Backfill split-name fields for existing records.
            $this->pdo->exec(
                'UPDATE users
                 SET first_name = TRIM(SUBSTRING_INDEX(full_name, \' \', 1))
                 WHERE first_name = \'\''
            );
            $this->pdo->exec(
                'UPDATE users
                 SET last_name = TRIM(SUBSTR(full_name, CHAR_LENGTH(SUBSTRING_INDEX(full_name, \' \', 1)) + 1))
                 WHERE last_name = \'\''
            );

            // Keep auth_provider consistent for existing records after migration.
            $this->pdo->exec(
                'UPDATE users
                 SET auth_provider = CASE
                    WHEN username IS NOT NULL
                      AND username <> \'\'
                      AND password_hash IS NOT NULL
                      AND password_hash <> \'\'
                      AND google_id LIKE \'manual_local_%\'
                    THEN \'local\'
                    WHEN username IS NOT NULL
                      AND username <> \'\'
                      AND password_hash IS NOT NULL
                      AND password_hash <> \'\'
                    THEN \'hybrid\'
                    ELSE \'google\'
                 END'
            );

            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS announcements (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    module_code VARCHAR(60) DEFAULT NULL,
                    title VARCHAR(120) NOT NULL,
                    body TEXT NOT NULL,
                    starts_at VARCHAR(35) NOT NULL,
                    ends_at VARCHAR(35) NOT NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at VARCHAR(35) NOT NULL,
                    PRIMARY KEY (id),
                    KEY idx_announcements_active_dates (is_active, starts_at, ends_at),
                    KEY idx_announcements_module (module_code)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS api_tokens (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    user_id BIGINT UNSIGNED NOT NULL,
                    token_hash CHAR(64) NOT NULL,
                    expires_at VARCHAR(35) NOT NULL,
                    created_at VARCHAR(35) NOT NULL,
                    last_used_at VARCHAR(35) NOT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_api_tokens_hash (token_hash),
                    KEY idx_api_tokens_user (user_id),
                    KEY idx_api_tokens_expires (expires_at),
                    CONSTRAINT fk_api_tokens_user
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS user_google_connections (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    user_id BIGINT UNSIGNED NOT NULL,
                    google_email VARCHAR(190) DEFAULT NULL,
                    access_token_enc TEXT NOT NULL,
                    refresh_token_enc TEXT DEFAULT NULL,
                    scope TEXT DEFAULT NULL,
                    token_expires_at VARCHAR(35) NOT NULL,
                    created_at VARCHAR(35) NOT NULL,
                    updated_at VARCHAR(35) NOT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_user_google_connections_user (user_id),
                    KEY idx_user_google_connections_expires (token_expires_at),
                    CONSTRAINT fk_user_google_connections_user
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS google_oauth_states (
                    state_token CHAR(64) NOT NULL,
                    user_id BIGINT UNSIGNED NOT NULL,
                    expires_at VARCHAR(35) NOT NULL,
                    used_at VARCHAR(35) DEFAULT NULL,
                    created_at VARCHAR(35) NOT NULL,
                    PRIMARY KEY (state_token),
                    KEY idx_google_oauth_states_user (user_id),
                    CONSTRAINT fk_google_oauth_states_user
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS google_mobile_login_states (
                    state_token CHAR(64) NOT NULL,
                    user_id BIGINT UNSIGNED DEFAULT NULL,
                    google_email VARCHAR(190) DEFAULT NULL,
                    api_token_enc TEXT DEFAULT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT \'pending\',
                    error_message VARCHAR(500) DEFAULT NULL,
                    expires_at VARCHAR(35) NOT NULL,
                    completed_at VARCHAR(35) DEFAULT NULL,
                    consumed_at VARCHAR(35) DEFAULT NULL,
                    created_at VARCHAR(35) NOT NULL,
                    updated_at VARCHAR(35) NOT NULL,
                    PRIMARY KEY (state_token),
                    KEY idx_google_mobile_login_status (status),
                    KEY idx_google_mobile_login_expires (expires_at),
                    CONSTRAINT fk_google_mobile_login_user
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS integration_logs (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    user_id BIGINT UNSIGNED DEFAULT NULL,
                    channel VARCHAR(30) NOT NULL,
                    status VARCHAR(20) NOT NULL,
                    target VARCHAR(220) NOT NULL,
                    details TEXT DEFAULT NULL,
                    created_at VARCHAR(35) NOT NULL,
                    PRIMARY KEY (id),
                    KEY idx_integration_logs_user (user_id),
                    KEY idx_integration_logs_channel_status (channel, status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS rate_limits (
                    key_name VARCHAR(255) NOT NULL,
                    window_started_at INT UNSIGNED NOT NULL,
                    hit_count INT UNSIGNED NOT NULL,
                    PRIMARY KEY (key_name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $this->seedModules((array) ($config['allowed_modules'] ?? []));
            $this->seedAdmin((array) ($config['admin_seed'] ?? []));
            $this->seedLocalUser((array) ($config['local_user_seed'] ?? []));
        } catch (\Throwable $exception) {
            throw $exception;
        }
    }

    private function columnExists(string $tableName, string $columnName): bool
    {
        $query = $this->pdo->prepare(
            'SELECT COUNT(*) AS total
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name'
        );
        $query->execute([
            ':table_name' => $tableName,
            ':column_name' => $columnName,
        ]);

        return (int) $query->fetchColumn() > 0;
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $query = $this->pdo->prepare(
            'SELECT COUNT(*) AS total
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND INDEX_NAME = :index_name'
        );
        $query->execute([
            ':table_name' => $tableName,
            ':index_name' => $indexName,
        ]);

        return (int) $query->fetchColumn() > 0;
    }

    /**
     * @param array<string, string> $allowedModules
     */
    private function seedModules(array $allowedModules): void
    {
        $statement = $this->pdo->prepare('INSERT IGNORE INTO modules (code, name) VALUES (:code, :name)');
        foreach ($allowedModules as $code => $name) {
            $statement->execute([
                ':code' => $code,
                ':name' => $name,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $adminSeed
     */
    private function seedAdmin(array $adminSeed): void
    {
        $username = (string) ($adminSeed['username'] ?? 'admin');
        $password = (string) ($adminSeed['password'] ?? 'Admin12345!');

        $query = $this->pdo->prepare('SELECT id FROM admins WHERE username = :username LIMIT 1');
        $query->execute([':username' => $username]);
        $exists = $query->fetch();
        if ($exists !== false) {
            return;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO admins (username, password_hash, created_at) VALUES (:username, :password_hash, :created_at)'
        );
        $insert->execute([
            ':username' => $username,
            ':password_hash' => Security::hashPassword($password),
            ':created_at' => gmdate('c'),
        ]);
    }

    /**
     * @param array<string, mixed> $localUserSeed
     */
    private function seedLocalUser(array $localUserSeed): void
    {
        $username = trim((string) ($localUserSeed['username'] ?? 'clubagelai'));
        $password = (string) ($localUserSeed['password'] ?? 'clubagelai');
        $fullName = trim((string) ($localUserSeed['full_name'] ?? 'Club Agelai Usuario Pruebas'));
        $firstName = trim((string) ($localUserSeed['first_name'] ?? 'Club'));
        $lastName = trim((string) ($localUserSeed['last_name'] ?? 'Agelai Usuario Pruebas'));
        $phone = trim((string) ($localUserSeed['phone'] ?? ''));
        $address = trim((string) ($localUserSeed['address'] ?? ''));
        $email = trim((string) ($localUserSeed['email'] ?? 'clubagelai@local.agelai'));
        $status = trim((string) ($localUserSeed['status'] ?? 'pending'));

        if (!preg_match('/^[A-Za-z0-9._-]{4,60}$/', $username)) {
            return;
        }
        if (strlen($password) < 8 || strlen($password) > 72) {
            return;
        }
        if ($fullName === '' || mb_strlen($fullName) > 120) {
            return;
        }
        if ($firstName === '' || mb_strlen($firstName) > 80) {
            return;
        }
        if ($lastName === '' || mb_strlen($lastName) > 120) {
            return;
        }
        if ($phone !== '' && mb_strlen($phone) > 30) {
            return;
        }
        if ($address !== '' && mb_strlen($address) > 220) {
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        if (!in_array($status, ['pending', 'active', 'blocked'], true)) {
            $status = 'pending';
        }

        $googleId = 'manual_local_seed_' . $username;
        if (mb_strlen($googleId) > 128) {
            $googleId = 'manual_local_seed_' . substr(hash('sha256', $username), 0, 24);
        }

        $query = $this->pdo->prepare(
            'SELECT id
             FROM users
             WHERE username = :username
                OR email = :email
             LIMIT 1'
        );
        $query->execute([
            ':username' => $username,
            ':email' => $email,
        ]);
        $exists = $query->fetch();
        if ($exists !== false) {
            return;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO users (google_id, email, full_name, first_name, last_name, phone, address, username, password_hash, auth_provider, status, created_at)
             VALUES (:google_id, :email, :full_name, :first_name, :last_name, :phone, :address, :username, :password_hash, :auth_provider, :status, :created_at)'
        );
        $insert->execute([
            ':google_id' => $googleId,
            ':email' => $email,
            ':full_name' => $fullName,
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':phone' => $phone,
            ':address' => $address,
            ':username' => $username,
            ':password_hash' => Security::hashPassword($password),
            ':auth_provider' => 'local',
            ':status' => $status,
            ':created_at' => gmdate('c'),
        ]);
    }
}
