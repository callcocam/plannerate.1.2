<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Planogram as ModelsPlanogram;
use Callcocam\Plannerate\Http\Resources\PlannerateResource;
use Callcocam\Plannerate\Models\Gondola;
use Callcocam\Plannerate\Models\Layer;
use Callcocam\Plannerate\Models\Planogram;
use Callcocam\Plannerate\Models\Section;
use Callcocam\Plannerate\Models\Segment;
use Callcocam\Plannerate\Models\Shelf;
use Callcocam\Plannerate\Services\ShelfPositioningService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class PlannerateController extends Controller
{

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
            $planogram = Cache::rememberForever("planogram_{$id}", function () use ($id) {
                return $this->getModel()::query()->findOrFail($id);
            });



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
    public function save(Request $request, ModelsPlanogram $planogram)
    {

        // Iniciar uma transação para garantir a consistência dos dados
        DB::beginTransaction();

        try {
            $data = $request->all();

            // Atualiza os atributos básicos do planograma
            $planogram->fill($this->filterPlanogramAttributes($data));

            $planogram->save();
            // Processa as gôndolas e sua estrutura aninhada
            $this->processGondolas($planogram, data_get($data, 'gondolas', []));

            // Se chegou até aqui sem erros, confirma a transação
            DB::commit();

            // OTIMIZAÇÃO: Eager loading seletivo - remove relacionamentos pesados (sales, purchases)
            // e carrega apenas campos essenciais do produto
            // $planogram->load([
            //     'tenant:id,name',
            //     'store:id,name',
            //     'cluster:id,name',
            //     'client:id,name',
            //     'gondolas',
            //     'gondolas.sections',
            //     'gondolas.sections.shelves',
            //     'gondolas.sections.shelves.segments',
            //     'gondolas.sections.shelves.segments.layer',
            //     'gondolas.sections.shelves.segments.layer.product:id,name,ean,description,url'
            // ]);
            $id = $planogram->id;
            Cache::forget("planogram_{$id}");
            // $planogram = Cache::rememberForever("planogram_{$id}", function () use ($id) {
            //     return $this->getModel()::query()->findOrFail($id);
            // });
            return response()->json([
                'success' => true,
                'message' =>   'Planograma atualizado com sucesso',
                'data' => []
            ]);
        } catch (\Exception $e) {
            // Em caso de erro, reverte todas as alterações
            DB::rollBack();

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

    /**
     * Filtra apenas os atributos pertinentes ao modelo Planogram
     * 
     * @param array $data
     * @return array
     */
    private function filterPlanogramAttributes(array $data): array
    {
        // Incluir apenas os campos que fazem parte da tabela planograms
        $fillable = [
            'name',
            'slug',
            'description',
            'store_id',
            'store',
            'cluster_id',
            'cluster',
            'start_date',
            'end_date',
            'status',
            // Adicione outros campos conforme necessário
        ];

        return array_intersect_key($data, array_flip($fillable));
    }

    /**
     * Processa as gôndolas e sua estrutura aninhada
     * 
     * @param Planogram $planogram
     * @param array $gondolas
     * @return void
     */
    private function processGondolas(Planogram $planogram, array $gondolas)
    {
        // Coletar IDs existentes para depois remover os que não estão mais presentes
        $existingGondolaIds = $planogram->gondolas()->pluck('id')->toArray();
        $processedGondolaIds = [];

        // OTIMIZAÇÃO: Bulk loading - carregar todas as gondolas de uma vez
        $gondolaIds = array_filter(array_column($gondolas, 'id'));
        $existingGondolas = Gondola::whereIn('id', $gondolaIds)->get()->keyBy('id');
        $data = [];

        foreach ($gondolas as $gondolaData) {
            // Verificar se é uma gôndola existente ou nova
            $gondolaId = data_get($gondolaData, 'id');
            $gondola = $existingGondolas->get($gondolaId);

            if (!$gondola) {
                $gondola = new Gondola();
            }

            // Atualizar atributos da gôndola
            $gondola->fill($this->filterGondolaAttributes($gondolaData));
            $gondola->save();

            // Registrar o ID para não remover depois
            $processedGondolaIds[] = $gondola->id;

            // Processar seções desta gôndola
            if (isset($gondolaData['sections'])) {
                $this->processSections($gondola, data_get($gondolaData, 'sections', []));
            }
        }

        // Remover gôndolas que não estão mais presentes no planograma
        $gondolasToDelete = array_diff($existingGondolaIds, $processedGondolaIds);
        if (!empty($gondolasToDelete)) {
            // Gondola::whereIn('id', $gondolasToDelete)->delete();
        }
    }

    /**
     * Filtra atributos da gôndola
     * 
     * @param array $data
     * @return array
     */
    private function filterGondolaAttributes(array $data): array
    {
        $fillable = [
            'name',
            'width',
            'height',
            'base_height',
            'thickness',
            'scale_factor',
            'location',
            'alignment',
            'linked_map_gondola_id',
            'linked_map_gondola_category',
            'flow',
            'ordering',
            // 'status',
            // Adicione outros campos conforme necessário
        ];

        return array_intersect_key($data, array_flip($fillable));
    }

    /**
     * Processa as seções de uma gôndola
     * 
     * @param Gondola $gondola
     * @param array $sections
     * @return void
     */
    private function processSections(Gondola $gondola, array $sections): void
    {
        // Coletar IDs existentes para depois remover os que não estão mais presentes
        $existingSectionIds = $gondola->sections()->pluck('id')->toArray();
        $processedSectionIds = [];

        // OTIMIZAÇÃO: Mover ShelfPositioningService para fora do loop
        $shelfService = new ShelfPositioningService();

        // OTIMIZAÇÃO: Bulk loading - carregar todas as sections de uma vez
        $sectionIds = array_filter(array_column($sections, 'id'));
        $existingSections = Section::whereIn('id', $sectionIds)->get()->keyBy('id');

        foreach ($sections as $i => $sectionData) {
            // Verificar se é uma seção existente ou nova
            $sectionId = data_get($sectionData, 'id');
            $section = $existingSections->get($sectionId);

            if (!$section) {
                $section = Section::query()->create([
                    'id' => (string) Str::orderedUuid(),
                    'tenant_id' => $gondola->tenant_id,
                    'user_id' => $gondola->user_id,
                    'gondola_id' => $gondola->id,
                    'name' => data_get($sectionData, 'name'),
                ]);
            }

            $data = $this->filterSectionAttributes($sectionData, $shelfService, $gondola);
            $data['gondola_id'] = $gondola->id;
            $data['name'] = sprintf('%s# Sessão', $i);
            $section->update($data);

            // Registrar o ID para não remover depois
            $processedSectionIds[] = $section->id;

            // Processar prateleiras desta seção
            if (isset($sectionData['shelves'])) {
                $this->processShelves($section, data_get($sectionData, 'shelves', []), $shelfService);
            }
        }

        // Remover seções que não estão mais presentes na gôndola
        $sectionsToDelete = array_diff($existingSectionIds, $processedSectionIds);
        if (!empty($sectionsToDelete)) {
            Section::whereIn('id', $sectionsToDelete)->delete();
        }
    }

    /**
     * Filtra atributos da seção
     * 
     * @param array $data
     * @param ShelfPositioningService $shelfService
     * @return array
     */
    private function filterSectionAttributes(array $data, ShelfPositioningService $shelfService, Gondola $gondola): array
    {
        $fillable = [
            'name' => data_get($data, 'name', 'Seção'),
            'slug' => data_get($data, 'slug', Str::slug(data_get($data, 'name', 'seccao'))),
            'width' => data_get($data, 'width', 130),
            'height' => data_get($data, 'height', 180),
            'num_shelves' => data_get($data, 'num_shelves', 4),
            'base_height' => data_get($data, 'base_height', 10),
            'base_depth' => data_get($data, 'base_depth', 20),
            'base_width' => data_get($data, 'base_width', 130),
            'hole_height' => data_get($data, 'hole_height', 4),
            'hole_width' => data_get($data, 'hole_width', 2),
            'hole_spacing' => data_get($data, 'hole_spacing', 2),
            'shelf_height' => data_get($data, 'shelf_height', 4),
            'cremalheira_width' => data_get($data, 'cremalheira_width', 2),
            'ordering' => data_get($data, 'ordering', 0),
            // 'settings',
            // 'status',
            // Adicione outros campos conforme necessário
        ];


        $sectionSettings = $sectionData['settings'] ?? [];

        $sectionSettings['holes'] = $shelfService->calculateHoles($data);

        $fillable['settings'] = $sectionSettings;

        return $fillable;
    }

    /**
     * Processa as prateleiras de uma seção
     * 
     * @param Section $section
     * @param array $shelves
     * @return void
     */
    private function processShelves(Section $section, array $shelves, ShelfPositioningService $shelfService): void
    {
        // Coletar IDs existentes para depois remover os que não estão mais presentes
        $existingShelfIds = $section->shelves()->pluck('id')->toArray();
        $processedShelfIds = [];

        // OTIMIZAÇÃO: Bulk loading - carregar todas as shelves de uma vez
        $shelfIds = array_filter(array_column($shelves, 'id'));
        $existingShelves = Shelf::whereIn('id', $shelfIds)->get()->keyBy('id');

        foreach ($shelves as  $i => $shelfData) {
            // Verificar se é uma prateleira existente ou nova
            $shelfId = data_get($shelfData, 'id');
            $shelf = $existingShelves->get($shelfId);

            if (!$shelf) {
                $shelf = Shelf::query()->create([
                    'id' => (string) Str::orderedUuid(),
                    'tenant_id' => $section->tenant_id,
                    'user_id' => $section->user_id,
                    'section_id' => $section->id,
                ]);
            }

            // Atualizar atributos da prateleira
            $data = $this->filterShelfAttributes($shelfData, $shelfService, $i, $section);
            $data['section_id'] = $section->id;
            $shelf->update($data);

            // Registrar o ID para não remover depois
            $processedShelfIds[] = $shelf->id;

            // Processar segmentos desta prateleira
            if (isset($shelfData['segments'])) {
                $this->processSegments($shelf, data_get($shelfData, 'segments', []));
            }
        }

        // Remover prateleiras que não estão mais presentes na seção
        $shelvesToDelete = array_diff($existingShelfIds, $processedShelfIds);
        if (!empty($shelvesToDelete)) {
            Shelf::whereIn('id', $shelvesToDelete)->delete();
        }
    }

    /**
     * Filtra atributos da prateleira
     * 
     * @param array $data
     * @param ShelfPositioningService $shelfService
     * @param int $i
     * @param Section $section
     * @return array
     */
    private function filterShelfAttributes(array $data, ShelfPositioningService $shelfService, int $i, Section $section): array
    {
        $fillable = [
            // 'code',
            'product_type' => data_get($data, 'product_type', 'generic'),
            'shelf_width' => data_get($data, 'shelf_width', 130),
            'shelf_height' => data_get($data, 'shelf_height', 4),
            'shelf_depth' => data_get($data, 'shelf_depth', 20),
            'shelf_position' => data_get($data, 'shelf_position', 0),
            'shelf_x_position' => data_get($data, 'shelf_x_position', 0),
            'quantity' => data_get($data, 'quantity', 1),
            'ordering' => data_get($data, 'ordering', 0),
            'spacing' => data_get($data, 'spacing', 2),
            'settings' => data_get($data, 'settings', []),
            'status' => data_get($data, 'status', 'published'),
            'alignment' => data_get($data, 'alignment', 'left'),
            // Adicione outros campos conforme necessário
        ];
        // $holes = data_get($section, 'settings.holes', []);
        // $position = $shelfService->calculateShelfPosition($section->num_shelves, data_get($data, 'shelf_height', 4), $holes, $i, $section->gondola->scale_factor);
        // $data['shelf_position'] = $position;
        // Converter settings para JSON se for array


        return $fillable;
    }

    /**
     * Processa os segmentos de uma prateleira
     * 
     * @param Shelf $shelf
     * @param array $segments
     * @return void
     */
    private function processSegments(Shelf $shelf, array $segments): void
    {
        // Coletar IDs existentes para depois remover os que não estão mais presentes
        $existingSegmentIds = $shelf->segments()->pluck('id')->toArray();
        $processedSegmentIds = [];

        // OTIMIZAÇÃO: Bulk loading - carregar todos os segments de uma vez
        $segmentIds = array_filter(array_column($segments, 'id'));
        $existingSegments = Segment::whereIn('id', $segmentIds)->get()->keyBy('id');

        foreach ($segments as $segmentData) {
            // Verificar se é um segmento existente ou novo
            $segmentId = data_get($segmentData, 'id');
            $segment = $existingSegments->get($segmentId);

            if (!$segment) {
                $segment = Segment::query()->create([
                    'id' => (string) Str::orderedUuid(),
                    'tenant_id' => $shelf->tenant_id,
                    'user_id' => $shelf->user_id,
                    'shelf_id' => $shelf->id,
                ]);
            }

            // Atualizar atributos do segmento
            $data = $this->filterSegmentAttributes($segmentData);
            $data['shelf_id'] = $shelf->id;
            $segment->update($data);

            // Registrar o ID para não remover depois
            $processedSegmentIds[] = $segment->id;

            // Processar camada (layer) deste segmento
            if (isset($segmentData['layer'])) {
                $this->processLayer($segment, data_get($segmentData, 'layer', []));
            }
        }

        // Remover segmentos que não estão mais presentes na prateleira
        $segmentsToDelete = array_diff($existingSegmentIds, $processedSegmentIds);
        if (!empty($segmentsToDelete)) {
            Segment::whereIn('id', $segmentsToDelete)->delete();
        }
    }

    /**
     * Filtra atributos do segmento
     * 
     * @param array $data
     * @return array
     */
    private function filterSegmentAttributes(array $data): array
    {
        $fillable = [
            'width' => data_get($data, 'width', 30),
            'ordering' => data_get($data, 'ordering', 0),
            'position' => data_get($data, 'position', 0),
            'quantity' => data_get($data, 'quantity', 1),
            'spacing' => data_get($data, 'spacing', 2),
            'settings' => data_get($data, 'settings', []),
            'alignment' => data_get($data, 'alignment', 'left'),
            'status' => data_get($data, 'status', 'published'),
            'tabindex' => data_get($data, 'tabindex', 0),
            // Adicione outros campos conforme necessário
        ];

        // Converter settings para JSON se for array
        if (isset($data['settings']) && is_array($data['settings'])) {
            $fillable['settings'] = json_encode($data['settings']);
        }

        return $fillable;
    }

    /**
     * Processa a camada (layer) de um segmento
     * 
     * @param Segment $segment
     * @param array $layerData
     * @return void
     */
    private function processLayer(Segment $segment, array $layerData): void
    {
        // OTIMIZAÇÃO: Query única para verificar se layer existe
        $layerId = data_get($layerData, 'id');
        $layer = null;

        if ($layerId) {
            $layer = Layer::query()->where('id', $layerId)->first();
        }

        if (!$layer) {
            $layer = Layer::query()->create([
                'tenant_id' => $segment->tenant_id,
                'user_id' => $segment->user_id,
                'segment_id' => $segment->id,
            ]);
        }

        // Atualizar atributos da camada
        $layer->fill($this->filterLayerAttributes($layerData));
        $layer->segment_id = $segment->id;
        $layer->save();
    }

    /**
     * Filtra atributos da camada (layer)
     * 
     * @param array $data
     * @return array
     */
    private function filterLayerAttributes(array $data): array
    {
        $fillable = [
            'product_id',
            'height',
            'quantity',
            'spacing',
            'settings',
            'alignment',
            'reload',
            'status',
            'tabindex',
            // Adicione outros campos conforme necessário
        ];

        // Extrair o product_id de objetos aninhados, se necessário
        // if (isset($data['product']) && isset($data['product']['id']) && !isset($data['product_id'])) {
        //     $data['product_id'] = $data['product']['id'];
        // }

        // Converter settings para JSON se for array
        if (isset($data['settings']) && is_array($data['settings'])) {
            $data['settings'] = json_encode($data['settings']);
        }

        return array_intersect_key($data, array_flip($fillable));
    }

    protected function getModel()
    {
        if (class_exists('App\Models\V1\Planogram')) {
            return 'App\Models\V1\Planogram';
        }
        return Planogram::class;
    }
}
