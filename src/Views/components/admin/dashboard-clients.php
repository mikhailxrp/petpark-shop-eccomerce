<?php

declare(strict_types=1);

/**
 * Блок «Последние клиенты» / «Мои клиенты» на сводке (FR-MGR-001,
 * FR-MGR-003) — /admin и /specialist.
 *
 * @var string $clientsTitle
 * @var list<array<string, mixed>> $recentClients clientRecent()
 */
?>
<section class="card mb-4" aria-labelledby="dashboard-clients-title">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title mb-0" id="dashboard-clients-title"><?= e($clientsTitle) ?></h2>
        <a href="/admin/clients" class="btn btn-sm btn-outline-primary">Все клиенты</a>
    </div>
    <div class="card-body">
        <?php if ($recentClients === []): ?>
            <p class="mb-0 text-muted">Клиентов пока нет.</p>
        <?php else: ?>
            <ul class="list-unstyled mb-0">
                <?php foreach ($recentClients as $client): ?>
                    <li class="mb-2">
                        <a href="/admin/clients/<?= (int) $client['id'] ?>"><?= e((string) $client['name']) ?></a>
                        <span class="text-muted fs-12">
                            <?= ($client['phone'] ?? '') !== '' ? e((string) $client['phone']) : '—' ?>
                            <?php if (($client['pet_names'] ?? '') !== ''): ?>
                                · <?= e((string) $client['pet_names']) ?>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
