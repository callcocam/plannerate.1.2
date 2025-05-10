<template>
    <AlertDialog v-model:open="isDialogOpen">
        <AlertDialogTrigger>
            <slot />
        </AlertDialogTrigger>
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Tem certeza que deseja aplicar estas alterações?</AlertDialogTitle>
                <AlertDialogDescription>
                    Esta alteração será aplicada diretamente ao produto na base de dados. Esta ação afeta todos os produtos que utilizam este produto.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-2 col-span-2">
                    <Label for="image">Imagem</Label>
                    <!-- Visualização da imagem atual -->
                    <div class="relative w-full h-48 overflow-hidden rounded-md border border-input">
                        <img v-if="previewImageUrl" :src="previewImageUrl" :alt="record.name || 'Preview'" class="w-full h-full object-contain" />
                        <img v-else-if="originalImageUrl" :src="originalImageUrl" :alt="record.name || 'Produto'" class="w-full h-full object-contain" @error="(e: Event) => handleImageError(e, record)" />
                        <div v-else class="w-full h-full flex items-center justify-center bg-gray-100 dark:bg-gray-800">
                            <div class="text-center">
                                <div
                                    class="rounded-full bg-gray-200 dark:bg-gray-700 w-16 h-16 mx-auto flex items-center justify-center mb-2">
                                    <Upload class="h-8 w-8 text-gray-500 dark:text-gray-400" />
                                </div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Sem imagem</p>
                            </div>
                        </div>
                        <Button v-if="(previewImageUrl || originalImageUrl)" type="button" variant="destructive" size="icon"
                            class="absolute top-2 right-2 h-8 w-8" @click="(e: Event) => removeImage(e)"
                            title="Remover imagem">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                        <Button v-if="previewImageUrl" type="button" variant="outline" size="icon"
                            class="absolute top-2 left-2 h-8 w-8" @click="(e: Event) => cancelImageSelection(e)"
                            title="Cancelar seleção">
                            Cancelar
                        </Button>
                    </div>
                    <!-- Upload de nova imagem -->
                    <div class="flex items-center gap-2 mt-2">
                        <input type="file" id="image" accept="image/*" ref="fileInput"
                            @change="(e: Event) => selectImage(e)" class="hidden" />
                        <div class="flex-1 flex gap-2">
                            <Button type="button" variant="outline" class="flex-1"
                                @click="(e: Event) => triggerFileInput(e)">
                                <Upload class="h-4 w-4 mr-2" />
                                Selecionar imagem
                            </Button>
                            <Button type="button" variant="default" :disabled="!newImage"
                                @click="(e: Event) => applyImage(e)">
                                <Check class="h-4 w-4 mr-2" />
                                Aplicar
                            </Button>
                        </div>
                    </div>
                    <p v-if="newImage" class="text-sm text-green-600 mt-1">
                        Imagem selecionada: {{ newImage.name }}
                    </p>
                </div>
                <div class="flex flex-col gap-2 col-span-2">
                    <Label>Nome</Label>
                    <Input type="text" v-model="record.name" />
                    <span v-if="validationErrors.name" class="text-red-500 text-xs">{{ validationErrors.name }}</span>
                </div>
                <div class="flex flex-col gap-2 col-span-2">
                    <Label>EAN</Label>
                    <Input type="text" v-model="record.ean" readonly />
                    <span v-if="validationErrors.ean" class="text-red-500 text-xs">{{ validationErrors.ean }}</span>
                </div>
                <div class="flex flex-col gap-2">
                    <Label>Largura</Label>
                    <Input type="number" v-model="record.width" />
                    <span v-if="validationErrors.width" class="text-red-500 text-xs">{{ validationErrors.width }}</span>
                </div>
                <div class="flex flex-col gap-2">
                    <Label>Altura</Label>
                    <Input type="number" v-model="record.height" />
                    <span v-if="validationErrors.height" class="text-red-500 text-xs">{{ validationErrors.height }}</span>
                </div>
                <div class="flex flex-col gap-2 col-span-2">
                    <Label>Descrição</Label>
                    <Input type="text" v-model="record.description" />
                    <span v-if="validationErrors.description" class="text-red-500 text-xs">{{ validationErrors.description }}</span>
                </div>
            </div>
            <AlertDialogFooter>
                <AlertDialogCancel @click="isDialogOpen = false">Cancelar</AlertDialogCancel>
                <Button @click="applyChanges">
                    Salvar alterações
                </Button>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>

<script setup lang="ts"> 
import { Product } from '@plannerate/types/segment';
import { Check, Trash2, Upload } from 'lucide-vue-next';
import { reactive, ref, onMounted } from 'vue';
import { useProductService } from '@plannerate/services/productService';
import { toast } from 'vue-sonner'; 

const props = defineProps<{
    product: Product
}>();
const emit = defineEmits<{
    (e: 'update:product', product: Product): void
}>();
const record = reactive(props.product || {});
const newImage = ref<File | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const originalImageUrl = ref<string>('');
const previewImageUrl = ref<string>('');
const { uploadProductImage, updateProduct } = useProductService();
const isDialogOpen = ref(false);
const validationErrors = reactive<{ [key: string]: string | null }>({});

onMounted(() => {
    originalImageUrl.value = props.product?.image_url || '';
});

const triggerFileInput = (e: Event) => {
    e.preventDefault(); 
    fileInput.value?.click(); 
};

const selectImage = (e: Event) => {
    e.preventDefault();
    const file = (e.target as HTMLInputElement).files?.[0] || null;
    newImage.value = file;
    if (file) {
        previewImageUrl.value = URL.createObjectURL(file);
    } else {
        previewImageUrl.value = '';
    }
};

const applyImage = async (e: Event) => {
    e.preventDefault();
    if (newImage.value) {
        try {
            const response = await uploadProductImage(record.id, newImage.value); 
            if (response.status === 200) { 
                record.image_url = response.image_url;
                toast.success('Imagem enviada com sucesso!');
            } else {
                toast.error('Erro ao enviar imagem.');
            }
        } catch (error: any) {
            if (error?.response?.status === 404) {
                toast.error(`Rota /api/products/${record.id}/image não existe. Crie esta rota no backend para receber uploads de imagem.`);
            } else {
                toast.error('Erro ao enviar imagem.');
            }
        }
    }
};

const removeImage = (e: Event) => { 
     // Impedir a propagação do evento para garantir que o modal não fecha
     e.stopPropagation(); 
    if (props.product) {
        const updatedProduct = {
            ...props.product,
            image_url: ''
        };
        emit('update:product', updatedProduct);
    }
    newImage.value = null;
};

const cancelImageSelection = (e: Event) => {
    e.preventDefault();
    newImage.value = null;
    previewImageUrl.value = '';
    // Restaura a imagem original no campo de preview
};

const applyChanges = async (e: Event) => {
    e.preventDefault();
    // Limpa erros anteriores
    Object.keys(validationErrors).forEach(key => validationErrors[key] = null);
    try {
        const response = await updateProduct(record.id, record);
        toast.success('Produto atualizado com sucesso!');
        emit('update:product', response);
        isDialogOpen.value = false; // Fecha o modal só em caso de sucesso
    } catch (error: any) {
        if (error?.response?.status === 422) {
            const errors = error.response.data.errors || {};
            Object.entries(errors).forEach(([field, messages]) => {
                validationErrors[field] = Array.isArray(messages) ? messages[0] : messages;
            });
            toast.error('Corrija os erros de validação.');
        } else {
            toast.error('Erro ao atualizar produto.');
        }
        // Modal permanece aberto
    }
};

const handleImageError = (event: Event, product: Product) => {
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
</script>
