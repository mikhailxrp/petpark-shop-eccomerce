<?php

declare(strict_types=1);

/**
 * Модель Возврата — только SQL через PDO (php.md). `database.md`
 * (`order_returns`, `order_return_photos`). Правила переходов и «Заказ можно
 * вернуть» — Core/OrderReturn.php; письма — Services/Notifier.php.
 *
 * Возврат — на весь Заказ целиком, 0..1 на Заказ. Деньги и остаток
 * Варианта здесь не трогаются: это завершение Возврата (Таск 8).
 */

/**
 * Подать заявку на Возврат: заявка, пути фото и письмо «заявка принята» —
 * одной транзакцией. Строка Заказа блокируется FOR UPDATE, поэтому два
 * одновременных запроса не создадут вторую заявку.
 *
 * Чужой Заказ (не $userId) неотличим от несуществующего — `not_found`.
 *
 * @param list<string> $photoPaths пути из fileUploadSaveImages()
 * @return array{status: 'created', id: int}|array{status: 'not_found'|'not_returnable'}
 */
function returnCreate(int $orderId, int $userId, string $reason, array $photoPaths): array
{
    $pdo = getPdo();
    $ownsTransaction = !$pdo->inTransaction();

    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare('
            SELECT id, status, contact_name, contact_email, total
            FROM orders
            WHERE id = ? AND user_id = ?
            FOR UPDATE
        ');
        $stmt->execute([$orderId, $userId]);
        $order = $stmt->fetch();

        if ($order === false) {
            if ($ownsTransaction) {
                $pdo->rollBack();
            }
            return ['status' => 'not_found'];
        }

        $existsStmt = $pdo->prepare('SELECT 1 FROM order_returns WHERE order_id = ?');
        $existsStmt->execute([$orderId]);

        if (!returnOrderCanBeReturned((string) $order['status'], $existsStmt->fetchColumn() !== false)) {
            if ($ownsTransaction) {
                $pdo->rollBack();
            }
            return ['status' => 'not_returnable'];
        }

        $insertStmt = $pdo->prepare('INSERT INTO order_returns (order_id, reason) VALUES (?, ?)');
        $insertStmt->execute([$orderId, $reason]);
        $returnId = (int) $pdo->lastInsertId();

        $photoStmt = $pdo->prepare('INSERT INTO order_return_photos (return_id, path) VALUES (?, ?)');
        foreach ($photoPaths as $path) {
            $photoStmt->execute([$returnId, $path]);
        }

        // Письмо в той же транзакции: откат заявки откатывает и письмо (FR-NOTIF-002).
        notifierEnqueueReturnStatus([
            'id'               => $returnId,
            'contact_name'     => $order['contact_name'],
            'contact_email'    => $order['contact_email'],
            'total'            => $order['total'],
            'order_id'         => $orderId,
            'decision_comment' => null,
        ], 'submitted');

        if ($ownsTransaction) {
            $pdo->commit();
        }

        return ['status' => 'created', 'id' => $returnId];
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

/**
 * Единая точка смены order_returns.status (php.md: никаких сырых UPDATE из
 * контроллера). Заявка блокируется FOR UPDATE, переход проверяется по
 * returnCanTransition(), письмо ставится в той же транзакции.
 *
 * Работает в своей транзакции либо присоединяется к уже открытой.
 *
 * @param int|null $resolvedByUserId кто провёл переход; null — не менять
 * @param string|null $decisionComment причина отказа / дальнейшие шаги; null — не менять
 * @return bool false — заявки нет или переход недопустим (статус не менялся)
 * @throws InvalidArgumentException решение (approved/rejected) без комментария
 */
function returnTransition(
    int $returnId,
    string $toStatus,
    ?int $resolvedByUserId = null,
    ?string $decisionComment = null
): bool {
    $decisionComment = $decisionComment === null ? null : trim($decisionComment);

    if (returnTransitionRequiresComment($toStatus) && ($decisionComment === null || $decisionComment === '')) {
        throw new InvalidArgumentException('Для решения по Возврату нужен комментарий покупателю.');
    }

    $pdo = getPdo();
    $ownsTransaction = !$pdo->inTransaction();

    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare('
            SELECT r.id, r.order_id, r.status, r.decision_comment,
                   o.contact_name, o.contact_email, o.total
            FROM order_returns r
            JOIN orders o ON o.id = r.order_id
            WHERE r.id = ?
            FOR UPDATE OF r
        ');
        $stmt->execute([$returnId]);
        $return = $stmt->fetch();

        if ($return === false || !returnCanTransition((string) $return['status'], $toStatus)) {
            if ($ownsTransaction) {
                $pdo->rollBack();
            }
            return false;
        }

        $updateStmt = $pdo->prepare('
            UPDATE order_returns
            SET status = :status,
                resolved_by_user_id = COALESCE(:resolved_by, resolved_by_user_id),
                decision_comment = COALESCE(:comment, decision_comment)
            WHERE id = :id
        ');
        $updateStmt->execute([
            'status'      => $toStatus,
            'resolved_by' => $resolvedByUserId,
            'comment'     => $decisionComment === '' ? null : $decisionComment,
            'id'          => $returnId,
        ]);

        // Письмо в той же транзакции: откат статуса откатывает и письмо (FR-NOTIF-002).
        $return['decision_comment'] = $decisionComment === null || $decisionComment === ''
            ? $return['decision_comment']
            : $decisionComment;
        notifierEnqueueReturnStatus($return, $toStatus);

        if ($ownsTransaction) {
            $pdo->commit();
        }

        return true;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

/**
 * Блокирует заявку FOR UPDATE и отдаёт данные для завершения. Вызывать в
 * открытой транзакции (её ведёт Services/ReturnCompletion): блокировка
 * держится на время `refund()`, второй запрос дождётся и увидит `completed`.
 *
 * @return array<string, mixed>|null null — заявки нет
 */
function returnLockForCompletion(int $returnId): ?array
{
    $stmt = getPdo()->prepare('
        SELECT r.id, r.order_id, r.status,
               o.payment_method, o.payment_status, o.total
        FROM order_returns r
        JOIN orders o ON o.id = r.order_id
        WHERE r.id = ?
        FOR UPDATE
    ');
    $stmt->execute([$returnId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * Завершить Возврат (FR-RET-003): переход в `completed` через
 * returnTransition() (с письмом), возврат остатка и, если деньги вернули,
 * `payment_status = refunded` — внутри уже открытой транзакции вызывающего.
 *
 * @return bool false — переход недопустим, ничего не изменено
 */
function returnComplete(int $returnId, int $orderId, int $resolvedByUserId, bool $markRefunded): bool
{
    if (!returnTransition($returnId, 'completed', $resolvedByUserId)) {
        return false;
    }

    orderRestoreStock($orderId);

    if ($markRefunded) {
        orderMarkRefunded($orderId);
    }

    return true;
}

/**
 * Заявка с контактами Покупателя из Заказа (снэпшот `contact_*`, ADR-017).
 */
function returnFindById(int $id): ?array
{
    $stmt = getPdo()->prepare('
        SELECT r.id, r.order_id, r.reason, r.status, r.decision_comment,
               r.resolved_by_user_id, r.created_at, r.updated_at,
               o.user_id, o.contact_name, o.contact_email, o.total
        FROM order_returns r
        JOIN orders o ON o.id = r.order_id
        WHERE r.id = ?
    ');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * Заявка на Заказ (0..1 на Заказ). null — заявки нет.
 */
function returnFindByOrderId(int $orderId): ?array
{
    $stmt = getPdo()->prepare('SELECT id FROM order_returns WHERE order_id = ?');
    $stmt->execute([$orderId]);
    $id = $stmt->fetchColumn();

    return $id === false ? null : returnFindById((int) $id);
}

/**
 * Заявки Покупателя со статусами для `/account/returns`. Новые — первыми.
 *
 * @return list<array<string, mixed>>
 */
function returnsByUser(int $userId): array
{
    $stmt = getPdo()->prepare('
        SELECT r.id, r.order_id, r.reason, r.status, r.decision_comment, r.created_at,
               (SELECT COUNT(*) FROM order_return_photos p WHERE p.return_id = r.id) AS photo_count
        FROM order_returns r
        JOIN orders o ON o.id = r.order_id
        WHERE o.user_id = ?
        ORDER BY r.created_at DESC, r.id DESC
    ');
    $stmt->execute([$userId]);

    return $stmt->fetchAll();
}

/**
 * Заявки для очереди `/admin/returns` (FR-RET-002) с контактами и суммой
 * Заказа. Новые — первыми.
 *
 * @return list<array<string, mixed>>
 */
function returnListForAdmin(?string $status, int $limit, int $offset): array
{
    $sql = '
        SELECT r.id, r.order_id, r.status, r.created_at,
               o.contact_name, o.contact_phone, o.total,
               (SELECT COUNT(*) FROM order_return_photos p WHERE p.return_id = r.id) AS photo_count
        FROM order_returns r
        JOIN orders o ON o.id = r.order_id
    ';

    if ($status !== null) {
        $sql .= ' WHERE r.status = :status';
    }

    $sql .= ' ORDER BY r.created_at DESC, r.id DESC LIMIT :limit OFFSET :offset';

    $stmt = getPdo()->prepare($sql);
    if ($status !== null) {
        $stmt->bindValue(':status', $status);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function returnCountForAdmin(?string $status): int
{
    return $status === null
        ? (int) getPdo()->query('SELECT COUNT(*) FROM order_returns')->fetchColumn()
        : returnCountByStatus($status);
}

function returnCountByStatus(string $status): int
{
    $stmt = getPdo()->prepare('SELECT COUNT(*) FROM order_returns WHERE status = ?');
    $stmt->execute([$status]);

    return (int) $stmt->fetchColumn();
}

/**
 * Карточка заявки для админки: заявка + контакты и состояние Заказа + кто
 * принял решение.
 *
 * @return array<string, mixed>|null
 */
function returnFindForAdmin(int $id): ?array
{
    $stmt = getPdo()->prepare('
        SELECT r.id, r.order_id, r.reason, r.status, r.decision_comment,
               r.resolved_by_user_id, r.created_at, r.updated_at,
               o.status AS order_status, o.payment_method, o.payment_status,
               o.contact_name, o.contact_phone, o.contact_email, o.total,
               u.name AS resolved_by_name
        FROM order_returns r
        JOIN orders o ON o.id = r.order_id
        LEFT JOIN users u ON u.id = r.resolved_by_user_id
        WHERE r.id = ?
    ');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * Пути фото заявки (относительно public/).
 *
 * @return list<string>
 */
function returnPhotos(int $returnId): array
{
    $stmt = getPdo()->prepare('SELECT path FROM order_return_photos WHERE return_id = ? ORDER BY id');
    $stmt->execute([$returnId]);

    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}
