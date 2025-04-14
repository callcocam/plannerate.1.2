<template>
    <div class="product-group" :style="productGroupStyle">
        <!-- Renderize uma representação visual dos produtos -->
        <div 
            v-for="index in productCount" 
            :key="index"
            class="product-item"
            :style="getProductStyle(index)"
        >
            <Product
                :product="product"
                :scale-factor="scaleFactor"
                :product-spacing="0"  
            />
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
    const totalWidth = (props.product.width * props.quantity) * props.scaleFactor + 
                      (props.quantity > 1 ? props.productSpacing * (props.quantity - 1) * props.scaleFactor : 0);
    
    return {
        display: 'flex',
        width: `${totalWidth}px`,
        height: '100%',
        position: 'relative' as const,
    };
});

// Função para calcular o estilo de cada produto individual
const getProductStyle = (index: number) => {
    const productWidth = props.product.width * props.scaleFactor;
    const itemSpacing = props.productSpacing * props.scaleFactor;
    
    // Se tivermos muitos produtos (mais que MAX_VISUAL_PRODUCTS),
    // ajustamos o espaçamento para distribuir proporcionalmente
    let adjustedSpacing = itemSpacing;
    if (props.quantity > MAX_VISUAL_PRODUCTS) {
        const actualWidth = (props.product.width * props.quantity) * props.scaleFactor + 
                          (props.quantity > 1 ? props.productSpacing * (props.quantity - 1) * props.scaleFactor : 0);
        const visualWidth = productWidth * productCount.value + 
                          (productCount.value > 1 ? itemSpacing * (productCount.value - 1) : 0);
        
        // Se não houver produtos suficientes para representar visualmente,
        // ajustamos o espaçamento para manter a largura total correta
        if (visualWidth < actualWidth && productCount.value > 1) {
            adjustedSpacing = (actualWidth - (productWidth * productCount.value)) / (productCount.value - 1);
        }
    }
    
    return {
        width: `${productWidth}px`,
        marginRight: index < productCount.value ? `${adjustedSpacing}px` : '0',
    };
};
</script>

<style scoped>
.product-group {
    overflow: hidden; /* Para garantir que os produtos não ultrapassem o container */
}

.product-item {
    flex-shrink: 0;
}
</style>