<?php

namespace Tests\Feature;

use App\Models\Tool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testes de Feature para o CRUD de Ferramentas (ToolController)
 */
class ToolControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * Testa listagem de ferramentas paginada.
     */
    public function test_can_list_tools(): void
    {
        Tool::factory()->count(12)->create();

        $response = $this->actingAs($this->user)->getJson('/api/tools');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'serial_number', 'status', 'created_at']
                ],
            ]);
    }

    /**
     * Testa cadastro de nova ferramenta.
     */
    public function test_can_create_tool(): void
    {
        $payload = [
            'name'          => 'Chave Estrela 17x19mm',
            'serial_number' => 'TL-EST-001',
            'status'        => 'available',
        ];

        $response = $this->actingAs($this->user)->postJson('/api/tools', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Chave Estrela 17x19mm')
            ->assertJsonPath('data.status', 'available');

        $this->assertDatabaseHas('tools', ['serial_number' => 'TL-EST-001']);
    }

    /**
     * Testa validação de status inválido.
     */
    public function test_cannot_create_tool_with_invalid_status(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/tools', [
            'name'   => 'Martelo de Borracha',
            'status' => 'status_inexistente',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    /**
     * Testa visualização de ferramenta específica.
     */
    public function test_can_show_tool(): void
    {
        $tool = Tool::factory()->create(['name' => 'Scanner Bosch KTS']);

        $response = $this->actingAs($this->user)->getJson("/api/tools/{$tool->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Scanner Bosch KTS');
    }

    /**
     * Testa atualização de status de uma ferramenta (ex: envio para manutenção preventiva).
     */
    public function test_can_update_tool_status(): void
    {
        $tool = Tool::factory()->create(['status' => 'available']);

        $response = $this->actingAs($this->user)->patchJson("/api/tools/{$tool->id}", [
            'status' => 'maintenance',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'maintenance');

        $this->assertDatabaseHas('tools', [
            'id'     => $tool->id,
            'status' => 'maintenance',
        ]);
    }

    /**
     * Testa exclusão de ferramenta.
     */
    public function test_can_delete_tool(): void
    {
        $tool = Tool::factory()->create();

        $response = $this->actingAs($this->user)->deleteJson("/api/tools/{$tool->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('tools', ['id' => $tool->id]);
    }
}
