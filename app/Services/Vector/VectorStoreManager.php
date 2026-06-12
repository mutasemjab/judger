<?php

namespace App\Services\Vector;

use App\Services\Vector\Contracts\VectorStoreInterface;

class VectorStoreManager
{
    private ?VectorStoreInterface $store = null;
    private ?string $driver = null;

    public function resolve(): VectorStoreInterface
    {
        if ($this->store !== null) {
            return $this->store;
        }

        if (app()->environment('testing')) {
            $this->driver = 'fake';

            return $this->store = new FakeVectorStore();
        }

        $configuredDriver = config('ai.vector_store', 'auto');

        if ($configuredDriver === 'fake') {
            $this->driver = 'fake';

            return $this->store = new FakeVectorStore();
        }

        if ($configuredDriver === 'database') {
            $this->driver = 'database';

            return $this->store = new DatabaseVectorStore();
        }

        if ($configuredDriver === 'qdrant') {
            $this->driver = 'qdrant';

            return $this->store = new QdrantVectorStore();
        }

        $qdrant = new QdrantVectorStore();

        if ($qdrant->isAvailable()) {
            $this->driver = 'qdrant';

            return $this->store = $qdrant;
        }

        $this->driver = 'database';

        return $this->store = new DatabaseVectorStore();
    }

    public function driver(): string
    {
        $this->resolve();

        return $this->driver ?? 'database';
    }

    public function label(): string
    {
        return match ($this->driver()) {
            'qdrant' => 'Qdrant',
            'fake' => 'Fake Vector Store',
            default => 'Local Database Index',
        };
    }
}
