import { Injectable, inject, Inject, PLATFORM_ID } from '@angular/core';
import { Meta, Title } from '@angular/platform-browser';
import { isPlatformBrowser } from '@angular/common';
import { DOCUMENT } from '@angular/common';
import { StructuredDataService } from './structured-data.service';

export interface SEOData {
  title?: string;
  description?: string;
  keywords?: string;
  image?: string;
  url?: string;
  type?: 'website' | 'article' | 'product';
  price?: string;
  currency?: string;
  availability?: 'InStock' | 'OutOfStock' | 'PreOrder';
  brand?: string;
  category?: string;
  sku?: string;
  gtin?: string;
  mpn?: string;
  condition?: 'NewCondition' | 'UsedCondition' | 'RefurbishedCondition';
  rating?: {
    value: number;
    count: number;
  };
  author?: string;
  publishedTime?: string;
  modifiedTime?: string;
}

@Injectable({
  providedIn: 'root'
})
export class SeoService {
  private meta = inject(Meta);
  private title = inject(Title);
  private document = inject(DOCUMENT);
  private structuredDataService = inject(StructuredDataService);
  
  private defaultTitle = 'E-Commerce Store - Premium Products Online';
  private defaultDescription = 'Discover premium products at unbeatable prices. Fast shipping, secure checkout, and excellent customer service.';
  private defaultImage = '/assets/images/og-default.jpg';
  private siteName = 'E-Commerce Store';
  private baseUrl = 'https://your-domain.com';

  constructor(
    @Inject(PLATFORM_ID) private platformId: Object
  ) {
    this.initializeDefaultSEO();
  }

  private initializeDefaultSEO(): void {
    // Set default meta tags
    this.setBasicSEO({
      title: this.defaultTitle,
      description: this.defaultDescription,
      image: this.defaultImage
    });

    // Add structured data for organization
    this.structuredDataService.addOrganizationStructuredData();
  }



  setBasicSEO(data: SEOData): void {
    const title = data.title || this.defaultTitle;
    const description = data.description || this.defaultDescription;
    const image = data.image || this.defaultImage;
    const url = data.url || this.getCurrentUrl();

    // Set page title
    this.title.setTitle(title);

    // Basic meta tags
    this.meta.updateTag({ name: 'description', content: description });
    this.meta.updateTag({ name: 'keywords', content: data.keywords || '' });
    this.meta.updateTag({ name: 'robots', content: 'index, follow' });
    this.meta.updateTag({ name: 'viewport', content: 'width=device-width, initial-scale=1' });
    this.meta.updateTag({ name: 'theme-color', content: '#3B82F6' });

    // Open Graph tags
    this.meta.updateTag({ property: 'og:title', content: title });
    this.meta.updateTag({ property: 'og:description', content: description });
    this.meta.updateTag({ property: 'og:image', content: this.getAbsoluteUrl(image) });
    this.meta.updateTag({ property: 'og:url', content: url });
    this.meta.updateTag({ property: 'og:type', content: data.type || 'website' });
    this.meta.updateTag({ property: 'og:site_name', content: this.siteName });
    this.meta.updateTag({ property: 'og:locale', content: 'en_US' });

    // Twitter Card tags
    this.meta.updateTag({ name: 'twitter:card', content: 'summary_large_image' });
    this.meta.updateTag({ name: 'twitter:title', content: title });
    this.meta.updateTag({ name: 'twitter:description', content: description });
    this.meta.updateTag({ name: 'twitter:image', content: this.getAbsoluteUrl(image) });
    this.meta.updateTag({ name: 'twitter:site', content: '@yourstore' });
    this.meta.updateTag({ name: 'twitter:creator', content: '@yourstore' });

    // Canonical URL
    this.updateCanonicalUrl(url);
  }

