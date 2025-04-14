<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Callcocam\Plannerate\Facades\Plannerate;
use Callcocam\Plannerate\Http\Requests\Planogram\StorePlanogramRequest;
use Callcocam\Plannerate\Http\Requests\Planogram\UpdatePlanogramRequest;
use Callcocam\Plannerate\Http\Resources\PlanogramResource;
use Callcocam\Plannerate\Models\Planogram;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PlannerateController extends Controller
{
    /**
     * Exibe a listagem dos planogramas
     * 
     * @return AnonymousResourceCollection
     */
    public function index()
    {
        try {
            $query = $this->getModel()::query()->latest();

            // Filtros opcionais baseados nos parâmetros da requisição
            if (request()->has('search')) {
                $search = request()->input('search');
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }

            if (request()->has('status')) {
                $query->where('status', request()->input('status'));
            }

            // Aplicar filtros por relacionamentos
            if (request()->has('store_id')) {
                $query->where('store_id', request()->input('store_id'));
            }

            if (request()->has('cluster_id')) {
                $query->where('cluster_id', request()->input('cluster_id'));
            }

            if (request()->has('department_id')) {
                $query->where('department_id', request()->input('department_id'));
            }

            $data = $query
            ->with([ 
                'gondolas', 
            ])
            ->paginate(request()->input('per_page', 15));


            return PlanogramResource::collection($data)
                ->additional([
                    'meta' => [
                        'title' => 'Planejamento de Tarefas',
                        'description' => 'Planejamento de Tarefas',
                        'breadcrumbs' => [
                            ['title' => 'Planejamento de Tarefas', 'url' => route(Plannerate::getRoute())],
                        ],
                        'pagination' => [
                            'total' => $data->total(),
                            'count' => $data->count(),
                            'per_page' => $data->perPage(),
                            'current_page' => $data->currentPage(),
                            'total_pages' => $data->lastPage(),
                            'has_more_pages' => $data->hasMorePages(),
                            'next_page_url' => $data->nextPageUrl(),
                            'previous_page_url' => $data->previousPageUrl(),
                            'first_page_url' => $data->url(1),
                            'last_page_url' => $data->url($data->lastPage()),
                            'from' => $data->firstItem(),
                            'to' => $data->lastItem(),
                        ],
                    ],
                    'message' => null,
                    'status' => 'success',
                ]);
        } catch (Throwable $e) {
            Log::error('Erro ao listar planogramas', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Ocorreu um erro ao carregar os planogramas',
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Fornece informações para o formulário de criação
     * 
     * @return PlanogramResource
     */
    public function create()
    {
        try {
            return new PlanogramResource(new Planogram());
        } catch (Throwable $e) {
            Log::error('Erro ao preparar novo planograma', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Ocorreu um erro ao preparar o formulário de criação',
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Exibe um planograma específico
     * 
     * @param Planogram $planogram
     * @return PlanogramResource|JsonResponse
     */
    public function show(string $id)
    {
        try {
            $planogram = $this->getModel()::query(0)->with(['tenant','store', 'cluster', 'department', 
            'gondolas',
            'gondolas.sections',
            'gondolas.sections.shelves',
            'gondolas.sections.shelves.segments',
            'gondolas.sections.shelves.segments.layer',
            'gondolas.sections.shelves.segments.layer.product'
            ])->findOrFail($id);
 

            return new PlanogramResource($planogram);
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
     * Armazena um novo planograma
     * 
     * @param StorePlanogramRequest $request
     * @return PlanogramResource|JsonResponse
     */
    public function store(StorePlanogramRequest $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();

            // Adicionar o ID do usuário atual
            $validatedData['user_id'] = auth()->id();

            // Adicionar o tenant_id se aplicável
            if (method_exists(auth()->user(), 'tenant_id')) {
                $validatedData['tenant_id'] = auth()->user()->tenant_id;
            }

            $planogram = $this->getModel()::create($validatedData);

            DB::commit();

            return (new PlanogramResource($planogram))
                ->additional([
                    'message' => 'Planograma criado com sucesso!',
                    'status' => 'success'
                ]);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Erro ao criar planograma', [
                'data' => $request->validated(),
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Ocorreu um erro ao criar o planograma',
                'errors' => app()->environment('production') ? null : $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Atualiza um planograma existente
     * 
     * @param UpdatePlanogramRequest $request
     * @param Planogram $planogram
     * @return PlanogramResource|JsonResponse
     */
    public function update(UpdatePlanogramRequest $request, string $id)
    {
        try {
            DB::beginTransaction();

            $planogram = $this->getModel()::findOrFail($id);
            $validatedData = $request->validated();

            $planogram->update($validatedData);

            DB::commit();

            return (new PlanogramResource($planogram))
                ->additional([
                    'message' => 'Planograma atualizado com sucesso!',
                    'status' => 'success'
                ]);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Planograma não encontrado',
                'status' => 'error'
            ], 404);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Erro ao atualizar planograma', [
                'id' => $id,
                'data' => $request->validated(),
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Ocorreu um erro ao atualizar o planograma',
                'errors' => app()->environment('production') ? null : $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Remove um planograma
     * 
     * @param Planogram $planogram
     * @return JsonResponse
     */
    public function destroy(string $id)
    {
        $planogram = $this->getModel()::findOrFail($id);

        try {
            DB::beginTransaction();

            $planogram->delete();

            DB::commit();

            return response()->json([
                'message' => 'Planograma excluído com sucesso!',
                'status' => 'success'
            ]);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Planograma não encontrado',
                'status' => 'error'
            ], 404);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Erro ao excluir planograma', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Ocorreu um erro ao excluir o planograma',
                'errors' => app()->environment('production') ? null : $e->getMessage(),
                'status' => 'error'
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
