<template>
    <div :class="sidebarClasses" @click="viewStatsStore.reset();">
        <!-- Colapsado: só o ícone -->
        <template v-if="showIconOnly">
            <button @click="$emit('toggle')" aria-label="Expandir menu de produtos" title="Produtos"
                class="absolute left-0 top-4 flex items-center justify-center w-12 h-12 bg-transparent border-none shadow-none p-0 m-0"
                style="outline: none;">
                <Package class="h-6 w-6" />
            </button>
        </template>
        <!-- Expandido: conteúdo normal -->
        <div v-if="showContent" class="flex h-full flex-col">
            <!-- Botão de fechar, visível apenas em telas pequenas -->
            <Button variant="outline" size="icon" class="absolute right-2 top-2 z-50 md:hidden" @click="$emit('close')"
                aria-label="Fechar menu de produtos">
                <X class="h-4 w-4" />
            </Button>
            <div class="border-b border-gray-200 p-3 dark:border-gray-700 flex flex-col bg-transparent">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <h3 class="text-lg font-medium text-gray-800 dark:text-gray-100">Produtos Disponíveis</h3>
                        <div v-if="selectedProductsCount > 0"
                            class="bg-blue-500 text-white text-xs rounded-full px-2 py-1 min-w-[1.5rem] text-center">
                            {{ selectedProductsCount }}
                        </div>
                    </div>
                    <div class="flex items-center space-x-1">
                        <Button v-if="selectedProductsCount > 0" variant="ghost" size="sm" @click="clearSelection"
                            aria-label="Limpar seleção" title="Limpar seleção" class="text-xs">
                            Limpar
                        </Button>
                        <Button variant="ghost" size="icon" @click="$emit('toggle')"
                            aria-label="Colapsar menu de produtos" title="Colapsar">
                            <ChevronLeft class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
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
                    class="mt-2 rounded-md border border-gray-200 p-3 text-sm dark:border-gray-600 dark:bg-gray-700 bg-transparent">
                    <div class="mb-2">
                        <Label class="mb-1 block">Mercadológico</Label>
                        <Popover v-model:open="showMercadologicoPopover">
                            <PopoverTrigger as-child>
                                <Button variant="outline" class="w-full justify-between">
                                    <span>{{ filters.category ? 'Nível selecionado' : 'Selecionar nível mercadológico'
                                        }}</span>
                                    <ChevronDown class="h-4 w-4 opacity-50" />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent class="w-2xl p-4" side="right" align="start">
                                <div class="space-y-4">
                                    <h4 class="font-medium leading-none">Nível Mercadológico</h4>
                                    <MercadologicoSelector :field="{
                                        name: 'mercadologico_nivel',
                                        label: 'Mercadológico',
                                        apiUrl: '/api/categories/mercadologico',
                                        valueKey: 'id',
                                        labelKey: 'name'
                                    }" id="mercadologico_nivel" v-model="filters.category"
                                        @update:model-value="showMercadologicoPopover = false" />
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
                            <div class="flex items-center space-x-2 col-span-2">
                                <Checkbox id="dimension" v-model:modelValue="filters.dimension" />
                                <Label for="dimension" class="text-sm font-normal">Com Dimensões</Label>
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
            <div class="flex-1 overflow-y-auto p-2 dark:bg-gray-800 bg-transparent">
                <div v-if="!loading && filteredProducts.length > 0"
                    class="mb-2 px-2 py-1 text-sm text-gray-500 dark:text-gray-400">
                    <span>{{ filteredProducts.length }} produtos encontrados</span>
                </div>

                <ul v-if="!loading && filteredProducts.length > 0" class="space-y-1">
                    <li v-for="product in filteredProducts" :key="product.id"
                        class="group  rounded-md p-2 shadow-sm transition" :class="{
                            'cursor-pointer': product.dimensions ? true : false,
                            'disabled:opacity-50 cursor-not-allowed disabled': !product.dimensions,
                            'bg-blue-100 border-2 border-blue-400 dark:bg-blue-900 dark:border-blue-500': isProductSelected(product.id),
                            'bg-white hover:bg-blue-50 dark:bg-gray-700 dark:hover:bg-gray-600': !isProductSelected(product.id)
                        }" @click="handleProductSelect(product, $event)" :draggable="product.dimensions ? true : false"
                        @dragstart="handleDragStart($event, product)">
                        <div class="flex items-center space-x-3">
                            <div
                                class="flex-shrink-0 overflow-hidden rounded border bg-white p-1 dark:border-gray-600 dark:bg-gray-800">
                                <img :src="product.image_url" :alt="product.name"
                                    class="h-12 w-12 object-contain select-none" :disabled="!product.dimensions"
                                    @error="(e) => handleImageError(e, product)" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-gray-800 dark:text-gray-100">{{ product.name
                                    }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ product.width }}×{{
                                    product.height
                                    }}×{{ product.depth }} cm</p>
                            </div>
                        </div>
                        <!-- <div class="mt-1 flex justify-end">
                            <Button variant="ghost" size="sm"
                                class="invisible text-xs group-hover:visible cursor-pointer"
                                @click.stop="viewStats(product)">
                                Ver estatísticas
                            </Button>
                        </div> -->
                    </li>
                </ul>

                <!-- Loading state -->
                <div v-if="loading" class="flex items-center justify-center py-10">
                    <Loader class="h-6 w-6 animate-spin text-primary" />
                    <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">Carregando...</span>
                </div>

                <!-- Empty state -->
                <div v-if="!loading && filteredProducts.length === 0"
                    class="flex flex-col items-center justify-center py-10 text-center">
                    <Package class="h-10 w-10 text-gray-300 dark:text-gray-600" />
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Nenhum produto disponível encontrado</p>
                    <p v-if="Object.values(filters).some((f) => f)"
                        class="mt-1 text-xs text-gray-400 dark:text-gray-500">
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
    </div>
