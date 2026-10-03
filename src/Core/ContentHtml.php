<?php

declare(strict_types=1);

/**
 * Санитайзер HTML статических страниц (`content_pages.body`, phase-8.md,
 * Таск 1) — белый список тегов и атрибутов. Применяется и при сохранении
 * (Таск 9), и при выводе: в `body` не должно остаться исполняемого кода,
 * даже если строку правили в обход админки.
 */

/** Теги, которые остаются в выводе; у всех, кроме `a`, атрибутов нет. */
const CONTENT_HTML_ALLOWED_TAGS = ['p', 'h2', 'h3', 'ul', 'ol', 'li', 'strong', 'em', 'a'];

/** Теги, которые вырезаются вместе с содержимым (остальные запрещённые — только обёртка). */
const CONTENT_HTML_DROPPED_TAGS = [
    'script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template',
    'svg', 'math', 'textarea', 'select', 'title', 'head',
];

/** Схемы `href`, кроме относительных ссылок. */
const CONTENT_HTML_ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

function contentHtmlSanitize(string $html): string
{
    if (trim($html) === '') {
        return '';
    }

    $previous = libxml_use_internal_errors(true);
    $document = new DOMDocument('1.0', 'UTF-8');
    // XML-префикс с encoding — единственный способ сказать loadHTML(), что строка в UTF-8.
    $document->loadHTML(
        '<?xml encoding="UTF-8"?><body>' . $html . '</body>',
        LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $body = $document->getElementsByTagName('body')->item(0);
    if ($body === null) {
        return '';
    }

    contentHtmlCleanChildren($body);

    $result = '';
    foreach ($body->childNodes as $child) {
        $result .= $document->saveHTML($child);
    }

    return trim($result);
}

/** Проверяет `href`: http(s)/mailto/tel или относительная ссылка. */
function contentHtmlIsSafeHref(string $href): bool
{
    // Браузер выкидывает управляющие символы и пробелы внутри схемы
    // ("java\tscript:"), поэтому проверяем строку без них.
    $normalized = preg_replace('/[\x00-\x20\x7F]+/u', '', $href) ?? '';
    if ($normalized === '') {
        return false;
    }

    if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $normalized, $match) === 1) {
        return in_array(strtolower($match[1]), CONTENT_HTML_ALLOWED_SCHEMES, true);
    }

    // `//host/path` — ссылка на чужой хост, не относительная.
    return !str_starts_with($normalized, '//') && !str_starts_with($normalized, '\\');
}

function contentHtmlCleanChildren(DOMNode $parent): void
{
    // Копия списка: ниже узлы удаляются и заменяются по ходу обхода.
    foreach (iterator_to_array($parent->childNodes) as $node) {
        if ($node instanceof DOMText) {
            continue;
        }

        if (!$node instanceof DOMElement) {
            $parent->removeChild($node);
            continue;
        }

        $tag = strtolower($node->tagName);

        if (in_array($tag, CONTENT_HTML_DROPPED_TAGS, true)) {
            $parent->removeChild($node);
            continue;
        }

        contentHtmlCleanChildren($node);

        if (!in_array($tag, CONTENT_HTML_ALLOWED_TAGS, true)) {
            // Запрещённый тег — остаётся только его (уже очищенное) содержимое.
            while ($node->firstChild !== null) {
                $parent->insertBefore($node->firstChild, $node);
            }
            $parent->removeChild($node);
            continue;
        }

        $href = $tag === 'a' ? $node->getAttribute('href') : '';
        foreach (iterator_to_array($node->attributes) as $attribute) {
            $node->removeAttributeNode($attribute);
        }
        if ($tag === 'a' && contentHtmlIsSafeHref($href)) {
            $node->setAttribute('href', trim($href));
        }
    }
}
