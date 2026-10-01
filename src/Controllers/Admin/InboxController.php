<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

/**
 * Единый инбокс Каналов — /admin/inbox (phase-5.md, Таск 7; FR-CHANNELS-001).
 * Список Обращений и страница переписки. Доступ — `shift_admin` и `owner`,
 * остальным 404 (как у /admin/ai), гостю — форма входа.
 */
final class InboxController
{
    private const PER_PAGE = 20;

    private const CHANNEL_LABELS = [
        'max'      => 'MAX',
        'telegram' => 'Telegram',
        'vk'       => 'ВКонтакте',
        'avito'    => 'Avito',
    ];

    private const UNKNOWN_SENDER = 'Неопознанный отправитель';

    public function index(): void
    {
        $role = $this->authorize();
        if ($role === null) {
            return;
        }

        $total = conversationCount();
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $pageInput = input('page', '1');
        $page = is_string($pageInput) && ctype_digit($pageInput)
            ? min(max(1, (int) $pageInput), $totalPages)
            : 1;

        $rows = array_map(
            fn (array $row): array => [
                'id'         => (int) $row['id'],
                'channel'    => $this->channelLabel((string) $row['channel']),
                'sender'     => $this->senderName($row),
                'identified' => $row['customer_name'] !== null,
                'preview'    => mb_strimwidth((string) $row['last_body'], 0, 90, '…'),
                'time'       => date('d.m.Y H:i', (int) strtotime((string) $row['last_sent_at'])),
                'unread'     => (int) $row['is_read'] === 0,
            ],
            conversationList(self::PER_PAGE, ($page - 1) * self::PER_PAGE)
        );

        render('admin/inbox', [
            'pageTitle'     => 'Обращения — PetPark',
            'roleLabel'     => adminRoleLabel($role),
            'homeUrl'       => homePathForRole($role),
            'userRole'      => $role,
            'conversations' => $rows,
            'page'          => $page,
            'totalPages'    => $totalPages,
            'total'         => $total,
        ]);
    }

    public function show(string $id): void
    {
        $role = $this->authorize();
        if ($role === null) {
            return;
        }

        $conversation = ctype_digit($id) ? conversationFind((int) $id) : null;
        if ($conversation === null) {
            $this->notFound();
            return;
        }

        // Открытие отмечает прочитанным (FR-CHANNELS-001); метка в шапке
        // страницы — до отметки, чтобы администратор видел, что было новым.
        $wasUnread = (int) $conversation['is_read'] === 0;
        conversationMarkRead((int) $conversation['id']);

        $messages = array_map(
            static fn (array $message): array => [
                'incoming' => $message['direction'] === 'in',
                'body'     => (string) $message['body'],
                'time'     => date('d.m.Y H:i', (int) strtotime((string) $message['sent_at'])),
            ],
            conversationMessages((int) $conversation['id'])
        );

        render('admin/conversation', [
            'pageTitle'    => 'Обращение №' . $conversation['id'] . ' — PetPark',
            'roleLabel'    => adminRoleLabel($role),
            'homeUrl'      => homePathForRole($role),
            'userRole'     => $role,
            'channel'      => $this->channelLabel((string) $conversation['channel']),
            'sender'       => $this->senderName($conversation),
            'identified'   => $conversation['customer_name'] !== null,
            'contact'      => (string) ($conversation['contact_identifier'] ?? ''),
            'wasUnread'    => $wasUnread,
            'messages'     => $messages,
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