</template>

<script setup lang="ts">
import { ChevronDown, Loader, Package, Search, SlidersHorizontal, X, ChevronLeft } from 'lucide-vue-next';
import { storeToRefs } from 'pinia';
import { onMounted, onUnmounted, reactive, ref, watch, computed } from 'vue';
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
import { useViewStatsStore } from '@plannerate/store/editor/viewStats';
import { useProductService } from '@plannerate/services/productService';

const viewStatsStore = useViewStatsStore();

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
    dimension: boolean;
    sales: boolean;
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

// Estado para seleção múltipla
const selectedProducts = ref<Set<string>>(new Set());
const isMultiSelectMode = ref(false);

const emit = defineEmits(['select-product', 'drag-start', 'view-stats', 'close', 'toggle']);

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
    dimension: true,
    sales: true
});
const loading = ref(false);
const filteredProducts = ref<Product[]>([]);
const currentPage = ref(1);
const hasMorePages = ref(true);
const LIST_LIMIT = 20;


// Computed para contar produtos selecionados
const selectedProductsCount = computed(() => selectedProducts.value.size);

// Função para verificar se um produto está selecionado
const isProductSelected = (productId: string): boolean => {
    return selectedProducts.value.has(productId);
};

// Função para limpar seleção
const clearSelection = () => {
    selectedProducts.value.clear();
    isMultiSelectMode.value = false;
};

// Função para toggle de seleção de produto
const toggleProductSelection = (product: Product) => {
    if (selectedProducts.value.has(product.id)) {
        selectedProducts.value.delete(product.id);
    } else {
        selectedProducts.value.add(product.id);
    }

    // Atualiza o modo de seleção múltipla
    isMultiSelectMode.value = selectedProducts.value.size > 0;
};

// Função para obter produtos selecionados
const getSelectedProducts = (): Product[] => {
    const selected = filteredProducts.value.filter(product => selectedProducts.value.has(product.id));
    return selected;
};

