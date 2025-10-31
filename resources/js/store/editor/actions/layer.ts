

import { Layer } from '@/types/segment';
import { selectedLayerIds, selectedLayer, currentGondola } from '../state';
import { recordChange } from '../history';

export function selectLayer(layerId: string) {
    selectedLayerIds.value.add(layerId);
}

export function isSelectedLayer(layerId: string) {
    return selectedLayerIds.value.has(layerId);
}
export function setSelectedLayer(layer: Layer) {
    selectedLayer.value = layer;
}
export function deselectLayer(layerId: string) {
    selectedLayerIds.value.delete(layerId);
}
export function isDeselectedLayer(layerId: string) {
    return !selectedLayerIds.value.has(layerId);
}

export function toggleLayerSelection(layerId: string) {
    if (selectedLayerIds.value.has(layerId)) {
        selectedLayerIds.value.delete(layerId);
    } else {
        selectedLayerIds.value.add(layerId);
    }
}
export function isToggleSelectedLayer(layerId: string) {
    if (selectedLayerIds.value.has(layerId)) {
        selectedLayerIds.value.delete(layerId);
    } else {
        selectedLayerIds.value.add(layerId);
    }
}

export function getSelectedLayerIds() {
    return selectedLayerIds.value;
}

export function clearLayerSelection() {
    selectedLayerIds.value.clear();
}

/**
 * Atualiza a image_url de um produto específico em todos os segmentos da gôndola atual
 * @param productId ID do produto a ser atualizado
 * @param newImageUrl Nova URL da imagem
 */
export function updateProductImage(productId: string, newImageUrl: string) {
    const gondola = currentGondola.value;
    
    if (!gondola) {
        console.warn('Nenhuma gôndola atual encontrada para atualizar imagem do produto.');
        return;
    }

    let updated = false;

    // Percorre toda a estrutura da gôndola para encontrar e atualizar o produto
    gondola.sections?.forEach((section) => {
        section.shelves?.forEach((shelf) => {
            shelf.segments?.forEach((segment) => {
                // Verifica se o segmento tem um layer com o produto correspondente
                if (segment.layer?.product?.id === productId) {
                    // Cria uma nova referência do produto para forçar reatividade do Vue
                    segment.layer.product = {
                        ...segment.layer.product,
                        image_url: newImageUrl
                    };
                    console.log(`Imagem do produto ${productId} atualizada com sucesso no segmento ${segment.id}.`);
                    updated = true;
                }
            });
        });
    });

    if (updated) {
        console.log(`Imagem do produto ${productId} atualizada com sucesso no store.`);
        recordChange(false); // Registra a mudança no histórico sem criar snapshot imediato
    } else {
        console.warn(`Produto ${productId} não encontrado na gôndola atual.`);
    }
}



