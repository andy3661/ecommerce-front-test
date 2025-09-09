import { Injectable, Inject, PLATFORM_ID } from '@angular/core';
import { PERFORMANCE_CONFIG, PerformanceConfig } from '../config/performance.config';
import { isPlatformBrowser, DOCUMENT } from '@angular/common';
import { BehaviorSubject, Observable, fromEvent } from 'rxjs';
import { debounceTime, throttleTime } from 'rxjs/operators';
import { AnalyticsService } from './analytics.service';

export interface PerformanceMetrics {
  // Core Web Vitals
  lcp?: number; // Largest Contentful Paint
  fid?: number; // First Input Delay
  cls?: number; // Cumulative Layout Shift
  
  // Other Performance Metrics
  fcp?: number; // First Contentful Paint
  ttfb?: number; // Time to First Byte
  domContentLoaded?: number;
  loadComplete?: number;
  
  // Custom Metrics
  timeToInteractive?: number;
  totalBlockingTime?: number;
  speedIndex?: number;
}

export interface PerformanceEntry {
  name: string;
  entryType: string;
  startTime: number;
  duration: number;
  [key: string]: any;
}

export interface ResourceTiming {
  name: string;
  size: number;
  duration: number;
  type: string;
  cached: boolean;
}

export interface PerformanceBudget {
  lcp: number; // 2.5s
  fid: number; // 100ms
  cls: number; // 0.1
  fcp: number; // 1.8s
  ttfb: number; // 600ms
}

@Injectable({
  providedIn: 'root'
})
export class PerformanceService {
  private metricsSubject = new BehaviorSubject<PerformanceMetrics>({});
  private performanceObserver?: PerformanceObserver;
  private isBrowser: boolean;
  
  // Performance Budget (Core Web Vitals thresholds)
  private readonly performanceBudget: PerformanceBudget = {
    lcp: 2500, // 2.5 seconds
    fid: 100,  // 100 milliseconds
    cls: 0.1,  // 0.1
    fcp: 1800, // 1.8 seconds
    ttfb: 600  // 600 milliseconds
  };
  
  constructor(
    @Inject(PLATFORM_ID) platformId: Object,
    @Inject(DOCUMENT) private document: Document,
    private analyticsService: AnalyticsService,
    @Inject(PERFORMANCE_CONFIG) private config: PerformanceConfig
  ) {
    this.isBrowser = isPlatformBrowser(platformId);
    
    if (this.isBrowser) {
      this.initializePerformanceMonitoring();
      this.setupResourceTimingObserver();
      this.setupLongTaskObserver();
      this.monitorMemoryUsage();
      this.startContinuousMonitoring();
    }
  }
  
  get metrics$(): Observable<PerformanceMetrics> {
    return this.metricsSubject.asObservable();
  }
  
  get currentMetrics(): PerformanceMetrics {
    return this.metricsSubject.value;
  }
  
  private initializePerformanceMonitoring(): void {
    if (!this.isBrowser || !('PerformanceObserver' in window)) {
      return;
    }
    
    // Monitor Core Web Vitals
    this.observeWebVitals();
    
    // Monitor Navigation Timing
    this.observeNavigationTiming();
    
    // Monitor Paint Timing
    this.observePaintTiming();
    
    // Setup performance event listeners
    this.setupPerformanceEventListeners();
    
    console.log('🚀 Performance monitoring initialized');
  }
  
  private observeWebVitals(): void {
    try {
      // Largest Contentful Paint (LCP)
      const lcpObserver = new PerformanceObserver((list) => {
        const entries = list.getEntries();
        const lastEntry = entries[entries.length - 1] as any;
        
        if (lastEntry) {
          const lcp = lastEntry.startTime;
          this.updateMetric('lcp', lcp);
          this.trackWebVital('LCP', lcp, this.performanceBudget.lcp);
        }
      });
      lcpObserver.observe({ entryTypes: ['largest-contentful-paint'] });
      
      // First Input Delay (FID)
      const fidObserver = new PerformanceObserver((list) => {
        const entries = list.getEntries();
        entries.forEach((entry: any) => {
          const fid = entry.processingStart - entry.startTime;
          this.updateMetric('fid', fid);
          this.trackWebVital('FID', fid, this.performanceBudget.fid);
        });
      });
      fidObserver.observe({ entryTypes: ['first-input'] });
      
      // Cumulative Layout Shift (CLS)
      let clsValue = 0;
      const clsObserver = new PerformanceObserver((list) => {
        const entries = list.getEntries();
        entries.forEach((entry: any) => {
          if (!entry.hadRecentInput) {
            clsValue += entry.value;
          }
        });
        
        this.updateMetric('cls', clsValue);
        this.trackWebVital('CLS', clsValue, this.performanceBudget.cls);
      });
      clsObserver.observe({ entryTypes: ['layout-shift'] });
      
    } catch (error) {
      console.warn('⚠️ Web Vitals observation not supported:', error);
    }
  }
  
