<template>
    <Dialog v-model:open="isDialogOpen">
        <DialogTrigger as-child>
            <slot />
        </DialogTrigger>
        <DialogContent class="  md:max-w-6xl w-full   max-h-[90vh] overflow-y-auto p-0">
            <div class="p-6 w-full">
                <DialogHeader>
                    <DialogTitle>Editar Produto</DialogTitle>
                    <DialogDescription>
                        Faça as alterações necessárias no produto. As mudanças serão aplicadas diretamente na base de
                        dados.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
                    <div class="grid gap-2 col-span-full">
                        <ImageField v-model="record.image_url" label="Imagem" id="product-image"
                            :alt="record.name || 'Produto'" :error="validationErrors.image_url"
                            @crop="handleImageCrop" />
                    </div>
                    <div class="flex flex-col gap-2 col-span-full md:col-span-2">
                        <Label>Nome</Label>
                        <Input type="text" v-model="record.name" />
                        <span v-if="validationErrors.name" class="text-red-500 text-xs">{{ validationErrors.name
                            }}</span>
                    </div>
                    <div class="flex flex-col gap-2 col-span-full md:col-span-1">
                        <Label>EAN</Label>
                        <Input type="text" v-model="record.ean" readonly />
                        <span v-if="validationErrors.ean" class="text-red-500 text-xs">{{ validationErrors.ean }}</span>
                    </div>

                    <div class="flex flex-col gap-2 col-span-full">
                        <ProductDimensions :field="{
                            name: 'dimensions',
                            label: 'Dimensões',
                            description: 'Dimensões do produto',
                            required: true,
                            units: {
                                dimensions: 'cm',
                                weight: 'kg'
                            }
                        }" :id="record.id" v-model="record.dimensions" />
                    </div>
                    <div class="flex flex-col gap-2 col-span-full">
                        <MercadologicoSelector :field="{
                            name: 'mercadologico_nivel',
                            label: 'Mercadológico',
                            apiUrl: '/api/categories/mercadologico',
                            valueKey: 'id',
                            labelKey: 'name'
                        }" :id="record.id" v-model="record.mercadologico_nivel" />
                    </div>
                    <div class="flex flex-col gap-2 col-span-full">
                        <ProductAdditionalData :field="{
                            name: 'additional_data',
                            label: 'Dados Adicionais',
                            description: 'Dados adicionais do produto',
                            required: true
                        }" :id="record.id" v-model="record.product_additional_data" />
                    </div>
                    <div class="flex flex-col gap-2 col-span-full">
                        <Label>Descrição</Label>
                        <Input type="text" v-model="record.description" />
                        <span v-if="validationErrors.description" class="text-red-500 text-xs">{{
                            validationErrors.description }}</span>
                    </div>
                </div>
                <DialogFooter class="mt-6">
                    <Button variant="outline" @click="isDialogOpen = false">Cancelar</Button>
                    <Button @click="applyChanges">
                        Salvar alterações
                    </Button>
                </DialogFooter>
            </div>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { Product, MercadologicoNivel } from '@plannerate/types/segment';
import { reactive, ref, onMounted } from 'vue';
import { useProductService } from '@plannerate/services/productService';
import { toast } from 'vue-sonner';
import type { CropperResult } from 'vue-advanced-cropper';
import ImageField from '@plannerate/components/fields/ImageField.vue';
import MercadologicoSelector from '@/components/form/fields/MercadologicoSelector.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ProductDimensions from '@/components/form/fields/ProductDimensions.vue';
import ProductAdditionalData from '@/components/form/fields/ProductAdditionalData.vue';
const props = defineProps<{
    product: Product & { mercadologico_nivel?: MercadologicoNivel | null, product_additional_data?: Record<string, any> }
}>();
const emit = defineEmits<{
    (e: 'update:product', product: Product): void
}>();

// Inicializa o record com valores seguros
const record = reactive({
    ...props.product,
    product_additional_data: props.product?.product_additional_data || {},
    // Garante que mercadologico_nivel seja sempre um objeto válido
    mercadologico_nivel: props.product?.mercadologico_nivel && typeof props.product.mercadologico_nivel === 'object'
        ? props.product.mercadologico_nivel
        : {
            mercadologico_nivel_1: null,
            mercadologico_nivel_2: null,
            mercadologico_nivel_3: null,
            mercadologico_nivel_4: null,
            mercadologico_nivel_5: null,
            mercadologico_nivel_6: null,
        }
});

const originalImageUrl = ref<string>('');
const { uploadProductImage, updateProduct } = useProductService();
const isDialogOpen = ref(false);
const validationErrors = reactive<{ [key: string]: string | null }>({});

onMounted(() => {
    originalImageUrl.value = props.product?.image_url || 'https://placehold.co/400x600.png';
    if (!record.image_url) {
        record.image_url = 'https://placehold.co/400x600.png';
    }
});

const applyChanges = async (e: Event) => {
    e.preventDefault();
    // Limpa erros anteriores
    Object.keys(validationErrors).forEach(key => validationErrors[key] = null);
    try {
        const response = await updateProduct(record.id, record);
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

const handleImageCrop = async (result: CropperResult) => {
    const canvas = result.canvas;
    if (!canvas) {
        toast.error('Erro ao processar imagem. Tente novamente.');
        return;
    }

    try {
        const blob = await new Promise<Blob>((resolve) => {
            canvas.toBlob((blob) => {
                if (blob) resolve(blob);
            }, 'image/jpeg');
        });

        const file = new File([blob], 'cropped-image.jpg', { type: 'image/jpeg' });
        const response = await uploadProductImage(record.id, file);

        if (response.status === 200) {
            record.image_url = response.image_url;
            toast.success('Imagem atualizada com sucesso!');
        } else {
            toast.error('Erro ao atualizar imagem.');
        }
    } catch (error: any) {
        if (error?.response?.status === 404) {
            toast.error(`Rota /api/products/${record.id}/image não existe. Crie esta rota no backend para receber uploads de imagem.`);
        } else {
            toast.error('Erro ao atualizar imagem.');
        }
    }
};
</script>
