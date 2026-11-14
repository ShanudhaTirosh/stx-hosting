<?php
declare(strict_types=1);

namespace Webmail\Core;

final class Response
{
    /** @param array<string,string> $headers */
    public function __construct(
        public int $status = 200,
        public string $body = '',
        public array $headers = [],
    ) {
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($status, $html, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self($status, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR), ['Content-Type' => 'application/json']);
    }

    public static function redirect(string $to, int $status = 303): self
    {
        return new self($status, '', ['Location' => $to]);
    }

    public function withHeader(string $k, string $v): self
    {
        $this->headers[$k] = $v;
        return $this;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $k => $v) {
            header($k . ': ' . $v);
        }
        echo $this->body;
    }
}
