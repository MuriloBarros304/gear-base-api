<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreToolRequest;
use App\Http\Requests\UpdateToolRequest;
use App\Http\Resources\ToolResource;
use App\Models\Tool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Controller de Gestão do Ferramental da Oficina (ToolController)
 *
 * Controla os registros de ferramentas utilizadas pelos mecânicos, permitindo
 * rastreamento de números de série e controle de disponibilidade operacional
 * ('available', 'in_use', 'maintenance').
 */
class ToolController extends Controller
{
    /**
     * Lista todas as ferramentas cadastradas com paginação.
     *
     * Método: GET /api/tools
     * Status HTTP retornado: 200 OK
     */
    public function index(): AnonymousResourceCollection
    {
        $tools = Tool::latest()->paginate(10);

        return ToolResource::collection($tools);
    }

    /**
     * Cadastra uma nova ferramenta no inventário.
     *
     * Método: POST /api/tools
     * Status HTTP retornado: 201 Created
     */
    public function store(StoreToolRequest $request): JsonResponse
    {
        $tool = Tool::create($request->validated());

        return (new ToolResource($tool))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Exibe os detalhes de uma ferramenta específica.
     *
     * Método: GET /api/tools/{tool}
     * Status HTTP retornado: 200 OK
     *
     * Injeção de Dependência e Route Model Binding:
     * O Laravel resolve o parâmetro da URL {tool} automaticamente para a instância do Model Tool.
     */
    public function show(Tool $tool): ToolResource
    {
        return new ToolResource($tool);
    }

    /**
     * Atualiza os dados ou status de uma ferramenta existente.
     *
     * Método: PUT/PATCH /api/tools/{tool}
     * Status HTTP retornado: 200 OK
     *
     * Exemplo de uso: Alterar o status de uma chave de impacto para 'maintenance'
     * enviando apenas: {"status": "maintenance"} via PATCH.
     */
    public function update(UpdateToolRequest $request, Tool $tool): ToolResource
    {
        $tool->update($request->validated());

        return new ToolResource($tool);
    }

    /**
     * Remove uma ferramenta do inventário.
     *
     * Método: DELETE /api/tools/{tool}
     * Status HTTP retornado: 204 No Content
     */
    public function destroy(Tool $tool): Response
    {
        $tool->delete();

        return response()->noContent();
    }
}
