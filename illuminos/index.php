<?php
/**
 * index.php
 * Главная страница онлайн-кинотеатра Illuminos.
 *
 * ВАЖНО: в SQL-запросах один и тот же плейсхолдер
 * НЕ повторяется дважды, т.к. PDO при
 * PDO::ATTR_EMULATE_PREPARES = false это не разрешает.
 */

require_once 'db.php';         // подключение к БД + старт сессии

$user_id = $_SESSION['user_id'];

/* ==========================================================
   1. ПОИСК по фильмам и сериалам
   :q1 и :q2 — РАЗНЫЕ плейсхолдеры с одинаковым значением
   ========================================================== */
$search = trim($_GET['q'] ?? '');
$searchResults = [];

if ($search !== '') {
    $stmt = $pdo->prepare(
        "SELECT * FROM movies
         WHERE title LIKE :q1 OR description LIKE :q2
         ORDER BY rating DESC
         LIMIT 20"
    );
    $stmt->execute([
        ':q1' => '%' . $search . '%',
        ':q2' => '%' . $search . '%',
    ]);
    $searchResults = $stmt->fetchAll();
}

/* ==========================================================
   2. НОВИНКИ
   ========================================================== */
$newMovies = $pdo->query(
    "SELECT * FROM movies
     WHERE is_new = 1
     ORDER BY created_at DESC
     LIMIT 10"
)->fetchAll();

/* ==========================================================
   3. ПОПУЛЯРНОЕ
   ========================================================== */
$popularMovies = $pdo->query(
    "SELECT * FROM movies
     ORDER BY views DESC
     LIMIT 10"
)->fetchAll();

/* ==========================================================
   4. КАТЕГОРИИ (жанры)
   ========================================================== */
$genres = $pdo->query("SELECT * FROM genres ORDER BY name")->fetchAll();

/* 4.5. ФИЛЬМЫ ДЛЯ ГЛАВНОГО БАННЕРА */
$featuredMovies = $pdo->query(
    "SELECT * FROM movies
     WHERE is_featured = 1
     ORDER BY rating DESC
     LIMIT 5"
)->fetchAll();

// Заглушка: если баннер пуст — берём топ-5 по рейтингу
if (empty($featuredMovies)) {
    $featuredMovies = $pdo->query(
        "SELECT * FROM movies ORDER BY rating DESC LIMIT 5"
    )->fetchAll();
}
// Заглушка: если баннер пуст — берём топ-5 по рейтингу
if (empty($featuredMovies)) {
    $featuredMovies = $pdo->query(
        "SELECT * FROM movies ORDER BY rating DESC LIMIT 5"
    )->fetchAll();
}
/* ==========================================================
   5. ПЕРСОНАЛЬНЫЕ РЕКОМЕНДАЦИИ
   :uid1 и :uid2 — РАЗНЫЕ плейсхолдеры с одинаковым значением
   ========================================================== */
$recomStmt = $pdo->prepare(
    "SELECT m.*
     FROM movies m
     WHERE m.genre_id = (
         SELECT m2.genre_id
         FROM views_history vh
         JOIN movies m2 ON m2.id = vh.movie_id
         WHERE vh.user_id = :uid1
         GROUP BY m2.genre_id
         ORDER BY COUNT(*) DESC
         LIMIT 1
     )
     AND m.id NOT IN (
         SELECT movie_id FROM views_history WHERE user_id = :uid2
     )
     ORDER BY m.rating DESC
     LIMIT 8"
);

$recomStmt->execute([
    ':uid1' => $user_id,
    ':uid2' => $user_id,
]);

$recommendations = $recomStmt->fetchAll();

// Заглушка, если истории просмотров нет
if (empty($recommendations)) {
    $recommendations = $pdo->query(
        "SELECT * FROM movies ORDER BY rating DESC LIMIT 8"
    )->fetchAll();
}

