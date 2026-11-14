<?php
declare(strict_types=1);

namespace Webmail\WildDuck\Model;

final class Page
{
    /** @param list<MessageSummary> $items */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly ?string $next,
        public readonly ?string $previous,
    ) {
    }
}
