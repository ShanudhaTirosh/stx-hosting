<?php

namespace App\Services\Supabase;

class SupabaseQueryBuilder
{
    protected SupabaseClient $client;
    protected string $table;
    protected array $queryParams = [];
    protected bool $useServiceRole = true;

    public function __construct(SupabaseClient $client, string $table)
    {
        $this->client = $client;
        $this->table = $table;
    }

    public function asUser(): static
    {
        $this->useServiceRole = false;

        return $this;
    }

    public function select(string $columns = '*'): static
    {
        $this->queryParams['select'] = $columns;

        return $this;
    }

    public function eq(string $column, mixed $value): static
    {
        $this->queryParams[$column] = "eq.{$value}";

        return $this;
    }

    public function neq(string $column, mixed $value): static
    {
        $this->queryParams[$column] = "neq.{$value}";

        return $this;
    }

    public function in(string $column, array $values): static
    {
        $joined = implode(',', $values);
        $this->queryParams[$column] = "in.({$joined})";

        return $this;
    }

    public function order(string $column, string $direction = 'asc'): static
    {
        $this->queryParams['order'] = "{$column}.{$direction}";

        return $this;
    }

    public function limit(int $count): static
    {
        $this->queryParams['limit'] = $count;

        return $this;
    }

    public function get(): array
    {
        if (! isset($this->queryParams['select'])) {
            $this->queryParams['select'] = '*';
        }

        $response = $this->client->request('GET', $this->table, [], $this->queryParams, $this->useServiceRole);

        return $response && $response->successful() ? $response->json() : [];
    }

    public function first(): ?array
    {
        $this->limit(1);
        $results = $this->get();

        return ! empty($results) ? $results[0] : null;
    }

    public function insert(array $data): ?array
    {
        $response = $this->client->request('POST', $this->table, $data, [], $this->useServiceRole);

        return $response && $response->successful() ? $response->json() : null;
    }

    public function update(array $data): ?array
    {
        $response = $this->client->request('PATCH', $this->table, $data, $this->queryParams, $this->useServiceRole);

        return $response && $response->successful() ? $response->json() : null;
    }

    public function upsert(array $data, ?string $onConflict = null): ?array
    {
        $response = $this->client->upsert($this->table, $data, $onConflict, $this->useServiceRole);

        return $response && $response->successful() ? $response->json() : null;
    }

    public function delete(): bool
    {
        $response = $this->client->request('DELETE', $this->table, [], $this->queryParams, $this->useServiceRole);

        return $response !== null && $response->successful();
    }
}

