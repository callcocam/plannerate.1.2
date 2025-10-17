<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Controllers\Api;

use Callcocam\Plannerate\Models\Gondola;
use Callcocam\Plannerate\Models\GondolaZone;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ZoneController extends Controller
{
    /**
     * Lista todas as zonas de uma gôndola
     *
     * @param string $gondolaId
     * @return JsonResponse
     */
    public function index(string $gondolaId): JsonResponse
    {
        try {
            $gondola = Gondola::findOrFail($gondolaId);
            
            $zones = $gondola->zones()->ordered()->get();

            return response()->json([
                'data' => $zones,
                'meta' => [
                    'gondola' => [
                        'id' => $gondola->id,
                        'name' => $gondola->name,
                    ],
                    'total' => $zones->count(),
                ],
                'message' => 'Zonas carregadas com sucesso',
                'status' => 'success',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Gôndola não encontrada',
                'status' => 'error',
            ], 404);
        } catch (Throwable $e) {
            Log::error('Erro ao carregar zonas:', [
                'gondola_id' => $gondolaId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erro ao carregar zonas',
                'status' => 'error',
            ], 500);
        }
    }

    /**
     * Salva múltiplas zonas para uma gôndola (substitui todas as existentes)
     *
     * @param Request $request
     * @param string $gondolaId
     * @return JsonResponse
     */
    public function store(Request $request, string $gondolaId): JsonResponse
    {
        try {
            $gondola = Gondola::findOrFail($gondolaId);

            // Validação
            $validator = Validator::make($request->all(), [
                'zones' => 'required|array|min:1',
                'zones.*.name' => 'required|string|max:255',
                'zones.*.shelf_indexes' => 'required|array|min:1',
                'zones.*.shelf_indexes.*' => 'required|integer|min:0',
                'zones.*.performance_multiplier' => 'nullable|numeric|min:0|max:10',
                'zones.*.rules' => 'nullable|array',
                'zones.*.rules.priority' => 'nullable|string|in:high_margin,low_price,high_rotation,new_products,reference_brand,class_a,class_b,class_c,complementary',
                'zones.*.rules.exposure_type' => 'nullable|string|in:vertical,horizontal,mixed',
                'zones.*.rules.abc_filter' => 'nullable|array',
                'zones.*.rules.abc_filter.*' => 'string|in:A,B,C',
                'zones.*.rules.min_margin_percent' => 'nullable|numeric|min:0|max:100',
                'zones.*.rules.max_margin_percent' => 'nullable|numeric|min:0|max:100',
                'zones.*.rules.reference_brands' => 'nullable|array',
                'zones.*.rules.reference_brands.*' => 'string',
                'zones.*.rules.customer_flow_weight' => 'nullable|numeric|min:0|max:2',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors(),
                    'status' => 'error',
                ], 422);
            }

            DB::beginTransaction();

            try {
                // Remover todas as zonas existentes
                $gondola->zones()->delete();

                // Criar novas zonas
                $createdZones = collect($request->input('zones'))->map(function ($zoneData, $index) use ($gondola) {
                    return $gondola->zones()->create([
                        'name' => $zoneData['name'],
                        'shelf_indexes' => $zoneData['shelf_indexes'],
                        'performance_multiplier' => $zoneData['performance_multiplier'] ?? 1.0,
                        'rules' => $zoneData['rules'] ?? [],
                        'order' => $index,
                    ]);
                });

                DB::commit();

                Log::info('Zonas salvas com sucesso:', [
                    'gondola_id' => $gondolaId,
                    'zones_count' => $createdZones->count(),
                ]);

                return response()->json([
                    'data' => $createdZones,
                    'message' => 'Zonas salvas com sucesso',
                    'status' => 'success',
                ], 201);

            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Gôndola não encontrada',
                'status' => 'error',
            ], 404);
        } catch (Throwable $e) {
            Log::error('Erro ao salvar zonas:', [
                'gondola_id' => $gondolaId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erro ao salvar zonas',
                'error' => $e->getMessage(),
                'status' => 'error',
            ], 500);
        }
    }

    /**
     * Atualiza uma zona específica
     *
     * @param Request $request
     * @param string $gondolaId
     * @param string $zoneId
     * @return JsonResponse
     */
    public function update(Request $request, string $gondolaId, string $zoneId): JsonResponse
    {
        try {
            $gondola = Gondola::findOrFail($gondolaId);
            $zone = $gondola->zones()->findOrFail($zoneId);

            // Validação
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'shelf_indexes' => 'sometimes|array|min:1',
                'shelf_indexes.*' => 'integer|min:0',
                'performance_multiplier' => 'sometimes|numeric|min:0|max:10',
                'rules' => 'sometimes|array',
                'rules.priority' => 'nullable|string|in:high_margin,low_price,high_rotation,new_products,reference_brand,class_a,class_b,class_c,complementary',
                'rules.exposure_type' => 'nullable|string|in:vertical,horizontal,mixed',
                'rules.abc_filter' => 'nullable|array',
                'rules.abc_filter.*' => 'string|in:A,B,C',
                'rules.min_margin_percent' => 'nullable|numeric|min:0|max:100',
                'rules.max_margin_percent' => 'nullable|numeric|min:0|max:100',
                'rules.reference_brands' => 'nullable|array',
                'rules.reference_brands.*' => 'string',
                'rules.customer_flow_weight' => 'nullable|numeric|min:0|max:2',
                'order' => 'sometimes|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors(),
                    'status' => 'error',
                ], 422);
            }

            $zone->update($request->only([
                'name',
                'shelf_indexes',
                'performance_multiplier',
                'rules',
                'order',
            ]));

            Log::info('Zona atualizada com sucesso:', [
                'gondola_id' => $gondolaId,
                'zone_id' => $zoneId,
            ]);

            return response()->json([
                'data' => $zone->fresh(),
                'message' => 'Zona atualizada com sucesso',
                'status' => 'success',
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Gôndola ou zona não encontrada',
                'status' => 'error',
            ], 404);
        } catch (Throwable $e) {
            Log::error('Erro ao atualizar zona:', [
                'gondola_id' => $gondolaId,
                'zone_id' => $zoneId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erro ao atualizar zona',
                'error' => $e->getMessage(),
                'status' => 'error',
            ], 500);
        }
    }

    /**
     * Remove uma zona específica
     *
     * @param string $gondolaId
     * @param string $zoneId
     * @return JsonResponse
     */
    public function destroy(string $gondolaId, string $zoneId): JsonResponse
    {
        try {
            $gondola = Gondola::findOrFail($gondolaId);
            $zone = $gondola->zones()->findOrFail($zoneId);

            $zone->delete();

            Log::info('Zona removida com sucesso:', [
                'gondola_id' => $gondolaId,
                'zone_id' => $zoneId,
            ]);

            return response()->json([
                'message' => 'Zona removida com sucesso',
                'status' => 'success',
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Gôndola ou zona não encontrada',
                'status' => 'error',
            ], 404);
        } catch (Throwable $e) {
            Log::error('Erro ao remover zona:', [
                'gondola_id' => $gondolaId,
                'zone_id' => $zoneId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erro ao remover zona',
                'error' => $e->getMessage(),
                'status' => 'error',
            ], 500);
        }
    }

    /**
     * Remove todas as zonas de uma gôndola
     *
     * @param string $gondolaId
     * @return JsonResponse
     */
    public function destroyAll(string $gondolaId): JsonResponse
    {
        try {
            $gondola = Gondola::findOrFail($gondolaId);
            
            $deletedCount = $gondola->zones()->delete();

            Log::info('Todas as zonas removidas:', [
                'gondola_id' => $gondolaId,
                'deleted_count' => $deletedCount,
            ]);

            return response()->json([
                'message' => "Todas as {$deletedCount} zonas foram removidas com sucesso",
                'status' => 'success',
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Gôndola não encontrada',
                'status' => 'error',
            ], 404);
        } catch (Throwable $e) {
            Log::error('Erro ao remover todas as zonas:', [
                'gondola_id' => $gondolaId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erro ao remover zonas',
                'error' => $e->getMessage(),
                'status' => 'error',
            ], 500);
        }
    }
}

