<template>
    <div :class="sidebarClasses">
        <!-- Botão de fechar, visível apenas em telas pequenas -->
        <Button variant="outline" size="icon" class="absolute right-2 top-2 z-50 md:hidden" @click="$emit('close')"
            aria-label="Fechar menu de produtos">
            <X class="h-4 w-4" />
        </Button>
        <div class="border-b border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
            <h3 class="text-center text-lg font-medium text-gray-800 dark:text-gray-100">Produtos Disponíveis</h3>

            <!-- Campo de busca com design aprimorado -->
            <div class="relative mt-3">
                <Input v-model="filters.search" type="text" placeholder="Buscar produtos..." class="pr-10" />
                <Search class="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            </div>

            <!-- Botão de filtros com design aprimorado -->
            <Button variant="outline" class="mt-2 w-full justify-between" @click="showFilters = !showFilters">
                <div class="flex items-center">
                    <SlidersHorizontal class="mr-2 h-4 w-4" />
                    <span>Filtros</span>
                </div>
                <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': showFilters }" />
            </Button>

            <!-- Painel de filtros colapsável -->
            <div v-if="showFilters"
                class="mt-2 rounded-md border border-gray-200 bg-white p-3 text-sm dark:border-gray-600 dark:bg-gray-700">
                <div class="mb-2">
                    <Label class="mb-1 block">Mercadológico</Label>
                    <Popover v-model:open="showMercadologicoPopover">
                        <PopoverTrigger as-child>
                            <Button variant="outline" class="w-full justify-between">
                                <span>{{ filters.category ? 'Nível selecionado' : 'Selecionar nível mercadológico' }}</span>
                                <ChevronDown class="h-4 w-4 opacity-50" />
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent class="w-2xl p-4" side="right" align="start">
                            <div class="space-y-4">
                                <h4 class="font-medium leading-none">Nível Mercadológico</h4>
                                <MercadologicoSelector 
                                    :field="{
                                        name: 'mercadologico_nivel',
                                        label: 'Mercadológico',
                                        apiUrl: '/api/categories/mercadologico',
                                        valueKey: 'id',
                                        labelKey: 'name'
                                    }" 
                                    id="mercadologico_nivel" 
                                    v-model="filters.category" 
                                    @update:model-value="showMercadologicoPopover = false"
                                />
                            </div>
                        </PopoverContent>
                    </Popover>
                </div>

                <div class="mb-2">
                    <Label class="mb-1 block">Status de uso</Label>
                    <div class="space-y-2">
                        <div class="flex items-center space-x-2">
                            <input id="all" type="radio" value="all" v-model="filters.usageStatus"
                                class="h-4 w-4 border-gray-300 text-primary focus:ring-2 focus:ring-primary" />
                            <Label for="all" class="text-sm font-normal">Todos</Label>
                        </div>
                        <div class="flex items-center space-x-2">
                            <input id="unused" type="radio" value="unused" v-model="filters.usageStatus"
                                class="h-4 w-4 border-gray-300 text-primary focus:ring-2 focus:ring-primary" />
                            <Label for="unused" class="text-sm font-normal">Não usados</Label>
                        </div>
                        <div class="flex items-center space-x-2">
                            <input id="used" type="radio" value="used" v-model="filters.usageStatus"
                                class="h-4 w-4 border-gray-300 text-primary focus:ring-2 focus:ring-primary" />
                            <Label for="used" class="text-sm font-normal">Já usados</Label>
                        </div>
                    </div>
                </div>

                <div class="mb-2">
                    <Label class="mb-1 block">Atributos</Label>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="flex items-center space-x-2">
                            <Checkbox id="hangable" v-model:modelValue="filters.hangable" />
                            <Label for="hangable" class="text-sm font-normal">Pendurável</Label>
                        </div>
                        <div class="flex items-center space-x-2">
                            <Checkbox id="stackable" v-model:modelValue="filters.stackable" />
                            <Label for="stackable" class="text-sm font-normal">Empilhável</Label>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <Button variant="secondary" size="sm" @click="clearFilters">
                        Limpar filtros
                    </Button>
                </div>
            </div>
        </div>

        <!-- Lista de produtos com design limpo -->
        <div class="flex-1 overflow-y-auto p-2 dark:bg-gray-800">
            <div v-if="!editorStore.isLoading && filteredProducts.length > 0"
                class="mb-2 px-2 py-1 text-sm text-gray-500 dark:text-gray-400">
                <span>{{ filteredProducts.length }} produtos encontrados</span>
            </div>

            <ul v-if="!editorStore.isLoading && filteredProducts.length > 0" class="space-y-1">
                <li v-for="product in filteredProducts" :key="product.id"
                    class="group cursor-pointer rounded-md bg-white p-2 shadow-sm transition hover:bg-blue-50 dark:bg-gray-700 dark:hover:bg-gray-600"
                    @click="handleProductSelect(product)" draggable="true"
                    @dragstart="handleDragStart($event, product)">
                    <div class="flex items-center space-x-3">
                        <div
                            class="flex-shrink-0 overflow-hidden rounded border bg-white p-1 dark:border-gray-600 dark:bg-gray-800">
                            <img :src="product.image_url" :alt="product.name" class="h-12 w-12 object-contain"
                                @error="(e) => handleImageError(e, product)" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-800 dark:text-gray-100">{{ product.name }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ product.width }}×{{ product.height
                                }}×{{ product.depth }} cm</p>
                        </div>
                    </div>
                    <div class="mt-1 flex justify-end">
                        <Button variant="ghost" size="sm" class="invisible text-xs group-hover:visible"
                            @click.stop="viewStats(product)">
                            Ver estatísticas
                        </Button>
                    </div>
                </li>
            </ul>

            <!-- Loading state -->
            <div v-if="editorStore.isLoading" class="flex items-center justify-center py-10">
                <Loader class="h-6 w-6 animate-spin text-primary" />
                <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">Carregando...</span>
            </div>

            <!-- Empty state -->
            <div v-if="!editorStore.isLoading && filteredProducts.length === 0"
                class="flex flex-col items-center justify-center py-10 text-center">
                <Package class="h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Nenhum produto disponível encontrado</p>
                <p v-if="Object.values(filters).some((f) => f)" class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                    Tente ajustar os filtros.</p>
            </div>

            <!-- Load More button -->
            <div v-if="!loading && hasMorePages" class="flex justify-center py-4">
                <Button variant="secondary" @click="loadMore">
                    Carregar mais produtos
                </Button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ChevronDown, Loader, Package, Search, SlidersHorizontal, X } from 'lucide-vue-next';
