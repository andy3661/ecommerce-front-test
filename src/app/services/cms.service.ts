import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Observable, of, BehaviorSubject } from 'rxjs';
import { map, catchError } from 'rxjs/operators';
import { AnalyticsService } from './analytics.service';

export interface CMSProvider {
  id: string;
  name: string;
  enabled: boolean;
  apiUrl: string;
  config: any;
}

export interface CMSContent {
  id: string;
  type: 'page' | 'product' | 'category' | 'blog' | 'banner' | 'component';
  title: string;
  slug: string;
  content: any;
  metadata: CMSMetadata;
  status: 'draft' | 'published' | 'archived';
  createdAt: Date;
  updatedAt: Date;
  publishedAt?: Date;
  author?: string;
  tags?: string[];
  featured?: boolean;
}

export interface CMSMetadata {
  seoTitle?: string;
  seoDescription?: string;
  seoKeywords?: string[];
  ogTitle?: string;
  ogDescription?: string;
  ogImage?: string;
  canonicalUrl?: string;
  noIndex?: boolean;
  noFollow?: boolean;
}

export interface CMSPage {
  id: string;
  title: string;
  slug: string;
  content: CMSPageContent[];
  metadata: CMSMetadata;
  template: string;
  status: 'draft' | 'published';
  publishedAt?: Date;
}

export interface CMSPageContent {
  id: string;
  type: 'hero' | 'text' | 'image' | 'gallery' | 'video' | 'products' | 'testimonials' | 'faq' | 'form';
  data: any;
  order: number;
  visible: boolean;
}

export interface CMSProduct {
  id: string;
  name: string;
  slug: string;
  description: string;
  shortDescription?: string;
  images: CMSImage[];
  price: number;
  comparePrice?: number;
  sku: string;
  inventory: number;
  categories: string[];
  tags: string[];
  variants?: CMSProductVariant[];
  attributes: { [key: string]: any };
  metadata: CMSMetadata;
  status: 'draft' | 'published';
  featured: boolean;
}

export interface CMSProductVariant {
  id: string;
  name: string;
  sku: string;
  price: number;
  inventory: number;
  attributes: { [key: string]: string };
  image?: string;
}

export interface CMSImage {
  id: string;
  url: string;
  alt: string;
  width?: number;
  height?: number;
  formats?: { [key: string]: string };
}

export interface CMSCategory {
  id: string;
  name: string;
  slug: string;
  description?: string;
  image?: CMSImage;
  parentId?: string;
  children?: CMSCategory[];
  metadata: CMSMetadata;
  order: number;
  visible: boolean;
}

export interface CMSBlogPost {
  id: string;
  title: string;
  slug: string;
  excerpt: string;
  content: string;
  featuredImage?: CMSImage;
  author: string;
  categories: string[];
  tags: string[];
  metadata: CMSMetadata;
  status: 'draft' | 'published';
  publishedAt?: Date;
  readTime?: number;
}

export interface CMSBanner {
  id: string;
  title: string;
  subtitle?: string;
  image: CMSImage;
  link?: string;
  buttonText?: string;
  position: 'hero' | 'sidebar' | 'footer' | 'popup';
  startDate?: Date;
  endDate?: Date;
  active: boolean;
  order: number;
}

@Injectable({
  providedIn: 'root'
})
export class CMSService {
  private http = inject(HttpClient);
  private analyticsService = inject(AnalyticsService);

  // CMS providers configuration
  private providers: CMSProvider[] = [
    {
      id: 'strapi',
      name: 'Strapi',
      enabled: true,
      apiUrl: 'http://localhost:1337/api',
      config: {
        apiToken: 'your_strapi_api_token',
        version: 'v4'
      }
    },
    {
      id: 'sanity',
      name: 'Sanity',
      enabled: false,
      apiUrl: 'https://your-project.api.sanity.io/v2021-10-21/data/query/production',
      config: {
        projectId: 'your_sanity_project_id',
        dataset: 'production',
        apiVersion: '2021-10-21',
        token: 'your_sanity_token'
      }
    },
    {
      id: 'contentful',
      name: 'Contentful',
      enabled: false,
      apiUrl: 'https://cdn.contentful.com',
      config: {
        spaceId: 'your_contentful_space_id',
        accessToken: 'your_contentful_access_token',
        environment: 'master'
      }
    }
  ];

  private currentProvider = this.providers.find(p => p.enabled) || this.providers[0];
  private contentCache = new Map<string, any>();
  private cacheExpiry = new Map<string, number>();
  private readonly CACHE_DURATION = 5 * 60 * 1000; // 5 minutes

