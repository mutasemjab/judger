<?php

namespace Tests\Feature;

use App\Enums\MessageRole;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\AI\Contracts\LlmProviderInterface;
use App\Services\AI\Providers\MockLlmProvider;
use App\Services\Vector\Contracts\VectorStoreInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LegalChatExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.provider' => 'mock']);
        Storage::fake('local');
    }

    public function test_chat_redirects_clear_non_legal_requests_without_calling_llm(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'title' => 'General Legal Chat',
        ]);

        $provider = new class implements LlmProviderInterface {
            public array $chatMessages = [];

            public function chat(array $messages, array $options = []): string
            {
                $this->chatMessages = $messages;

                return "## Legal Scope\n\nI can only help with legal questions here.";
            }

            public function chatJson(array $messages, array $schema = [], array $options = []): array
            {
                return [];
            }

            public function embedding(string $text): array
            {
                return [1.0, 0.0];
            }

            public function embeddingMany(array $texts): array
            {
                return array_fill(0, count($texts), [1.0, 0.0]);
            }
        };

        $this->app->instance(MockLlmProvider::class, $provider);

        $response = $this->apiAs($user)->postJson("/api/v1/conversations/{$conversation->id}/chat", [
            'message' => 'Tell me a joke about coffee.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.scope.allowed', false)
            ->assertJsonPath('data.scope.reason', 'non_legal_topic')
            ->assertJsonPath('data.presentation.variant', 'legal_only_redirect')
            ->assertJsonPath('data.presentation.render_hints.show_download_button', false)
            ->assertJsonPath('data.actions.0.id', 'ask_legal_question');

        $this->assertSame([], $provider->chatMessages);
        $this->assertStringContainsString('Legal Topics Only', $response->json('data.answer'));
        $this->assertNotEmpty($response->json('data.follow_up_questions'));
    }

    public function test_chat_allows_legal_follow_up_without_repeating_full_legal_terms(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'title' => 'Contract Review',
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => MessageRole::User->value,
            'content' => 'I need help reviewing a contract breach issue.',
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => null,
            'role' => MessageRole::Assistant->value,
            'content' => '## Legal Guidance' . "\n\n" . 'A written notice may be the right next step.' . "\n\n" . config('ai.legal_disclaimer'),
            'disclaimer' => config('ai.legal_disclaimer'),
        ]);

        $response = $this->apiAs($user)->postJson("/api/v1/conversations/{$conversation->id}/chat", [
            'message' => 'What should I do next with this?',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.scope.allowed', true)
            ->assertJsonPath('data.scope.reason', 'legal_follow_up')
            ->assertJsonPath('data.presentation.format', 'markdown')
            ->assertJsonPath('data.download.format', 'docx')
            ->assertJsonPath('data.actions.0.id', 'download_docx');

        $downloadUrl = $response->json('data.download.url');
        $this->assertNotEmpty($downloadUrl);

        $downloadResponse = $this->apiAs($user)->get($downloadUrl);
        $downloadResponse->assertOk();
        $this->assertStringContainsString('.docx', $downloadResponse->headers->get('content-disposition', ''));
    }

    public function test_chat_supports_arabic_legal_questions_with_rtl_metadata(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'title' => 'استشارة قانونية',
        ]);

        $response = $this->apiAs($user)->postJson("/api/v1/conversations/{$conversation->id}/chat", [
            'message' => 'ما هي حقوقي القانونية في عقد الإيجار؟',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.scope.allowed', true)
            ->assertJsonPath('data.scope.reason', 'legal_topic')
            ->assertJsonPath('data.presentation.language', 'ar')
            ->assertJsonPath('data.presentation.direction', 'rtl')
            ->assertJsonPath('data.download.format', 'docx')
            ->assertJsonPath('data.disclaimer', config('ai.legal_disclaimer_ar'));

        $this->assertStringContainsString('إرشاد قانوني', $response->json('data.answer'));
        $this->assertTrue(collect($response->json('data.follow_up_questions'))->contains(fn ($question) => str_contains($question, 'محام')));
    }

    public function test_chat_sends_knowledge_base_output_to_llm_before_answering(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'title' => 'Lease Notice',
        ]);

        $provider = new class implements LlmProviderInterface {
            public array $chatMessages = [];

            public function chat(array $messages, array $options = []): string
            {
                $this->chatMessages = $messages;

                return "## Quick Answer\n\nUse a written notice before termination. [KB_SOURCE_1]";
            }

            public function chatJson(array $messages, array $schema = [], array $options = []): array
            {
                return [];
            }

            public function embedding(string $text): array
            {
                return [1.0, 0.0];
            }

            public function embeddingMany(array $texts): array
            {
                return array_fill(0, count($texts), [1.0, 0.0]);
            }
        };

        $this->app->instance(MockLlmProvider::class, $provider);
        $this->app->instance(VectorStoreInterface::class, new class implements VectorStoreInterface {
            public function ensureCollection(string $collectionName, int $vectorSize): void
            {
            }

            public function upsertPoint(string $collectionName, string|int $id, array $vector, array $payload): void
            {
            }

            public function search(string $collectionName, array $vector, int $limit = 10, array $filter = []): array
            {
                if ($collectionName !== config('ai.qdrant_knowledge_collection')) {
                    return [];
                }

                return [[
                    'id' => 'kb-lease-1',
                    'score' => 0.95,
                    'payload' => [
                        'source_type' => 'knowledge_base',
                        'knowledge_document_id' => 10,
                        'chunk_index' => 2,
                        'document_name' => 'Lease Notice Guide.pdf',
                        'category' => 'leases',
                        'page_number' => 4,
                        'content' => 'A lease termination should be supported by a clear written notice period.',
                        'snippet' => 'clear written notice period',
                        'status' => 'processed',
                    ],
                ]];
            }

            public function deleteByFilter(string $collectionName, array $filter): void
            {
            }
        });

        $response = $this->apiAs($user)->postJson("/api/v1/conversations/{$conversation->id}/chat", [
            'message' => 'What legal notice is required for lease termination?',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.source_type', 'knowledge_base')
            ->assertJsonPath('data.sources.0.source_label', 'KB_SOURCE_1')
            ->assertJsonPath('data.retrieval.knowledge_base_searched_before_llm', true)
            ->assertJsonPath('data.retrieval.knowledge_base_results_count', 1)
            ->assertJsonPath('data.presentation.status_cards.1.id', 'sources');

        $prompt = $provider->chatMessages[1]['content'] ?? '';

        $this->assertStringContainsString('RETRIEVAL STATUS', $prompt);
        $this->assertStringContainsString('Knowledge base searched before LLM: yes', $prompt);
        $this->assertStringContainsString('[KB_SOURCE_1]', $prompt);
        $this->assertStringContainsString('A lease termination should be supported by a clear written notice period.', $prompt);
    }

    private function apiAs(User $user): self
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', "Bearer {$token}");
    }
}
