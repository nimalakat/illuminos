<?php
/**
 * db.php
 * Подключение к базе данных онлайн-кинотеатра Illuminos через PDO.
 */

// Параметры подключения
$DB_HOST = 'localhost';
$DB_NAME = 'illuminos_db';
$DB_USER = 'root';
$DB_PASS = '';

$dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4";

try {
    // Создаём PDO-соединение с нужными атрибутами
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // исключения при ошибках
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // ассоциативные массивы
        PDO::ATTR_EMULATE_PREPARES   => false,                  // настоящие prepared statements
    ]);
} catch (PDOException $e) {
    // Прекращаем работу при ошибке
    die('Ошибка подключения к БД: ' . $e->getMessage());
}

// Стартуем сессию (для персональных рекомендаций)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Тестовый пользователь (в реальном проекте — авторизация)
if (empty($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}