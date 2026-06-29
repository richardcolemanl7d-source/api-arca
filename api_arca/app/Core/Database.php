<?php

namespace ApiArca\App\Core;

/**
 * Conexión y gestión de base de datos usando PDO
 */
class Database
{
    private static ?Database $instance = null;
    private ?\PDO $connection = null;
    private array $config;

    /**
     * Constructor privado para patrón Singleton
     */
    private function __construct()
    {
        $this->config = require __DIR__ . '/../../config/DatabaseConfig.php';
        $this->connect();
    }

    /**
     * Obtiene la instancia única de Database
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }

    /**
     * Establece la conexión a la base de datos
     */
    private function connect(): void
    {
        try {
            $dsn = sprintf(
                '%s:host=%s;port=%d;dbname=%s;charset=%s',
                $this->config['driver'],
                $this->config['host'],
                $this->config['port'],
                $this->config['database'],
                $this->config['charset']
            );

            $this->connection = new \PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $this->config['options']
            );

            // Configurar modo de error
            $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
            // Configurar fetch mode por defecto
            $this->connection->setDefaultFetchMode(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw new \RuntimeException(
                "Error de conexión a la base de datos: " . $e->getMessage(),
                500,
                $e
            );
        }
    }

    /**
     * Obtiene la conexión PDO
     */
    public function getConnection(): \PDO
    {
        if ($this->connection === null) {
            $this->connect();
        }
        
        return $this->connection;
    }

    /**
     * Ejecuta una consulta preparada y retorna los resultados
     * 
     * @param string $sql Consulta SQL
     * @param array $params Parámetros para la consulta preparada
     * @return array Resultados de la consulta
     */
    public function query(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            throw new \RuntimeException("Error en consulta: " . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Ejecuta una consulta y retorna un único resultado
     * 
     * @param string $sql Consulta SQL
     * @param array $params Parámetros para la consulta preparada
     * @return array|null Resultado o null si no hay resultados
     */
    public function queryOne(string $sql, array $params = []): ?array
    {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            
            $result = $stmt->fetch();
            
            return $result ?: null;
        } catch (\PDOException $e) {
            throw new \RuntimeException("Error en consulta: " . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Ejecuta una consulta INSERT y retorna el ID del último registro insertado
     * 
     * @param string $table Nombre de la tabla
     * @param array $data Datos a insertar
     * @return int ID del registro insertado
     */
    public function insert(string $table, array $data): int
    {
        try {
            $columns = implode(', ', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            
            $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
            
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($data);
            
            return (int)$this->getConnection()->lastInsertId();
        } catch (\PDOException $e) {
            throw new \RuntimeException("Error al insertar: " . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Ejecuta una consulta UPDATE
     * 
     * @param string $table Nombre de la tabla
     * @param array $data Datos a actualizar
     * @param string $where Cláusula WHERE
     * @param array $whereParams Parámetros del WHERE
     * @return int Número de filas afectadas
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        try {
            $setParts = [];
            foreach (array_keys($data) as $column) {
                $setParts[] = "{$column} = :{$column}";
            }
            $setClause = implode(', ', $setParts);
            
            $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
            
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute(array_merge($data, $whereParams));
            
            return $stmt->rowCount();
        } catch (\PDOException $e) {
            throw new \RuntimeException("Error al actualizar: " . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Ejecuta una consulta DELETE
     * 
     * @param string $table Nombre de la tabla
     * @param string $where Cláusula WHERE
     * @param array $params Parámetros del WHERE
     * @return int Número de filas afectadas
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        try {
            $sql = "DELETE FROM {$table} WHERE {$where}";
            
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->rowCount();
        } catch (\PDOException $e) {
            throw new \RuntimeException("Error al eliminar: " . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Inicia una transacción
     */
    public function beginTransaction(): bool
    {
        return $this->getConnection()->beginTransaction();
    }

    /**
     * Confirma una transacción
     */
    public function commit(): bool
    {
        return $this->getConnection()->commit();
    }

    /**
     * Revierte una transacción
     */
    public function rollback(): bool
    {
        return $this->getConnection()->rollBack();
    }

    /**
     * Ejecuta una transacción con callback
     * 
     * @param callable $callback Función a ejecutar dentro de la transacción
     * @return mixed Resultado del callback
     * @throws \Exception Re-lanza la excepción después del rollback
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        
        try {
            $result = $callback($this);
            $this->commit();
            
            return $result;
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Previene clonación de la instancia
     */
    private function __clone() {}

    /**
     * Previene deserialización de la instancia
     */
    public function __wakeup()
    {
        throw new \Exception("No se puede deserializar esta clase");
    }
}