  private observeNavigationTiming(): void {
    if (!('performance' in window) || !performance.getEntriesByType) {
      return;
    }
    
    // Wait for navigation timing to be available
    setTimeout(() => {
      const navigation = performance.getEntriesByType('navigation')[0] as any;
      
      if (navigation) {
        const ttfb = navigation.responseStart - navigation.requestStart;
        const domContentLoaded = navigation.domContentLoadedEventEnd - navigation.navigationStart;
        const loadComplete = navigation.loadEventEnd - navigation.navigationStart;
        
        this.updateMetric('ttfb', ttfb);
        this.updateMetric('domContentLoaded', domContentLoaded);
        this.updateMetric('loadComplete', loadComplete);
        
        this.trackWebVital('TTFB', ttfb, this.performanceBudget.ttfb);
        
        console.log('📊 Navigation timing captured:', {
          ttfb: `${ttfb.toFixed(2)}ms`,
          domContentLoaded: `${domContentLoaded.toFixed(2)}ms`,
          loadComplete: `${loadComplete.toFixed(2)}ms`
        });
      }
    }, 1000);
  }
  
  private observePaintTiming(): void {
    try {
      const paintObserver = new PerformanceObserver((list) => {
        const entries = list.getEntries();
        
        entries.forEach((entry) => {
          if (entry.name === 'first-contentful-paint') {
            const fcp = entry.startTime;
            this.updateMetric('fcp', fcp);
            this.trackWebVital('FCP', fcp, this.performanceBudget.fcp);
          }
        });
      });
      
      paintObserver.observe({ entryTypes: ['paint'] });
    } catch (error) {
      console.warn('⚠️ Paint timing observation not supported:', error);
    }
  }
  
  private setupResourceTimingObserver(): void {
    try {
      const resourceObserver = new PerformanceObserver((list) => {
        const entries = list.getEntries();
        
        entries.forEach((entry: any) => {
          const resource: ResourceTiming = {
            name: entry.name,
            size: entry.transferSize || 0,
            duration: entry.duration,
            type: this.getResourceType(entry.name),
            cached: entry.transferSize === 0 && entry.decodedBodySize > 0
          };
          
          this.analyzeResourcePerformance(resource);
        });
      });
      
      resourceObserver.observe({ entryTypes: ['resource'] });
    } catch (error) {
      console.warn('⚠️ Resource timing observation not supported:', error);
    }
  }
  
  private setupLongTaskObserver(): void {
    try {
      const longTaskObserver = new PerformanceObserver((list) => {
        const entries = list.getEntries();
        
        entries.forEach((entry) => {
          console.warn('🐌 Long task detected:', {
            duration: `${entry.duration.toFixed(2)}ms`,
            startTime: `${entry.startTime.toFixed(2)}ms`
          });
          
          // Track long tasks in analytics
          this.analyticsService.trackEvent({
            event: 'performance_long_task',
            event_category: 'performance',
            event_label: 'long_task_detected',
            value: Math.round(entry.duration),
            custom_parameters: {
              start_time: entry.startTime
            }
          });
        });
      });
      
      longTaskObserver.observe({ entryTypes: ['longtask'] });
    } catch (error) {
      console.warn('⚠️ Long task observation not supported:', error);
    }
  }
  
  private setupPerformanceEventListeners(): void {
    // Monitor page visibility changes
    fromEvent(this.document, 'visibilitychange')
      .pipe(throttleTime(1000))
      .subscribe(() => {
        if (this.document.hidden) {
          this.sendPerformanceReport();
        }
      });
    
    // Monitor beforeunload for final metrics
    fromEvent(window, 'beforeunload')
      .subscribe(() => {
        this.sendPerformanceReport();
      });
  }
  
