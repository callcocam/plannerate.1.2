<template>
    <div class="product relative mb-2" :style="productStyle" @click="handleProductClick" ref="productRef" 
    >
        <!-- Aqui você pode adicionar a representação visual do produto -->
        <!-- Pode ser uma imagem, um retângulo colorido, ou qualquer outro elemento visual -->
        <slot name="depth-count"></slot>
        <div class="product-content relative overflow-auto" :style="contentStyle"></div>
    </div>
</template>



<script setup lang="ts">
import { Layer } from '@/types/segment';
import { computed, onMounted, ref } from 'vue'; 
import { useEditorStore } from '@plannerate/store/editor';
import { useAnalysisResultStore } from '@plannerate/store/editor/analysisResult';
const props = defineProps<{
    product: any;
    scaleFactor: number;
    index: number;
    shelfDepth: number;
    layer: Layer;
}>();
 
const editorStore = useEditorStore();
const analysisResultStore = useAnalysisResultStore();
const productRef = ref<HTMLDivElement | null>(null);

/**
 * Verifica se o layer está selecionado
 */
 const isSelected = computed(() => {
    const layerId = props.layer.id;
    // Usa selectedLayerIds (nome corrigido e agora existente)
    return editorStore.isSelectedLayer(String(layerId));
});

const productActiveTrigger = () => {
    if (isSelected.value) {
       
        return {
            border: '1px solid red',
        }
    }
    return {
        border: '1px solid transparent',
    }
};
const analysisResult = computed(() => {
    return analysisResultStore.getById(props.product.ean);
});
// Estilo do produto
const productStyle = computed(() => {
    let image_url = props.product.image_url;
    // verifyImageExists(image_url).then((exists) => {
    //     if (!exists) {
    //         // Pegar as iniciais do nome do produto
    //         const initials = props.product.name
    //             .split(' ')
    //             .map((word: string) => word.charAt(0).toUpperCase())
    //             .join('')
    //             .slice(0, 2); // Limita a 2 letras (opcional)

    //         // Exemplo de uso com placehold.co
    //         image_url = `https://placehold.co/400x600?text=${initials}`;
    //     }
    // });  
    if (analysisResult.value) {
        productRef.value?.classList.add(analysisResult.value.abcClass);
    }
    return {
        width: `${props.product.width * props.scaleFactor}px`,
        height: `${props.product.height * props.scaleFactor}px`,
        position: 'relative' as const,
        boxSizing: 'border-box' as const, 
        backgroundImage: `url(${image_url})`,
        backgroundSize: 'contain',
        backgroundRepeat: 'no-repeat',
        backgroundPosition: 'center',
        ...productActiveTrigger
    };
});

// Estilo do conteúdo interno do produto
const contentStyle = computed(() => {
    // Calcula dimensões proporcionais considerando o tamanho do produto
    const maxWidth = Math.min(props.product.width * props.scaleFactor * 0.9, 100);
    const maxHeight = Math.min(props.product.height * props.scaleFactor * 0.9, 100);

    return {
        maxWidth: `${maxWidth}px`,
        maxHeight: `${maxHeight}px`,
        overflow: 'hidden',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
    };
});

const handleProductClick = () => {
    productActiveTrigger();
};

onMounted(() => {
    if (productRef.value) {
        productRef.value.addEventListener('click', handleProductClick);
    }
});

</script>

<style scoped>
.product {
    transition: transform 0.2s;
}

.product:hover {
    transform: scale(1.02);
    z-index: 5;
    box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
}

.product-image {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.product-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #e0e0e0;
    color: #666;
    font-weight: bold;
    font-size: 16px;
}
.A {
    background-color: #00ff00;
}
.B {
    background-color: #0000ff;
}
.C {
    background-color: #ff0000;
}
</style>
