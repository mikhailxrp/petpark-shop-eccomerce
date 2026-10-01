<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use Throwable;

/**
 * Единый инбокс Каналов — /admin/inbox (phase-5.md, Таски 7–8;
 * FR-CHANNELS-001/002/005). Список и переписка, ответ через ChannelGateway,
 * «Сымитировать входящее» (тот же код приёма, что у будущего вебхука) и
 * JSON-опрос для обновления без перезагрузки. Доступ — `shift_admin` и
 * `owner`, остальным 404 (как у /admin/ai), гостю — форма входа. Показываются
 * только Каналы из CHANNELS_ENABLED; Обращение выключенного Канала — 404.
 */
final class InboxController
{
    private const PER_PAGE = 20;
    private const PREVIEW_LENGTH = 90;
    private const INBOX_URL = '/admin/inbox';
    private const POLL_URL = '/admin/inbox/poll';

    private const CHANNEL_LABELS = [
        'max'      => 'MAX',
        'telegram' => 'Telegram',
        'vk'       => 'ВКонтакте',
        'avito'    => 'Avito',
    ];

    private const UNKNOWN_SENDER = 'Неопознанный отправитель';
    private const SIMULATED_SENDER = 'Тестовый отправитель';

    /** Тексты для «Сымитировать входящее» (демо, ADR-001). */
    private const SIMULATED_MESSAGES = [
        'Здравствуйте! Подскажите, есть ли сейчас в наличии корм для кошек?',
        'Добрый день! Можно записать собаку на груминг на выходные?',
        'Скажите, во сколько обойдётся доставка по Ростову?',
        'Привет! Нужен наполнитель комкующийся, 10 литров, есть такой?',
    ];

    public function index(): void
    {
        $role = $this->authorize();
        if ($role === null) {
            return;
        }

        $channels = $this->enabledChannels();
        $total = conversationCount($channels);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $pageInput = input('page', '1');
        $page = is_string($pageInput) && ctype_digit($pageInput)
            ? min(max(1, (int) $pageInput), $totalPages)
            : 1;

        render('admin/inbox', [
            'pageTitle'       => 'Обращения — PetPark',
            'roleLabel'       => adminRoleLabel($role),
            'homeUrl'         => homePathForRole($role),
            'userRole'        => $role,
            'conversations'   => array_map(
                fn (array $row): array => $this->listRow($row),
                conversationList($channels, self::PER_PAGE, ($page - 1) * self::PER_PAGE)
            ),
            'page'            => $page,
            'totalPages'      => $totalPages,
            'total'           => $total,
            'perPage'         => self::PER_PAGE,
            'pollUrl'         => self::POLL_URL,
            'pollIntervalMs'  => CHANNEL_POLL_INTERVAL_SECONDS * 1000,
            'sinceMessageId'  => conversationMaxMessageId($channels),
            'simulateUrl'     => self::INBOX_URL . '/simulate',
            'simulateChannels' => array_map(fn (string $code): string => $this->channelLabel($code), array_combine($channels, $channels)),
            'success'         => getFlash('success'),
            'error'           => getFlash('error'),
        ]);
    }