  private monitorMemoryUsage(): void {
    if (!('memory' in performance)) {
      return;
    }
    
    setInterval(() => {
      const memory = (performance as any).memory;
      
      if (memory) {
        const memoryInfo = {
          used: Math.round(memory.usedJSHeapSize / 1048576), // MB
          total: Math.round(memory.totalJSHeapSize / 1048576), // MB
          limit: Math.round(memory.jsHeapSizeLimit / 1048576) // MB
        };
        
        // Warn if memory usage is high
        if (memoryInfo.used / memoryInfo.limit > 0.8) {
          console.warn('⚠️ High memory usage detected:', memoryInfo);
          
          this.analyticsService.trackEvent({
            event: 'performance_memory_warning',
            event_category: 'performance',
            event_label: 'high_memory_usage',
            value: Math.round((memoryInfo.used / memoryInfo.limit) * 100),
            custom_parameters: memoryInfo
          });
        }
      }
    }, 30000); // Check every 30 seconds
  }
  
  private updateMetric(key: keyof PerformanceMetrics, value: number): void {
    const currentMetrics = this.metricsSubject.value;
    this.metricsSubject.next({
      ...currentMetrics,
      [key]: value
    });
  }
  
  private trackWebVital(name: string, value: number, threshold: number): void {
    const rating = this.getPerformanceRating(value, threshold, name);
    
    console.log(`📈 ${name}: ${value.toFixed(2)}${name === 'CLS' ? '' : 'ms'} (${rating})`);
    
    // Track in analytics
    this.analyticsService.trackEvent({
      event: 'web_vital',
      event_category: 'performance',
      event_label: name.toLowerCase(),
      value: Math.round(value),
      custom_parameters: {
        rating,
        threshold
      }
    });
  }
  
  private getPerformanceRating(value: number, threshold: number, metric: string): string {
    if (metric === 'CLS') {
      if (value <= 0.1) return 'good';
      if (value <= 0.25) return 'needs-improvement';
      return 'poor';
    }
    
    if (value <= threshold) return 'good';
    if (value <= threshold * 1.5) return 'needs-improvement';
    return 'poor';
  }
  
  private getResourceType(url: string): string {
    if (url.includes('.js')) return 'script';
    if (url.includes('.css')) return 'stylesheet';
    if (url.match(/\.(jpg|jpeg|png|gif|webp|svg)$/)) return 'image';
    if (url.match(/\.(woff|woff2|ttf|eot)$/)) return 'font';
    if (url.includes('api/') || url.includes('/api')) return 'api';
    return 'other';
  }
  
  private analyzeResourcePerformance(resource: ResourceTiming): void {
    // Warn about slow resources
    if (resource.duration > 1000) {
      console.warn('🐌 Slow resource detected:', {
        name: resource.name,
        duration: `${resource.duration.toFixed(2)}ms`,
        size: `${(resource.size / 1024).toFixed(2)}KB`,
        type: resource.type
      });
    }
    
    // Warn about large resources
    if (resource.size > 500000) { // 500KB
      console.warn('📦 Large resource detected:', {
        name: resource.name,
        size: `${(resource.size / 1024).toFixed(2)}KB`,
        type: resource.type
      });
    }
  }
  
  private sendPerformanceReport(): void {
    const metrics = this.currentMetrics;
    
    if (Object.keys(metrics).length === 0) {
      return;
    }
    
    // Send comprehensive performance report
    this.analyticsService.trackEvent({
      event: 'performance_report',
      event_category: 'performance',
      event_label: 'comprehensive_report',
      custom_parameters: {
        ...metrics,
        timestamp: Date.now(),
        user_agent: navigator.userAgent,
        connection_type: this.getConnectionType()
      }
    });
    
    console.log('📊 Performance report sent:', metrics);
  }
  
  private getConnectionType(): string {
    const connection = (navigator as any).connection || (navigator as any).mozConnection || (navigator as any).webkitConnection;
    return connection ? connection.effectiveType || connection.type || 'unknown' : 'unknown';
  }
  
  // Public methods for manual performance tracking
  
  public markStart(name: string): void {
    if (this.isBrowser && 'performance' in window) {
      performance.mark(`${name}-start`);
    }
  }
  
  public markEnd(name: string): number | null {
    if (!this.isBrowser || !('performance' in window)) {
      return null;
    }
    
    performance.mark(`${name}-end`);
    performance.measure(name, `${name}-start`, `${name}-end`);
    
    const measure = performance.getEntriesByName(name, 'measure')[0];
    
    if (measure) {
      console.log(`⏱️ ${name}: ${measure.duration.toFixed(2)}ms`);
      
      this.analyticsService.trackEvent({
        event: 'custom_performance_measure',
        event_category: 'performance',
        event_label: name,
        value: Math.round(measure.duration)
      });
      
      return measure.duration;
    }
    
    return null;
  }
  
  public measureFunction<T>(name: string, fn: () => T): T {
    this.markStart(name);
    const result = fn();
    this.markEnd(name);
    return result;
  }
  
