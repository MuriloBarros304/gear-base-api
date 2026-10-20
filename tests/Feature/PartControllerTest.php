<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testes de Feature para o CRUD de Peças (PartController)
 *
 * Valida todos os endpoints REST da entidade Part sob autenticação Sanctum:
 * - Listagem paginada (GET /api/parts)
 * - Criação e regras de integridade (POST /api/parts)
 * - Consulta individual (GET /api/parts/{id})
 * - Atualização total e parcial (PUT/PATCH /api/parts/{id})
 * - Exclusão (DELETE /api/parts/{id})
 */
class PartControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    /**
     * setUp() roda antes de cada teste desta classe.
     * Criamos um usuário autenticado padrão para reutilizar em todas as requisições.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * Testa a listagem paginada de peças.
     */
    public function test_can_list_parts_with_pagination(): void
    {
        // Cria 15 peças usando a Factory
        Part::factory()->count(15)->create();

        $response = $this->actingAs($this->user)->getJson('/api/parts');

        // Como configuramos paginate(10), a primeira página deve conter 10 itens
        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'sku', 'stock_quantity', 'cost_price', 'sale_price', 'created_at']
                ],
                'links',
                'meta' => ['current_page', 'last_page', 'total', 'per_page'],
            ]);
    }

    /**
     * Testa cadastro de nova peça com dados válidos.
     */
    public function test_can_create_a_new_part(): void
    {
        $payload = [
            'name'           => 'Disco de Freio Ventilado 280mm',
            'sku'            => 'BRK-DISC-001',
            'stock_quantity' => 12,
            'cost_price'     => 120.50,
            'sale_price'     => 219.90,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/parts', $payload);

        // Deve retornar 201 Created
        $response->assertStatus(201)
            ->assertJsonPath('data.sku', 'BRK-DISC-001')
            ->assertJsonPath('data.stock_quantity', 12);

        // Confirma a gravação no banco de dados SQLite
        $this->assertDatabaseHas('parts', [
            'sku'  => 'BRK-DISC-001',
            'name' => 'Disco de Freio Ventilado 280mm',
        ]);
    }

    /**
     * Testa validação contra código SKU duplicado.
     */
    public function test_cannot_create_part_with_duplicate_sku(): void
    {
        // Cria primeira peça com um SKU específico
        Part::factory()->create(['sku' => 'EXISTING-SKU-123']);

        // Tenta cadastrar uma segunda peça com o mesmo SKU
        $response = $this->actingAs($this->user)->postJson('/api/parts', [
            'name'           => 'Outra Peça Qualquer',
            'sku'            => 'EXISTING-SKU-123',
            'stock_quantity' => 5,
            'sale_price'     => 50.00,
        ]);

        // Espera-se erro 422 na chave 'sku'
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sku']);
    }

    /**
     * Testa visualização de peça específica pelo ID.
     */
    public function test_can_show_single_part(): void
    {
        $part = Part::factory()->create(['name' => 'Vela de Iridium']);

        $response = $this->actingAs($this->user)->getJson("/api/parts/{$part->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $part->id)
            ->assertJsonPath('data.name', 'Vela de Iridium');
    }

    /**
     * Testa atualização de peça mantendo o mesmo SKU (validação Rule::unique()->ignore).
     */
    public function test_can_update_part_attributes(): void
    {
        $part = Part::factory()->create([
            'sku'        => 'MY-UNIQUE-SKU',
            'sale_price' => 100.00,
        ]);

        // Atualização via PATCH alterando apenas o preço de venda e mantendo o mesmo SKU
        $response = $this->actingAs($this->user)->patchJson("/api/parts/{$part->id}", [
            'sku'        => 'MY-UNIQUE-SKU',
            'sale_price' => 149.90,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.sale_price', 149.90);

        $this->assertDatabaseHas('parts', [
            'id'         => $part->id,
            'sale_price' => 149.90,
        ]);
    }

    /**
     * Testa exclusão de uma peça (HTTP 204 No Content).
     */
    public function test_can_delete_part(): void
    {
        $part = Part::factory()->create();

        $response = $this->actingAs($this->user)->deleteJson("/api/parts/{$part->id}");

        // 204 indica sucesso sem conteúdo no corpo da resposta
        $response->assertStatus(204);

        // Confirma remoção da tabela
        $this->assertDatabaseMissing('parts', ['id' => $part->id]);
    }
}
