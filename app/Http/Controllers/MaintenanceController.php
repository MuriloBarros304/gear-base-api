<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaintenanceRequest;
use App\Http\Requests\UpdateMaintenanceRequest;
use App\Http\Resources\MaintenanceResource;
use App\Models\Maintenance;
use App\Models\Part;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Controller de Gestão de Ordens de Manutenção (MaintenanceController)
 *
 * Este controller é o coração das regras de negócio do sistema GearBase.
 * Ele exemplifica padrões avançados de desenvolvimento backend em Laravel:
 *
 * 1. Eager Loading (Carregamento Ansioso):
 *    Uso de ->with(['user', 'parts']) para eliminar o infame problema de "N+1 Queries".
 *
 * 2. Transações de Banco de Dados Atômicas (DB::transaction):
 *    Garante as propriedades ACID (Atomicidade, Consistência, Isolamento e Durabilidade).
 *    Ao cadastrar ou atualizar uma ordem com peças, se o estoque for insuficiente ou
 *    ocorrer qualquer falha inesperada, NENHUMA alteração é persistida (Rollback automático).
 *
 * 3. Gerenciamento de Estoque Concorrente:
 *    Baixa e reposição automática de peças na tabela 'parts' vinculadas pela tabela pivot 'maintenance_part'.
 */
class MaintenanceController extends Controller
{
    /**
     * Lista todas as ordens de manutenção com paginação e relacionamentos carregados.
     *
     * Método: GET /api/maintenances
     * Status HTTP retornado: 200 OK
     *
     * Por que usar with(['user', 'parts']) aqui?
     * Se tivéssemos 10 manutenções e chamássemos `$maintenance->user` e `$maintenance->parts` no Resource
     * sem o eager loading, o Laravel executaria 1 query inicial + (10 * 2 queries para cada item) = 21 queries SQL!
     * Com `with(['user', 'parts'])`, o Laravel faz exatamente 3 queries independentemente do número de itens:
     * 1. SELECT * FROM maintenances LIMIT 10;
     * 2. SELECT * FROM users WHERE id IN (...);
     * 3. SELECT * FROM parts INNER JOIN maintenance_part ... WHERE maintenance_id IN (...);
     */
    public function index(): AnonymousResourceCollection
    {
        $maintenances = Maintenance::with(['user', 'parts'])
            ->latest()
            ->paginate(10);

        return MaintenanceResource::collection($maintenances);
    }