    public function show(string $id): void
    {
        $role = $this->authorize();
        if ($role === null) {
            return;
        }

        $conversation = $this->findEnabled($id);
        if ($conversation === null) {
            $this->notFound();
            return;
        }

        // Открытие отмечает прочитанным (FR-CHANNELS-001); метка в шапке
        // страницы — до отметки, чтобы администратор видел, что было новым.
        $wasUnread = (int) $conversation['is_read'] === 0;
        conversationMarkRead((int) $conversation['id']);

        $rawMessages = conversationMessages((int) $conversation['id']);
        $lastMessageId = $rawMessages === [] ? 0 : (int) $rawMessages[array_key_last($rawMessages)]['id'];

        render('admin/conversation', [
            'pageTitle'      => 'Обращение №' . $conversation['id'] . ' — PetPark',
            'roleLabel'      => adminRoleLabel($role),
            'homeUrl'        => homePathForRole($role),
            'userRole'       => $role,
            'conversationId' => (int) $conversation['id'],
            'channel'        => $this->channelLabel((string) $conversation['channel']),
            'sender'         => $this->senderName($conversation),
            'identified'     => $conversation['customer_name'] !== null,
            'contact'        => (string) ($conversation['contact_identifier'] ?? ''),
            'wasUnread'      => $wasUnread,
            'messages'       => array_map(fn (array $message): array => $this->messageRow($message), $rawMessages),
            'lastMessageId'  => $lastMessageId,
            'pollUrl'        => self::POLL_URL,
            'pollIntervalMs' => CHANNEL_POLL_INTERVAL_SECONDS * 1000,
            'replyUrl'       => self::INBOX_URL . '/' . $conversation['id'] . '/reply',
            'maxLength'      => CHANNEL_REPLY_MAX_LENGTH,
            'replyDraft'     => getFlash('reply_draft') ?? '',
            'orderDraft'     => $this->orderDraftView($conversation['order_draft'] ?? null),
            'draftUrl'       => self::INBOX_URL . '/' . $conversation['id'] . '/draft',
            'attributeCoverage' => productConfirmedAttributesCoverage(),
            'success'        => getFlash('success'),
            'error'          => getFlash('error'),
        ]);
    }

    /** Разбор Обращения в черновик Заказа (FR-AI-004): по кнопке, Заказ не создаётся. */
    public function draft(string $id): void
    {
        if ($this->authorize() === null) {
            return;
        }

        $conversation = $this->findEnabled($id);
        if ($conversation === null) {
            $this->notFound();
            return;
        }

        requireCsrf();

        $backUrl = self::INBOX_URL . '/' . $conversation['id'];

        try {
            $result = orderDraftGenerate(conversationMessages((int) $conversation['id']));
            if ($result['status'] === 'ok' && $result['draft'] !== null) {
                conversationSaveOrderDraft((int) $conversation['id'], $result['draft']);
            }
        } catch (Throwable $e) {
            logError('Черновик Заказа не создан', ['conversation_id' => (int) $conversation['id'], 'error' => $e->getMessage()]);
            $result = ['status' => 'error', 'draft' => null];
        }

        match ($result['status']) {
            'ok'      => setFlash('success', 'Черновик готов — проверьте состав перед оформлением Заказа.'),
            'empty'   => setFlash('error', 'В Обращении нет сообщений покупателя — разбирать нечего.'),
            'blocked' => setFlash('error', 'Лимит расходов на ИИ исчерпан. Оформите Заказ вручную из текста Обращения.'),
            default   => setFlash('error', 'ИИ сейчас недоступен. Оформите Заказ вручную из текста Обращения.'),
        };

        redirect($backUrl);
    }

    /**
     * Сохранённый JSON черновика → данные для View (цены отформатированы).
     *
     * @return array{items: list<array{name: string, label: string, price: string, quantity: int}>, note: string, generated_at: string}|null
     */
    private function orderDraftView(mixed $json): ?array
    {
        if (!is_string($json) || $json === '') {
            return null;
        }

        $draft = json_decode($json, true);
        if (!is_array($draft) || !is_array($draft['items'] ?? null)) {
            return null;
        }

        return [
            'items' => array_map(static fn (array $item): array => [
                'name'     => (string) $item['name'],
                'label'    => (string) $item['label'],
                'price'    => cartFormatMoney((string) $item['price']),
                'quantity' => (int) $item['quantity'],
            ], $draft['items']),
            'note'         => (string) ($draft['note'] ?? ''),
            'generated_at' => date('d.m.Y H:i', (int) strtotime((string) ($draft['generated_at'] ?? ''))),
        ];
    }