import { storeToRefs } from 'pinia';
import { onMounted, reactive, ref, watch, computed } from 'vue';
import { apiService } from '@plannerate/services';
import { useEditorStore } from '@plannerate/store/editor';
import { Product } from '@plannerate/types/segment';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
// import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import MercadologicoSelector from '@/components/form/fields/MercadologicoSelector.vue';


interface Category {
    id: number | string;
    name: string;
}

interface FilterState {
    search: string;
    category: number | string | null | '';
    hangable: boolean;
    stackable: boolean;
    usageStatus: string;
}
const props = defineProps({
    categories: {
        type: Array as () => Category[],
        default: () => [],
    },
    open: {
        type: Boolean,
        default: true,
    },
});

const editorStore = useEditorStore();

const allCategories = ref<Category[]>(props.categories || []);

const { productIdsInCurrentGondola } = storeToRefs(editorStore);

const emit = defineEmits(['select-product', 'drag-start', 'view-stats', 'close']);

// Função para limpar valores null/undefined de um objeto
const cleanMercadologicoNivel = (obj: any) => {
    if (!obj || typeof obj !== 'object') return null;
    
    const cleaned: any = {};
    Object.keys(obj).forEach(key => {
        const value = obj[key];
        // Remove valores null, 'null', undefined, ou strings vazias
        if (value !== null && value !== 'null' && value !== undefined && value !== '') {
            cleaned[key] = value;
        }
    });
    
    // Retorna null se o objeto estiver vazio após limpeza
    return Object.keys(cleaned).length > 0 ? cleaned : null;
};

const showFilters = ref(false);
const showMercadologicoPopover = ref(false);
const mercadologicoNivel = ref(cleanMercadologicoNivel(editorStore.currentState?.mercadologico_nivel));
const filters = reactive<FilterState>({
    search: '',
    category: mercadologicoNivel.value,
    hangable: false,
    stackable: false,
    usageStatus: 'unused',
}); 
const loading = ref(false);
const filteredProducts = ref<Product[]>([]);
const currentPage = ref(1);
const hasMorePages = ref(true);
const LIST_LIMIT = 20;

interface PaginatedProductsResponse {
    data: Product[];
    meta: {
        current_page: number;
        last_page: number;
    };
}

const fetchCategories = async () => {
    const response = await apiService.get<Category[]>('categories');
    allCategories.value = response;
};

