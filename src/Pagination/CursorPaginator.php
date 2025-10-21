<?php

namespace Hibla\PdoQueryBuilder\Pagination;

class CursorPaginator
{
    private static ?TemplateEngine $templateEngine = null;

    public function __construct(
        private array $items,
        private int $perPage,
        private ?string $nextCursor,
        private string $cursorColumn,
        private ?string $path = null,
    ) {}

    /**
     * Set a custom templates path
     */
    public static function setTemplatesPath(string $path): void
    {
        self::$templateEngine = new TemplateEngine($path);
    }

    /**
     * Get the template engine instance
     */
    private static function getTemplateEngine(): TemplateEngine
    {
        if (self::$templateEngine === null) {
            self::$templateEngine = new TemplateEngine();
        }

        return self::$templateEngine;
    }

    public function items(): array
    {
        return $this->items;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function nextCursor(): ?string
    {
        return $this->nextCursor;
    }

    public function hasMore(): bool
    {
        return $this->nextCursor !== null;
    }

    public function nextPageUrl(?string $basePath = null): ?string
    {
        if (!$this->hasMore()) {
            return null;
        }

        $basePath = $basePath ?? $this->path;

        if ($basePath === null) {
            return null;
        }

        $separator = str_contains($basePath, '?') ? '&' : '?';

        return $basePath . $separator . 'cursor=' . $this->nextCursor;
    }

    /**
     * Render cursor pagination using a template
     *
     * @param  string  $template  Template name
     * @param  string|null  $basePath  Base path for next page URL
     * @return string Rendered HTML
     */
    public function render(string $template = 'cursor-simple', ?string $basePath = null): string
    {
        if (!$this->hasMore()) {
            return '';
        }

        $engine = self::getTemplateEngine();

        // Temporarily set base path if provided
        if ($basePath !== null) {
            $this->path = $basePath;
        }

        return $engine->render($template, ['paginator' => $this]);
    }

    /**
     * Return cursor pagination metadata as JSON
     *
     * @param  bool  $includeItems  Include items in JSON response
     * @param  string|null  $basePath  Base path for next page URL
     * @return string JSON string
     */
    public function toJson(bool $includeItems = true, ?string $basePath = null): string
    {
        $data = [
            'data' => $includeItems ? $this->items : [],
            'meta' => [
                'per_page' => $this->perPage(),
                'has_more' => $this->hasMore(),
            ],
            'links' => [
                'next' => $this->nextPageUrl($basePath),
            ],
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Return cursor pagination metadata as array
     *
     * @param  bool  $includeItems  Include items in array response
     * @param  string|null  $basePath  Base path for next page URL
     * @return array<string, mixed>
     */
    public function toArray(bool $includeItems = true, ?string $basePath = null): array
    {
        return [
            'data' => $includeItems ? $this->items : [],
            'meta' => [
                'per_page' => $this->perPage(),
                'has_more' => $this->hasMore(),
            ],
            'links' => [
                'next' => $this->nextPageUrl($basePath),
            ],
        ];
    }

    /**
     * Return cursor pagination as JSON response
     * Useful for API responses
     *
     * @param  int  $statusCode  HTTP status code
     * @param  bool  $includeItems  Include items in response
     * @param  string|null  $basePath  Base path for next page URL
     * @return void
     */
    public function respondJson(int $statusCode = 200, bool $includeItems = true, ?string $basePath = null): void
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo $this->toJson($includeItems, $basePath);
        exit;
    }
}
