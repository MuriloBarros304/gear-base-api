<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testes de Feature para o Controller de Ordens de Manutenção (MaintenanceController)
 *
 * Valida fluxos críticos da aplicação:
 * - Criação de ordens de serviço
 * - Sincronização atômica da tabela pivot 'maintenance_part'
 * - Dedução de estoque físico das peças em DB::transaction
 * - Rejeição por falta de saldo em estoque
 * - Reposição de estoque na exclusão da ordem
 */
class MaintenanceControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $mechanic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mechanic = User::factory()->create();
    }

    /**
     * Testa criação de manutenção simples sem peças.
     */
    public function test_can_create_maintenance_without_parts(): void
    {
        $payload = [
            'user_id'       => $this->mechanic->id,
            'description'   => 'Troca de óleo do motor e alinhamento',
            'vehicle_plate' => 'BRA2E19',
            'vehicle_model' => 'Toyota Corolla 2.0 2021',
            'status'        => 'pending',
            'labor_cost'    => 150.00,
            'entry_date'    => now()->toDateTimeString(),
        ];

        $response = $this->actingAs($this->mechanic)->postJson('/api/maintenances', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.vehicle_plate', 'BRA2E19');

        $this->assertEquals(150.00, $response->json('data.labor_cost'));

        $this->assertDatabaseHas('maintenances', [
            'vehicle_plate' => 'BRA2E19',
            'user_id'       => $this->mechanic->id,
        ]);
    }

    /**
     * Testa criação de manutenção COM peças vinculadas e verifica dedução de estoque.
     */
    public function test_can_create_maintenance_with_parts_and_deduct_stock(): void
    {
        // 1. Cria duas peças com estoque conhecido
        $part1 = Part::factory()->create(['stock_quantity' => 10, 'sale_price' => 50.00]);
        $part2 = Part::factory()->create(['stock_quantity' => 5,  'sale_price' => 100.00]);

        $payload = [
            'user_id'       => $this->mechanic->id,
            'description'   => 'Revisão com substituição de 2 pastilhas e 1 filtro',
            'vehicle_plate' => 'XYZ9A88',
            'vehicle_model' => 'Honda Civic 2020',
            'status'        => 'in_progress',
            'labor_cost'    => 200.00,
            'entry_date'    => now()->toDateTimeString(),
            'parts'         => [
                ['id' => $part1->id, 'quantity' => 2],
                ['id' => $part2->id, 'quantity' => 1],
            ],
        ];

        $response = $this->actingAs($this->mechanic)->postJson('/api/maintenances', $payload);

        $response->assertStatus(201)
            ->assertJsonCount(2, 'data.parts')
            // Cálculo do custo: mão de obra (200) + (2 * 50) + (1 * 100) = 200 + 100 + 100 = 400
            ->assertStatus(201);

        $this->assertEquals(200.00, $response->json('data.parts_cost'));
        $this->assertEquals(400.00, $response->json('data.total_cost'));

        // 2. Verifica se a tabela pivot 'maintenance_part' recebeu os registros
        $this->assertDatabaseHas('maintenance_part', [
            'part_id'  => $part1->id,
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('maintenance_part', [
            'part_id'  => $part2->id,
            'quantity' => 1,
        ]);

        // 3. Verifica se o estoque físico foi devidamente deduzido no banco!
        $this->assertEquals(8, $part1->fresh()->stock_quantity); // 10 - 2 = 8
        $this->assertEquals(4, $part2->fresh()->stock_quantity); // 5 - 1 = 4
    }

    /**
     * Testa rejeição quando a quantidade de peças solicitada ultrapassa o estoque disponível.
     */
    public function test_fails_to_create_maintenance_when_stock_is_insufficient(): void
    {
        $part = Part::factory()->create([
            'name'           => 'Embreagem Especial',
            'stock_quantity' => 2,
        ]);

        $payload = [
            'user_id'       => $this->mechanic->id,
            'description'   => 'Tentativa de usar mais peças do que o estoque permite',
            'vehicle_plate' => 'ABC1234',
            'vehicle_model' => 'VW Gol',
            'entry_date'    => now()->toDateTimeString(),
            'parts'         => [
                ['id' => $part->id, 'quantity' => 5], // Pede 5, mas só tem 2!
            ],
        ];

        $response = $this->actingAs($this->mechanic)->postJson('/api/maintenances', $payload);

        // Deve falhar com 422 e apontar erro na chave 'parts'
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['parts']);

        // Garante que o estoque não foi alterado (Rollback da transação)
        $this->assertEquals(2, $part->fresh()->stock_quantity);
    }

    /**
     * Testa visualização individual de manutenção com mecânico e peças carregadas via Eager Loading.
     */
    public function test_can_show_maintenance_details_with_relations(): void
    {
        $maintenance = Maintenance::factory()->create(['user_id' => $this->mechanic->id]);
        $part = Part::factory()->create();
        $maintenance->parts()->attach($part->id, ['quantity' => 3]);

        $response = $this->actingAs($this->mechanic)->getJson("/api/maintenances/{$maintenance->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $maintenance->id)
            ->assertJsonPath('data.mechanic.id', $this->mechanic->id)
            ->assertJsonCount(1, 'data.parts');
    }

    /**
     * Testa exclusão de manutenção e reposição das peças no estoque físico.
     */
    public function test_deleting_maintenance_restores_part_stock(): void
    {
        $part = Part::factory()->create(['stock_quantity' => 10]);

        // Cria a manutenção e consome 3 peças
        $maintenance = Maintenance::factory()->create(['user_id' => $this->mechanic->id]);
        $maintenance->parts()->attach($part->id, ['quantity' => 3]);
        $part->decrement('stock_quantity', 3);
        $this->assertEquals(7, $part->fresh()->stock_quantity);

        // Exclui a manutenção via DELETE
        $response = $this->actingAs($this->mechanic)->deleteJson("/api/maintenances/{$maintenance->id}");

        $response->assertStatus(204);

        // O estoque deve ter voltado para 10!
        $this->assertEquals(10, $part->fresh()->stock_quantity);
        $this->assertDatabaseMissing('maintenances', ['id' => $maintenance->id]);
    }
}
