<?php

namespace App\Models;

use Database\Factories\ToolFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model Tool (Ferramenta da Oficina)
 *
 * Registra ferramentas operacionais (torquímetros, chaves, scanners de injeção, etc.)
 * e seu status de disponibilidade.
 */
class Tool extends Model
{
    /** @use HasFactory<ToolFactory> */
    use HasFactory;

    /**
     * Campos liberados para atribuição em massa.
     */
    protected $fillable = [
        'name',             // string: Nome descritivo do ferramental
        'serial_number',    // string|null: Número de patrimônio ou série do fabricante
        'status',           // string: 'available' (disponível), 'in_use' (em uso), 'maintenance' (em manutenção)
    ];
}
