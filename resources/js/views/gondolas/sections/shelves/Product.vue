<template> 
        <!-- Imagem do produto, com fallback para placeholder dinâmico -->
        <img
            :src="imageUrl"
            @error="handleImageError"
            alt="Imagem do Produto"
            :style="{...imageStyle, ...productStyle}"
        />  
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
 * URL do placeholder dinâmico baseado nas dimensões do produto
 */
const placeholderUrl = computed(() => { 
    // Garante um tamanho mínimo para o placeholder não quebrar 
    return `/img/fall4.jpg`;
});

/**
 * URL da imagem do produto com fallback para placeholder
 */
const imageUrl = computed(() => {
    // Verifica se o produto tem uma URL de imagem válida
    if (props.product?.image_url && typeof props.product.image_url === 'string') {
        return props.product.image_url;
    }
    return placeholderUrl.value;
});

/**
 * Estilo da imagem - aplica object-fit: contain apenas para placeholder
 */
const imageStyle = computed(() => {
    // Verifica se não há imagem do produto (vai usar placeholder)
    const hasProductImage = props.product?.image_url && typeof props.product.image_url === 'string';
    
    if (!hasProductImage) {
        return {
            width: '100%',
            height: '100%',
            objectFit: 'contain' as const,
            backgroundColor: '#f5f5f5',
            border: '1px solid #ddd'
        };
    }
    
    return {
        width: '100%',
        height: '100%'
    };
});

/**
 * Manipula erros de carregamento de imagem
 */
const handleImageError = (event: Event) => {
    const target = event.target as HTMLImageElement;
    
    // Evita loop infinito caso o próprio placeholder falhe
    if (target.src !== placeholderUrl.value) {
        console.warn(`Erro ao carregar imagem do produto: ${target.src}`);
        target.src = placeholderUrl.value;
        // Aplica estilos do placeholder quando há erro
        target.style.objectFit = 'contain';
        target.style.backgroundColor = '#f5f5f5';
        target.style.border = '1px solid #ddd';
    } else {
        console.error('Erro ao carregar placeholder, usando imagem padrão');
        // Fallback final: imagem inline base64 simples
        target.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNTAiIGhlaWdodD0iNTAiIHZpZXdCb3g9IjAgMCA1MCA1MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjUwIiBoZWlnaHQ9IjUwIiBmaWxsPSIjRkY4MDgwIi8+Cjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBkb21pbmFudC1iYXNlbGluZT0ibWlkZGxlIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmaWxsPSJ3aGl0ZSIgZm9udC1zaXplPSIxNCI+WDwvdGV4dD4KPC9zdmc+';
        target.style.objectFit = 'contain';
        target.style.backgroundColor = '#f5f5f5';
        target.style.border = '1px solid #ddd';
    }
};
 
 
/**
 * Obtém o resultado da análise ABC do produto
 */
const analysisResult = computed(() => {
    // Verifica se o produto tem EAN antes de buscar o resultado
    if (!props.product?.ean) {
        return null;
    }
    return analysisResultStore.getById(props.product.ean);
});

/**
 * Estilo do produto com verificações de segurança
 */
const productStyle = computed(() => {
    // Aplica classes CSS baseadas no resultado da análise ABC
    if (analysisResult.value && productRef.value) {
        // Remove classes antigas antes de adicionar a nova
        productRef.value.classList.remove('A', 'B', 'C');
        
        if (analysisResult.value.abcClass) {
            productRef.value.classList.add(analysisResult.value.abcClass);
        }
    } else if (productRef.value) {
        // Remove todas as classes se não há resultado de análise
        productRef.value.classList.remove('A', 'B', 'C');
    }
    
    // Verifica se o produto tem dimensões válidas
    const width = props.product?.width || 50; // fallback para 50px
    const height = props.product?.height || 50; // fallback para 50px
    
    return {
        width: `${width * props.scaleFactor}px`,
        height: `${height * props.scaleFactor}px`, 
        flexShrink: 0, 
    };
});
 

/**
 * Manipula o clique no produto
 */
const handleProductClick = () => {
    // Não precisa chamar productActiveTrigger aqui, pois já é reativo através do computed
    // A função productActiveTrigger() já é chamada automaticamente quando isSelected muda 
    editorStore.clearSelectedSection(); // Limpa seleção de camadas ao selecionar prateleira
    
    // Aqui você pode adicionar lógica adicional para quando o produto é clicado
    // Por exemplo, emitir um evento ou atualizar o estado do editor
};

/**
 * Configura event listeners quando o componente é montado
 */
onMounted(() => {
    
});

</script>

<style scoped>
.product {
    transition: transform 0.2s;
}

.product:hover {
    transform: scale(1.02);
    z-index: 0;
    box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
    border: 2px solid blue;
    box-shadow: 0 0 5px rgba(0, 0, 255, 0.5);
    box-sizing: border-box;
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
