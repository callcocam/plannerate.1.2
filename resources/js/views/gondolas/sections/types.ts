
/**
 * Interfaces para tipagem do componente
 */

import { Shelf as ShelfSetting } from "../../../types/shelves";

// Interface para o produto
interface Product {
    id: string | number;
    name: string;
    image: string;
    height: number;
    [key: string]: any; // Para propriedades adicionais do produto
}

// Interface para a camada que representa o produto no segmento
interface Layer {
    product_id?: string | number;
    product_name?: string;
    product_image?: string;
    product: Product;
    height: number;
    spacing: number;
    quantity: number;
    status: string;
    [key: string]: any; // Para propriedades adicionais da camada
}

// Interface para um segmento individual
interface Segment {
    id: string;
    width: number;
    ordering: number;
    quantity: number;
    spacing: number;
    position: number;
    preserveState: boolean;
    status: string;
    layer: Layer;
    [key: string]: any; // Para propriedades adicionais do segmento
}

// Interface para uma prateleira
interface Shelf extends ShelfSetting {
    id: string;
    shelf_height: number;
    shelf_position: number;
    quantity: number;
    spacing: number;
    segments: Segment[];
    [key: string]: any; // Para propriedades adicionais da prateleira
}

// Interface para a prateleira com a camada 
            
interface Section {
    id: string;
    gondola_id: string;
    name: string;
    slug: string;
    width: number;
    height: number;
    num_shelves: number;
    base_height: number;
    base_depth: number;
    base_width: number; 
    hole_height: number;
    hole_width: number;
    hole_spacing: number;
    shelf_height: number;
    cremalheira_width: number;
    ordering: number;
    settings: {
        [key: string]: any; // Para propriedades adicionais da seção
    };
    shelves: Shelf[];
    [key: string]: any; // Para propriedades adicionais da seção
}
interface LayerSegment extends Layer {
    segement: Segment;
}
export type { Product, Layer, Segment, Shelf, Section , LayerSegment };