import { Injectable, inject, signal, computed } from '@angular/core';
import { Observable, BehaviorSubject } from 'rxjs';
import { map, catchError } from 'rxjs/operators';
import { ApiService, ApiResponse } from './api.service';
import { AnalyticsService } from './analytics.service';

export interface Product {
  id: string;
  name: string;
  slug: string;
  description: string;
  short_description?: string;
  price: number;
  sale_price?: number;
  sku: string;
  stock_quantity: number;
  manage_stock: boolean;
  in_stock: boolean;
  weight?: number;
  dimensions?: {
    length?: number;
    width?: number;
    height?: number;
  };
  images: ProductImage[];
  categories: ProductCategory[];
  tags?: string[];
  attributes?: ProductAttribute[];
  variations?: ProductVariation[];
  reviews?: ProductReview[];
  rating_average?: number;
  rating_count?: number;
  status: 'active' | 'inactive' | 'draft';
  featured: boolean;
  digital: boolean;
  downloadable: boolean;
  virtual: boolean;
  meta_title?: string;
  meta_description?: string;
  meta_keywords?: string[];
  created_at: string;
  updated_at: string;
}

export interface ProductImage {
  id: string;
  url: string;
  alt_text?: string;
  is_primary: boolean;
  sort_order: number;
}

export interface ProductCategory {
  id: string;
  name: string;
  slug: string;
  parent_id?: string;
}

export interface ProductAttribute {
  id: string;
  name: string;
  value: string;
  type: 'text' | 'number' | 'select' | 'multiselect' | 'boolean';
}

export interface ProductVariation {
  id: string;
  sku: string;
  price: number;
  sale_price?: number;
  stock_quantity: number;
  attributes: { [key: string]: string };
  image?: string;
}

export interface ProductReview {
  id: string;
  user_id: string;
  user_name: string;
  rating: number;
  title?: string;
  comment: string;
  verified_purchase: boolean;
  created_at: string;
}

export interface ProductFilters {
  search?: string;
  category_id?: string;
  min_price?: number;
  max_price?: number;
  in_stock?: boolean;
  featured?: boolean;
  rating?: number;
  attributes?: { [key: string]: string[] };
  tags?: string[];
}

export interface ProductSort {
  field: 'name' | 'price' | 'created_at' | 'rating' | 'popularity';
  direction: 'asc' | 'desc';
}

export interface ProductListResponse {
  products: Product[];
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
  filters: {
    categories: { id: string; name: string; count: number }[];
    price_ranges: { min: number; max: number; count: number }[];
    attributes: { name: string; values: { value: string; count: number }[] }[];
  };
}

@Injectable({
  providedIn: 'root'
})
export class ProductService {
  private apiService = inject(ApiService);
  private analyticsService = inject(AnalyticsService);

  private productsSubject = new BehaviorSubject<Product[]>([]);
  private filtersSubject = new BehaviorSubject<ProductFilters>({});
  private sortSubject = new BehaviorSubject<ProductSort>({ field: 'created_at', direction: 'desc' });
  
  public products$ = this.productsSubject.asObservable();
  public filters$ = this.filtersSubject.asObservable();
  public sort$ = this.sortSubject.asObservable();

  // Signals for reactive state
  private productsSignal = signal<Product[]>([]);
  private loadingSignal = signal<boolean>(false);
  private totalSignal = signal<number>(0);
  private currentPageSignal = signal<number>(1);
  private lastPageSignal = signal<number>(1);

  // Computed signals
  public products = this.productsSignal.asReadonly();
  public loading = this.loadingSignal.asReadonly();
  public total = this.totalSignal.asReadonly();
  public currentPage = this.currentPageSignal.asReadonly();
  public lastPage = this.lastPageSignal.asReadonly();
  public hasNextPage = computed(() => this.currentPageSignal() < this.lastPageSignal());
  public hasPrevPage = computed(() => this.currentPageSignal() > 1);

  constructor() {}

  // Get products with filters and pagination
  getProducts(filters: ProductFilters = {}, sort: ProductSort = { field: 'created_at', direction: 'desc' }, page: number = 1, perPage: number = 12): Observable<ProductListResponse> {
    this.loadingSignal.set(true);
    
    const params = {
      page,
      per_page: perPage,
      sort_by: sort.field,
      sort_direction: sort.direction,
      ...filters
    };

    return this.apiService.get<ProductListResponse>('products', params).pipe(
      map(response => {
        if (response.success && response.data) {
          this.productsSignal.set(response.data.products);
          this.totalSignal.set(response.data.total);
          this.currentPageSignal.set(response.data.current_page);
          this.lastPageSignal.set(response.data.last_page);
          this.productsSubject.next(response.data.products);
          this.filtersSubject.next(filters);
          this.sortSubject.next(sort);
          
          // Track product list view
          this.analyticsService.trackEvent({
            event: 'view_item_list',
            event_category: 'ecommerce',
            event_label: 'product_list',
            custom_parameters: {
              item_list_name: 'Product Catalog',
              items: response.data.products.slice(0, 5).map(product => ({
                item_id: product.id,
                item_name: product.name,
                item_category: product.categories[0]?.name || 'Uncategorized',
                price: product.price
              }))
            }
          });
        }
        this.loadingSignal.set(false);
        return response.data;
      }),
      catchError(error => {
        this.loadingSignal.set(false);
        throw error;
      })
    );
  }

