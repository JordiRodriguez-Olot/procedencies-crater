<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Auth.php';

// Només per POST amb CSRF: un enllaç GET permetria que qualsevol web
// externa tanqués la sessió de l'usuari amb una simple <img src="...">.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigeix('index.php');
}
try {
    verificaCsrf();
} catch (RuntimeException) {
    redirigeix('index.php');
}

Auth::logout();
redirigeix('login.php');
