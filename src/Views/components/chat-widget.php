<?php

declare(strict_types=1);

/**
 * Виджет консультанта (FR-AI-003): кнопка + панель чата. Логика — public/assets/js/chat.js,
 * ответы — POST /chat. Без JS виджет не показывается (кнопка скрыта до инициализации).
 * Запасные ссылки на мессенджеры — те же, что у кнопки мессенджеров (layouts/public.php).
 *
 * @var list<array{code: string, label: string, url: string}> $messengerLinks
 */
$chatLinks = $messengerLinks ?? [];
?>
<section class="chat-widget" id="chat-widget" aria-label="Консультант" data-csrf="<?= e(csrfToken()) ?>" hidden>
    <button class="chat-widget__toggle" id="chat-toggle" type="button" aria-expanded="false" aria-controls="chat-panel">
        <i class="fa-solid fa-comments" aria-hidden="true"></i>
        <span class="chat-widget__toggle-text">Консультант</span>
    </button>

    <div class="chat-widget__panel" id="chat-panel" hidden>
        <header class="chat-widget__header">
            <h2 class="chat-widget__title">Консультант PetPark</h2>
            <button class="chat-widget__close" id="chat-close" type="button" aria-label="Закрыть чат">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </header>

        <div class="chat-widget__messages" id="chat-messages" role="log" aria-live="polite">
            <p class="chat-widget__message chat-widget__message--bot">
                Здравствуйте! Подскажу про услуги, доставку и запись, а также подберу товар из каталога.
            </p>
            <?php if (CHAT_LIMIT_REQUESTS): ?>
                <p class="chat-widget__message chat-widget__message--bot">
                    Это демонстрационная версия консультанта ограниченная <?= e((string) CHAT_DEMO_MAX_REQUESTS) ?>-тью запросами для одного пользователя.
                </p>
            <?php endif; ?>
        </div>

        <div class="chat-widget__fallback" id="chat-fallback" role="alert" hidden>
            <p class="chat-widget__fallback-text">Консультант временно недоступен. Остальной сайт работает как обычно.</p>
            <?php if ($chatLinks !== []): ?>
                <p class="chat-widget__fallback-text">Напишите нам в мессенджер:</p>
                <ul class="chat-widget__links">
                    <?php foreach ($chatLinks as $link): ?>
                        <li class="chat-widget__links-item">
                            <a class="chat-widget__link" href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($link['label']) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <form class="chat-widget__form" id="chat-form" autocomplete="off">
            <label class="visually-hidden" for="chat-input">Ваш вопрос</label>
            <input class="chat-widget__input form-control" id="chat-input" type="text" name="message"
                   maxlength="<?= e((string) CHAT_MAX_QUESTION_LENGTH) ?>" placeholder="Ваш вопрос" required>
            <button class="chat-widget__send" id="chat-send" type="submit" aria-label="Отправить">
                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
            </button>
        </form>
    </div>
</section>
