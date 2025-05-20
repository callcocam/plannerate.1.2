// Composable for target stock calculations based on the provided VBA logic

export interface Product {
  ean: string;
  name: string;
  classification: string;
  sales: number[];
  currentStock: number;
  [key: string]: any; // For extra fields
}

export interface ServiceLevel {
  classification: string;
  level: number;
}

export interface Replenishment {
  classification: string;
  coverageDays: number;
}

export interface StockAnalysis extends Product {
  averageSales: number;
  standardDeviation: number;
  variability: number;
  serviceLevel: number;
  zScore: number;
  safetyStock: number;
  minimumStock: number;
  targetStock: number;
  allowsFacing: boolean;
  highVariability: boolean;
}

export function useTargetStock(
  products: Product[],
  serviceLevels: ServiceLevel[],
  replenishmentParams: Replenishment[]
): StockAnalysis[] {
  // Step 1: Group products by unique combination of EAN, name and classification
  const uniqueProducts = new Map<string, Product>();
  
  products.forEach(product => {
    const key = `${product.ean}|${product.name}|${product.classification}`;
    if (!uniqueProducts.has(key)) {
      uniqueProducts.set(key, product);
    }
  });

  // Step 2: Calculate stock analysis for each unique product
  const analyzed: StockAnalysis[] = Array.from(uniqueProducts.values()).map(product => {
    // Calculate average sales and standard deviation
    const sales = product.sales;
    const averageSales = sales.reduce((sum, sale) => sum + sale, 0) / sales.length;
    
    // Calculate standard deviation
    const squareDiffs = sales.map(sale => Math.pow(sale - averageSales, 2));
    const standardDeviation = Math.sqrt(squareDiffs.reduce((sum, diff) => sum + diff, 0) / sales.length);
    
    // Calculate variability
    const variability = averageSales > 0 ? standardDeviation / averageSales : 0;
    
    // Get service level from parameters
    const serviceLevel = serviceLevels.find(sl => sl.classification === product.classification)?.level || 0;
    
    // Validate service level
    if (serviceLevel < 0.5 || serviceLevel >= 1) {
      throw new Error(`Nível de serviço inválido para o produto: ${product.name}
        Classificação: ${product.classification}
        Valor informado: ${serviceLevel}
        Por favor, corrija o valor nos parâmetros de nível de serviço.`);
    }
    
    // Calculate z-score based on service level
    const zScore = calculateZScore(serviceLevel);
    
    // Get coverage days from parameters
    const coverageDays = replenishmentParams.find(rp => rp.classification === product.classification)?.coverageDays || 0;
    
    // Calculate stocks
    const safetyStock = zScore * standardDeviation;
    const minimumStock = averageSales * coverageDays;
    const targetStock = minimumStock + safetyStock;
    
    // Determine if allows facing
    const allowsFacing = product.currentStock >= targetStock;
    
    return {
      ...product,
      averageSales: Number(averageSales.toFixed(2)),
      standardDeviation: Number(standardDeviation.toFixed(2)),
      variability: Number(variability.toFixed(2)),
      serviceLevel,
      zScore: Number(zScore.toFixed(3)),
      safetyStock: Math.round(safetyStock),
      minimumStock: Math.round(minimumStock),
      targetStock: Math.round(targetStock),
      allowsFacing,
      highVariability: variability > 1
    };
  });

  return analyzed;
}

// Helper function to calculate z-score from service level
function calculateZScore(serviceLevel: number): number {
  // Using the inverse of the standard normal cumulative distribution
  // This is an approximation - for production use, consider using a statistical library
  const p = serviceLevel;
  const a1 = -39.6968302866538;
  const a2 = 220.946098424521;
  const a3 = -275.928510446969;
  const a4 = 138.357751867269;
  const a5 = -30.6647980661472;
  const a6 = 2.50662827745924;
  
  const b1 = -54.4760987982241;
  const b2 = 161.585836858041;
  const b3 = -155.698979859887;
  const b4 = 66.8013118877197;
  const b5 = -13.2806815528857;
  
  const c1 = -7.78489400243029E-03;
  const c2 = -0.322396458041136;
  const c3 = -2.40075827716184;
  const c4 = -2.54973253934373;
  const c5 = 4.37466414146497;
  const c6 = 2.93816398269878;
  
  const d1 = 7.78469570904146E-03;
  const d2 = 0.32246712907004;
  const d3 = 2.445134137143;
  const d4 = 3.75440866190742;
  
  const p_low = 0.02425;
  const p_high = 1 - p_low;
  
  let q, r, z;
  
  if (p < p_low) {
    q = Math.sqrt(-2 * Math.log(p));
    z = (((((c1 * q + c2) * q + c3) * q + c4) * q + c5) * q + c6) /
        ((((d1 * q + d2) * q + d3) * q + d4) * q + 1);
  } else if (p <= p_high) {
    q = p - 0.5;
    r = q * q;
    z = (((((a1 * r + a2) * r + a3) * r + a4) * r + a5) * r + a6) * q /
        (((((b1 * r + b2) * r + b3) * r + b4) * r + b5) * r + 1);
  } else {
    q = Math.sqrt(-2 * Math.log(1 - p));
    z = -(((((c1 * q + c2) * q + c3) * q + c4) * q + c5) * q + c6) /
        ((((d1 * q + d2) * q + d3) * q + d4) * q + 1);
  }
  
  return z;
} 