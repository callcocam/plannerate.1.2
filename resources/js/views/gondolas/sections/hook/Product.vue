<template>
    <div class="product relative" :style="productStyle">
        <!-- Aqui você pode adicionar a representação visual do produto -->
        <!-- Pode ser uma imagem, um retângulo colorido, ou qualquer outro elemento visual -->
        <slot name="depth-count"></slot>
        <div class="product-content relative overflow-auto" :style="contentStyle"></div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    product: any;
    scaleFactor: number;
    index: number;
    shelfDepth: number;
}>();

// Estilo do produto
const productStyle = computed(() => {
    return {
        width: `${props.product.width * props.scaleFactor}px`,
        height: `${props.product.height * props.scaleFactor}px`,
        position: 'relative' as const,
        boxSizing: 'border-box' as const,
        backgroundImage: props.product.image_url ? `url(${props.product.image_url})` : 'none',
        backgroundSize: 'contain',
        backgroundRepeat: 'no-repeat',
        backgroundPosition: 'center',
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
</style>
