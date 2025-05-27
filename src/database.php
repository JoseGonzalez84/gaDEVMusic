<?php

use SergiX44\Nutgram\Nutgram;

function connectDB(): ?SQLite3
{
    $connection = null;
    try {
        $connection = new SQLite3('/db/data.db');
    } catch (Exception $ex) {
        logServicio("Error conectando a la base de datos: ".$ex->getMessage());
    }

    return $connection;
}


function disconnectDB(SQLite3 $connection): void
{
    $connection->close();
}


function logData(string $userId, string $message)
{
    $connection = connectDB();

    $sql = sprintf(
        'INSERT INTO logs (users_id, message, createdAt) VALUES (%s, "%s", "%s");',
        $userId,
        $message,
        timeNow()
    );

    $connection->query($sql);
}


function createUser(Nutgram $bot)
{
    $connection = connectDB();
    $userId     = getIdUsuario($bot);
    $userName   = getNombreUsuario($bot);

    // Primero check de que el usuario existe.
    $sqlUserExists = sprintf('SELECT * FROM users WHERE id = "%s";', $userId);
    $userExists = $connection->querySingle($sqlUserExists, true);
    if (empty($userExists) === true) {
        $sql = sprintf(
            'INSERT INTO users (id, name, status, createdAt) VALUES (%s, "%s", "1", "%s");',
            $userId,
            $userName,
            timeNow()
        );
        $salida = $connection->query($sql);
        if ($salida === false) {
            logData($userId, "Hubo un problema al crear el usuario.");
        }
    }
}