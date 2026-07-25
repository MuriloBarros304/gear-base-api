<?php

namespace App\Http\Controllers;

use App\Models\Part;
use Illuminate\Http\Request;

class PartController extends Controller
{
    /**
     * Lista todas as peças (Método GET /api/parts)
     */
    public function index()
    {
        // O Eloquent busca todos os registros e o Laravel converte automaticamente para JSON
        return Part::all();
    }

    /**
     * Salva uma nova peça (POST /api/parts)
     */
    public function store(Request $request)
    {
        // 1. O framework intercepta a requisição aqui.
        // Se alguma regra falhar, ele nem continua lendo o código. Retorna um erro automático.
        $validatedData = $request->validate([
            'name'           => 'required|string|max:255',
            'sku'            => 'required|string|unique:parts,sku', // Verifica sozinho se o SKU já existe na tabela 'parts'!
            'stock_quantity' => 'required|integer|min:0',
            'cost_price'     => 'nullable|numeric|min:0',
            'sale_price'     => 'required|numeric|min:0',
        ]);

        // 2. Se passou, criamos a peça usando APENAS os dados validados e seguros.
        $part = Part::create($validatedData);

        return response()->json($part, 201); // 201 = Created
    }

    /**
     * Exibe uma peça específica (GET /api/parts/{id})
     */
    public function show(Part $part)
    {
        return response()->json($part);
    }

    /**
     * Atualiza uma peça existente (PUT/PATCH /api/parts/{id})
     */
    public function update(Request $request, Part $part)
    {
        $validatedData = $request->validate([
            'name'           => 'sometimes|required|string|max:255',
            // O código abaixo diz: o SKU deve ser único na tabela parts, EXCETO para o ID desta própria peça
            'sku'            => 'sometimes|required|string|unique:parts,sku,' . $part->id,
            'stock_quantity' => 'sometimes|required|integer|min:0',
            'cost_price'     => 'nullable|numeric|min:0',
            'sale_price'     => 'sometimes|required|numeric|min:0',
        ]);

        // A regra 'sometimes' indica que o campo só será validado se ele for enviado na requisição.
        // Isso permite atualizações parciais (PATCH), onde o cliente envia apenas o que mudou.

        // Atualiza os dados na memória e salva no banco
        $part->update($validatedData);

        return response()->json($part);
    }

    /**
     * Remove uma peça do banco de dados (DELETE /api/parts/{id})
     */
    public function destroy(Part $part)
    {
        $part->delete();

        return response()->json(null, 204);
    }
}
