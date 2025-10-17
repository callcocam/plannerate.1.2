export { default as ZoneConfiguration } from './ZoneConfiguration.vue'
export { default as ZoneEditor } from './ZoneEditor.vue'
export { default as ZonePreview } from './ZonePreview.vue'
export { default as ZoneRuleForm } from './ZoneRuleForm.vue'

// Tipos compartilhados
export interface ZoneRules {
  priority: string;
  exposure_type: string;
  abc_filter?: string[];
  min_margin_percent?: number;
  max_margin_percent?: number;
}

export interface Zone {
  id: string;
  name: string;
  shelf_indexes: number[];
  performance_multiplier: number;
  rules: ZoneRules;
}

