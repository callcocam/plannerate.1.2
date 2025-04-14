<template>
    <div class="container mx-auto max-w-7xl p-4">
        <!-- Header com título e botão de adicionar -->
        <div class="mb-6 flex flex-col items-center justify-between gap-4 md:flex-row">
            <h1 class="text-3xl font-bold text-primary">Planogramas</h1>
            <Button variant="default" class="w-full md:w-auto" @click="$router.push({ name: 'plannerate.create' })">
                <Plus class="mr-2 h-4 w-4" />
                Adicionar Novo
            </Button>
        </div>

        <Card class="shadow-md">
            <CardHeader class="bg-muted/30">
                <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <CardTitle>Lista de Planogramas</CardTitle>
                        <CardDescription>Gerencie seus Planogramas aqui</CardDescription>
                    </div>
                    <Button variant="outline" size="sm" class="flex items-center gap-2" @click="showFilters = !showFilters">
                        <FilterIcon class="h-4 w-4" />
                        {{ showFilters ? 'Ocultar Filtros' : 'Mostrar Filtros' }}
                    </Button>
                </div>
            </CardHeader>

            <!-- Filtros colapsáveis -->
            <div v-if="showFilters" class="border-b border-border/40 bg-muted/10 p-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="space-y-2">
                        <Label for="search">Pesquisar</Label>
                        <Input id="search" placeholder="Nome ou ID" v-model="filters.search" />
                    </div>
                    <div class="space-y-2">
                        <Label for="status">Status</Label>
                        <Select v-model="filters.status">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Selecione o status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="draft">Rascunho</SelectItem>
                                <SelectItem value="published">Publicado</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex items-end space-x-2">
                        <Button variant="secondary" class="flex-1" @click="applyFilters">
                            <SearchIcon class="mr-2 h-4 w-4" />
                            Filtrar
                        </Button>
                        <Button variant="ghost" @click="resetFilters">
                            <RefreshCw class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Indicadores de filtros ativos -->
            <div v-if="hasActiveFilters" class="flex flex-wrap gap-2 border-b border-border/40 bg-muted/5 px-4 py-2">
                <Badge variant="outline" class="flex items-center gap-1" v-if="filters.search">
                    Pesquisa: {{ filters.search }}
                    <XIcon
                        class="h-3 w-3 cursor-pointer"
                        @click="
                            () => {
                                filters.search = '';
                                applyFilters();
                            }
                        "
                    />
                </Badge>
                <Badge variant="outline" class="flex items-center gap-1" v-if="filters.status">
                    Status: {{ getStatusLabel(filters.status) }}
                    <XIcon
                        class="h-3 w-3 cursor-pointer"
                        @click="
                            () => {
                                filters.status = '';
                                applyFilters();
                            }
                        "
                    />
                </Badge>
            </div>

            <CardContent class="p-0">
                <!-- Loading indicator -->
                <div v-if="isLoading" class="flex justify-center py-8">
                    <RefreshCw class="h-8 w-8 animate-spin text-primary" />
                </div>

                <div v-else class="relative overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow class="hover:bg-muted/5">
                                <TableHead class="w-32">ID</TableHead>
                                <TableHead>Nome</TableHead>
                                <TableHead>Data Início</TableHead>
                                <TableHead>Data Fim</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead class="w-24 text-right">Ações</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="item in items" :key="item.id" class="transition-colors hover:bg-muted/10">
                                <TableCell class="font-medium">{{ formatId(item.id) }}</TableCell>
                                <TableCell>{{ item.name }}</TableCell>
                                <TableCell>{{ formatDate(item.start_date) }}</TableCell>
                                <TableCell>{{ formatDate(item.end_date) }}</TableCell>
                                <TableCell>
                                    <Badge :variant="getStatusVariant(item.status)">
                                        {{ getStatusLabel(item.status) }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <div class="flex justify-end gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 text-muted-foreground hover:text-foreground"
                                            @click="viewPlanogram(item)"
                                        >
                                            <EyeIcon class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 text-muted-foreground hover:text-foreground"
                                            @click="editPlanogram(item.id)"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 text-muted-foreground hover:text-destructive"
                                            @click="confirmDelete(item)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>

                            <TableRow v-if="items.length === 0 && !isLoading">
                                <TableCell colspan="6" class="h-24 text-center">
                                    <div class="flex flex-col items-center justify-center text-muted-foreground">
                                        <FileX class="mb-2 h-8 w-8" />
                                        <p>Nenhum planograma encontrado</p>
                                        <Button variant="link" @click="resetFilters" v-if="hasActiveFilters"> Limpar filtros </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </CardContent>

            <div class="flex flex-col items-center justify-between gap-4 border-t border-border/40 p-4 sm:flex-row">
                <div class="text-sm text-muted-foreground">
                    Mostrando <span class="font-medium">{{ pagination.from || 0 }}</span> a
                    <span class="font-medium">{{ pagination.to || 0 }}</span> de
                    <span class="font-medium">{{ pagination.total || 0 }}</span> planogramas
                </div>
                <div class="flex items-center space-x-2">
                    <div class="mr-2 text-sm text-muted-foreground">Página {{ pagination.current_page }} de {{ pagination.total_pages }}</div>
                    <Button variant="outline" size="sm" :disabled="!pagination.previous_page_url" @click="goToPage(pagination.current_page - 1)">
                        <ChevronLeft class="h-4 w-4" />
                    </Button>
                    <Button variant="outline" size="sm" :disabled="!pagination.next_page_url" @click="goToPage(pagination.current_page + 1)">
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </Card>
    </div>
</template>

<script setup lang="ts">
import {
    ChevronLeft,
    ChevronRight,
    Eye as EyeIcon,
    FileX,
    Filter as FilterIcon,
    Pencil,
    Plus,
    RefreshCw,
    Search as SearchIcon,
    Trash2,
    X as XIcon,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { Badge } from '../components/ui/badge';
import { Button } from '../components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../components/ui/card';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../components/ui/table';
import { useToast } from '../components/ui/toast';
import { apiService } from '../services';

interface Item {
    id: string;
    name: string;
    slug: string;
    description: string;
    store_id: string | null;
    cluster_id: string | null;
    department_id: string | null;
    start_date: string | null;
    end_date: string | null;
    status: string;
    status_label: string;
    created_at: string;
    updated_at: string;
}

interface Pagination {
    total: number;
    count: number;
    per_page: number;
    current_page: number;
    total_pages: number;
    has_more_pages: boolean;
    next_page_url: string | null;
    previous_page_url: string | null;
    first_page_url: string;
    last_page_url: string;
    from: number;
    to: number;
}

// Estado e referências
const router = useRouter();
const { toast } = useToast();
const items = ref([] as Array<Item>);
const showFilters = ref(false);
const isLoading = ref(false);
const confirmingDelete = ref<Item | null>(null);

// Paginação
const pagination = ref<Pagination>({
    total: 0,
    count: 0,
    per_page: 15,
    current_page: 1,
    total_pages: 1,
    has_more_pages: false,
    next_page_url: null,
    previous_page_url: null,
    first_page_url: '',
    last_page_url: '',
    from: 0,
    to: 0,
});

// Filtros
const filters = ref({
    search: '',
    status: '',
    page: 1,
    per_page: 15,
});

// Verificar se há filtros ativos
const hasActiveFilters = computed(() => {
    return !!filters.value.search || !!filters.value.status;
});

// Funções
const getData = async () => {
    isLoading.value = true;
    try {
        // Construir query params baseado nos filtros
        const params = new URLSearchParams();

        if (filters.value.search) {
            params.append('search', filters.value.search);
        }

        if (filters.value.status) {
            params.append('status', filters.value.status);
        }

        params.append('page', filters.value.page.toString());
        params.append('per_page', filters.value.per_page.toString());

        const url = `/plannerate?${params.toString()}`;
        const response = await apiService.get(url);

        items.value = response.data || [];

        // Atualizar informações de paginação
        if (response.meta && response.meta.pagination) {
            pagination.value = response.meta.pagination;
        }
    } catch (error) {
        console.error('Erro ao carregar planogramas:', error);
        toast({
            title: 'Erro',
            description: 'Não foi possível carregar os planogramas. Tente novamente mais tarde.',
            variant: 'destructive',
        });
    } finally {
        isLoading.value = false;
    }
};

const applyFilters = () => {
    filters.value.page = 1; // Resetar para a primeira página ao filtrar
    getData();
};

const resetFilters = () => {
    filters.value = {
        search: '',
        status: '',
        page: 1,
        per_page: 15,
    };
    getData();
};

const goToPage = (page: number) => {
    if (page < 1 || page > pagination.value.total_pages) return;

    filters.value.page = page;
    getData();
};

const viewPlanogram = (item: any) => { 
    // Verifica se o item possui a propriedade "gondolas" e se é um array
    //pegar o id do primeiro item das dondolas
    let gondolaId = null;
    const id = item.id;
    if (item?.gondolas && item.gondolas.length > 0) {
        gondolaId = item.gondolas[0].id;
        router.push({ name: 'gondola.view', params: { id, gondolaId } });
        return;
    }
    router.push({ name: 'plannerate.view', params: { id } });
};

const editPlanogram = (id: string) => {
    router.push({ name: 'plannerate.edit', params: { id } });
};

const confirmDelete = (item: Item) => {
    if (confirm(`Tem certeza que deseja excluir o planograma "${item.name}"?`)) {
        deletePlanogram(item.id);
    }
};

const deletePlanogram = async (id: string) => {
    try {
        isLoading.value = true;
        await apiService.delete(`/plannerate/${id}`);

        toast({
            title: 'Sucesso',
            description: 'Planograma excluído com sucesso!',
            variant: 'default',
        });

        // Recarregar a lista
        getData();
    } catch (error) {
        console.error('Erro ao excluir planograma:', error);
        toast({
            title: 'Erro',
            description: 'Não foi possível excluir o planograma. Tente novamente mais tarde.',
            variant: 'destructive',
        });
    } finally {
        isLoading.value = false;
        confirmingDelete.value = null;
    }
};

// Funções utilitárias
const formatDate = (dateString: string | null) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR');
};

const formatId = (id: string) => {
    // Exibe apenas os primeiros 8 caracteres para ULIDs longas
    return id.length > 10 ? `${id.substring(0, 8)}...` : id;
};

const getStatusLabel = (status: string) => {
    const statusMap: Record<string, string> = {
        draft: 'Rascunho',
        pending: 'Pendente',
        active: 'Ativo',
        completed: 'Concluído',
        inactive: 'Inativo',
    };

    return statusMap[status] || status;
};

const getStatusVariant = (status: string): 'default' | 'outline' | 'secondary' | 'destructive' => {
    const variantMap: Record<string, any> = {
        draft: 'outline',
        pending: 'secondary',
        active: 'default',
        completed: 'default',
        inactive: 'destructive',
    };

    return variantMap[status] || 'outline';
};

// Inicialização
onMounted(() => {
    getData();
});
</script>
