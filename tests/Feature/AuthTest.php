<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testes de Feature para Autenticação e Laravel Sanctum
 *
 * O trait 'RefreshDatabase' roda as migrations no banco de teste em memória (:memory:)
 * antes de cada método de teste e reverte todas as transações ao final.
 * Isso garante que cada teste execute em um ambiente limpo e isolado.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Testa o cadastro de um novo usuário via API pública.
     */
    public function test_user_can_register_successfully(): void
    {
        // Payload enviado no corpo da requisição POST
        $payload = [
            'name'                  => 'Murilo Lima',
            'email'                 => 'murilo@example.com',
            'password'              => 'segredo123',
            'password_confirmation' => 'segredo123',
        ];

        // Dispara uma requisição POST com cabeçalho 'Accept: application/json'
        $response = $this->postJson('/api/register', $payload);

        // Asserções:
        // 1. Verifica se o status retornado é 201 Created
        $response->assertStatus(201);

        // 2. Verifica a estrutura do JSON devolvido
        $response->assertJsonStructure([
            'message',
            'user' => ['id', 'name', 'email', 'created_at'],
            'access_token',
            'token_type',
        ]);

        // 3. Verifica se o usuário foi realmente persistido na tabela 'users'
        $this->assertDatabaseHas('users', [
            'email' => 'murilo@example.com',
        ]);
    }

    /**
     * Testa login com credenciais válidas e emissão de token Sanctum.
     */
    public function test_user_can_login_with_valid_credentials(): void
    {
        // Cria um usuário no banco usando a Factory
        $user = User::factory()->create([
            'email'    => 'mecanico@gearbase.com',
            'password' => 'senhaCorreta123',
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => 'mecanico@gearbase.com',
            'password' => 'senhaCorreta123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'user', 'access_token', 'token_type']);
    }

    /**
     * Testa rejeição de login com senha incorreta (HTTP 422).
     */
    public function test_user_cannot_login_with_invalid_password(): void
    {
        User::factory()->create([
            'email'    => 'mecanico@gearbase.com',
            'password' => 'senhaCorreta123',
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => 'mecanico@gearbase.com',
            'password' => 'senhaERRADA',
        ]);

        // Validação falha e retorna 422 Unprocessable Content com mensagem
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Testa que rotas protegidas rejeitam requisições sem token (HTTP 401 Unauthorized).
     */
    public function test_unauthenticated_request_to_protected_route_is_rejected(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
    }

    /**
     * Testa acesso à rota protegida /api/me usando o método actingAs do Sanctum.
     */
    public function test_authenticated_user_can_access_me_profile(): void
    {
        $user = User::factory()->create();

        // O método actingAs() simula que o usuário enviou um token válido do Sanctum no cabeçalho
        $response = $this->actingAs($user)->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJson([
                'user' => [
                    'id'    => $user->id,
                    'email' => $user->email,
                ],
            ]);
    }

    /**
     * Testa revogação de token no logout.
     */
    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test_token')->plainTextToken;

        // Enviamos o token explicitamente no cabeçalho Authorization
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/logout');

        $response->assertStatus(200);

        // Verifica se o token foi apagado da tabela personal_access_tokens
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
