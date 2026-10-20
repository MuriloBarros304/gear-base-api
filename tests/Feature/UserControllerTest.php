<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testes de Feature para o CRUD de Usuários / Mecânicos (UserController)
 */
class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    /**
     * Testa listagem paginada de usuários.
     */
    public function test_can_list_users(): void
    {
        User::factory()->count(12)->create();

        $response = $this->actingAs($this->admin)->getJson('/api/users');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'created_at']
                ],
                'links',
                'meta',
            ]);
    }

    /**
     * Testa criação de um novo usuário / mecânico via endpoint.
     */
    public function test_can_create_user(): void
    {
        $payload = [
            'name'     => 'Novo Mecânico Teste',
            'email'    => 'novomecanico@gearbase.com',
            'password' => 'senhaSegura123',
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/users', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Novo Mecânico Teste')
            ->assertJsonPath('data.email', 'novomecanico@gearbase.com');

        $this->assertDatabaseHas('users', [
            'email' => 'novomecanico@gearbase.com',
        ]);
    }

    /**
     * Testa visualização de um usuário específico.
     */
    public function test_can_show_user(): void
    {
        $user = User::factory()->create(['name' => 'Roberto Silveira']);

        $response = $this->actingAs($this->admin)->getJson("/api/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Roberto Silveira');
    }

    /**
     * Testa atualização de dados cadastrais de um usuário.
     */
    public function test_can_update_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($this->admin)->patchJson("/api/users/{$user->id}", [
            'name' => 'Nome Atualizado do Mecânico',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Nome Atualizado do Mecânico');

        $this->assertDatabaseHas('users', [
            'id'   => $user->id,
            'name' => 'Nome Atualizado do Mecânico',
        ]);
    }

    /**
     * Testa exclusão de usuário.
     */
    public function test_can_delete_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($this->admin)->deleteJson("/api/users/{$user->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
