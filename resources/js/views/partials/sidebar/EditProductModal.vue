<template>
    <Dialog :open="isOpen" @update:open="(value) => emit('update:isOpen', value)">
        <DialogContent id="modal-edit-product-content" class="modal-edit-product max-w-md rounded-lg overflow-hidden">
            <DialogHeader>
                <DialogTitle>Editar Produto</DialogTitle>
                <DialogDescription>
                    Altere as informações do produto selecionado
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4 py-4">
                <!-- Imagem do produto -->
                <div class="grid gap-2">
                    <Label for="image">Imagem</Label>

                    <!-- Visualização da imagem atual -->
                    <div class="relative w-full h-48 overflow-hidden rounded-md border border-input">
                        <img v-if="product && product.image_url" :src="product.image_url"
                            :alt="product.name || 'Produto'" class="w-full h-full object-contain"
                            @error="(e: Event) => handleImageError(e)" />
                        <div v-else class="w-full h-full flex items-center justify-center bg-gray-100 dark:bg-gray-800">
                            <div class="text-center">
                                <div
                                    class="rounded-full bg-gray-200 dark:bg-gray-700 w-16 h-16 mx-auto flex items-center justify-center mb-2">
                                    <Upload class="h-8 w-8 text-gray-500 dark:text-gray-400" />
                                </div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Sem imagem</p>
                            </div>
                        </div>
                        <Button v-if="product && product.image_url" type="button" variant="destructive" size="icon"
                            class="absolute top-2 right-2 h-8 w-8" @click="(e: Event) => removerImagem(e)" title="Remover imagem">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>

                    <!-- Upload de nova imagem -->
                    <div class="flex items-center gap-2 mt-2">
                        <Input type="file" id="image" accept="image/*" ref="fileInput" @change="(e: Event) => selecionarImagem(e)"
                            class="hidden" />
                        <div class="flex-1 flex gap-2">
                            <Button type="button" variant="outline" class="flex-1" @click="(e: Event) => triggerFileInput(e)">
                                <Upload class="h-4 w-4 mr-2" />
                                Selecionar imagem
                            </Button>
                            <Button type="button" variant="default" :disabled="!novaImagem" @click="(e: Event) => aplicarNovaImagem(e)">
                                <Check class="h-4 w-4 mr-2" />
                                Aplicar
                            </Button>
                        </div>
                    </div>
                    <p v-if="novaImagem" class="text-sm text-green-600 mt-1">
                        Imagem selecionada: {{ novaImagem.name }}
                    </p>
                </div>

                <!-- Dimensões do produto -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="width">Largura (cm)</Label>
                        <Input type="number" id="width" v-model="formData.width" step="0.01" placeholder="Largura" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="height">Altura (cm)</Label>
                        <Input type="number" id="height" v-model="formData.height" step="0.01" placeholder="Altura" />
                    </div>
                </div>
            </div>

            <DialogFooter >
                <div class="flex gap-2 w-full justify-end">
                    <Button type="button" variant="outline" @click="(e: Event) => cancelar(e)" class="mr-2">
                        Cancelar
                    </Button>
                    <Button type="button" variant="default" @click="(e: Event) => salvar(e)" :disabled="!mudancasPendentes">
                        Salvar alterações
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Upload, Trash2, Check } from 'lucide-vue-next';

// Definir a interface do Product
interface Props {
    isOpen: boolean;
    product: any | null;
}

const props = defineProps<Props>();
const emit = defineEmits(['update:isOpen', 'update:product']);

// Referência para o input de arquivo
const fileInput = ref<HTMLInputElement | null>(null);

// Form state
const novaImagem = ref<File | null>(null);
const formData = ref({
    width: 0,
    height: 0,
    image_url: ''
});

// Inicializar o formulário quando o produto mudar
watch(() => props.product, (newProduct) => {
    if (newProduct) {
        formData.value = {
            width: typeof newProduct.width === 'string' ? parseFloat(newProduct.width) : (newProduct.width || 0),
            height: typeof newProduct.height === 'string' ? parseFloat(newProduct.height) : (newProduct.height || 0),
            image_url: newProduct.image_url || ''
        };
    }
}, { immediate: true });

