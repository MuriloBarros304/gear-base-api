<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Controller de Autenticação (Laravel Sanctum)
 *
 * REST APIs modernas são por definição "Stateless" (sem estado de sessão em cookies PHP no servidor).
 * O Laravel Sanctum resolve isso emitindo tokens criptografados (Personal Access Tokens).
 *
 * Fluxo de Autenticação Bearer Token:
 * 1. O cliente envia e-mail e senha para /api/login via POST.
 * 2. Se as credenciais estiverem corretas, o servidor gera uma string de token segura e a retorna.
 * 3. Em todas as requisições subsequentes para rotas protegidas, o cliente envia o cabeçalho HTTP:
 *    Authorization: Bearer <seu-token-aqui>
 * 4. O middleware 'auth:sanctum' intercepta o cabeçalho, localiza o token no banco SQLite
 *    (tabela personal_access_tokens), identifica a qual usuário pertence e autentica a requisição.
 */
class AuthController extends Controller
{
    /**
     * Registra um novo usuário no sistema e já retorna seu token de acesso inicial.
     *
     * Método: POST /api/register
     * Status HTTP retornado: 201 Created
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        // $request->validated() retorna APENAS os dados que passaram com sucesso pelas regras
        // definidas em RegisterRequest. Isso evita qualquer injeção de campos extras.
        $validated = $request->validated();

        // O hash da senha é feito automaticamente pelo cast 'password' => 'hashed' na Model User!
        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => $validated['password'],
        ]);

        // Emite o primeiro Bearer Token do usuário recém-criado
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'      => 'Usuário registrado com sucesso!',
            'user'         => new UserResource($user),
            'access_token' => $token,
            'token_type'   => 'Bearer',
        ], 201);
    }

    /**
     * Autentica um usuário existente através de e-mail e senha.
     *
     * Método: POST /api/login
     * Status HTTP retornado: 200 OK (ou 422 em caso de falha de credenciais)
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        // 1. Busca o usuário correspondente ao e-mail fornecido
        $user = User::where('email', $credentials['email'])->first();

        // 2. Compara a senha digitada com o hash Bcrypt armazenado no banco
        // Hash::check() usa tempo constante para evitar ataques de temporização (Timing Attacks)
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['As credenciais fornecidas estão incorretas.'],
            ]);
        }

        // 3. Credenciais válidas! Criamos um novo token Sanctum para esta sessão/dispositivo
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'      => 'Login realizado com sucesso!',
            'user'         => new UserResource($user),
            'access_token' => $token,
            'token_type'   => 'Bearer',
        ], 200);
    }

    /**
     * Encerra a sessão revogando (excluindo) o token atual da requisição.
     *
     * Método: POST /api/logout
     * Requer cabeçalho: Authorization: Bearer <token>
     * Status HTTP retornado: 200 OK
     */
    public function logout(Request $request): JsonResponse
    {
        // $request->user()->currentAccessToken() busca o registro na tabela personal_access_tokens
        // correspondente ao token enviado no cabeçalho Authorization desta requisição.
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Token revogado com sucesso. Logout efetuado.',
        ], 200);
    }

    /**
     * Retorna os dados do usuário atualmente autenticado.
     *
     * Método: GET /api/me
     * Status HTTP retornado: 200 OK
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ], 200);
    }
}
