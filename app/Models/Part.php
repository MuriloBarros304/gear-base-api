<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany; // Importação da classe de relacionamento

class Part extends Model
{
    // O $fillable atua como uma lista de permissões para "Mass Assignment". Evita que usuários injetem colunas extras (como 'is_admin') através do JSON da API.
    protected $fillable = [
        'name',             // string
        'sku',              // string (código único da peça)
        'stock_quantity',   // integer
        'cost_price',       // decimal
        'sale_price'        // decimal
    ];

    // No PHP moderno, é uma excelente prática tipar o retorno dos métodos. Ajuda ferramentas de autocompletar e linters a entenderem o código.
    public function maintenances(): BelongsToMany
    {
        // belongsToMany indica a relação Muitos-para-Muitos.
        // withPivot() diz ao Eloquent para também buscar a coluna 'quantity' da nossa futura tabela intermediária (maintenance_part).
        // withTimestamps() garante que os campos created_at e updated_at da tabela intermediária sejam preenchidos automaticamente.
        return $this->belongsToMany(Maintenance::class)->withPivot('quantity')->withTimestamps();
    }
}
