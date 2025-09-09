import { Component, OnInit, OnDestroy, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subscription } from 'rxjs';
import { PerformanceService, PerformanceMetrics } from '../../services/performance.service';
import { environment } from '../../../environments/environment';

@Component({
  selector: 'app-performance-monitor',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="performance-monitor" *ngIf="showMonitor && metrics">
      <div class="performance-header">
        <h3>Performance Monitor</h3>
        <button (click)="toggleMonitor()" class="close-btn">&times;</button>
      </div>
      
      <div class="performance-score">
        <div class="score-circle" [class]="getScoreClass(performanceScore)">
          <span class="score-value">{{ performanceScore }}</span>
          <span class="score-label">Score</span>
        </div>
      </div>
      
      <div class="metrics-grid">
        <div class="metric-item" *ngIf="metrics.lcp">
          <span class="metric-label">LCP</span>
          <span class="metric-value" [class]="getMetricClass('lcp', metrics.lcp)">{{ formatTime(metrics.lcp) }}</span>
        </div>
        
        <div class="metric-item" *ngIf="metrics.fid">
          <span class="metric-label">FID</span>
          <span class="metric-value" [class]="getMetricClass('fid', metrics.fid)">{{ formatTime(metrics.fid) }}</span>
        </div>
        
        <div class="metric-item" *ngIf="metrics.cls">
          <span class="metric-label">CLS</span>
          <span class="metric-value" [class]="getMetricClass('cls', metrics.cls)">{{ metrics.cls.toFixed(3) }}</span>
        </div>
        
        <div class="metric-item" *ngIf="metrics.fcp">
          <span class="metric-label">FCP</span>
          <span class="metric-value" [class]="getMetricClass('fcp', metrics.fcp)">{{ formatTime(metrics.fcp) }}</span>
        </div>
      </div>
      
      <div class="recommendations" *ngIf="recommendations.length > 0">
        <h4>Recommendations</h4>
        <ul>
          <li *ngFor="let rec of recommendations">{{ rec }}</li>
        </ul>
      </div>
      
      <div class="monitor-actions">
        <button (click)="generateReport()" class="btn-primary">Generate Report</button>
        <button (click)="clearMetrics()" class="btn-secondary">Clear</button>
      </div>
    </div>
    
    <button 
      *ngIf="!showMonitor" 
      (click)="toggleMonitor()" 
      class="performance-toggle"
      title="Show Performance Monitor">
      ⚡
    </button>
  `,
  styles: [`
    .performance-monitor {
      position: fixed;
      top: 20px;
      right: 20px;
      width: 320px;
      background: white;
      border-radius: 8px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
      z-index: 1000;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      border: 1px solid #e1e5e9;
    }
    
    .performance-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 16px;
      border-bottom: 1px solid #e1e5e9;
      background: #f8f9fa;
      border-radius: 8px 8px 0 0;
    }
    
    .performance-header h3 {
      margin: 0;
      font-size: 16px;
      font-weight: 600;
      color: #2d3748;
    }
    
    .close-btn {
      background: none;
      border: none;
      font-size: 20px;
      cursor: pointer;
      color: #718096;
      padding: 0;
      width: 24px;
      height: 24px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .close-btn:hover {
      color: #2d3748;
    }
    
    .performance-score {
      display: flex;
      justify-content: center;
      padding: 20px;
    }
    
    .score-circle {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      border: 4px solid;
      position: relative;
    }
    
    .score-circle.good {
      border-color: #48bb78;
      background: #f0fff4;
    }
    
    .score-circle.needs-improvement {
      border-color: #ed8936;
      background: #fffaf0;
    }
    
    .score-circle.poor {
      border-color: #f56565;
      background: #fff5f5;
    }
    
    .score-value {
      font-size: 24px;
      font-weight: bold;
      line-height: 1;
    }
    
    .score-label {
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-top: 2px;
    }
    
    .metrics-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      padding: 16px;
      border-bottom: 1px solid #e1e5e9;
    }
    
    .metric-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 12px;
      background: #f8f9fa;
      border-radius: 6px;
    }
    
    .metric-label {
      font-size: 12px;
      font-weight: 600;
      color: #718096;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 4px;
    }
    
    .metric-value {
      font-size: 16px;
      font-weight: bold;
    }
    
    .metric-value.good {
      color: #48bb78;
    }
    
    .metric-value.needs-improvement {
      color: #ed8936;
    }
    
    .metric-value.poor {
      color: #f56565;
    }
    
    .recommendations {
      padding: 16px;
      border-bottom: 1px solid #e1e5e9;
    }
    
    .recommendations h4 {
      margin: 0 0 12px 0;
      font-size: 14px;
      font-weight: 600;
      color: #2d3748;
    }
    
    .recommendations ul {
      margin: 0;
      padding-left: 16px;
      font-size: 13px;
      color: #4a5568;
      line-height: 1.5;
    }
    
    .recommendations li {
      margin-bottom: 8px;
    }
    
    .monitor-actions {
      display: flex;
      gap: 8px;
      padding: 16px;
    }
    
    .btn-primary, .btn-secondary {
      flex: 1;
      padding: 8px 12px;
      border: none;
      border-radius: 4px;
      font-size: 13px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
    }
    
    .btn-primary {
      background: #4299e1;
      color: white;
    }
    
    .btn-primary:hover {
      background: #3182ce;
    }
    
    .btn-secondary {
      background: #e2e8f0;
      color: #4a5568;
    }
    
    .btn-secondary:hover {
      background: #cbd5e0;
    }
    
    .performance-toggle {
      position: fixed;
      bottom: 20px;
      right: 20px;
      width: 56px;
      height: 56px;
      border-radius: 50%;
      background: #4299e1;
      color: white;
      border: none;
      font-size: 24px;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(66, 153, 225, 0.4);
      z-index: 1000;
      transition: all 0.2s;
    }
    
    .performance-toggle:hover {
      background: #3182ce;
      transform: scale(1.05);
    }
    
    @media (max-width: 768px) {
      .performance-monitor {
        width: calc(100vw - 40px);
        right: 20px;
        left: 20px;
      }
    }
  `]
})
export class PerformanceMonitorComponent implements OnInit, OnDestroy {
  private performanceService = inject(PerformanceService);
  private subscription?: Subscription;
  
  showMonitor = false;
  metrics: PerformanceMetrics | null = null;
  performanceScore = 0;
  recommendations: string[] = [];
  
  ngOnInit() {
    // Only show in development mode
    this.showMonitor = !environment.production;
    
    this.subscription = this.performanceService.metrics$.subscribe(metrics => {
      this.metrics = metrics;
      this.performanceScore = this.performanceService.getPerformanceScore();
      this.updateRecommendations();
    });
  }
  
  ngOnDestroy() {
    this.subscription?.unsubscribe();
  }
  
  toggleMonitor() {
    this.showMonitor = !this.showMonitor;
  }
  
  formatTime(ms: number): string {
    if (ms < 1000) {
      return `${Math.round(ms)}ms`;
    }
    return `${(ms / 1000).toFixed(2)}s`;
  }
  
  getScoreClass(score: number): string {
    if (score >= 90) return 'good';
    if (score >= 50) return 'needs-improvement';
    return 'poor';
  }
  
  getMetricClass(metric: string, value: number): string {
    const thresholds = {
      lcp: { good: 2500, poor: 4000 },
      fid: { good: 100, poor: 300 },
      cls: { good: 0.1, poor: 0.25 },
      fcp: { good: 1800, poor: 3000 }
    };
    
    const threshold = thresholds[metric as keyof typeof thresholds];
    if (!threshold) return '';
    
    if (value <= threshold.good) return 'good';
    if (value <= threshold.poor) return 'needs-improvement';
    return 'poor';
  }
  
  generateReport() {
    const report = this.performanceService.generatePerformanceReport();
    console.log('📊 Performance Report:', report);
    
    // In a real app, you might send this to an analytics service
    // or display it in a modal
    alert('Performance report generated! Check the console for details.');
  }
  
  clearMetrics() {
    // Reset metrics (this would need to be implemented in the service)
    console.log('🧹 Clearing performance metrics...');
  }
  
  private updateRecommendations() {
    if (!this.metrics) {
      this.recommendations = [];
      return;
    }
    
    const recommendations: string[] = [];
    
    if (this.metrics.lcp && this.metrics.lcp > 2500) {
      recommendations.push('Optimize images and reduce server response time to improve LCP');
    }
    
    if (this.metrics.fid && this.metrics.fid > 100) {
      recommendations.push('Reduce JavaScript execution time to improve FID');
    }
    
    if (this.metrics.cls && this.metrics.cls > 0.1) {
      recommendations.push('Set explicit dimensions for images to reduce CLS');
    }
    
    if (this.metrics.fcp && this.metrics.fcp > 1800) {
      recommendations.push('Eliminate render-blocking resources to improve FCP');
    }
    
    this.recommendations = recommendations;
  }
}