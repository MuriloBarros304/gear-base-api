<?php

namespace App\Models;

// Importações fundamentais do ecossistema Laravel:
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Model User (Mecânico / Operador do sistema)
 *
 * Herda de 'Authenticatable' (Illuminate\Foundation\Auth\User) em vez de Model comum.
 * Isso fornece ao User todas as funcionalidades nativas de autenticação do Laravel,
 * como verificação de credenciais, redefinição de senhas e integração com guards de segurança.
 */
class User extends Authenticatable
{
    /**
     * Trait HasApiTokens (Laravel Sanctum):
     * Adiciona métodos para criar, listar e revogar tokens de acesso pessoal (Personal Access Tokens).
     * Exemplo: $token = $user->createToken('token-name')->plainTextToken;
     *
     * Trait HasFactory:
     * Habilita o uso de factories para geração de dados falsos e testes automatizados (User::factory()).
     *
     * Trait Notifiable:
     * Permite que o modelo receba notificações via e-mail, SMS, banco de dados ou outros canais.
     */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Mass Assignment Protection ($fillable):
     * Lista branca de atributos que podem ser atribuídos em massa através de métodos como User::create()
     * ou $user->update(). Impede que usuários mal-intencionados injetem campos sensíveis (como 'is_admin')
     * diretamente no corpo da requisição HTTP (Request Injection).
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * Atributos Ocultos ($hidden):
     * Campos que NUNCA devem aparecer quando o modelo for convertido para Array ou JSON na resposta da API.
     * Crucial para a segurança: nunca exponha hashes de senhas ou tokens de "lembrar-me" em respostas REST.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casts de Atributos:
     * O método casts() intercepta o valor do atributo na leitura e na escrita:
     * - 'email_verified_at' => 'datetime': Converte a string do banco em uma instância Carbon.
     * - 'password' => 'hashed': No Laravel 10+, faz o hash automático com Bcrypt sempre que uma nova senha é atribuída!
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    /**
     * Relacionamento Um-para-Muitos (1:N):
     * Um mecânico/usuário pode ser responsável por várias ordens de manutenção.
     *
     * Ao chamar $user->maintenances, o Eloquent executa automaticamente:
     * "SELECT * FROM maintenances WHERE user_id = ?"
     *
     * @return HasMany
     */
    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }
}