    /** Ответ из панели (FR-CHANNELS-002): валидация → ChannelGateway → исходящее сообщение. */
    public function reply(string $id): void
    {
        if ($this->authorize() === null) {
            return;
        }

        $conversation = $this->findEnabled($id);
        if ($conversation === null) {
            $this->notFound();
            return;
        }

        requireCsrf();

        $backUrl = self::INBOX_URL . '/' . $conversation['id'];
        $bodyInput = input('body', '');
        $body = is_string($bodyInput) ? trim($bodyInput) : '';

        // Битая кодировка MySQL молча обрежет до пустой строки — не принимаем такой ответ.
        if ($body === '' || !mb_check_encoding($body, 'UTF-8')) {
            setFlash('error', 'Введите текст ответа.');
            redirect($backUrl);
        }

        if (mb_strlen($body) > CHANNEL_REPLY_MAX_LENGTH) {
            setFlash('error', 'Ответ слишком длинный — не более ' . CHANNEL_REPLY_MAX_LENGTH . ' символов.');
            setFlash('reply_draft', $body);
            redirect($backUrl);
        }

        try {
            $sent = \ChannelGateway::send((string) $conversation['channel'], (int) $conversation['id'], $body);
            if ($sent) {
                conversationAddOutgoing((int) $conversation['id'], $body);
            }
        } catch (Throwable $e) {
            logError('Ответ на Обращение не отправлен', ['conversation_id' => (int) $conversation['id'], 'error' => $e->getMessage()]);
            $sent = false;
        }

        if ($sent) {
            setFlash('success', 'Ответ отправлен.');
        } else {
            setFlash('error', 'Канал не принял сообщение. Ответ не отправлен — попробуйте ещё раз.');
            setFlash('reply_draft', $body);
        }

        redirect($backUrl);
    }

    /**
     * «Сымитировать входящее» (демо): сообщение в последнее Обращение выбранного
     * Канала, а если его нет — в новое неопознанное. Идёт через conversationReceive().
     */
    public function simulate(): void
    {
        if ($this->authorize() === null) {
            return;
        }

        requireCsrf();

        $channelInput = input('channel', '');
        $channel = is_string($channelInput) ? $channelInput : '';
        $isAjax = isAjaxRequest();

        if (!in_array($channel, $this->enabledChannels(), true)) {
            if ($isAjax) {
                $this->json(['ok' => false], 422);
            }
            setFlash('error', 'Этот Канал выключен или не существует.');
            redirect(self::INBOX_URL);
        }

        $text = self::SIMULATED_MESSAGES[array_rand(self::SIMULATED_MESSAGES)];
        $externalId = conversationLatestExternalId($channel);

        try {
            if ($externalId === null) {
                conversationReceive($channel, 'sim-' . bin2hex(random_bytes(4)), self::SIMULATED_SENDER, null, $text);
            } else {
                conversationReceive($channel, $externalId, null, null, $text);
            }
        } catch (Throwable $e) {
            logError('Входящее не принято', ['channel' => $channel, 'error' => $e->getMessage()]);
            if ($isAjax) {
                $this->json(['ok' => false], 500);
            }
            setFlash('error', 'Не удалось принять входящее сообщение.');
            redirect(self::INBOX_URL);
        }

        if ($isAjax) {
            $this->json(['ok' => true]);
        }

        setFlash('success', 'Входящее сообщение добавлено.');
        redirect(self::INBOX_URL);
    }

