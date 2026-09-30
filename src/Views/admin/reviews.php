<?php

declare(strict_types=1);

/**
 * Очередь модерации отзывов — /admin/reviews (phase-1.md, Таск 8).
 * Собрана из карточки «Comments» шаблона Valex
 * (public/admin/html/blog-details.html, admin-assembly.md) — тот же
 * паттерн строки отзыва (аватар/имя/рейтинг/текст), демо-действия
 * «Helpful»/«Comment»/«Report» заменены на реальные
 * «Опубликовать»/«Отклонить» (Admin\ReviewController).
 *
 * @var string $pageTitle
 * @var string $roleLabel
 * @var string $homeUrl
 * @var string $userRole
 * @var array<int, array<string, mixed>> $reviews reviewsPending() — id, author_name, rating, body, created_at, product_name
 * @var string|null $success
 */

ob_start();
?>
<div class="d-md-flex d-block align-items-center justify-content-between my-4">
    <div>
        <h1 class="mb-0">Модерация отзывов</h1>
        <p class="mb-0 text-muted">На проверке: <?= count($reviews) ?></p>
    </div>
</div>

<?php if ($success !== null): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= e($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"><i class="fe fe-x" aria-hidden="true"></i></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Отзывы на проверке</h3>
    </div>
    <div class="card-body">
        <?php if ($reviews === []): ?>
            <p class="mb-0 text-muted">Очередь пуста — новых отзывов нет.</p>
        <?php else: ?>
            <?php foreach ($reviews as $review): ?>
                <div class="d-sm-flex p-3 mt-3 border sub-review-section">
                    <div class="d-flex me-3">
                        <span class="avatar avatar-md rounded-circle bg-light d-flex align-items-center justify-content-center">
                            <i class="fe fe-user"></i>
                        </span>
                    </div>
                    <div class="media-body w-100">
                        <h6 class="mt-0 mb-1 font-weight-semibold">
                            <?= e((string) $review['author_name']) ?>
                            <span class="fs-14 ms-2 d-inline-block"><?= (int) $review['rating'] ?> <i class="fe fe-star text-warning"></i></span>
                        </h6>
                        <p class="fs-13 text-muted mb-1">Товар: <?= e((string) $review['product_name']) ?></p>
                        <p class="font-13 mb-2"><?= e((string) $review['body']) ?></p>
                        <div class="d-flex gap-2">
                            <form method="post" action="/admin/reviews/<?= (int) $review['id'] ?>/publish">
                                <?= csrfField() ?>
                                <button type="submit" class="btn btn-success btn-sm">Опубликовать</button>
                            </form>
                            <form method="post" action="/admin/reviews/<?= (int) $review['id'] ?>/reject">
                                <?= csrfField() ?>
                                <button type="submit" class="btn btn-outline-danger btn-sm">Отклонить</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/admin.php';