  public async measureAsyncFunction<T>(name: string, fn: () => Promise<T>): Promise<T> {
    this.markStart(name);
    try {
      const result = await fn();
      this.markEnd(name);
      return result;
    } catch (error) {
      this.markEnd(name);
      throw error;
    }
  }
  
  public getPerformanceScore(): number {
    const metrics = this.currentMetrics;
    let score = 100;
    
    // Deduct points based on Core Web Vitals
    if (metrics.lcp && metrics.lcp > this.performanceBudget.lcp) {
      score -= 20;
    }
    
    if (metrics.fid && metrics.fid > this.performanceBudget.fid) {
      score -= 20;
    }
    
    if (metrics.cls && metrics.cls > this.performanceBudget.cls) {
      score -= 20;
    }
    
    if (metrics.fcp && metrics.fcp > this.performanceBudget.fcp) {
      score -= 15;
    }
    
    if (metrics.ttfb && metrics.ttfb > this.performanceBudget.ttfb) {
      score -= 15;
    }
    
    return Math.max(0, score);
  }
  
  public generatePerformanceReport(): any {
    const metrics = this.currentMetrics;
    const score = this.getPerformanceScore();
    
    return {
      score,
      metrics,
      recommendations: this.getPerformanceRecommendations(metrics),
      timestamp: new Date().toISOString(),
      userAgent: navigator.userAgent,
      connectionType: this.getConnectionType()
    };
  }
  
  private getPerformanceRecommendations(metrics: PerformanceMetrics): string[] {
    const recommendations: string[] = [];
    
    if (metrics.lcp && metrics.lcp > this.performanceBudget.lcp) {
      recommendations.push('Optimize Largest Contentful Paint by reducing server response times and optimizing critical resources');
    }
    
    if (metrics.fid && metrics.fid > this.performanceBudget.fid) {
      recommendations.push('Improve First Input Delay by reducing JavaScript execution time and breaking up long tasks');
    }
    
    if (metrics.cls && metrics.cls > this.performanceBudget.cls) {
      recommendations.push('Reduce Cumulative Layout Shift by setting dimensions for images and avoiding dynamic content insertion');
    }
    
    if (metrics.fcp && metrics.fcp > this.performanceBudget.fcp) {
      recommendations.push('Optimize First Contentful Paint by eliminating render-blocking resources and optimizing critical CSS');
    }
    
    if (metrics.ttfb && metrics.ttfb > this.performanceBudget.ttfb) {
      recommendations.push('Improve Time to First Byte by optimizing server response times and using CDN');
    }
    
    return recommendations;
  }

  // Start continuous performance monitoring
  private startContinuousMonitoring(): void {
    if (!this.config.monitoring.enabled) {
      return;
    }

    // Monitor performance every 30 seconds
    setInterval(() => {
      this.checkPerformanceBudgets();
      this.monitorMemoryUsage();
    }, 30000);

    // Monitor page visibility changes
    if (typeof document !== 'undefined') {
      document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
          this.trackPageVisibility('visible');
        } else {
          this.trackPageVisibility('hidden');
        }
      });
    }
  }

  // Check if performance metrics exceed budgets
  private checkPerformanceBudgets(): void {
    const currentMetrics = this.metricsSubject.value;
    const budget = this.performanceBudget;

    const violations: string[] = [];

    if (currentMetrics.lcp && currentMetrics.lcp > budget.lcp * 1000) {
      violations.push(`LCP: ${currentMetrics.lcp}ms > ${budget.lcp * 1000}ms`);
    }

    if (currentMetrics.fid && currentMetrics.fid > budget.fid) {
      violations.push(`FID: ${currentMetrics.fid}ms > ${budget.fid}ms`);
    }

    if (currentMetrics.cls && currentMetrics.cls > budget.cls) {
      violations.push(`CLS: ${currentMetrics.cls} > ${budget.cls}`);
    }

    if (violations.length > 0) {
      console.warn('⚠️ Performance budget violations:', violations);
      this.analyticsService.trackEvent({
        event: 'performance_budget_violation',
        event_category: 'performance',
        event_label: violations.join(', '),
        custom_parameters: {
          timestamp: Date.now()
        }
      });
    }
  }

  // Track page visibility changes
  private trackPageVisibility(state: 'visible' | 'hidden'): void {
    this.analyticsService.trackEvent({
      event: 'page_visibility_change',
      event_category: 'performance',
      event_label: state,
      custom_parameters: {
        timestamp: Date.now()
      }
    });
  }
}