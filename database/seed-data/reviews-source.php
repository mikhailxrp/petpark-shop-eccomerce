<?php

declare(strict_types=1);

/**
 * Сид-отзывы (published/pending) для демонстрации модерации (Q-048,
 * ADR-011) и блока «Отзывы» на Главной (FR-HOME-008) — в xlsx данных нет,
 * отзывы не часть каталога-источника. Ключ — код товара из catalog-source.php.
 * author_email на домене @seed.petpark.test — по нему seed-catalog.php
 * узнаёт «свои» строки при повторном запуске и не трогает чужие данные.
 */

const REVIEWS_SOURCE = [
    'FD-01' => [
        [
            'author_name'  => 'Ирина Соколова',
            'author_email' => 'irina.sokolova@seed.petpark.test',
            'rating'       => 5,
            'body'         => 'Кот ест с удовольствием, шерсть стала мягче за месяц. Берём уже третью упаковку.',
            'status'       => 'published',
        ],
        [
            'author_name'  => 'Павел Дегтярёв',
            'author_email' => 'pavel.degtyarev@seed.petpark.test',
            'rating'       => 4,
            'body'         => 'Хороший корм, но хотелось бы упаковку 10 кг сразу с застёжкой — пересыпаем в контейнер.',
            'status'       => 'published',
        ],
    ],
    'FD-03' => [
        [
            'author_name'  => 'Анна Ким',
            'author_email' => 'anna.kim@seed.petpark.test',
            'rating'       => 5,
            'body'         => 'Кошка выбирает этот паучи из всех, что пробовали. Курица заходит лучше всего.',
            'status'       => 'published',
        ],
    ],
    'FD-07' => [
        [
            'author_name'  => 'Сергей Волков',
            'author_email' => 'sergey.volkov@seed.petpark.test',
            'rating'       => 5,
            'body'         => 'Перевели кота на беззерновой корм по рекомендации ветеринара — результат заметен уже через две недели.',
            'status'       => 'published',
        ],
    ],
    'TR-02' => [
        [
            'author_name'  => 'Мария Орлова',
            'author_email' => 'maria.orlova@seed.petpark.test',
            'rating'       => 4,
            'body'         => 'Пёс с удовольствием грызёт, зубы стали чище. Размер M подошёл для лабрадора.',
            'status'       => 'published',
        ],
        [
            'author_name'  => 'Дмитрий Носов',
            'author_email' => 'dmitry.nosov@seed.petpark.test',
            'rating'       => 2,
            'body'         => 'Косточка раскрошилась быстрее, чем ожидал — активной собаке на неделю не хватило.',
            'status'       => 'pending',
        ],
    ],
    'AC-01' => [
        [
            'author_name'  => 'Елена Гаврилова',
            'author_email' => 'elena.gavrilova@seed.petpark.test',
            'rating'       => 5,
            'body'         => 'Красивая керамическая миска, кошка стала есть спокойнее — не скользит по полу.',
            'status'       => 'published',
        ],
    ],
    'AC-03' => [
        [
            'author_name'  => 'Ольга Стрельникова',
            'author_email' => 'olga.strelnikova@seed.petpark.test',
            'rating'       => 5,
            'body'         => 'Лежанка мягкая, кот обжил её в первый же день. Бежевый цвет смотрится очень уютно.',
            'status'       => 'published',
        ],
    ],
    'AC-05' => [
        [
            'author_name'  => 'Игорь Фомин',
            'author_email' => 'igor.fomin@seed.petpark.test',
            'rating'       => 4,
            'body'         => 'Шлейка удобная, размер M сел точно по замерам. Единственное — карабин туговат.',
            'status'       => 'published',
        ],
        [
            'author_name'  => 'Юлия Бессонова',
            'author_email' => 'yulia.bessonova@seed.petpark.test',
            'rating'       => 3,
            'body'         => 'Шлейка нормальная, но синий цвет на фото ярче, чем в реальности.',
            'status'       => 'pending',
        ],
    ],
    'AC-10' => [
        [
            'author_name'  => 'Наталья Крылова',
            'author_email' => 'natalia.krylova@seed.petpark.test',
            'rating'       => 5,
            'body'         => 'Огромный домик, два яруса — кошки делят верхнюю полку. Собирается примерно за час.',
            'status'       => 'published',
        ],
    ],
    'TY-01' => [
        [
            'author_name'  => 'Виктор Ушаков',
            'author_email' => 'viktor.ushakov@seed.petpark.test',
            'rating'       => 5,
            'body'         => 'Кот носится по квартире с этими мышками весь вечер. Берём уже второй набор.',
            'status'       => 'published',
        ],
    ],
    'TY-07' => [
        [
            'author_name'  => 'Кристина Панова',
            'author_email' => 'kristina.panova@seed.petpark.test',
            'rating'       => 4,
            'body'         => 'Кошка ест медленнее, отрыжки после еды почти пропали. Мыть немного неудобно из-за лабиринта.',
            'status'       => 'published',
        ],
    ],
    'HY-03' => [
        [
            'author_name'  => 'Алексей Гришин',
            'author_email' => 'alexey.grishin@seed.petpark.test',
            'rating'       => 5,
            'body'         => 'Комкуется хорошо, запаха почти нет даже на третий день. Берём постоянно объём 10 л.',
            'status'       => 'published',
        ],
    ],
    'HY-08' => [
        [
            'author_name'  => 'Светлана Мельник',
            'author_email' => 'svetlana.melnik@seed.petpark.test',
            'rating'       => 1,
            'body'         => 'Зубцы царапают кожу собаке, пришлось вернуть после первого использования.',
            'status'       => 'pending',
        ],
    ],
];