  setProductSEO(product: any): void {
    const title = `${product.name} - ${this.siteName}`;
    const description = product.description || `Buy ${product.name} at the best price. ${this.defaultDescription}`;
    const image = product.images?.[0] || this.defaultImage;
    const url = `${this.baseUrl}/product/${product.slug || product.id}`;

    this.setBasicSEO({
      title,
      description,
      image,
      url,
      type: 'product'
    });

    // Product-specific Open Graph tags
    this.meta.updateTag({ property: 'product:price:amount', content: product.price?.toString() || '' });
    this.meta.updateTag({ property: 'product:price:currency', content: product.currency || 'USD' });
    this.meta.updateTag({ property: 'product:availability', content: product.inStock ? 'in stock' : 'out of stock' });
    this.meta.updateTag({ property: 'product:brand', content: product.brand || '' });
    this.meta.updateTag({ property: 'product:category', content: product.category || '' });

    // Add product structured data
    this.structuredDataService.addProductStructuredData({
      id: product.id,
      name: product.name,
      description: product.description,
      images: product.images || [],
      brand: product.brand,
      price: product.price,
      currency: product.currency || 'USD',
      inStock: product.inStock,
      sku: product.sku,
      category: product.category,
      rating: product.rating
    });
  }

  setCategorySEO(category: any): void {
    const title = `${category.name} - Shop ${category.name} Products | ${this.siteName}`;
    const description = category.description || `Explore our ${category.name} collection. ${this.defaultDescription}`;
    const url = `${this.baseUrl}/category/${category.slug || category.id}`;

    this.setBasicSEO({
      title,
      description,
      url,
      type: 'website'
    });

    // Add breadcrumb structured data
    this.structuredDataService.addBreadcrumbStructuredData([
      { name: 'Home', url: this.baseUrl },
      { name: category.name, url }
    ]);
  }

  setSearchSEO(query: string, resultsCount: number): void {
    const title = `Search Results for "${query}" - ${this.siteName}`;
    const description = `Found ${resultsCount} products for "${query}". ${this.defaultDescription}`;
    const url = `${this.baseUrl}/search?q=${encodeURIComponent(query)}`;

    this.setBasicSEO({
      title,
      description,
      url,
      type: 'website'
    });
  }



  private updateCanonicalUrl(url: string): void {
    if (isPlatformBrowser(this.platformId)) {
      let canonical = this.document.querySelector('link[rel="canonical"]') as HTMLLinkElement;
      if (!canonical) {
        canonical = this.document.createElement('link');
        canonical.rel = 'canonical';
        this.document.head.appendChild(canonical);
      }
      canonical.href = url;
    }
  }

  private getCurrentUrl(): string {
    if (isPlatformBrowser(this.platformId)) {
      return window.location.href;
    }
    return this.baseUrl;
  }

  private getAbsoluteUrl(url: string): string {
    if (url.startsWith('http')) {
      return url;
    }
    return `${this.baseUrl}${url.startsWith('/') ? '' : '/'}${url}`;
  }

  // Method to generate sitemap data
  generateSitemapData(): any[] {
    return [
      {
        url: this.baseUrl,
        lastmod: new Date().toISOString(),
        changefreq: 'daily',
        priority: '1.0'
      },
      {
        url: `${this.baseUrl}/products`,
        lastmod: new Date().toISOString(),
        changefreq: 'daily',
        priority: '0.9'
      },
      {
        url: `${this.baseUrl}/categories`,
        lastmod: new Date().toISOString(),
        changefreq: 'weekly',
        priority: '0.8'
      },
      {
        url: `${this.baseUrl}/about`,
        lastmod: new Date().toISOString(),
        changefreq: 'monthly',
        priority: '0.6'
      },
      {
        url: `${this.baseUrl}/contact`,
        lastmod: new Date().toISOString(),
        changefreq: 'monthly',
        priority: '0.6'
      }
    ];
  }

  /**
   * Handle route changes for SEO
   */
  handleRouteChange(url: string): void {
    // Update canonical URL
    this.updateCanonicalUrl(`${this.baseUrl}${url}`);
    
    // Clear previous structured data
    this.structuredDataService.clearAllStructuredData();
    
    // Add organization and website structured data to all pages
    this.structuredDataService.addOrganizationStructuredData();
    this.structuredDataService.addWebsiteStructuredData();
  }
}