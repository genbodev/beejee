<?php


namespace app\core\db;


use app\core\Application;

class Database
{

    public \PDO $pdo;

    /**
     * Database constructor.
     */
    public function __construct(array $config)
    {
        $dsn = $config['dsn'] ?? '';
        $user = $config['user'] ?? '';
        $password = $config['password'] ?? '';
        $this->pdo = new \PDO($dsn, $user, $password);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function applyMigrations()
    {
        $this->createMigrationTable();
        $appliedMigrations = $this->getAppliedMigrations();

        $newMigrations = [];

        $files = scandir(Application::$ROOT_DIR . '/migrations');
        $toApplyMigrations = array_diff($files, $appliedMigrations);

        foreach ($toApplyMigrations as $migration) {
            if ($migration === '.' || $migration === '..') {
                continue;
            }

            require_once Application::$ROOT_DIR . '/migrations/' . $migration;
            $className = pathinfo($migration, PATHINFO_FILENAME);

            $instance = new $className;
            echo "Applying migration $migration" . PHP_EOL;
            $instance->up();
            echo "Applied migration $migration" . PHP_EOL;
            $newMigrations[] = $migration;
        }

        if (!empty($newMigrations)) {
            $this->saveMigrations($newMigrations);
        } else {
            $this->log('All migrations are applied');
        }

    }

    public function createMigrationTable()
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS migrations (
            id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            migration VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
            ) 
            ENGINE=INNODB;'
        );
    }

    public function getAppliedMigrations()
    {
        $statement = $this->pdo->prepare('SELECT migration FROM migrations');
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function saveMigrations(array $migrations)
    {

        // ["('migration_name_1')", "('migration_name_2'}", ...]
        $migrations = array_map(fn($m) => "('$m')", $migrations);
        $str = implode(',', $migrations);

        $statement = $this->pdo->prepare("INSERT INTO migrations (migration) VALUES $str");
        $statement->execute();

    }

    protected function log($message) {
        echo '['.date('Y-m-d H:i:s').'] - ' . $message . PHP_EOL;

    }

    public function prepare($sql) {
        return $this->pdo->prepare($sql);
    }
}