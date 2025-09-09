import { InjectionToken } from '@angular/core';

// Performance optimization configuration
export interface PerformanceConfig {
  // Lazy Loading Configuration
  lazyLoading: {
    enabled: boolean;
    intersectionThreshold: number;
    rootMargin: string;
    preloadDistance: number;
  };
  
  // Image Optimization
  imageOptimization: {
    enabled: boolean;
    formats: string[];
    quality: number;
    placeholder: 'blur' | 'empty' | 'data-url';
    sizes: {
      thumbnail: number;
      small: number;
      medium: number;
      large: number;
    };
  };
  
  // Code Splitting
  codeSplitting: {
    enabled: boolean;
    chunkSize: number;
    maxChunks: number;
    preloadChunks: string[];
  };
  
  // Caching Strategy
  caching: {
    enabled: boolean;
    strategies: {
      api: 'cache-first' | 'network-first' | 'stale-while-revalidate';
      static: 'cache-first' | 'network-first' | 'stale-while-revalidate';
      images: 'cache-first' | 'network-first' | 'stale-while-revalidate';
    };
    ttl: {
      api: number;
      static: number;
      images: number;
    };
  };
  
  // Bundle Optimization
  bundleOptimization: {
    enabled: boolean;
    treeShaking: boolean;
    minification: boolean;
    compression: 'gzip' | 'brotli' | 'both';
    splitVendors: boolean;
  };
  
  // Resource Hints
  resourceHints: {
    enabled: boolean;
    preload: string[];
    prefetch: string[];
    preconnect: string[];
    dnsPrefetch: string[];
  };
  
  // Critical CSS
  criticalCSS: {
    enabled: boolean;
    inlineThreshold: number;
    extractCritical: boolean;
  };
  
  // Performance Monitoring
  monitoring: {
    enabled: boolean;
    sampleRate: number;
    reportInterval: number;
    thresholds: {
      lcp: number;
      fid: number;
      cls: number;
      fcp: number;
      ttfb: number;
    };
  };
}

// Injection token for performance configuration
export const PERFORMANCE_CONFIG = new InjectionToken<PerformanceConfig>('PERFORMANCE_CONFIG');

// Default Performance Configuration
export const DEFAULT_PERFORMANCE_CONFIG: PerformanceConfig = {
  lazyLoading: {
    enabled: true,
    intersectionThreshold: 0.1,
    rootMargin: '50px',
    preloadDistance: 2
  },
  
  imageOptimization: {
    enabled: true,
    formats: ['webp', 'avif', 'jpg'],
    quality: 85,
    placeholder: 'blur',
    sizes: {
      thumbnail: 150,
      small: 300,
      medium: 600,
      large: 1200
    }
  },
  
  codeSplitting: {
    enabled: true,
    chunkSize: 244000, // 244KB
    maxChunks: 20,
    preloadChunks: ['common', 'vendor']
  },
  
  caching: {
    enabled: true,
    strategies: {
      api: 'stale-while-revalidate',
      static: 'cache-first',
      images: 'cache-first'
    },
    ttl: {
      api: 300000, // 5 minutes
      static: 86400000, // 24 hours
      images: 604800000 // 7 days
    }
  },
  
  bundleOptimization: {
    enabled: true,
    treeShaking: true,
    minification: true,
    compression: 'both',
    splitVendors: true
  },
  
  resourceHints: {
    enabled: true,
    preload: [
      '/assets/fonts/primary.woff2',
      '/assets/css/critical.css'
    ],
    prefetch: [
      '/assets/images/hero-bg.webp',
      '/api/products/featured'
    ],
    preconnect: [
      'https://fonts.googleapis.com',
      'https://www.google-analytics.com',
      'https://api.stripe.com'
    ],
    dnsPrefetch: [
      'https://fonts.gstatic.com',
      'https://www.googletagmanager.com'
    ]
  },
  
  criticalCSS: {
    enabled: true,
    inlineThreshold: 14000, // 14KB
    extractCritical: true
  },
  
  monitoring: {
    enabled: true,
    sampleRate: 0.1, // 10% of users
    reportInterval: 30000, // 30 seconds
    thresholds: {
      lcp: 2500, // 2.5s
      fid: 100,  // 100ms
      cls: 0.1,  // 0.1
      fcp: 1800, // 1.8s
      ttfb: 600  // 600ms
    }
  }
};