  // Content subjects for real-time updates
  private pagesSubject = new BehaviorSubject<CMSPage[]>([]);
  private productsSubject = new BehaviorSubject<CMSProduct[]>([]);
  private categoriesSubject = new BehaviorSubject<CMSCategory[]>([]);
  private blogPostsSubject = new BehaviorSubject<CMSBlogPost[]>([]);
  private bannersSubject = new BehaviorSubject<CMSBanner[]>([]);

  public pages$ = this.pagesSubject.asObservable();
  public products$ = this.productsSubject.asObservable();
  public categories$ = this.categoriesSubject.asObservable();
  public blogPosts$ = this.blogPostsSubject.asObservable();
  public banners$ = this.bannersSubject.asObservable();

  // Get current CMS provider
  getCurrentProvider(): CMSProvider {
    return this.currentProvider;
  }

  // Switch CMS provider
  switchProvider(providerId: string): void {
    const provider = this.providers.find(p => p.id === providerId);
    if (provider && provider.enabled) {
      this.currentProvider = provider;
      this.clearCache();
    }
  }

  // Generic content fetching
  getContent<T>(endpoint: string, useCache = true): Observable<T> {
    const cacheKey = `${this.currentProvider.id}_${endpoint}`;
    
    // Check cache first
    if (useCache && this.isValidCache(cacheKey)) {
      return of(this.contentCache.get(cacheKey));
    }

    const headers = this.getHeaders();
    const url = this.buildUrl(endpoint);

    return this.http.get<T>(url, { headers }).pipe(
      map(response => {
        // Cache the response
        if (useCache) {
          this.contentCache.set(cacheKey, response);
          this.cacheExpiry.set(cacheKey, Date.now() + this.CACHE_DURATION);
        }
        return response;
      }),
      catchError(error => {
        console.error('CMS API Error:', error);
        // Return cached data if available
        if (this.contentCache.has(cacheKey)) {
          return of(this.contentCache.get(cacheKey));
        }
        throw error;
      })
    );
  }

  // Pages
  getPages(): Observable<CMSPage[]> {
    return this.getContent<any>('pages?populate=*').pipe(
      map(response => this.transformPages(response))
    );
  }

  getPageBySlug(slug: string): Observable<CMSPage | null> {
    return this.getContent<any>(`pages?filters[slug][$eq]=${slug}&populate=*`).pipe(
      map(response => {
        const pages = this.transformPages(response);
        const page = pages.length > 0 ? pages[0] : null;
        
        if (page) {
          this.analyticsService.trackEvent({
            event: 'cms_page_viewed',
            event_category: 'cms',
            event_label: slug,
            custom_parameters: {
              page_id: page.id,
              page_title: page.title
            }
          });
        }
        
        return page;
      })
    );
  }

  // Products
  getProducts(filters?: any): Observable<CMSProduct[]> {
    let endpoint = 'products?populate=*';
    
    if (filters) {
      const filterParams = this.buildFilters(filters);
      endpoint += `&${filterParams}`;
    }

    return this.getContent<any>(endpoint).pipe(
      map(response => this.transformProducts(response))
    );
  }

  getProductBySlug(slug: string): Observable<CMSProduct | null> {
    return this.getContent<any>(`products?filters[slug][$eq]=${slug}&populate=*`).pipe(
      map(response => {
        const products = this.transformProducts(response);
        const product = products.length > 0 ? products[0] : null;
        
        if (product) {
          this.analyticsService.trackEvent({
            event: 'cms_product_viewed',
            event_category: 'cms',
            event_label: slug,
            custom_parameters: {
              product_id: product.id,
              product_name: product.name,
              product_price: product.price
            }
          });
        }
        
        return product;
      })
    );
  }

  getFeaturedProducts(limit = 8): Observable<CMSProduct[]> {
    return this.getContent<any>(`products?filters[featured][$eq]=true&pagination[limit]=${limit}&populate=*`).pipe(
      map(response => this.transformProducts(response))
    );
  }

  // Categories
  getCategories(): Observable<CMSCategory[]> {
    return this.getContent<any>('categories?populate=*&sort=order:asc').pipe(
      map(response => this.transformCategories(response))
    );
  }

  getCategoryBySlug(slug: string): Observable<CMSCategory | null> {
    return this.getContent<any>(`categories?filters[slug][$eq]=${slug}&populate=*`).pipe(
      map(response => {
        const categories = this.transformCategories(response);
        return categories.length > 0 ? categories[0] : null;
      })
    );
  }