const fetchCategories = async () => {
    const response = await apiService.get<Category[]>('categories/mercadologico');
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
            dimension: filters.dimension || undefined,
            sales: filters.sales || undefined,
            planogram_id: editorStore.currentState?.id || undefined,
            client_id: editorStore.currentState?.client_id,
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

        const response = await useProductService().getProductsPost(params);
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
    setTimeout(async () => {
        // Refiltra baseado no usageStatus atual
        if (filters.usageStatus === 'unused') {
            // Mostra apenas produtos que NÃO estão na gôndola
            filteredProducts.value = filteredProducts.value.filter(p => !productIdsInCurrentGondola.value.has(p.id));
        } else if (filters.usageStatus === 'used') {
            // Mostra apenas produtos que estão na gôndola
            filteredProducts.value = filteredProducts.value.filter(p => productIdsInCurrentGondola.value.has(p.id));
        }
        // Se for 'all', não precisa filtrar
    }, 100); // Pequeno delay
});

function loadMore() {
    if (!loading.value && hasMorePages.value) {
        fetchProducts(currentPage.value + 1, true);
    }
}

function handleProductSelect(product: Product, event?: MouseEvent) {
    viewStatsStore.reset();
    // Se CTRL está pressionado ou já estamos em modo multi-seleção
    if (event?.ctrlKey || event?.metaKey || isMultiSelectMode.value) {
        toggleProductSelection(product);
    } else {
        // Limpa seleção anterior e seleciona apenas este produto
        clearSelection();
        emit('select-product', product);
    }
}

function handleDragStart(event: DragEvent, product: Product) {
    viewStatsStore.reset();
    if (!product.dimensions) {
        event.preventDefault();
        return;
    }
    if (event.dataTransfer) {
        // Se há produtos selecionados e o produto arrastado está entre eles
        if (selectedProductsCount.value > 0 && isProductSelected(product.id)) {
            // Arrastar todos os produtos selecionados
            const selectedProductsList = getSelectedProducts();

            event.dataTransfer.setData('text/products-multiple', JSON.stringify(selectedProductsList));
            event.dataTransfer.effectAllowed = 'copy';

            // Limpar seleção após iniciar o drag
            setTimeout(() => clearSelection(), 100);
        } else {
            // Arrastar apenas um produto (comportamento original)
            event.dataTransfer.setData('text/product', JSON.stringify(product));
            event.dataTransfer.effectAllowed = 'copy';
        }
    }
    emit('drag-start', event, product);
}

function viewStats(product: Product) {
    viewStatsStore.setSelectedProduct(product);
    editorStore.clearSelectedSection(); // Limpa seleção de camadas ao selecionar prateleira
    editorStore.clearLayerSelection(); // Limpa seleção de camadas ao selecionar prateleira    
    editorStore.clearSelectedShelf(); // Limpa seleção de prateleira ao selecionar produto
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

// Listener para tecla ESC
const handleKeyDown = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && selectedProductsCount.value > 0) {
        clearSelection();
    }
};

onMounted(() => {
    // console.log('Component mounted, fetching initial products...');
    fetchProducts(1, false);
    fetchCategories();

    // Adicionar listener global para ESC
    document.addEventListener('keydown', handleKeyDown);
});

// Cleanup no unmount
onUnmounted(() => {
    document.removeEventListener('keydown', handleKeyDown);
});

// Computed para classes responsivas do sidebar
const sidebarClasses = computed(() => {
    return showIconOnly.value
        ? 'w-12 h-screen relative z-40'
        : 'sticky top-0 flex h-screen flex-shrink-0 flex-col overflow-hidden rounded-lg bg-gray-50 dark:bg-gray-800 z-40 transition-all duration-300 ease-in-out w-72 relative';
});

// Computed properties for collapsing/expanding
const showIconOnly = computed(() => {
    return !props.open;
});

const showContent = computed(() => {
    return props.open;
});
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
