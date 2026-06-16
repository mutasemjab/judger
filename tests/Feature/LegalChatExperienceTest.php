<?php

namespace Tests\Feature;

use App\Enums\MessageRole;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
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

    public function test_chat_refuses_non_legal_questions_with_styled_redirect(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'title' => 'General Legal Chat',
        ]);

        $response = $this->apiAs($user)->postJson("/api/v1/conversations/{$conversation->id}/chat", [
            'message' => 'Tell me a joke about coffee.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.scope.allowed', false)
            ->assertJsonPath('data.scope.reason', 'non_legal_topic')
            ->assertJsonPath('data.presentation.variant', 'legal_only_redirect');

        $this->assertStringContainsString('Legal Topics Only', $response->json('data.answer'));
        $this->assertNotEmpty($response->json('data.follow_up_questions'));
        $this->assertNotEmpty($response->json('data.download.url'));
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
            ->assertJsonPath('data.presentation.format', 'markdown');

        $downloadUrl = $response->json('data.download.url');
        $this->assertNotEmpty($downloadUrl);

        $downloadResponse = $this->apiAs($user)->get($downloadUrl);
        $downloadResponse->assertOk();
        $this->assertStringContainsString('.md', $downloadResponse->headers->get('content-disposition', ''));
    }

    private function apiAs(User $user): self
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', "Bearer {$token}");
    }
}
