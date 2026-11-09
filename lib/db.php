<?php

/**
 * Accés a la base de dades amb PDO. Sempre consultes preparades: mai
 * concatenar valors d'usuari dins l'SQL. Per a noms de columna dinàmics
 * (p. ex. ordenació), fes servir una llista blanca, no el valor rebut.
 */

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $pdo = new PDO(
            (string) config('db.dsn'),
            (string) config('db.usuari'),
            (string) config('db.password'),
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }

    return $pdo;
}

/**
 * @param array<int|string, mixed> $params
 * @return array<int, array<string, mixed>>
 */
function dbFiles(string $sql, array $params = []): array
{
    $sentencia = db()->prepare($sql);
    $sentencia->execute($params);

    return $sentencia->fetchAll();
}

/**
 * @param array<int|string, mixed> $params
 * @return array<string, mixed>|null
 */
function dbFila(string $sql, array $params = []): ?array
{
    $sentencia = db()->prepare($sql);
    $sentencia->execute($params);
    $fila = $sentencia->fetch();

    return $fila === false ? null : $fila;
}

/**
 * @param array<int|string, mixed> $params
 */
function dbValor(string $sql, array $params = []): mixed
{
    $sentencia = db()->prepare($sql);
    $sentencia->execute($params);
    $valor = $sentencia->fetchColumn();

    return $valor === false ? null : $valor;
}

/**
 * Executa un INSERT/UPDATE/DELETE i retorna les files afectades.
 *
 * @param array<int|string, mixed> $params
 */
function dbExecuta(string $sql, array $params = []): int
{
    $sentencia = db()->prepare($sql);
    $sentencia->execute($params);

    return $sentencia->rowCount();
}

function dbUltimId(): int
{
    return (int) db()->lastInsertId();
}

/**
 * Executa diverses escriptures com una sola unitat: si alguna falla, es
 * desfà tot. Llença l'excepció dins el callback per forçar el rollback.
 *
 * @template T
 * @param callable(): T $accio
 * @return T
 */
function dbTransaccio(callable $accio): mixed
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $resultat = $accio();
        $pdo->commit();

        return $resultat;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function araSql(): string
{
    return date('Y-m-d H:i:s');
}
