<?php

namespace App\Models;

use Database\Factories\MaintenanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Model Maintenance (Ordem de Serviço / Manutenção)
 *
 * Representa uma manutenção veicular executada por um mecânico responsável (User),
 * associada a múltiplos itens de reposição consumidos (Part) na tabela pivot 'maintenance_part'.
 */
class Maintenance extends Model
{
    /** @use HasFactory<MaintenanceFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',          // foreign id: Mecânico responsável cadastrado
        'description',      // text: Diagnóstico e serviços executados
        'vehicle_plate',    // string: Placa do automóvel atendido
        'vehicle_model',    // string: Marca / Modelo do veículo
        'status',           // string: 'pending', 'in_progress', 'completed', 'cancelled'
        'labor_cost',       // decimal: Valor cobrado pela mão de obra do serviço
        'entry_date',       // datetime: Data/hora de entrada do veículo na oficina
        'delivery_date',    // datetime|null: Data/hora da entrega final ao cliente
    ];

    /**
     * O método casts() intercepta as colunas vindas do banco e as converte para tipos nativos PHP:
     * - 'entry_date' e 'delivery_date' viram instâncias do Carbon (manipulador de datas do PHP).
     * - 'labor_cost' garante formatação decimal consistente de 2 casas.
     */
    protected function casts(): array
    {
        return [
            'entry_date'    => 'datetime',
            'delivery_date' => 'datetime',
            'labor_cost'    => 'decimal:2',
        ];
    }

    /**
     * Relacionamento Muitos-para-Um (N:1):
     * A ordem de serviço "Pertence A" um Usuário/Mecânico.
     * O Eloquent infere a chave estrangeira como 'user_id' com base no nome do método 'user'.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relacionamento Muitos-para-Muitos (N:N) com as Peças:
     * Uma manutenção utiliza diversas peças, com suas respectivas quantidades gravadas
     * na tabela pivot 'maintenance_part'.
     *
     * @return BelongsToMany
     */
    public function parts(): BelongsToMany
    {
        return $this->belongsToMany(Part::class)
            ->withPivot('quantity')
            ->withTimestamps();
    }
}