  // Blog posts
  getBlogPosts(limit?: number): Observable<CMSBlogPost[]> {
    let endpoint = 'blog-posts?populate=*&sort=publishedAt:desc';
    if (limit) {
      endpoint += `&pagination[limit]=${limit}`;
    }

    return this.getContent<any>(endpoint).pipe(
      map(response => this.transformBlogPosts(response))
    );
  }

  getBlogPostBySlug(slug: string): Observable<CMSBlogPost | null> {
    return this.getContent<any>(`blog-posts?filters[slug][$eq]=${slug}&populate=*`).pipe(
      map(response => {
        const posts = this.transformBlogPosts(response);
        const post = posts.length > 0 ? posts[0] : null;
        
        if (post) {
          this.analyticsService.trackEvent({
            event: 'cms_blog_post_viewed',
            event_category: 'cms',
            event_label: slug,
            custom_parameters: {
              post_id: post.id,
              post_title: post.title,
              author: post.author
            }
          });
        }
        
        return post;
      })
    );
  }

  // Banners
  getBanners(position?: string): Observable<CMSBanner[]> {
    let endpoint = 'banners?populate=*&filters[active][$eq]=true&sort=order:asc';
    
    if (position) {
      endpoint += `&filters[position][$eq]=${position}`;
    }

    return this.getContent<any>(endpoint).pipe(
      map(response => this.transformBanners(response))
    );
  }

  // Search content
  searchContent(query: string, types?: string[]): Observable<CMSContent[]> {
    const searchEndpoints = types || ['pages', 'products', 'blog-posts'];
    const searchPromises = searchEndpoints.map(type => 
      this.getContent<any>(`${type}?filters[$or][0][title][$containsi]=${query}&filters[$or][1][content][$containsi]=${query}&populate=*`)
        .pipe(map(response => this.transformSearchResults(response, type)))
        .toPromise()
    );

    return new Observable(observer => {
      Promise.all(searchPromises).then(results => {
        const allResults = results.flat().filter(Boolean);
        
        this.analyticsService.trackEvent({
          event: 'cms_search',
          event_category: 'cms',
          custom_parameters: {
            search_query: query,
            results_count: allResults.length,
            content_types: types?.join(',') || 'all'
          }
        });
        
        observer.next(allResults);
        observer.complete();
      }).catch(error => {
        observer.error(error);
      });
    });
  }

  // Cache management
  clearCache(): void {
    this.contentCache.clear();
    this.cacheExpiry.clear();
  }

  refreshContent(): void {
    this.clearCache();
    
    // Refresh all content streams
    this.getPages().subscribe(pages => this.pagesSubject.next(pages));
    this.getProducts().subscribe(products => this.productsSubject.next(products));
    this.getCategories().subscribe(categories => this.categoriesSubject.next(categories));
    this.getBlogPosts().subscribe(posts => this.blogPostsSubject.next(posts));
    this.getBanners().subscribe(banners => this.bannersSubject.next(banners));
  }

  // Private helper methods
  private getHeaders(): HttpHeaders {
    let headers = new HttpHeaders({
      'Content-Type': 'application/json'
    });

    if (this.currentProvider.id === 'strapi' && this.currentProvider.config.apiToken) {
      headers = headers.set('Authorization', `Bearer ${this.currentProvider.config.apiToken}`);
    } else if (this.currentProvider.id === 'sanity' && this.currentProvider.config.token) {
      headers = headers.set('Authorization', `Bearer ${this.currentProvider.config.token}`);
    } else if (this.currentProvider.id === 'contentful') {
      headers = headers.set('Authorization', `Bearer ${this.currentProvider.config.accessToken}`);
    }

    return headers;
  }

  private buildUrl(endpoint: string): string {
    return `${this.currentProvider.apiUrl}/${endpoint}`;
  }

  private buildFilters(filters: any): string {
    const params = new URLSearchParams();
    
    Object.keys(filters).forEach(key => {
      if (filters[key] !== undefined && filters[key] !== null) {
        params.append(`filters[${key}][$eq]`, filters[key]);
      }
    });
    
    return params.toString();
  }

  private isValidCache(key: string): boolean {
    return this.contentCache.has(key) && 
           this.cacheExpiry.has(key) && 
           this.cacheExpiry.get(key)! > Date.now();
  }

  // Data transformation methods (Strapi format)
  private transformPages(response: any): CMSPage[] {
    if (!response?.data) return [];
    
    return response.data.map((item: any) => ({
      id: item.id,
      title: item.attributes.title,
      slug: item.attributes.slug,
      content: item.attributes.content || [],
      metadata: item.attributes.metadata || {},
      template: item.attributes.template || 'default',
      status: item.attributes.status || 'published',
      publishedAt: item.attributes.publishedAt ? new Date(item.attributes.publishedAt) : undefined
    }));
  }