// Performance Optimization Utilities
export class PerformanceOptimizer {
  
  // Lazy load images with intersection observer
  static setupLazyLoading(config: PerformanceConfig['lazyLoading']): void {
    if (!config.enabled || typeof window === 'undefined') {
      return;
    }
    
    const imageObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            const img = entry.target as HTMLImageElement;
            const src = img.dataset['src'];
            const srcset = img.dataset['srcset'];
            
            if (src) {
              img.src = src;
              img.removeAttribute('data-src');
            }
            
            if (srcset) {
              img.srcset = srcset;
              img.removeAttribute('data-srcset');
            }
            
            img.classList.remove('lazy');
            img.classList.add('loaded');
            imageObserver.unobserve(img);
          }
        });
      },
      {
        threshold: config.intersectionThreshold,
        rootMargin: config.rootMargin
      }
    );
    
    // Observe all lazy images
    document.querySelectorAll('img[data-src]').forEach(img => {
      imageObserver.observe(img);
    });
  }
  
  // Preload critical resources
  static preloadCriticalResources(resources: string[]): void {
    if (typeof document === 'undefined') {
      return;
    }
    
    resources.forEach(resource => {
      const link = document.createElement('link');
      link.rel = 'preload';
      link.href = resource;
      
      // Determine resource type
      if (resource.includes('.css')) {
        link.as = 'style';
      } else if (resource.includes('.js')) {
        link.as = 'script';
      } else if (resource.match(/\.(woff|woff2|ttf|eot)$/)) {
        link.as = 'font';
        link.crossOrigin = 'anonymous';
      } else if (resource.match(/\.(jpg|jpeg|png|gif|webp|svg)$/)) {
        link.as = 'image';
      }
      
      document.head.appendChild(link);
    });
  }
  
  // Setup resource hints
  static setupResourceHints(config: PerformanceConfig['resourceHints']): void {
    if (!config.enabled || typeof document === 'undefined') {
      return;
    }
    
    // Preconnect to external domains
    config.preconnect.forEach(domain => {
      const link = document.createElement('link');
      link.rel = 'preconnect';
      link.href = domain;
      link.crossOrigin = 'anonymous';
      document.head.appendChild(link);
    });
    
    // DNS prefetch for external domains
    config.dnsPrefetch.forEach(domain => {
      const link = document.createElement('link');
      link.rel = 'dns-prefetch';
      link.href = domain;
      document.head.appendChild(link);
    });
    
    // Prefetch resources
    config.prefetch.forEach(resource => {
      const link = document.createElement('link');
      link.rel = 'prefetch';
      link.href = resource;
      document.head.appendChild(link);
    });
  }
  
  // Optimize images with responsive sizes
  static generateResponsiveImageSizes(config: PerformanceConfig['imageOptimization']): string {
    const { sizes } = config;
    return [
      `(max-width: ${sizes.thumbnail}px) ${sizes.thumbnail}px`,
      `(max-width: ${sizes.small}px) ${sizes.small}px`,
      `(max-width: ${sizes.medium}px) ${sizes.medium}px`,
      `${sizes.large}px`
    ].join(', ');
  }
  
  // Generate optimized image srcset
  static generateImageSrcSet(basePath: string, config: PerformanceConfig['imageOptimization']): string {
    const { sizes, formats } = config;
    const srcsets: string[] = [];
    
    Object.entries(sizes).forEach(([size, width]) => {
      formats.forEach(format => {
        srcsets.push(`${basePath}_${width}w.${format} ${width}w`);
      });
    });
    
    return srcsets.join(', ');
  }
  
  // Measure and optimize bundle size
  static analyzeBundleSize(): Promise<any> {
    if (typeof window === 'undefined' || !('performance' in window)) {
      return Promise.resolve(null);
    }
    
    return new Promise((resolve) => {
      setTimeout(() => {
        const resources = performance.getEntriesByType('resource') as PerformanceResourceTiming[];
        
        const bundleAnalysis = {
          totalSize: 0,
          jsSize: 0,
          cssSize: 0,
          imageSize: 0,
          fontSize: 0,
          resources: [] as any[]
        };
        
        resources.forEach(resource => {
          const size = resource.transferSize || 0;
          bundleAnalysis.totalSize += size;
          
          const analysis = {
            name: resource.name,
            size,
            duration: resource.duration,
            type: 'other'
          };
          
          if (resource.name.includes('.js')) {
            bundleAnalysis.jsSize += size;
            analysis.type = 'javascript';
          } else if (resource.name.includes('.css')) {
            bundleAnalysis.cssSize += size;
            analysis.type = 'stylesheet';
          } else if (resource.name.match(/\.(jpg|jpeg|png|gif|webp|svg)$/)) {
            bundleAnalysis.imageSize += size;
            analysis.type = 'image';
          } else if (resource.name.match(/\.(woff|woff2|ttf|eot)$/)) {
            bundleAnalysis.fontSize += size;
            analysis.type = 'font';
          }
          
          bundleAnalysis.resources.push(analysis);
        });
        
        resolve(bundleAnalysis);
      }, 2000);
    });
  }
  
  // Critical CSS extraction
  static extractCriticalCSS(): string[] {
    if (typeof document === 'undefined') {
      return [];
    }
    
    const criticalSelectors: string[] = [];
    const viewportHeight = window.innerHeight;
    
    // Find elements in the viewport
    const elements = document.querySelectorAll('*');
    elements.forEach(element => {
      const rect = element.getBoundingClientRect();
      if (rect.top < viewportHeight && rect.bottom > 0) {
        // Element is in viewport, extract its CSS selectors
        const tagName = element.tagName.toLowerCase();
        const className = element.className;
        const id = element.id;
        
        if (id) {
          criticalSelectors.push(`#${id}`);
        }
        
        if (className && typeof className === 'string') {
          className.split(' ').forEach(cls => {
            if (cls.trim()) {
              criticalSelectors.push(`.${cls.trim()}`);
            }
          });
        }
        
        criticalSelectors.push(tagName);
      }
    });
    
    return [...new Set(criticalSelectors)];
  }
  
  // Performance budget checker
  static checkPerformanceBudget(metrics: any, thresholds: PerformanceConfig['monitoring']['thresholds']): {
    passed: boolean;
    violations: string[];
    score: number;
  } {
    const violations: string[] = [];
    let score = 100;
    
    if (metrics.lcp > thresholds.lcp) {
      violations.push(`LCP: ${metrics.lcp}ms > ${thresholds.lcp}ms`);
      score -= 20;
    }
    
    if (metrics.fid > thresholds.fid) {
      violations.push(`FID: ${metrics.fid}ms > ${thresholds.fid}ms`);
      score -= 20;
    }
    
    if (metrics.cls > thresholds.cls) {
      violations.push(`CLS: ${metrics.cls} > ${thresholds.cls}`);
      score -= 20;
    }
    
    if (metrics.fcp > thresholds.fcp) {
      violations.push(`FCP: ${metrics.fcp}ms > ${thresholds.fcp}ms`);
      score -= 20;
    }
    
    if (metrics.ttfb > thresholds.ttfb) {
      violations.push(`TTFB: ${metrics.ttfb}ms > ${thresholds.ttfb}ms`);
      score -= 20;
    }
    
    return {
      passed: violations.length === 0,
      violations,
      score: Math.max(0, score)
    };
  }
}