const fetchProducts = async (page = 1, append = false) => {
    if (loading.value) return;
    loading.value = true;

    try {
        const idsArray = Array.from(productIdsInCurrentGondola.value);

        const params: Record<string, any> = {
            search: filters.search || undefined,
            category: cleanMercadologicoNivel(filters.category) || undefined,
            hangable: filters.hangable || undefined,
            stackable: filters.stackable || undefined,
            planogram_id: editorStore.currentState?.id || undefined, 
            page: page,
            limit: LIST_LIMIT,
        };

        // Aplicar filtro baseado no status de uso
        if (filters.usageStatus === 'unused') {
            params.notInGondola = idsArray;
        } else if (filters.usageStatus === 'used') {
            params.inGondola = true;
        }
        // Se for 'all', não aplicamos nenhum filtro específico de uso

        Object.keys(params).forEach((key) => {
            if (params[key] === undefined || params[key] === '' || (Array.isArray(params[key]) && params[key].length === 0)) {
                delete params[key];
            }
        });

        const response = await apiService.get<PaginatedProductsResponse>('products', { params });
        // console.log('API Response:', response);

        const newProducts = response.data || [];
        if (append) {
            filteredProducts.value.push(...newProducts);
        } else {
            filteredProducts.value = newProducts;
        }

        if (response.meta) {
            currentPage.value = response.meta.current_page;
            hasMorePages.value = response.meta.current_page < response.meta.last_page;
        } else {
            hasMorePages.value = newProducts.length === LIST_LIMIT;
        }
    } catch (error) {
        console.error('Erro ao carregar produtos:', error);
        if (!append) {
            filteredProducts.value = [];
        }
        hasMorePages.value = false;
    } finally {
        loading.value = false;
    }
};

watch(
    filters,
    async () => {
        // console.log('Filters changed, fetching page 1...');
        await fetchProducts(1, false);
    },
    { deep: true },
);

watch(productIdsInCurrentGondola, () => {
    console.log('Product IDs in current gondola changed (via editorStore), fetching page 1...');
    setTimeout(async () => {
        await fetchProducts(1, false);
    }, 300); // Pequeno delay
});

function loadMore() {
    if (!loading.value && hasMorePages.value) {
        fetchProducts(currentPage.value + 1, true);
    }
}

function handleProductSelect(product: Product) {
    emit('select-product', product);
}

function handleDragStart(event: DragEvent, product: Product) {
    if (event.dataTransfer) {
        event.dataTransfer.setData('text/product', JSON.stringify(product));
        event.dataTransfer.effectAllowed = 'copy';
    }
    emit('drag-start', event, product);
}

function viewStats(product: Product) {
    emit('view-stats', product);
}

function handleImageError(event: Event, product: Product) {
    const target = event.target as HTMLImageElement;

    // Pegar as iniciais do nome do produto
    const initials = product.name
        .split(' ')
        .map(word => word.charAt(0).toUpperCase())
        .join('')
        .slice(0, 2); // Limita a 2 letras (opcional)

    // Exemplo de uso com placehold.co
    target.src = `https://placehold.co/400x600?text=${initials}`;
}
function clearFilters() {
    filters.search = '';
    filters.category = cleanMercadologicoNivel(editorStore.currentState?.mercadologico_nivel);
    filters.hangable = false;
    filters.stackable = false;
    filters.usageStatus = 'all';
    showFilters.value = false;
}

onMounted(() => {
    // console.log('Component mounted, fetching initial products...');
    fetchProducts(1, false);
    fetchCategories();
});

// Computed para classes responsivas do sidebar
const sidebarClasses = computed(() => {
    return [
        'sticky top-0 flex h-screen w-72 flex-shrink-0 flex-col overflow-hidden rounded-lg border bg-gray-50 dark:border-gray-700 dark:bg-gray-800 z-40 transition-transform duration-300',
        props.open ? 'translate-x-0' : '-translate-x-full',
        'fixed left-0',
    ].join(' ')
})
</script>

<style scoped>
.overflow-y-auto {
    max-height: calc(100vh - 200px);
    scrollbar-width: thin;
    scrollbar-color: #e2e8f0 #f8fafc;
}

.overflow-x-auto {
    overflow-x: hidden;
}

.overflow-y-auto::-webkit-scrollbar {
    width: 8px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f8fafc;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background-color: #e2e8f0;
    border-radius: 4px;
}

@media (prefers-color-scheme: dark) {
    .overflow-y-auto {
        scrollbar-color: #4b5563 #1f2937;
    }

    .overflow-y-auto::-webkit-scrollbar-track {
        background: #1f2937;
    }

    .overflow-y-auto::-webkit-scrollbar-thumb {
        background-color: #4b5563;
    }
}
</style>