  // Get single product by ID or slug
  getProduct(identifier: string): Observable<Product> {
    return this.apiService.get<Product>(`products/${identifier}`).pipe(
      map(response => {
        if (response.success && response.data) {
          // Track product view
          this.analyticsService.trackEvent({
            event: 'view_item',
            event_category: 'ecommerce',
            event_label: response.data.name,
            value: response.data.price,
            custom_parameters: {
              item_id: response.data.id,
              item_name: response.data.name,
              item_category: response.data.categories[0]?.name || 'Uncategorized',
              price: response.data.price,
              currency: 'USD'
            }
          });
        }
        return response.data;
      })
    );
  }

  // Search products
  searchProducts(query: string, filters: ProductFilters = {}, page: number = 1, perPage: number = 12): Observable<ProductListResponse> {
    const searchFilters = { ...filters, search: query };
    
    // Track search event
    this.analyticsService.trackEvent({
      event: 'search',
      event_category: 'engagement',
      event_label: 'product_search',
      custom_parameters: {
        search_term: query
      }
    });
    
    return this.getProducts(searchFilters, { field: 'name', direction: 'asc' }, page, perPage);
  }

  // Get featured products
  getFeaturedProducts(limit: number = 8): Observable<Product[]> {
    return this.apiService.get<Product[]>('products/featured', { limit }).pipe(
      map(response => response.data)
    );
  }

  // Get related products
  getRelatedProducts(productId: string, limit: number = 4): Observable<Product[]> {
    return this.apiService.get<Product[]>(`products/${productId}/related`, { limit }).pipe(
      map(response => response.data)
    );
  }

  // Get product categories
  getCategories(): Observable<ProductCategory[]> {
    return this.apiService.get<ProductCategory[]>('categories').pipe(
      map(response => response.data)
    );
  }

  // Get products by category
  getProductsByCategory(categoryId: string, page: number = 1, perPage: number = 12): Observable<ProductListResponse> {
    return this.getProducts({ category_id: categoryId }, { field: 'created_at', direction: 'desc' }, page, perPage);
  }

  // Add product review
  addReview(productId: string, review: { rating: number; title?: string; comment: string }): Observable<ProductReview> {
    return this.apiService.post<ProductReview>(`products/${productId}/reviews`, review).pipe(
      map(response => {
        if (response.success) {
          // Track review submission
          this.analyticsService.trackEvent({
            event: 'review_submitted',
            event_category: 'engagement',
            event_label: 'product_review',
            value: review.rating,
            custom_parameters: {
              product_id: productId,
              rating: review.rating
            }
          });
        }
        return response.data;
      })
    );
  }

  // Get product reviews
  getReviews(productId: string, page: number = 1, perPage: number = 10): Observable<{ reviews: ProductReview[]; total: number }> {
    return this.apiService.get<{ reviews: ProductReview[]; total: number }>(`products/${productId}/reviews`, {
      page,
      per_page: perPage
    }).pipe(
      map(response => response.data)
    );
  }

  // Track product interaction
  trackProductInteraction(product: Product, action: 'view' | 'add_to_cart' | 'add_to_wishlist'): void {
    const eventMap = {
      view: 'view_item',
      add_to_cart: 'add_to_cart',
      add_to_wishlist: 'add_to_wishlist'
    };

    this.analyticsService.trackEvent({
      event: eventMap[action],
      event_category: 'ecommerce',
      event_label: product.name,
      value: product.price,
      custom_parameters: {
        item_id: product.id,
        item_name: product.name,
        item_category: product.categories[0]?.name || 'Uncategorized',
        price: product.price,
        currency: 'USD'
      }
    });
  }

  // Update filters
  updateFilters(filters: ProductFilters): void {
    this.filtersSubject.next(filters);
  }

  // Update sort
  updateSort(sort: ProductSort): void {
    this.sortSubject.next(sort);
  }

  // Clear filters
  clearFilters(): void {
    this.filtersSubject.next({});
  }

  // Get current filters
  getCurrentFilters(): ProductFilters {
    return this.filtersSubject.value;
  }

  // Get current sort
  getCurrentSort(): ProductSort {
    return this.sortSubject.value;
  }

  // Check if product is in stock
  isInStock(product: Product, quantity: number = 1): boolean {
    if (!product.manage_stock) {
      return product.in_stock;
    }
    return product.stock_quantity >= quantity;
  }

  // Get product price (considering sale price)
  getProductPrice(product: Product): { price: number; originalPrice?: number; onSale: boolean } {
    const hasDiscount = product.sale_price && product.sale_price < product.price;
    
    return {
      price: hasDiscount ? product.sale_price! : product.price,
      originalPrice: hasDiscount ? product.price : undefined,
      onSale: !!hasDiscount
    };
  }

  // Format product URL
  getProductUrl(product: Product): string {
    return `/products/${product.slug || product.id}`;
  }
}