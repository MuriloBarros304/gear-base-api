<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePartRequest;
use App\Http\Requests\UpdatePartRequest;
use App\Http\Resources\PartResource;
use App\Models\Part;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Controller de Gestão de Peças de Reposição (PartController)
 *
 * Implementa o padrão de arquitetura REST com separação de responsabilidades:
 * - Validação de Entrada: Form Requests (StorePartRequest / UpdatePartRequest)
 * - Persistência e Regras de Negócio: Eloquent ORM (App\Models\Part)
 * - Transformação e Resposta: API Resources (App\Http\Resources\PartResource)
 */
class PartController extends Controller
{
    /**
     * Lista todas as peças com suporte a paginação de registros.
     *
     * Método: GET /api/parts
     * Status HTTP retornado: 200 OK
     *
     * Vantagem da Paginação em APIs REST:
     * Retorna metadados de paginação padronizados pelo Laravel (data, links, meta),
     * permitindo que aplicações front-end (React, Vue, mobile) criem tabelas paginadas
     * sem sobrecarregar a memória do servidor nem o tráfego de rede.
     */
    public function index(): AnonymousResourceCollection
    {
        // latest() ordena por created_at DESC (as peças mais recentes primeiro)
        $parts = Part::latest()->paginate(10);

        return PartResource::collection($parts);
    }

    /**
     * Cadastra uma nova peça no estoque da oficina.
     *
     * Método: POST /api/parts
     * Status HTTP retornado: 201 Created
     *
     * Fluxo de Execução:
     * 1. A requisição HTTP é interceptada pelo StorePartRequest antes de entrar neste método.
     * 2. Se a validação falhar (ex: SKU duplicado ou preço negativo), a aplicação responde
     *    imediatamente com status 422 e mensagens de erro, sem executar nenhuma linha abaixo.
     * 3. $request->validated() retorna um array seguro e sanitizado.
     * 4. Part::create() realiza a persistência no SQLite respeitando a lista $fillable do Model.
     * 5. O resultado é transformado pelo PartResource e retornado com status 201.
     */
    public function store(StorePartRequest $request): JsonResponse
    {
        $part = Part::create($request->validated());

        return (new PartResource($part))
            ->response()
            ->setStatusCode(201); // 201 = Recurso criado com sucesso no servidor
    }

    /**
     * Exibe os detalhes de uma peça específica pelo seu ID.
     *
     * Método: GET /api/parts/{part}
     * Status HTTP retornado: 200 OK (ou 404 Not Found se o ID não existir)
     *
     * Route Model Binding:
     * O Laravel faz a injeção automática de dependência da Model `Part`.
     * Se você requisitar /api/parts/999 e a peça 999 não existir,
     * o framework gera automaticamente uma resposta 404 formatada em JSON.
     */
    public function show(Part $part): PartResource
    {
        return new PartResource($part);
    }

    /**
     * Atualiza os dados de uma peça existente.
     *
     * Método: PUT/PATCH /api/parts/{part}
     * Status HTTP retornado: 200 OK
     *
     * A validação em UpdatePartRequest utiliza a regra 'sometimes', o que significa que
     * se o cliente enviar apenas `{"sale_price": 89.90}` (método PATCH), somente o preço
     * será validado e atualizado, mantendo os demais dados da peça inalterados.
     */
    public function update(UpdatePartRequest $request, Part $part): PartResource
    {
        $part->update($request->validated());

        return new PartResource($part);
    }

    /**
     * Remove uma peça do catálogo do banco de dados.
     *
     * Método: DELETE /api/parts/{part}
     * Status HTTP retornado: 204 No Content
     *
     * Boas práticas REST:
     * Quando um recurso é deletado com sucesso e não há conteúdo a ser devolvido no corpo,
     * o status HTTP correto é 204 (No Content), com corpo de resposta vazio.
     */
    public function destroy(Part $part): Response
    {
        $part->delete();

        return response()->noContent();
    }
}
