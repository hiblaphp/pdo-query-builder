<?php

namespace Hibla\PdoQueryBuilder\Pagination;

class Paginator
{
    private static ?TemplateEngine $templateEngine = null;

    public function __construct(
        private array $items,
        private int $total,
        private int $perPage,
        private int $currentPage,
        private ?string $path = null,
        private ?string $query = null,
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

    public function total(): int
    {
        return $this->total;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function lastPage(): int
    {
        return (int) ceil($this->total / $this->perPage);
    }

    public function from(): int
    {
        if ($this->total === 0) {
            return 0;
        }

        return ($this->currentPage - 1) * $this->perPage + 1;
    }

    public function to(): int
    {
        if ($this->total === 0) {
            return 0;
        }

        return min($this->currentPage * $this->perPage, $this->total);
    }

    public function hasMore(): bool
    {
        return $this->currentPage < $this->lastPage();
    }

    public function hasPages(): bool
    {
        return $this->lastPage() > 1;
    }

    public function isFirstPage(): bool
    {
        return $this->currentPage === 1;
    }

    public function isLastPage(): bool
    {
        return $this->currentPage >= $this->lastPage();
    }

    public function nextPageUrl(): ?string
    {
        if (!$this->hasMore()) {
            return null;
        }

        return $this->url($this->currentPage + 1);
    }

    public function previousPageUrl(): ?string
    {
        if ($this->currentPage <= 1) {
            return null;
        }

        return $this->url($this->currentPage - 1);
    }

    public function url(int $page): string
    {
        if ($this->path === null) {
            return '';
        }

        $separator = str_contains($this->path, '?') ? '&' : '?';
        $query = $this->query ? $this->query . '&' : '';

        return $this->path . $separator . $query . 'page=' . $page;
    }

    public function getUrlRange(int $start, int $end): array
    {
        $urls = [];
        for ($page = $start; $page <= $end; $page++) {
            $urls[$page] = $this->url($page);
        }

        return $urls;
    }

    /**
     * Render pagination using a template
     *
     * @param  string  $template  Template name (bootstrap, tailwind, simple)
     * @return string Rendered HTML
     */
    public function render(string $template = 'bootstrap'): string
    {
        if (!$this->hasPages()) {
            return '';
        }

        $engine = self::getTemplateEngine();

        return $engine->render($template, ['paginator' => $this]);
    }

    /**
     * Return pagination metadata as JSON
     *
     * @param  bool  $includeItems  Include items in JSON response
     * @return string JSON string
     */
    public function toJson(bool $includeItems = true): string
    {
        $data = [
            'data' => $includeItems ? $this->items : [],
            'meta' => [
                'total' => $this->total(),
                'per_page' => $this->perPage(),
                'current_page' => $this->currentPage(),
                'last_page' => $this->lastPage(),
                'from' => $this->from(),
                'to' => $this->to(),
            ],
            'links' => [
                'first' => $this->path ? $this->url(1) : null,
                'last' => $this->path ? $this->url($this->lastPage()) : null,
                'prev' => $this->previousPageUrl(),
                'next' => $this->nextPageUrl(),
            ],
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Return pagination metadata as array
     *
     * @param  bool  $includeItems  Include items in array response
     * @return array<string, mixed>
     */
    public function toArray(bool $includeItems = true): array
    {
        return [
            'data' => $includeItems ? $this->items : [],
            'meta' => [
                'total' => $this->total(),
                'per_page' => $this->perPage(),
                'current_page' => $this->currentPage(),
                'last_page' => $this->lastPage(),
                'from' => $this->from(),
                'to' => $this->to(),
            ],
            'links' => [
                'first' => $this->path ? $this->url(1) : null,
                'last' => $this->path ? $this->url($this->lastPage()) : null,
                'prev' => $this->previousPageUrl(),
                'next' => $this->nextPageUrl(),
            ],
        ];
    }

    /**
     * Return pagination as JSON response
     * Useful for API responses
     *
     * @param  int  $statusCode  HTTP status code
     * @param  bool  $includeItems  Include items in response
     * @return void
     */
    public function respondJson(int $statusCode = 200, bool $includeItems = true): void
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo $this->toJson($includeItems);
        exit;
    }
}