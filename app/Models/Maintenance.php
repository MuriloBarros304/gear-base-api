<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Maintenance extends Model
{
    protected $fillable = [
        'user_id',          // foreign id (Referência ao mecânico responsável)
        'description',      // text
        'vehicle_plate',    // string
        'vehicle_model',    // string
        'status',           // string (ex: 'pending', 'in_progress', 'completed')
        'labor_cost',       // decimal (Custo apenas da mão de obra)
        'entry_date',       // datetime
        'delivery_date'     // datetime
    ];

    // O método casts() é poderoso. Ele intercepta o dado quando sai do banco e converte para o tipo nativo do PHP.
    protected function casts(): array
    {
        return [
            'entry_date' => 'datetime',    // Converte automaticamente para um objeto Carbon (manipulador de datas nativo do ecossistema)
            'delivery_date' => 'datetime',
            'labor_cost' => 'decimal:2',   // Garante que retorne sempre com 2 casas decimais no JSON
        ];
    }

    // Uma Manutenção "Pertence A" um Usuário (o mecânico). O framework entende sozinho que a chave na tabela é 'user_id' por causa do nome do método (user).
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // O outro lado da relação Muitos-para-Muitos das peças.
    public function parts(): BelongsToMany
    {
        return $this->belongsToMany(Part::class)->withPivot('quantity')->withTimestamps();
    }
}
