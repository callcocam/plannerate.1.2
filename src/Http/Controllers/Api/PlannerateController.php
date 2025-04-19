<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Callcocam\Plannerate\Http\Resources\PlannerateResource;
use Callcocam\Plannerate\Models\Gondola;
use Callcocam\Plannerate\Models\Layer;
use Callcocam\Plannerate\Models\Planogram;
use Callcocam\Plannerate\Models\Section;
use Callcocam\Plannerate\Models\Segment;
use Callcocam\Plannerate\Models\Shelf;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function show(string $id)
    {
        try {
            $planogram = $this->getModel()::query(0)->with([
                'tenant',
                'store',
                'cluster',
                'department',
                'gondolas',
                'gondolas.sections',
                'gondolas.sections.shelves',
                'gondolas.sections.shelves.segments',
                'gondolas.sections.shelves.segments.layer',
                'gondolas.sections.shelves.segments.layer.product'
            ])->findOrFail($id);


            return new PlannerateResource($planogram);
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
    public function save(Request $request)
    { 
        // Iniciar uma transação para garantir a consistência dos dados
        DB::beginTransaction();

        try {
            $data = $request->all();

            // Verifica se estamos atualizando ou criando um novo planograma
            if (!empty($data['id'])) {
                $planogram = Planogram::findOrFail($data['id']);
                $isNew = false;
            } else {
                $planogram = new Planogram();
                $isNew = true;
                // Gerar ID único para novos planogramas se necessário
                $data['id'] = (string) Str::orderedUuid();
            }

            // Atualiza os atributos básicos do planograma
            $planogram->fill($this->filterPlanogramAttributes($data));
            $planogram->save();

            // Processa as gôndolas e sua estrutura aninhada
            $this->processGondolas($planogram, $data['gondolas'] ?? []);

            // Se chegou até aqui sem erros, confirma a transação
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $isNew ? 'Planograma criado com sucesso' : 'Planograma atualizado com sucesso',
                'data' => $planogram->fresh()->load([
                    'gondolas',
                    'gondolas.sections',
                    'gondolas.sections.shelves',
                    'gondolas.sections.shelves.segments',
                    'gondolas.sections.shelves.segments.layer'
                ])
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
            'id',
            'tenant_id',
            'user_id',
            'name',
            'slug',
            'description',
            'store_id',
            'store',
            'cluster_id',
            'cluster',
            'department_id',
            'department',
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
    private function processGondolas(Planogram $planogram, array $gondolas): void
    {
        // Coletar IDs existentes para depois remover os que não estão mais presentes
        $existingGondolaIds = $planogram->gondolas()->pluck('id')->toArray();
        $processedGondolaIds = [];

        foreach ($gondolas as $gondolaData) {
            // Verificar se é uma gôndola existente ou nova
            if (!empty($gondolaData['id']) && Str::startsWith($gondolaData['id'], '01')) {
                $gondola = Gondola::firstOrNew(['id' => $gondolaData['id']]);
                $isNewGondola = !$gondola->exists;
            } else {
                $gondola = new Gondola();
                $isNewGondola = true;
                // Gerar ID único para novas gôndolas
                $gondolaData['id'] = (string) Str::orderedUuid();
            }

            // Associar ao planograma
            $gondola->planogram_id = $planogram->id;

            // Atualizar atributos da gôndola
            $gondola->fill($this->filterGondolaAttributes($gondolaData));
            $gondola->save();

            // Registrar o ID para não remover depois
            $processedGondolaIds[] = $gondola->id;

            // Processar seções desta gôndola
            if (isset($gondolaData['sections'])) {
                $this->processSections($gondola, $gondolaData['sections']);
            }
        }

        // Remover gôndolas que não estão mais presentes no planograma
        $gondolasToDelete = array_diff($existingGondolaIds, $processedGondolaIds);
        if (!empty($gondolasToDelete)) {
            Gondola::whereIn('id', $gondolasToDelete)->delete();
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
            'id',
            'tenant_id',
            'user_id',
            'name',
            'width',
            'height',
            'base_height',
            'thickness',
            'scale_factor',
            'location',
            'alignment',
            'status',
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

        foreach ($sections as $sectionData) {
            // Verificar se é uma seção existente ou nova
            if (!empty($sectionData['id']) && Str::startsWith($sectionData['id'], '01')) {
                $section = Section::firstOrNew(['id' => $sectionData['id']]);
                $isNewSection = !$section->exists;
            } else {
                $section = new Section();
                $isNewSection = true;
                // Gerar ID único para novas seções
                $sectionData['id'] = (string) Str::orderedUuid();
            }

            // Associar à gôndola
            $section->gondola_id = $gondola->id;

            // Atualizar atributos da seção
            $section->fill($this->filterSectionAttributes($sectionData));
            $section->save();

            // Registrar o ID para não remover depois
            $processedSectionIds[] = $section->id;

            // Processar prateleiras desta seção
            if (isset($sectionData['shelves'])) {
                $this->processShelves($section, $sectionData['shelves']);
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
     * @return array
     */
    private function filterSectionAttributes(array $data): array
    {
        $fillable = [
            'id',
            'tenant_id',
            'user_id',
            'gondola_id',
            'name',
            'slug',
            'width',
            'height',
            'num_shelves',
            'base_height',
            'base_depth',
            'base_width',
            'hole_height',
            'hole_width',
            'hole_spacing',
            'shelf_height',
            'cremalheira_width',
            'ordering',
            'alignment',
            'settings',
            'status',
            // Adicione outros campos conforme necessário
        ];

        // Converter settings para JSON se for array
        if (isset($data['settings']) && is_array($data['settings'])) {
            $data['settings'] = json_encode($data['settings']);
        }

        return array_intersect_key($data, array_flip($fillable));
    }

    /**
     * Processa as prateleiras de uma seção
     * 
     * @param Section $section
     * @param array $shelves
     * @return void
     */
    private function processShelves(Section $section, array $shelves): void
    {
        // Coletar IDs existentes para depois remover os que não estão mais presentes
        $existingShelfIds = $section->shelves()->pluck('id')->toArray();
        $processedShelfIds = [];

        foreach ($shelves as $shelfData) {
            // Verificar se é uma prateleira existente ou nova
            if (!empty($shelfData['id']) && Str::startsWith($shelfData['id'], '01')) {
                $shelf = Shelf::firstOrNew(['id' => $shelfData['id']]);
                $isNewShelf = !$shelf->exists;
            } else {
                $shelf = new Shelf();
                $isNewShelf = true;
                // Gerar ID único para novas prateleiras
                $shelfData['id'] = (string) Str::orderedUuid();
            }

            // Associar à seção
            $shelf->section_id = $section->id;

            // Atualizar atributos da prateleira
            $shelf->fill($this->filterShelfAttributes($shelfData));
            $shelf->save();

            // Registrar o ID para não remover depois
            $processedShelfIds[] = $shelf->id;

            // Processar segmentos desta prateleira
            if (isset($shelfData['segments'])) {
                $this->processSegments($shelf, $shelfData['segments']);
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
     * @return array
     */
    private function filterShelfAttributes(array $data): array
    {
        $fillable = [
            'id',
            'tenant_id',
            'user_id',
            'section_id',
            'code',
            'product_type',
            'shelf_width',
            'shelf_height',
            'shelf_depth',
            'shelf_position',
            'shelf_x_position',
            'quantity',
            'ordering',
            'spacing',
            'settings',
            'status',
            'alignment',
            // Adicione outros campos conforme necessário
        ];

        // Converter settings para JSON se for array
        if (isset($data['settings']) && is_array($data['settings'])) {
            $data['settings'] = json_encode($data['settings']);
        }

        return array_intersect_key($data, array_flip($fillable));
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

        foreach ($segments as $segmentData) {
            // Verificar se é um segmento existente ou novo
            // Para segmentos temporários (ex: "segment-1745084634214-0"), geramos um novo ID
            if (!empty($segmentData['id']) && Str::startsWith($segmentData['id'], '01') && !Str::startsWith($segmentData['id'], 'segment-')) {
                $segment = Segment::firstOrNew(['id' => $segmentData['id']]);
                $isNewSegment = !$segment->exists;
            } else {
                $segment = new Segment();
                $isNewSegment = true;
                // Gerar ID único para novos segmentos
                $segmentData['id'] = (string) Str::orderedUuid();
            }

            // Associar à prateleira
            $segment->shelf_id = $shelf->id;

            // Atualizar atributos do segmento
            $segment->fill($this->filterSegmentAttributes($segmentData));
            $segment->save();

            // Registrar o ID para não remover depois
            $processedSegmentIds[] = $segment->id;

            // Processar camada (layer) deste segmento
            if (isset($segmentData['layer'])) {
                $this->processLayer($segment, $segmentData['layer']);
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
            'id',
            'tenant_id',
            'user_id',
            'shelf_id',
            'width',
            'ordering',
            'position',
            'quantity',
            'spacing',
            'settings',
            'alignment',
            'status',
            'tabindex',
            // Adicione outros campos conforme necessário
        ];

        // Converter settings para JSON se for array
        if (isset($data['settings']) && is_array($data['settings'])) {
            $data['settings'] = json_encode($data['settings']);
        }

        return array_intersect_key($data, array_flip($fillable));
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
        // Verificar se é uma camada existente ou nova
        // Para camadas temporárias (ex: "layer-1745084634214-01jqp9bx4t369a5aqe9z90xdhg"), geramos um novo ID
        if (!empty($layerData['id']) && Str::startsWith($layerData['id'], '01') && !Str::startsWith($layerData['id'], 'layer-')) {
            $layer = Layer::firstOrNew(['id' => $layerData['id']]);
            $isNewLayer = !$layer->exists;
        } else {
            $layer = new Layer();
            $isNewLayer = true;
            // Gerar ID único para novas camadas
            $layerData['id'] = (string) Str::orderedUuid();
        }

        // Associar ao segmento
        $layer->segment_id = $segment->id;

        // Atualizar atributos da camada
        $layer->fill($this->filterLayerAttributes($layerData));
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
            'id',
            'tenant_id',
            'user_id',
            'segment_id',
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
        if (isset($data['product']) && isset($data['product']['id']) && !isset($data['product_id'])) {
            $data['product_id'] = $data['product']['id'];
        }

        // Converter settings para JSON se for array
        if (isset($data['settings']) && is_array($data['settings'])) {
            $data['settings'] = json_encode($data['settings']);
        }

        return array_intersect_key($data, array_flip($fillable));
    }

    protected function getModel()
    {
        if (class_exists('App\Models\Planogram')) {
            return 'App\Models\Planogram';
        }
        return Planogram::class;
    }
}