/* Функция вывода карточки фильма */
function renderCard(array $m): string {
    $badge  = $m['is_new'] ? '<span class="badge-new">Новинка</span>' : '';
    $poster = htmlspecialchars($m['poster'] ?? 'img/placeholder.jpg');
    $title  = htmlspecialchars($m['title']);
    return <<<HTML
        <div class="movie-card">
            {$badge}
            <img src="{$poster}" alt="{$title}">
            <div class="info">
                <div class="title">{$title}</div>
                <div class="meta">
                    <span>{$m['year']}</span>
                    <span>★ {$m['rating']}</span>
                </div>
            </div>
        </div>
    HTML;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Illuminos — онлайн-кинотеатр</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Montserrat:wght@700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- ================== ШАПКА С ПОИСКОМ ================== -->
<header class="site-header">
    <div class="container d-flex align-items-center justify-content-between flex-wrap gap-3">
        <a href="index.php" class="logo">Illuminos</a>

        <form class="search-form" method="get" action="index.php">
            <input type="text" name="q" placeholder="Поиск фильмов и сериалов..."
                   value="<?= htmlspecialchars($search) ?>">
        </form>

        <nav class="d-flex gap-3">
            <a href="#categories">Жанры</a>
            <a href="#"><i class="bi bi-person-circle"></i> Войти</a>
        </nav>
    </div>
</header>

<!-- ================== РЕЗУЛЬТАТЫ ПОИСКА ================== -->
<?php if ($search !== ''): ?>
    <section class="container py-5">
        <h2 class="section-title">
            <i class="bi bi-search"></i> Результаты поиска: «<?= htmlspecialchars($search) ?>»
        </h2>

        <?php if (empty($searchResults)): ?>
            <p class="text-secondary">Ничего не найдено. Попробуйте другой запрос.</p>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($searchResults as $m): ?>
                    <div class="col-6 col-md-3 col-lg-2">
                        <?= renderCard($m) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($search === ''): ?>
<!-- ================== БАННЕР-КАРУСЕЛЬ ================== -->
<?php if (!empty($featuredMovies)): ?>
<section class="hero-slider">

    <div id="heroCarousel" class="carousel slide carousel-fade"
         data-bs-ride="carousel" data-bs-interval="5000">

        <!-- Индикаторы (точки внизу) -->
        <div class="carousel-indicators">
            <?php foreach ($featuredMovies as $i => $m): ?>
                <button type="button"
                        data-bs-target="#heroCarousel"
                        data-bs-slide-to="<?= $i ?>"
                        class="<?= $i === 0 ? 'active' : '' ?>"
                        aria-label="Слайд <?= $i + 1 ?>"></button>
            <?php endforeach; ?>
        </div>

        <!-- Слайды -->
        <div class="carousel-inner">
            <?php foreach ($featuredMovies as $i => $m): ?>
                <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                <?php
// Пути к картинкам баннера (по порядку)
$bannerImages = [
    'img/banner1.jpg',
    'img/banner2.jpg',
    'img/banner3.jpg',
    'img/banner4.jpg',
    'img/banner5.jpg',
];
?>

<div class="hero-slide"
     style="background-image: url('img/poster.jpg');">
                        <div class="container">
                            <div class="meta">
                                <span>★ <?= $m['rating'] ?></span>
                                <span><?= $m['year'] ?></span>
                                <span><?= $m['type'] === 'series' ? 'Сериал' : 'Фильм' ?></span>
                            </div>
                            <h2><?= htmlspecialchars($m['title']) ?></h2>
                            <p><?= htmlspecialchars($m['description']) ?></p>
                            <div class="d-flex gap-3">
                                <button class="btn-accent">
                                    <i class="bi bi-play-fill"></i> Смотреть
                                </button>
                                <button class="btn-ghost">
                                    <i class="bi bi-info-circle"></i> Подробнее
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Стрелки навигации -->
        <button class="carousel-control-prev" type="button"
                data-bs-target="#heroCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Назад</span>
        </button>
        <button class="carousel-control-next" type="button"
                data-bs-target="#heroCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Вперёд</span>
        </button>

    </div>
</section>
<?php endif; ?>
<!-- ================== НОВИНКИ ================== -->
<section class="container py-5">
<section class="container pt-4 pb-5">
    <h2 class="section-title"><i class="bi bi-fire text-warning"></i> Новинки</h2>
    <div class="row-scroll centered">           <!-- ← добавили centered -->
        <?php foreach ($newMovies as $m): ?>
            <?= renderCard($m) ?>
        <?php endforeach; ?>
    </div>
</section>

<!-- ================== ПОПУЛЯРНОЕ ================== -->
<section class="container py-5">
    <h2 class="section-title"><i class="bi bi-star-fill text-warning"></i> Популярное</h2>
    <div class="row-scroll">
        <?php foreach ($popularMovies as $m): ?>
            <?= renderCard($m) ?>
        <?php endforeach; ?>
    </div>
</section>

<!-- ================== КАТЕГОРИИ ================== -->
<section class="container py-5" id="categories">
    <h2 class="section-title"><i class="bi bi-collection-play"></i> Категории</h2>
    <div class="d-flex flex-wrap gap-2">
        <?php foreach ($genres as $g): ?>
            <a href="genre.php?slug=<?= urlencode($g['slug']) ?>" class="genre-tag">
                <?= htmlspecialchars($g['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- ================== РЕКОМЕНДАЦИИ ================== -->
<section class="container py-5">
    <h2 class="section-title"><i class="bi bi-lightbulb text-warning"></i> Рекомендуем вам</h2>
    <div class="row g-3">
        <?php foreach ($recommendations as $m): ?>
            <div class="col-6 col-md-3 col-lg-2">
                <?= renderCard($m) ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php endif; ?>

<!-- ================== ПОДВАЛ ================== -->
<footer class="site-footer">
    <div class="container">
        &copy; <?= date('Y') ?> Illuminos — онлайн-кинотеатр. Все права защищены.
    </div>
</footer>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>