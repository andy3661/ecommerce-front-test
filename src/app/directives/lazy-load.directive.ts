import { Directive, ElementRef, Input, OnInit, OnDestroy, inject } from '@angular/core';
import { DOCUMENT } from '@angular/common';

@Directive({
  selector: '[appLazyLoad]',
  standalone: true
})
export class LazyLoadDirective implements OnInit, OnDestroy {
  @Input() appLazyLoad!: string; // Image URL
  @Input() placeholder?: string; // Placeholder image URL
  @Input() threshold = 0.1; // Intersection threshold (10%)
  @Input() rootMargin = '50px'; // Load images 50px before they enter viewport
  
  private elementRef = inject(ElementRef);
  private document = inject(DOCUMENT);
  private observer?: IntersectionObserver;
  private loaded = false;
  
  ngOnInit() {
    if (!this.isIntersectionObserverSupported()) {
      // Fallback: load image immediately if IntersectionObserver is not supported
      this.loadImage();
      return;
    }
    
    this.setupPlaceholder();
    this.createObserver();
  }
  
  ngOnDestroy() {
    if (this.observer) {
      this.observer.disconnect();
    }
  }
  
  private isIntersectionObserverSupported(): boolean {
    return 'IntersectionObserver' in window;
  }
  
  private setupPlaceholder() {
    const img = this.elementRef.nativeElement as HTMLImageElement;
    
    if (this.placeholder) {
      img.src = this.placeholder;
    } else {
      // Create a simple placeholder
      img.src = this.createPlaceholderDataUrl();
    }
    
    // Add loading class for styling
    img.classList.add('lazy-loading');
    
    // Set loading attribute for native lazy loading support
    img.loading = 'lazy';
  }
  
  private createPlaceholderDataUrl(): string {
    // Create a simple gray placeholder
    const canvas = this.document.createElement('canvas');
    canvas.width = 1;
    canvas.height = 1;
    const ctx = canvas.getContext('2d');
    if (ctx) {
      ctx.fillStyle = '#f0f0f0';
      ctx.fillRect(0, 0, 1, 1);
    }
    return canvas.toDataURL();
  }
  
  private createObserver() {
    this.observer = new IntersectionObserver(
      (entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting && !this.loaded) {
            this.loadImage();
            this.observer?.unobserve(entry.target);
          }
        });
      },
      {
        threshold: this.threshold,
        rootMargin: this.rootMargin
      }
    );
    
    this.observer.observe(this.elementRef.nativeElement);
  }
  
  private loadImage() {
    if (this.loaded) return;
    
    const img = this.elementRef.nativeElement as HTMLImageElement;
    const imageLoader = new Image();
    
    imageLoader.onload = () => {
      img.src = this.appLazyLoad;
      img.classList.remove('lazy-loading');
      img.classList.add('lazy-loaded');
      this.loaded = true;
      
      // Dispatch custom event for analytics
      img.dispatchEvent(new CustomEvent('lazyImageLoaded', {
        detail: { src: this.appLazyLoad }
      }));
    };
    
    imageLoader.onerror = () => {
      img.classList.remove('lazy-loading');
      img.classList.add('lazy-error');
      
      // Dispatch error event
      img.dispatchEvent(new CustomEvent('lazyImageError', {
        detail: { src: this.appLazyLoad }
      }));
    };
    
    // Start loading the actual image
    imageLoader.src = this.appLazyLoad;
  }
}

// CSS classes that can be used with this directive:
/*
.lazy-loading {
  filter: blur(5px);
  transition: filter 0.3s;
}

.lazy-loaded {
  filter: none;
}

.lazy-error {
  background-color: #f5f5f5;
  display: flex;
  align-items: center;
  justify-content: center;
}

.lazy-error::after {
  content: '⚠️ Failed to load image';
  color: #666;
  font-size: 14px;
}
*/