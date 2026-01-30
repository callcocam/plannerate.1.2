<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Cluster;
use App\Models\Departament;
use App\Models\Planogram;
use App\Models\Store;
use Callcocam\Plannerate\Facades\Plannerate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlannerateController extends Controller
{
    public function index(Request $request)
    {
        // Obtém os parâmetros da solicitação para filtragem e paginação
        $search = $request->input('search');
        $status = $request->input('status');
        $perPage = $request->input('per_page', 15);
        
        // Consulta base
        $query = Planogram::query();
        
        // Aplicar filtros
        if ($search) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        }
        
        if ($status) {
            $query->where('status', $status);
        }
        
        // Ordenação e relacionamentos
        $query->with(['store', 'cluster', 'gondolas'])
              ->orderBy('created_at', 'desc');
        
        // Paginação
        $planograms = $query->paginate($perPage);

        return Inertia::render('plannerate/List', [
            'planograms' => $planograms,
            'filters' => $request->only(['search', 'status']),
            'title' => 'Planogramas',
            'description' => 'Gerenciamento de Planogramas',
            'breadcrumbs' => [
                ['title' => 'Planogramas', 'url' => route(Plannerate::getRoute())],
            ],
        ]);
    }
 
    public function create()
    {
        // Carrega as lojas, clusters e departamentos para os selects
        $stores = Store::where('status', 'published')->get();
        $clusters = Cluster::where('status', 'published')->get();
       

        return Inertia::render('plannerate/Create', [
            'stores' => $stores,
            'clusters' => $clusters, 
            'title' => 'Novo Planograma',
            'description' => 'Criar um novo planograma',
            'breadcrumbs' => [
                ['title' => 'Planogramas', 'url' => route(Plannerate::getRoute())],
                ['title' => 'Novo Planograma'],
            ],
        ]);
    }

    public function edit($id)
    {
        // Busca o planograma pelo ID
        $planogram = Planogram::with(['store', 'cluster'])->findOrFail($id);
        
        // Carrega as lojas, clusters e departamentos para os selects
        $stores = Store::where('status', 'published')->get();

        $clusters = Cluster::where('status', 'published')->get();

        return Inertia::render('plannerate/Edit', [
            'planogram' => $planogram,
            'stores' => $stores,
            'clusters' => $clusters, 
            'title' => 'Editar Planograma',
            'description' => 'Editar um planograma existente',
            'breadcrumbs' => [
                ['title' => 'Planogramas', 'url' => route(Plannerate::getRoute())],
                ['title' => $planogram->name],
            ],
        ]);
    }

    public function show($id)
    {
        // Busca o planograma pelo ID com seus relacionamentos
        $planogram = Planogram::with(['store', 'cluster', 'gondolas.sections.shelves.segments'])->findOrFail($id); 

        return Inertia::render('plannerate/View', [
            'record' => $planogram,
            'title' => 'Visualizar Planograma',
            'description' => 'Detalhes do planograma',
            'breadcrumbs' => [
                ['title' => 'Planogramas', 'url' => route(Plannerate::getRoute())],
                ['title' => $planogram->name],
            ],
        ]);
    }

    public function store(Request $request)
    {
        // Validação dos dados do formulário
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'store_id' => 'nullable|exists:stores,id',
            'cluster_id' => 'nullable|exists:clusters,id', 
            'status' => 'required|string|in:draft,published,active,inactive',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $validated['user_id'] = auth()->id(); // Adiciona o ID do usuário autenticado
    
        // Criar o planograma
        $planogram = Planogram::create($validated);

        return redirect()->route(sprintf("%s.index", Plannerate::getRoute()))->with('success', 'Planograma criado com sucesso!');
    }

    public function update(Request $request, $id)
    {
        // Busca o planograma pelo ID
        $planogram = Planogram::findOrFail($id);

        // Validação dos dados do formulário
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'store_id' => 'nullable|exists:stores,id',
            'cluster_id' => 'nullable|exists:clusters,id', 
            'status' => 'required|string|in:draft,published,active,inactive',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]); 

        // Atualizar o planograma
        $planogram->update($validated);

        return redirect()->route(sprintf("%s.index", Plannerate::getRoute()))->with('success', 'Planograma atualizado com sucesso!');
    }

    public function destroy($id)
    {
        // Busca o planograma pelo ID
        $planogram = Planogram::findOrFail($id);
        
        // Verificar se existem gôndolas associadas
        if ($planogram->gondolas->count() > 0) {
            // Excluir gôndolas relacionadas (ou ajustar conforme a lógica do seu negócio)
            // Aqui você pode optar por desatachar, excluir em cascata, etc.
        }
        
        // Excluir o planograma
        $planogram->delete();

        return redirect()->route(sprintf("%s.index", Plannerate::getRoute()))->with('success', 'Planograma excluído com sucesso!');
    } 
}
