

import { Layer } from '@/types/segment';
import { selectedLayerIds, selectedLayer } from '../state';

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



