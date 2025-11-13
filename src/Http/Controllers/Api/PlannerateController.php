<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Callcocam\Plannerate\Http\Resources\PlannerateResource;
use Callcocam\Plannerate\Jobs\SavePlanogramMetadataJob;
use Callcocam\Plannerate\Jobs\SaveGondolaJob;
use Callcocam\Plannerate\Jobs\SaveSectionJob;
use Callcocam\Plannerate\Jobs\SaveShelfJob;
use Callcocam\Plannerate\Models\Planogram;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class PlannerateController extends Controller
{


    public function index(Request $request): JsonResponse
    {
        try {
            $planograms = Planogram::query()
                ->with(['tenant:id,name', 'gondolas']) 
                ->when($request->has('store_id'), function ($query) use ($request) {
                    $query->where('store_id', $request->get('store_id'));
                })
                ->when($request->has('cluster_id'), function ($query) use ($request) {
                    $query->where('cluster_id', $request->get('cluster_id'));
                })
                ->when($request->has('client_id'), function ($query) use ($request) {
                    $query->where('client_id', $request->get('client_id'));
                })
                ->get();

            return response()->json(PlannerateResource::collection($planograms));
        } catch (Throwable $e) {
            Log::error('Erro ao listar planogramas', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }
    /**
     * Exibe um planograma específico
     * 
     * @param Planogram $planogram
     * @return PlannerateResource|JsonResponse
     */
    public function show(Request $request, string $id)
    {
        try {
            // OTIMIZAÇÃO: Eager loading seletivo - remove relacionamentos pesados (sales, purchases)
            // e carrega apenas campos essenciais
            $planogram = $this->getModel()::query()->with([
                'tenant:id,name',
                'store.store_map.gondolas',
                'cluster:id,name',
                'client:id,name',
                'gondolas',
                'gondolas.sections',
                'gondolas.sections.shelves',
                'gondolas.sections.shelves.segments',
                'gondolas.sections.shelves.segments.layer',
                'gondolas.sections.shelves.segments.layer.product:id,name,ean,description,url'
            ])->findOrFail($id);


            // $planogram->load([
            //     'gondolas' => function ($query) use ($request) {
            //         if ($request->has('gondolaId')) {
            //             $query->where('id', $request->get('gondolaId'));
            //         }
            //     }
            // ]);


            return response()->json(new PlannerateResource($planogram));
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Planograma não encontrado',
                'status' => 'error'
            ], 404);
        } catch (Throwable $e) {
            Log::error('Erro ao exibir planograma', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }


    /**
     * Salva ou atualiza um planograma completo com toda a estrutura aninhada
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function save(Request $request, Planogram $planogram)
    {
        // Iniciar uma transação para garantir a consistência dos dados
        // DB::beginTransaction();

        try {
            $data = $request->all();
            
            // Capturar tamanho dos dados da requisição
            $dataSize = strlen(json_encode($data));
            $gondolasCount = count(data_get($data, 'gondolas', []));
            $sectionsCount = 0;
            $shelvesCount = 0;
            $segmentsCount = 0;
            Storage::disk('local')->put('planogram_debug.json', json_encode($data, JSON_PRETTY_PRINT));
            foreach (data_get($data, 'gondolas', []) as $gondola) {
                $sections = data_get($gondola, 'sections', []);
                $sectionsCount += count($sections);
                foreach ($sections as $section) {
                    $shelves = data_get($section, 'shelves', []);
                    $shelvesCount += count($shelves);
                    foreach ($shelves as $shelf) {
                        $segmentsCount += count(data_get($shelf, 'segments', []));
                    }
                }
            }
            
            Log::info('📊 [REQUEST SIZE] Dados da requisição de salvamento', [
                'planogram_id' => $planogram->id,
                'data_size_bytes' => $dataSize,
                'data_size_kb' => round($dataSize / 1024, 2),
                'data_size_mb' => round($dataSize / (1024 * 1024), 2),
                'gondolas' => $gondolasCount,
                'sections' => $sectionsCount,
                'shelves' => $shelvesCount,
                'segments' => $segmentsCount,
                'timestamp' => now()->toDateTimeString(),
            ]);

            // Disparar jobs separados para processamento assíncrono
            // 1. Atualizar metadados do planograma (sem gondolas)
            $metadataOnly = $data;
            unset($metadataOnly['gondolas']); // Remove gondolas para reduzir tamanho
            SavePlanogramMetadataJob::dispatch($metadataOnly, $planogram->id, auth()->user());

            // 2. Processar cada gôndola, seção e prateleira separadamente
            $gondolas = data_get($data, 'gondolas', []);
            $totalJobs = 1; // metadata job
            
            foreach ($gondolas as $gondolaData) {
                // Disparar job da gôndola (sem sections)
                $gondolaOnly = $gondolaData;
                $sections = data_get($gondolaOnly, 'sections', []);
                unset($gondolaOnly['sections']); // Remove sections para reduzir tamanho
                SaveGondolaJob::dispatch($gondolaOnly, $planogram->id, auth()->user());
                $totalJobs++;
                
                // Disparar job para cada seção (sem shelves)
                foreach ($sections as $sectionData) {
                    $sectionOnly = $sectionData;
                    $shelves = data_get($sectionOnly, 'shelves', []);
                    unset($sectionOnly['shelves']); // Remove shelves para reduzir tamanho
                    SaveSectionJob::dispatch($sectionOnly, data_get($gondolaData, 'id'), auth()->user());
                    $totalJobs++;
                    
                    // Disparar job para cada prateleira
                    foreach ($shelves as $shelfData) {
                        SaveShelfJob::dispatch($shelfData, data_get($sectionData, 'id'), auth()->user());
                        $totalJobs++;
                    }
                }
            }

            Log::info('🚀 [JOBS DISPATCHED] Jobs de salvamento disparados', [
                'planogram_id' => $planogram->id,
                'jobs_count' => $totalJobs,
                'breakdown' => [
                    'metadata' => 1,
                    'gondolas' => count($gondolas),
                    'sections' => $sectionsCount,
                    'shelves' => $shelvesCount,
                ],
            ]);

            return response()->json([
                'success' => true,
                'message' =>   'Planograma atualizado com sucesso',
                'data' => []
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao salvar planograma:', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao salvar planograma: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'trace' => app()->environment('production') ? null : $e->getTraceAsString()
            ], 500);
        }
    }

    protected function getModel()
    {
        if (class_exists('App\Models\Planogram')) {
            return 'App\Models\Planogram';
        }
        return Planogram::class;
    }
}
