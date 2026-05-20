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
            throw new \RuntimeException(
                'No se pudo conectar a MariaDB. Revisa AGELAI_DB_HOST/PORT/NAME/USER/PASS y que la BD exista.',
                0,
                $exception
            );
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
        $this->pdo->beginTransaction();
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
                    status VARCHAR(20) NOT NULL DEFAULT \'pending\',
                    created_at VARCHAR(35) NOT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_users_google_id (google_id),
                    UNIQUE KEY uniq_users_email (email)
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
                    created_at VARCHAR(35) NOT NULL,
                    updated_at VARCHAR(35) NOT NULL,
                    PRIMARY KEY (id),
                    KEY idx_reservations_user (user_id),
                    KEY idx_reservations_activity_status (activity_id, status),
                    CONSTRAINT fk_reservations_user
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    CONSTRAINT fk_reservations_activity
                        FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
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
                'CREATE TABLE IF NOT EXISTS rate_limits (
                    key_name VARCHAR(255) NOT NULL,
                    window_started_at INT UNSIGNED NOT NULL,
                    hit_count INT UNSIGNED NOT NULL,
                    PRIMARY KEY (key_name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $this->seedModules((array) ($config['allowed_modules'] ?? []));
            $this->seedAdmin((array) ($config['admin_seed'] ?? []));

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
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
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ':created_at' => gmdate('c'),
        ]);
    }
}
