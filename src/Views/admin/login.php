<?php

declare(strict_types=1);

/**
 * Вход персонала — /admin/login (phase-1.md, Таск 7, FR-AUTH-004).
 * Отдельная страница (Valex), не расширяет каркас админки — до входа
 * сайдбара/шапки персонала ещё нет.
 *
 * @var string|null $error Общее сообщение об ошибке — getFlash('error')
 */
?>
<!DOCTYPE html>
<html lang="ru" data-theme-mode="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход для персонала — PetPark</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/png" href="/assets/img/favicon.png">

    <link href="/admin/assets/libs/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="/admin/assets/css/styles.min.css" rel="stylesheet">
    <link href="/admin/assets/css/icons.min.css" rel="stylesheet">
    <link href="/assets/css/fontawesome.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid custom-page">
        <div class="row bg-white">
            <div class="col-md-6 col-lg-6 col-xl-7 d-none d-md-flex bg-primary-transparent-3" style="background-image: url(/assets/img/home-page/faq-bg.jpg); background-size: cover; background-position: center;">
                <div class="row w-100 mx-auto text-center">
                    <div class="col-12 my-auto mx-auto">
                        <img src="/assets/img/home-page/dogs-1.png" class="my-auto mx-auto" style="max-width: 60%" alt="Довольные кошки и собаки — клиенты PetPark">
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-6 col-xl-5 bg-white py-4">
                <div class="login d-flex align-items-center py-2">
                    <div class="container p-0">
                        <div class="row">
                            <div class="col-md-10 col-lg-10 col-xl-9 mx-auto">
                                <div class="card-sigin">
                                    <div class="mb-5 d-flex">
                                        <a href="/" class="header-logo">
                                            <img src="/assets/img/logo.png" class="ht-40" alt="PetPark">
                                        </a>
                                    </div>
                                    <div class="main-signup-header">
                                        <h1 class="fs-24">Вход для персонала</h1>
                                        <h6 class="fw-medium mb-4 fs-15 text-muted">Специалист, администратор смены, контент-редактор, владелец</h6>

                                        <?php if ($error !== null): ?>
                                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                                <?= e($error) ?>
                                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"><i class="fe fe-x" aria-hidden="true"></i></button>
                                            </div>
                                        <?php endif; ?>

                                        <form method="post" action="/admin/login">
                                            <?= csrfField() ?>
                                            <div class="form-group mb-3">
                                                <label class="form-label" for="admin-login-email">Email</label>
                                                <input class="form-control" id="admin-login-email" name="email" type="email" placeholder="Введите email" required>
                                            </div>
                                            <div class="form-group mb-3">
                                                <label class="form-label" for="admin-login-password">Пароль</label>
                                                <div class="input-group">
                                                    <input class="form-control" id="admin-login-password" name="password" type="password" placeholder="Введите пароль" required>
                                                    <button type="button" class="btn btn-light password-toggle" data-target="admin-login-password" aria-label="Показать пароль" aria-pressed="false">
                                                        <i class="fa-regular fa-eye"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <button type="submit" class="btn btn-primary btn-block w-100">Войти</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="/admin/assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/password-toggle.js"></script>
    <script type="module" src="/assets/js/alerts.js"></script>
</body>
</html>
