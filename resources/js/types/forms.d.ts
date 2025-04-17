// types/forms.d.ts

// --- Gondola Creation (views/gondolas/Create.vue) ---

/**
 * Interface para o objeto formData reativo usado no wizard de criação de gôndola.
 * Combina dados da gôndola e configurações da primeira seção/prateleiras.
 * (camelCase, como no formulário)
 */
export interface GondolaFormData {
    planogram_id: string;
    gondolaName: string;
    location: 'Center' | 'Wall' | string;
    side: 'A' | 'B' | string;
    flow: 'left_to_right' | 'right_to_left' | string;
    scaleFactor: number;
    status: 'published' | 'draft' | string; // Simplificado para string literal por enquanto

    // Section/Module Settings
    numModules: number;
    width: number; // Largura da seção
    height: number; // Altura da seção

    // Base Settings
    baseHeight: number;
    baseWidth: number;
    baseDepth: number;

    // Rack Settings
    rackWidth: number;
    holeHeight: number;
    holeWidth: number;
    holeSpacing: number;

    // Default Shelf Settings
    shelfWidth: number;
    shelfHeight: number;
    shelfDepth: number;
    numShelves: number;
    productType: 'normal' | 'hook' | string; // Tipo de produto para prateleiras iniciais
}

/**
 * Interface para o payload enviado à API para criar uma nova gôndola.
 * (snake_case, como esperado pela API)
 */
export interface GondolaCreatePayload {
    planogram_id: string;
    name: string;
    location: string;
    side: string;
    flow: string;
    scale_factor: number;
    status: string;

    // Dados aninhados para a primeira seção
    section: {
        name: string;
        width: number;
        height: number;
        base_height: number;
        base_width: number;
        base_depth: number;
        cremalheira_width: number;
        hole_height: number;
        hole_width: number;
        hole_spacing: number;
        num_modulos: number;

        // Configurações aninhadas para as prateleiras iniciais
        shelf_config: {
            num_shelves: number;
            shelf_width: number;
            shelf_height: number;
            shelf_depth: number;
            product_type: string;
        };
        settings?: Record<string, any>; // Configurações adicionais da seção (opcional)
    };
}


// --- Section Creation (views/gondolas/AddSectionModal.vue) ---

/**
 * Interface para o objeto formData reativo usado no modal de adição de seção.
 * (camelCase, como no formulário)
 */
export interface SectionFormData {
    name: string;
    code: string; // Código da seção
    gondola_id: string;
    num_modulos: number;
    width: number;
    height: number;
    base_height: number;
    base_width: number;
    base_depth: number;
    cremalheira_width: number;
    hole_height: number;
    hole_width: number;
    hole_spacing: number;
    // Configurações para prateleiras a serem criadas com a seção
    shelf_width: number;
    shelf_height: number;
    shelf_depth: number;
    num_shelves: number;
    product_type: 'normal' | 'hook' | string;
}

/**
 * Interface para o payload enviado à API para criar uma nova seção.
 * (Assumindo snake_case para a API, espelhando SectionFormData)
 */
export interface SectionCreatePayload {
    name: string;
    code: string;
    gondola_id: string;
    num_modulos: number;
    width: number;
    height: number;
    base_height: number;
    base_width: number;
    base_depth: number;
    cremalheira_width: number;
    hole_height: number;
    hole_width: number;
    hole_spacing: number;
    // Configurações para prateleiras a serem criadas com a seção
    shelf_width: number;
    shelf_height: number;
    shelf_depth: number;
    num_shelves: number;
    product_type: string;
} 