// Verifica se houve mudanças
const mudancasPendentes = computed(() => {
    if (!props.product) return false;

    const productWidth = typeof props.product.width === 'string'
        ? parseFloat(props.product.width)
        : (props.product.width || 0);

    const productHeight = typeof props.product.height === 'string'
        ? parseFloat(props.product.height)
        : (props.product.height || 0);

    return novaImagem.value !== null ||
        formData.value.width !== productWidth ||
        formData.value.height !== productHeight;
});

// Event listener para impedir propagação de cliques
function setupModalEventListeners() {
    const modalContent = document.getElementById('modal-edit-product-content');
    if (modalContent) {
        modalContent.addEventListener('click', handleModalClick);
    }
}

function cleanupModalEventListeners() {
    const modalContent = document.getElementById('modal-edit-product-content');
    if (modalContent) {
        modalContent.removeEventListener('click', handleModalClick);
    }
}

function handleModalClick(e: Event) {
    e.stopPropagation();
}

// Configurar event listeners quando o componente for montado
onMounted(() => {
    // Pequeno atraso para garantir que o DOM esteja atualizado
    setTimeout(setupModalEventListeners, 100);
});

// Limpar event listeners quando o componente for desmontado
onUnmounted(() => {
    cleanupModalEventListeners();
});

// Atualizar os event listeners quando o modal abrir ou fechar
watch(() => props.isOpen, (newValue) => {
    if (newValue) {
        // Modal aberto - adicionar event listeners após DOM atualizar
        setTimeout(setupModalEventListeners, 100);
    } else {
        // Modal fechado - remover event listeners
        cleanupModalEventListeners();
    }
});

// Funções
function triggerFileInput(e: Event) {
    // Impedir a propagação do evento para garantir que o modal não fecha
    e.stopPropagation();
    
    if (fileInput.value) {
        fileInput.value.click();
    }
}

function selecionarImagem(event: Event) {
    event.stopPropagation();
    
    const input = event.target as HTMLInputElement;
    const arquivo = input.files?.[0];
    if (arquivo) {
        novaImagem.value = arquivo;
    }
}

function removerImagem(e: Event) {
    // Impedir a propagação do evento para garantir que o modal não fecha
    e.stopPropagation();
    
    if (props.product) {
        const updatedProduct = {
            ...props.product,
            image_url: ''
        };
        emit('update:product', updatedProduct);
    }
    novaImagem.value = null;
}

function handleImageError(event: Event) {
    event.stopPropagation();
    
    const target = event.target as HTMLImageElement;
    const product = props.product;

    if (product?.name) {
        // Pegar as iniciais do nome do produto
        const initials = product.name
            .split(' ')
            .map((word: string) => word.charAt(0).toUpperCase())
            .join('')
            .slice(0, 2);

        target.src = `https://placehold.co/400x600?text=${initials}`;
    } else {
        target.src = 'https://placehold.co/400x600?text=NA';
    }
}

function cancelar(e: Event) {
    // Impedir a propagação do evento para garantir que o modal não fecha incorretamente
    e.stopPropagation();
    
    emit('update:isOpen', false);
}

function aplicarNovaImagem(e: Event) {
    // Impedir a propagação do evento para garantir que o modal não fecha
    e.stopPropagation();
    
    if (novaImagem.value && props.product) {
        const updatedProduct = {
            ...props.product,
            image_url: URL.createObjectURL(novaImagem.value)
        };
        emit('update:product', updatedProduct);
        novaImagem.value = null;
    }
}

function salvar(e: Event) {
    // Impedir a propagação do evento para garantir que o modal não fecha incorretamente
    e.stopPropagation();
    
    if (!props.product) return;

    const updatedProduct = {
        ...props.product,
        width: formData.value.width,
        height: formData.value.height
    };

    emit('update:product', updatedProduct);
    emit('update:isOpen', false);
}
</script>

<style>
/* Estilos para garantir que o modal receba eventos corretamente */
.modal-edit-product {
    pointer-events: auto !important;
    z-index: 9999 !important;
}
</style>