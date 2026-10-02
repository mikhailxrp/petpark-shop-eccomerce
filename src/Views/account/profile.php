<?php

declare(strict_types=1);

/**
 * Личные данные — /account/profile (`FR-ACC-005`, phase-7.md, Таск 4).
 * @var array<string, string> $values  Значения полей формы
 * @var array<string, string> $errors  Ошибки по полям
 * @var string|null           $success getFlash('success')
 * @var string|null           $error   getFlash('error')
 */

$pageTitle = seoTitle('generic');
$pageDescription = seoDescription('generic');
$robotsNoindex = true;
$footerVariant = 'catalog';

$bannerTitle = 'Профиль';
$breadcrumbs = [
    ['name' => 'Главная', 'url' => '/'],
    ['name' => 'Личный кабинет', 'url' => '/account'],
    ['name' => 'Профиль', 'url' => null],
];

ob_start();
include __DIR__ . '/../components/page-banner.php';
?>
<section class="gap">
    <div class="container">
        <div class="row">
            <div class="col-lg-3">
                <?php $accountActive = 'profile'; include __DIR__ . '/../components/account-nav.php'; ?>
            </div>
            <div class="col-lg-9">
                <?php if ($success !== null): ?>
                    <div class="alert alert-success" role="status"><?= e($success) ?></div>
                <?php endif; ?>
                <?php if ($error !== null): ?>
                    <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-xl-7">
                        <div class="box login pet-form">
                            <h2 class="account-heading">Личные данные</h2>
                            <form method="post" action="/account/profile" novalidate>
                                <?= csrfField() ?>

                                <label for="profile-name">Имя</label>
                                <input type="text" name="name" id="profile-name" maxlength="100" required
                                       autocomplete="name"
                                       class="<?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                                       value="<?= e($values['name']) ?>">
                                <?php if (isset($errors['name'])): ?>
                                    <div class="invalid-feedback d-block"><?= e($errors['name']) ?></div>
                                <?php endif; ?>

                                <label for="profile-phone">Телефон</label>
                                <input type="tel" name="phone" id="profile-phone" maxlength="20" required
                                       autocomplete="tel"
                                       class="<?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                                       value="<?= e($values['phone']) ?>">
                                <?php if (isset($errors['phone'])): ?>
                                    <div class="invalid-feedback d-block"><?= e($errors['phone']) ?></div>
                                <?php endif; ?>

                                <label for="profile-email">Email</label>
                                <input type="email" name="email" id="profile-email" maxlength="150" required
                                       autocomplete="email"
                                       class="<?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                       value="<?= e($values['email']) ?>">
                                <?php if (isset($errors['email'])): ?>
                                    <div class="invalid-feedback d-block"><?= e($errors['email']) ?></div>
                                <?php endif; ?>

                                <button type="submit" class="button">Сохранить</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/../layouts/public.php';