    /**
     * Registra uma nova ordem de serviço e realiza a dedução atômica das peças no estoque.
     *
     * Método: POST /api/maintenances
     * Status HTTP retornado: 201 Created (ou 422 se faltar estoque)
     */
    public function store(StoreMaintenanceRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $partsData = $validated['parts'] ?? [];

        // Remove a chave 'parts' antes de criar a manutenção, pois 'parts' não é coluna da tabela 'maintenances'
        unset($validated['parts']);

        // Executamos toda a operação dentro de uma Transação de Banco de Dados (DB::transaction)
        $maintenance = DB::transaction(function () use ($validated, $partsData) {
            // 1. Cria a ordem de manutenção básica
            $maintenance = Maintenance::create($validated);

            // 2. Processa o vínculo de peças e baixa de estoque se houver peças informadas
            if (!empty($partsData)) {
                $syncPayload = [];

                foreach ($partsData as $item) {
                    $partId = $item['id'];
                    $quantityRequested = (int) $item['quantity'];

                    // Busca a peça para verificar saldo físico em estoque
                    $part = Part::findOrFail($partId);

                    // Validação de regra de negócio: Estoque suficiente
                    if ($part->stock_quantity < $quantityRequested) {
                        throw ValidationException::withMessages([
                            'parts' => [
                                "Estoque insuficiente para a peça '{$part->name}'. " .
                                "Disponível: {$part->stock_quantity}, Solicitado: {$quantityRequested}."
                            ],
                        ]);
                    }

                    // Decrementa o estoque da peça no banco
                    $part->decrement('stock_quantity', $quantityRequested);

                    // Monta o array para o Eloquent preencher a tabela pivot 'maintenance_part'
                    // Formato esperado pelo attach/sync: [ id_da_peca => [ 'coluna_pivot' => valor ] ]
                    $syncPayload[$partId] = ['quantity' => $quantityRequested];
                }

                // Salva os registros na tabela intermediária (maintenance_part) com timestamps automáticos
                $maintenance->parts()->attach($syncPayload);
            }

            return $maintenance;
        });

        // Carrega os relacionamentos recém-criados para compor o JSON de resposta
        $maintenance->load(['user', 'parts']);

        return (new MaintenanceResource($maintenance))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Exibe os detalhes completos de uma ordem de manutenção.
     *
     * Método: GET /api/maintenances/{maintenance}
     * Status HTTP retornado: 200 OK
     */
    public function show(Maintenance $maintenance): MaintenanceResource
    {
        // load() executa Eager Loading sobre uma instância do Model que já foi resolvida
        $maintenance->load(['user', 'parts']);

        return new MaintenanceResource($maintenance);
    }

    /**
     * Atualiza os dados de uma manutenção e sincroniza o estoque das peças caso tenham sido alteradas.
     *
     * Método: PUT/PATCH /api/maintenances/{maintenance}
     * Status HTTP retornado: 200 OK
     */
    public function update(UpdateMaintenanceRequest $request, Maintenance $maintenance): MaintenanceResource
    {
        $validated = $request->validated();
        $hasPartsUpdate = array_key_exists('parts', $validated);
        $newParts = $validated['parts'] ?? [];

        unset($validated['parts']);

        DB::transaction(function () use ($validated, $hasPartsUpdate, $newParts, $maintenance) {
            // 1. Atualiza os dados cadastrais da manutenção (status, mecânico, placa, etc.)
            $maintenance->update($validated);

            // 2. Se a requisição incluiu a chave 'parts', sincronizamos as peças e recalculamos o estoque
            if ($hasPartsUpdate) {
                // Carrega as peças que estavam associadas anteriormente para repor o estoque antigo
                $currentParts = $maintenance->parts()->get();

                // Repõe o estoque que havia sido consumido por esta manutenção
                foreach ($currentParts as $oldPart) {
                    $oldQuantity = (int) $oldPart->pivot->quantity;
                    $oldPart->increment('stock_quantity', $oldQuantity);
                }

                // Agora valida e dá baixa no novo lote de peças solicitado
                $syncPayload = [];
                if (!empty($newParts)) {
                    foreach ($newParts as $item) {
                        $partId = $item['id'];
                        $quantityRequested = (int) $item['quantity'];

                        $part = Part::findOrFail($partId);

                        if ($part->stock_quantity < $quantityRequested) {
                            throw ValidationException::withMessages([
                                'parts' => [
                                    "Estoque insuficiente para a peça '{$part->name}'. " .
                                    "Disponível: {$part->stock_quantity}, Solicitado: {$quantityRequested}."
                                ],
                            ]);
                        }

                        $part->decrement('stock_quantity', $quantityRequested);
                        $syncPayload[$partId] = ['quantity' => $quantityRequested];
                    }
                }

                // sync() do Eloquent: Remove as peças antigas do pivot e insere as novas com seus atributos adicionais
                $maintenance->parts()->sync($syncPayload);
            }
        });

        // Recarrega as relações atualizadas
        $maintenance->load(['user', 'parts']);

        return new MaintenanceResource($maintenance);
    }

    /**
     * Exclui uma ordem de manutenção e devolve as peças consumidas ao estoque físico.
     *
     * Método: DELETE /api/maintenances/{maintenance}
     * Status HTTP retornado: 204 No Content
     */
    public function destroy(Maintenance $maintenance): Response
    {
        DB::transaction(function () use ($maintenance) {
            // Antes de excluir, repõe o estoque de todas as peças associadas a esta manutenção
            foreach ($maintenance->parts as $part) {
                $part->increment('stock_quantity', (int) $part->pivot->quantity);
            }

            // Ao excluir a manutenção, as chaves estrangeiras com onDelete('cascade')
            // na tabela maintenance_part limpam automaticamente o relacionamento pivot
            $maintenance->delete();
        });

        return response()->noContent();
    }
}
