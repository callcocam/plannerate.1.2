<template>
    <div class="product-group" >
        <!-- Renderize uma representação visual dos produtos -->
        <div v-for="index in productCount" :key="index" class="product-item" :style="productGroupStyle">
            <Product :product="product" :scale-factor="scaleFactor" :product-spacing="0" />
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import Product from './Product.vue';

const props = defineProps<{
    product: any;
    quantity: number;
    scaleFactor: number;
    productSpacing: number;
}>();

// Número de produtos a serem renderizados visualmente
// Para quantidades maiores, podemos limitar para melhor performance
const MAX_VISUAL_PRODUCTS = 30; // Limite para evitar problemas de desempenho

const productCount = computed(() => {
    // Para quantidades muito grandes, podemos optar por mostrar um número limitado
    // de produtos para representação visual
    return Math.min(props.quantity, MAX_VISUAL_PRODUCTS);
});

// Estilo do container do grupo de produtos
const productGroupStyle = computed(() => {
    const productWidth = props.product.width * props.scaleFactor;
    // Calcula a largura total (produtos + espaçamentos)
    const totalWidth = props.product.width * props.quantity * props.scaleFactor;

    return {
        display: 'flex',
        width: `${totalWidth}px`,
        height: '100%',
        position: 'relative' as const,
    };
});
 
</script>

<style scoped>
.product-group {
    overflow: hidden; /* Para garantir que os produtos não ultrapassem o container */
}

.product-item {
    flex-shrink: 0;
}
</style>
