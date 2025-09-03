export interface Module {
  id: string;
  name: string;
  element: HTMLElement;
  index: number;
}

export interface CaptureResult {
  id: string;
  name: string;
  imageData: string;
  element: HTMLElement;
}

export interface PrintConfig {
  scale?: number;
  format?: string;
  orientation?: string;
  backgroundColor?: string;
  margins?: {
    top: number;
    right: number;
    bottom: number;
    left: number;
  };
  quality?: number;
}

export interface BrowserCompatibility {
  domToImage: boolean;
  canvas: boolean;
  download: boolean;
  print: boolean;
  supported: boolean;
}

export declare class PrintService {
  detectModules(): Module[];
  captureElement(element: HTMLElement, config?: PrintConfig): Promise<string>;
  captureModules(moduleIds: string[], config?: PrintConfig): Promise<CaptureResult[]>;
  generatePDF(captures: CaptureResult[], config?: PrintConfig): Promise<any>;
  printDirect(captures: CaptureResult[], config?: PrintConfig): Promise<void>;
  checkBrowserCompatibility(): BrowserCompatibility;
}

export declare const printService: PrintService;
