<?php
namespace App\Services\Order;


use App\Services\Order\Contracts\OrderRepository;
use App\Services\Order\Parsing\CommandParser;


final class OrderService
{
    public function __construct(
        private readonly OrderRepository $repo,
        private readonly CommandParser $parser,
        private readonly OrderMutator $mutator,
    ) {}


    public function all(): array { return $this->repo->all(); }
    public function clear(): array { return $this->repo->clear(); }


    /** Main entry: parse a natural command and mutate order */
    public function processCommand(string $text): array
    {
        $parsed = $this->parser->parse($text);
        return $this->mutator->apply($parsed);
    }
}
