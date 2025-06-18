import type { Section, Status } from './sections';

// Interface principal para Gondola
interface Gondola {
    id: string;                  // ID único da gôndola
    planogram_id: string;        // ID do planograma ao qual pertence
    name: string;                // Nome da gôndola (ex: "GND-2405-1234")
    location?: 'Center' | 'Wall' | string; // Localização (Centro, Parede, etc.)
    side?: 'A' | 'B' | string;   // Lado (A ou B)
    flow?: 'left_to_right' | 'right_to_left' | string; // Fluxo dos produtos
    alignment?: 'left' | 'right' | 'center' | 'justify' | string; // ADICIONADO
    scale_factor?: number;       // Fator de escala para visualização (ex: 3)
    status: Status;              // Status da gôndola (usando o tipo Status)
    sections: Section[];         // Array de seções contidas na gôndola
    tenant_id?: string;          // ID do tenant (opcional)
    user_id?: string;            // ID do usuário que criou (opcional)
    created_at?: string;         // Timestamp de criação (opcional)
    updated_at?: string;         // Timestamp de atualização (opcional)
    linked_map_gondola_id?: string; // ID da gôndola no mapa
    linked_map_gondola_category?: string; // Categoria da gôndola no mapa
    // Outros campos conforme necessário
}

export type { Gondola }; 