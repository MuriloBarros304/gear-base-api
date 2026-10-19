<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Controller de Gestão de Usuários / Mecânicos (REST CRUD)
 *
 * Responsável pelas operações padrão de CRUD da entidade User:
 * - GET    /api/users       -> index()   (Listagem paginada)
 * - POST   /api/users       -> store()   (Criação de novo usuário)
 * - GET    /api/users/{id}  -> show()    (Exibição de registro específico)
 * - PUT    /api/users/{id}  -> update()  (Atualização cadastral)
 * - DELETE /api/users/{id}  -> destroy() (Remoção do usuário)
 */
class UserController extends Controller
{
    /**
     * Lista os usuários com paginação de dados.
     *
     * GET /api/users
     *
     * Por que paginar em vez de usar User::all()?
     * Em aplicações reais, trazer todos os registros (::all()) causa consumo excessivo
     * de memória (Memory Exhaustion) e queries lentas conforme a tabela cresce.
     * O método ->paginate(10) executa "LIMIT 10 OFFSET X" no SQL e gera metadados de página
     * (total de registros, página atual, última página, links) de forma automática.
     */
    public function index(): AnonymousResourceCollection
    {
        $users = User::latest()->paginate(10);

        // UserResource::collection() empacota a paginação mantendo a estrutura 'data', 'links' e 'meta'
        return UserResource::collection($users);
    }

    /**
     * Salva um novo usuário/mecânico no sistema.
     *
     * POST /api/users
     *
     * Código HTTP 201 (Created):
     * De acordo com as boas práticas REST, ao criar um novo recurso no servidor,
     * a resposta não deve ser 200 OK, mas sim 201 Created, preferencialmente
     * acompanhada da representação do recurso recém-criado.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Exibe os detalhes de um usuário específico.
     *
     * GET /api/users/{user}
     *
     * Route Model Binding:
     * Note que tipamos o parâmetro como `User $user` em vez de receber um `$id` numérico!
     * O Laravel automaticamente busca: User::where('id', $id)->firstOrFail().
     * Se o ID não existir, ele lança uma ModelNotFoundException que é convertida
     * automaticamente para uma resposta HTTP 404 Not Found em formato JSON.
     */
    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    /**
     * Atualiza os dados de um usuário existente.
     *
     * PUT/PATCH /api/users/{user}
     *
     * PUT: Semântica de substituição completa do recurso.
     * PATCH: Semântica de modificação parcial de atributos específicos.
     * O Form Request UpdateUserRequest com regras 'sometimes' suporta ambos com excelência.
     */
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        // $request->validated() filtra apenas chaves enviadas e validadas
        $validatedData = $request->validated();

        // Se o campo password foi enviado como nulo ou vazio, não sobrescrevemos a senha existente
        if (array_key_exists('password', $validatedData) && empty($validatedData['password'])) {
            unset($validatedData['password']);
        }

        $user->update($validatedData);

        return new UserResource($user);
    }

    /**
     * Exclui um usuário do sistema.
     *
     * DELETE /api/users/{user}
     *
     * Código HTTP 204 (No Content):
     * A convenção REST preconiza que, após uma exclusão com sucesso onde nenhum conteúdo
     * precisa ser devolvido no corpo da mensagem, a API deve responder com status 204 No Content.
     */
    public function destroy(User $user): Response
    {
        $user->delete();

        return response()->noContent(); // Retorna resposta vazia com status 204
    }
}
