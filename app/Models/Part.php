<?php

namespace App\Models;

use Database\Factories\PartFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Model Part (Peça Automotiva de Reposição)
 *
 * Representa os itens em estoque utilizados nos reparos (ex: filtros, velas, pastilhas).
 */
class Part extends Model
{
    /** @use HasFactory<PartFactory> */
    use HasFactory;

    /**
     * O $fillable atua como uma lista de permissões para "Mass Assignment".
     * Evita que usuários injetem colunas extras através do JSON da API.
     */
    protected $fillable = [
        'name',             // string: Nome da peça
        'sku',              // string: Código único da peça para controle de estoque
        'stock_quantity',   // integer: Quantidade física disponível na oficina
        'cost_price',       // decimal: Custo de compra da oficina
        'sale_price',       // decimal: Preço cobrado do cliente final
    ];

    /**
     * Conversão de tipos de dados ao recuperar do banco de dados.
     */
    protected function casts(): array
    {
        return [
            'stock_quantity' => 'integer',
            'cost_price'     => 'decimal:2',
            'sale_price'     => 'decimal:2',
        ];
    }

    /**
     * Relacionamento Muitos-para-Muitos (N:N) com Manutenções:
     * Uma peça pode ser utilizada em várias ordens de manutenção, e uma manutenção
     * pode utilizar diversas peças diferentes.
     *
     * - withPivot('quantity'): Inclui a coluna extra 'quantity' da tabela pivot 'maintenance_part'.
     * - withTimestamps(): Garante que created_at e updated_at da tabela pivot sejam mantidos pelo Eloquent.
     *
     * @return BelongsToMany
     */
    public function maintenances(): BelongsToMany
    {
        return $this->belongsToMany(Maintenance::class)
            ->withPivot('quantity')
            ->withTimestamps();
    }
}