    /**
     * Опрос: `?since=<id сообщения>` — изменившиеся Обращения для списка;
     * с `&conversation=<id>` — новые сообщения этой переписки. Отдаёт только
     * поля, нужные для отрисовки.
     */
    public function poll(): void
    {
        if ($this->authorize() === null) {
            return;
        }

        // Сессия больше не нужна — не блокируем остальные запросы панели.
        session_write_close();

        $sinceInput = input('since', '0');
        $since = is_string($sinceInput) && ctype_digit($sinceInput) ? (int) $sinceInput : 0;
        $conversationInput = input('conversation', '');

        if (is_string($conversationInput) && $conversationInput !== '') {
            $conversation = $this->findEnabled($conversationInput);
            if ($conversation === null) {
                $this->json(['ok' => false], 404);
            }

            $messages = conversationMessagesSince((int) $conversation['id'], $since);
            // Переписка открыта — входящее сразу прочитано.
            if (array_filter($messages, static fn (array $m): bool => $m['direction'] === 'in') !== []) {
                conversationMarkRead((int) $conversation['id']);
            }

            $this->json([
                'ok'       => true,
                'messages' => array_map(fn (array $m): array => $this->messageRow($m), $messages),
                'latest'   => $messages === [] ? $since : (int) $messages[array_key_last($messages)]['id'],
            ]);
        }

        $channels = $this->enabledChannels();
        $rows = array_map(
            fn (array $row): array => $this->listRow($row),
            conversationChangedSince($channels, $since, self::PER_PAGE)
        );

        $this->json([
            'ok'     => true,
            'rows'   => $rows,
            'total'  => conversationCount($channels),
            'latest' => $rows === [] ? $since : max($since, ...array_column($rows, 'last_message_id')),
        ]);
    }

    /** Роль допущенного пользователя; иначе редирект/404 и null. */
    private function authorize(): ?string
    {
        ensureSessionStarted();

        if (!isAuthenticated()) {
            redirect('/admin/login');
        }

        $role = (string) ($_SESSION['user_role'] ?? '');
        if (!in_array($role, ['shift_admin', 'owner'], true)) {
            $this->notFound();
            return null;
        }

        return $role;
    }

    /** @return array<int, string> */
    private function enabledChannels(): array
    {
        return enabledChannels(CHANNELS_ENABLED);
    }

    /** Обращение по id из URL, если оно существует и его Канал включён. @return array<string, mixed>|null */
    private function findEnabled(string $id): ?array
    {
        $conversation = ctype_digit($id) ? conversationFind((int) $id) : null;
        if ($conversation === null || !in_array((string) $conversation['channel'], $this->enabledChannels(), true)) {
            return null;
        }

        return $conversation;
    }

    /** @param array<string, mixed> $json */
    private function json(array $json, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($json, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id: int, channel: string, sender: string, identified: bool, preview: string, time: string, unread: bool, last_message_id: int}
     */
    private function listRow(array $row): array
    {
        return [
            'id'              => (int) $row['id'],
            'channel'         => $this->channelLabel((string) $row['channel']),
            'sender'          => $this->senderName($row),
            'identified'      => $row['customer_name'] !== null,
            'preview'         => mb_strimwidth((string) $row['last_body'], 0, self::PREVIEW_LENGTH, '…'),
            'time'            => date('d.m.Y H:i', (int) strtotime((string) $row['last_sent_at'])),
            'unread'          => (int) $row['is_read'] === 0,
            'last_message_id' => (int) $row['last_message_id'],
        ];
    }

    /**
     * @param array<string, mixed> $message
     * @return array{id: int, incoming: bool, body: string, time: string}
     */
    private function messageRow(array $message): array
    {
        return [
            'id'       => (int) $message['id'],
            'incoming' => $message['direction'] === 'in',
            'body'     => (string) $message['body'],
            'time'     => date('d.m.Y H:i', (int) strtotime((string) $message['sent_at'])),
        ];
    }

    private function notFound(): void
    {
        http_response_code(404);
        render('errors/404');
    }

    private function channelLabel(string $channel): string
    {
        return self::CHANNEL_LABELS[$channel] ?? $channel;
    }

    /** @param array<string, mixed> $row */
    private function senderName(array $row): string
    {
        foreach (['customer_name', 'sender_name', 'contact_identifier'] as $key) {
            $value = (string) ($row[$key] ?? '');
            if ($value !== '') {
                return $value;
            }
        }

        return self::UNKNOWN_SENDER;
    }
}