  private transformProducts(response: any): CMSProduct[] {
    if (!response?.data) return [];
    
    return response.data.map((item: any) => ({
      id: item.id,
      name: item.attributes.name,
      slug: item.attributes.slug,
      description: item.attributes.description || '',
      shortDescription: item.attributes.shortDescription,
      images: this.transformImages(item.attributes.images),
      price: item.attributes.price || 0,
      comparePrice: item.attributes.comparePrice,
      sku: item.attributes.sku || '',
      inventory: item.attributes.inventory || 0,
      categories: item.attributes.categories?.data?.map((cat: any) => cat.attributes.name) || [],
      tags: item.attributes.tags || [],
      variants: item.attributes.variants || [],
      attributes: item.attributes.productAttributes || {},
      metadata: item.attributes.metadata || {},
      status: item.attributes.status || 'published',
      featured: item.attributes.featured || false
    }));
  }

  private transformCategories(response: any): CMSCategory[] {
    if (!response?.data) return [];
    
    return response.data.map((item: any) => ({
      id: item.id,
      name: item.attributes.name,
      slug: item.attributes.slug,
      description: item.attributes.description,
      image: item.attributes.image ? this.transformImage(item.attributes.image) : undefined,
      parentId: item.attributes.parent?.data?.id,
      children: [],
      metadata: item.attributes.metadata || {},
      order: item.attributes.order || 0,
      visible: item.attributes.visible !== false
    }));
  }

  private transformBlogPosts(response: any): CMSBlogPost[] {
    if (!response?.data) return [];
    
    return response.data.map((item: any) => ({
      id: item.id,
      title: item.attributes.title,
      slug: item.attributes.slug,
      excerpt: item.attributes.excerpt || '',
      content: item.attributes.content || '',
      featuredImage: item.attributes.featuredImage ? this.transformImage(item.attributes.featuredImage) : undefined,
      author: item.attributes.author || 'Admin',
      categories: item.attributes.categories?.data?.map((cat: any) => cat.attributes.name) || [],
      tags: item.attributes.tags || [],
      metadata: item.attributes.metadata || {},
      status: item.attributes.status || 'published',
      publishedAt: item.attributes.publishedAt ? new Date(item.attributes.publishedAt) : undefined,
      readTime: item.attributes.readTime
    }));
  }

  private transformBanners(response: any): CMSBanner[] {
    if (!response?.data) return [];
    
    return response.data.map((item: any) => ({
      id: item.id,
      title: item.attributes.title,
      subtitle: item.attributes.subtitle,
      image: this.transformImage(item.attributes.image),
      link: item.attributes.link,
      buttonText: item.attributes.buttonText,
      position: item.attributes.position || 'hero',
      startDate: item.attributes.startDate ? new Date(item.attributes.startDate) : undefined,
      endDate: item.attributes.endDate ? new Date(item.attributes.endDate) : undefined,
      active: item.attributes.active !== false,
      order: item.attributes.order || 0
    }));
  }

  private transformSearchResults(response: any, type: string): CMSContent[] {
    if (!response?.data) return [];
    
    return response.data.map((item: any) => ({
      id: item.id,
      type: type.replace('-posts', '') as any,
      title: item.attributes.title || item.attributes.name,
      slug: item.attributes.slug,
      content: item.attributes.content || item.attributes.description,
      metadata: item.attributes.metadata || {},
      status: item.attributes.status || 'published',
      createdAt: new Date(item.attributes.createdAt),
      updatedAt: new Date(item.attributes.updatedAt),
      publishedAt: item.attributes.publishedAt ? new Date(item.attributes.publishedAt) : undefined,
      author: item.attributes.author,
      tags: item.attributes.tags || [],
      featured: item.attributes.featured || false
    }));
  }

  private transformImages(imagesData: any): CMSImage[] {
    if (!imagesData?.data) return [];
    
    return imagesData.data.map((img: any) => this.transformImage(img));
  }

  private transformImage(imageData: any): CMSImage {
    if (!imageData) return { id: '', url: '', alt: '' };
    
    const attrs = imageData.attributes || imageData;
    return {
      id: imageData.id || '',
      url: attrs.url || '',
      alt: attrs.alternativeText || attrs.alt || '',
      width: attrs.width,
      height: attrs.height,
      formats: attrs.formats
    };
  }
}