<?php
/**
 * genre.php
 * Страница конкретного жанра: список фильмов выбранной категории.
 */

require_once 'db.php';

$slug = trim($_GET['slug'] ?? '');    // slug жанра из URL

// Ищем жанр по slug
$gStmt = $pdo->prepare("SELECT * FROM genres WHERE slug = :slug LIMIT 1");
$gStmt->execute([':slug' => $slug]);
$genre = $gStmt->fetch();

if (!$genre) {
    http_response_code(404);
    die('Жанр не найден');
}

// Все фильмы этого жанра, отсортированные по рейтингу
$mStmt = $pdo->prepare(
    "SELECT * FROM movies WHERE genre_id = :gid ORDER BY rating DESC"
);
$mStmt->execute([':gid' => $genre['id']]);
$movies = $mStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($genre['name']) ?> — Illuminos</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<header class="site-header">
    <div class="container">
        <a href="index.php" class="logo">Illuminos</a>
    </div>
</header>

<main class="container py-5">
    <h1 class="section-title"><?= htmlspecialchars($genre['name']) ?></h1>
    <div class="row g-3">
        <?php foreach ($movies as $m): ?>
            <div class="col-6 col-md-3 col-lg-2">
                <div class="movie-card">
                    <img src="<?= htmlspecialchars($m['poster'] ?? 'img/placeholder.jpg') ?>" alt="">
                    <div class="info">
                        <div class="title"><?= htmlspecialchars($m['title']) ?></div>
                        <div class="meta">
                            <span><?= $m['year'] ?></span>
                            <span>★ <?= $m['rating'] ?></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<footer class="site-footer">
    <div class="container">&copy; <?= date('Y') ?> Illuminos</div>
</footer>
</body>
</html>