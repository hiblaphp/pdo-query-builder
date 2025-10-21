<?php

namespace Hibla\PdoQueryBuilder\Pagination;

class TemplateEngine
{
    private string $templatesPath;

    public function __construct(?string $templatesPath = null)
    {
        $this->templatesPath = $templatesPath ?? __DIR__ . '/templates';
    }

    /**
     * Register a custom templates path
     */
    public static function setTemplatesPath(string $path): void
    {
        if (!is_dir($path)) {
            throw new \RuntimeException("Templates path does not exist: {$path}");
        }
    }

    /**
     * Render a template with variables
     *
     * @param  string  $template  Template name
     * @param  array<string, mixed>  $data  Variables to pass to template
     * @return string Rendered HTML
     */
    public function render(string $template, array $data = []): string
    {
        if (str_contains($template, '::')) {
            $template = explode('::', $template)[1];
        }

        $templatePath = $this->getTemplatePath($template);

        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Template not found: {$template} at {$templatePath}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        include $templatePath;

        return ob_get_clean() ?: '';
    }

    /**
     * Get full path to template file
     */
    private function getTemplatePath(string $template): string
    {
        $path = $this->templatesPath . '/' . $template . '.php';

        return $path;
    }

    /**
     * Check if template exists
     */
    public function exists(string $template): bool
    {
        if (str_contains($template, '::')) {
            $template = explode('::', $template)[1];
        }

        return file_exists($this->getTemplatePath($template));
    }
}