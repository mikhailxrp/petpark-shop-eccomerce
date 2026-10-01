<?php

declare(strict_types=1);

/**
 * Отправка ответа в Канал (phase-5.md, Таск 8; FR-CHANNELS-002). Каналы —
 * заглушка (ADR-001): ничего никуда не уходит, только запись в лог. Когда
 * появятся реальные API, здесь станет интерфейс + класс на Канал (php.md).
 */
final class ChannelGateway
{
    /** Возвращает true, если Канал принял сообщение. Текст в лог не пишем — только длину. */
    public static function send(string $channel, int $conversationId, string $text): bool
    {
        logInfo('Канал (заглушка): ответ «отправлен»', [
            'channel'         => $channel,
            'conversation_id' => $conversationId,
            'length'          => mb_strlen($text),
        ]);

        return true;
    }
}
