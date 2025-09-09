import { Injectable, signal, computed, inject } from '@angular/core';
import { BehaviorSubject, Observable, of, debounceTime, distinctUntilChanged, switchMap } from 'rxjs';
import { AnalyticsService } from './analytics.service';

export interface SearchSuggestion {
  id: string;
  text: string;
  type: 'product' | 'category' | 'brand' | 'tag';
  count?: number;
  imageUrl?: string;
}

export interface SearchFilters {
  query: string;
  categories: string[];
  brands: string[];
  priceRange: [number, number];
  rating: number;
  inStock: boolean;
  sortBy: string;
}

export interface SearchResult {
  products: any[];
  suggestions: SearchSuggestion[];
  totalCount: number;
  facets: {
    categories: { name: string; count: number }[];
    brands: { name: string; count: number }[];
    priceRanges: { min: number; max: number; count: number }[];
  };
}

@Injectable({
  providedIn: 'root'
})
export class SearchService {
  private analyticsService = inject(AnalyticsService);
  
  private searchQuery = signal('');
  private searchFilters = signal<SearchFilters>({
    query: '',
    categories: [],
    brands: [],
    priceRange: [0, 1000],
    rating: 0,
    inStock: false,
    sortBy: 'relevance'
  });

  private searchHistory = signal<string[]>([]);
  private popularSearches = signal<string[]>([
    'wireless headphones',
    'gaming laptop',
    'smartphone',
    'fitness tracker',
    'bluetooth speaker'
  ]);

  // Mock product data for search (in real app, this would come from API)
  private mockProducts = [
    {
      id: '1',
      name: 'Premium Wireless Headphones',
      price: 299.99,
      originalPrice: 399.99,
      rating: 4.5,
      reviews: 128,
      category: 'Electronics',
      subcategory: 'Headphones',
      brand: 'TechBrand',
      inStock: true,
      description: 'High-quality wireless headphones with noise cancellation',
      tags: ['wireless', 'noise-cancelling', 'premium'],
      imageUrl: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=300&h=300&fit=crop'
    },
    {
      id: '2',
      name: 'Smart Fitness Watch',
      price: 199.99,
      rating: 4.3,
      reviews: 89,
      category: 'Electronics',
      subcategory: 'Wearables',
      brand: 'FitTech',
      inStock: true,
      description: 'Advanced fitness tracking with heart rate monitor',
      tags: ['fitness', 'smartwatch', 'health'],
      imageUrl: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=300&h=300&fit=crop'
    },
    {
      id: '3',
      name: 'Gaming Laptop',
      price: 1299.99,
      rating: 4.2,
      reviews: 94,
      category: 'Electronics',
      subcategory: 'Laptops',
      brand: 'GameTech',
      inStock: true,
      description: 'High-performance gaming laptop with RTX graphics',
      tags: ['gaming', 'laptop', 'high-performance'],
      imageUrl: 'https://images.unsplash.com/photo-1603302576837-37561b2e2302?w=300&h=300&fit=crop'
    },
    {
      id: '4',
      name: 'Bluetooth Speaker',
      price: 79.99,
      rating: 4.4,
      reviews: 156,
      category: 'Electronics',
      subcategory: 'Audio',
      brand: 'SoundWave',
      inStock: true,
      description: 'Portable wireless speaker with rich bass',
      tags: ['bluetooth', 'speaker', 'portable'],
      imageUrl: 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?w=300&h=300&fit=crop'
    }
  ];

  constructor() {
    this.loadSearchHistory();
  }

  // Computed signals
  currentQuery = computed(() => this.searchQuery());
  currentFilters = computed(() => this.searchFilters());
  recentSearches = computed(() => this.searchHistory().slice(0, 5));
  popularTerms = computed(() => this.popularSearches());

  // Search methods
  setQuery(query: string): void {
    this.searchQuery.set(query);
    this.updateFilters({ query });
    
    // Track search event
    if (query.trim()) {
      this.analyticsService.trackSearch(query.trim());
    }
  }

  updateFilters(filters: Partial<SearchFilters>): void {
    this.searchFilters.update(current => ({ ...current, ...filters }));
  }

  clearFilters(): void {
    this.searchFilters.set({
      query: this.searchQuery(),
      categories: [],
      brands: [],
      priceRange: [0, 1000],
      rating: 0,
      inStock: false,
      sortBy: 'relevance'
    });
  }

  // Search suggestions with debouncing
  getSuggestions(query: string): Observable<SearchSuggestion[]> {
    if (!query || query.length < 2) {
      return of([]);
    }

    return of(query).pipe(
      debounceTime(300),
      distinctUntilChanged(),
      switchMap(q => this.generateSuggestions(q))
    );
  }

  private generateSuggestions(query: string): Observable<SearchSuggestion[]> {
    const suggestions: SearchSuggestion[] = [];
    const lowerQuery = query.toLowerCase();

    // Product suggestions
    const productMatches = this.mockProducts.filter((product: any) =>
      product.name.toLowerCase().includes(lowerQuery) ||
      product.description.toLowerCase().includes(lowerQuery) ||
      (product.tags && product.tags.some((tag: string) => tag.toLowerCase().includes(lowerQuery)))
    ).slice(0, 5);

    productMatches.forEach(product => {
      suggestions.push({
        id: `product-${product.id}`,
        text: product.name,
        type: 'product',
        imageUrl: product.imageUrl
      });
    });

    // Category suggestions
    const categories = ['Electronics', 'Clothing', 'Home & Garden', 'Sports & Outdoors', 'Books & Media'];
    const categoryMatches = categories.filter(cat => 
      cat.toLowerCase().includes(lowerQuery)
    ).slice(0, 3);

    categoryMatches.forEach(category => {
      const count = this.mockProducts.filter(p => p.category === category).length;
      suggestions.push({
        id: `category-${category}`,
        text: category,
        type: 'category',
        count
      });
    });

    // Brand suggestions
    const brands = [...new Set(this.mockProducts.map(p => p.brand))];
    const brandMatches = brands.filter(brand => 
      brand.toLowerCase().includes(lowerQuery)
    ).slice(0, 3);

    brandMatches.forEach(brand => {
      const count = this.mockProducts.filter(p => p.brand === brand).length;
      suggestions.push({
        id: `brand-${brand}`,
        text: brand,
        type: 'brand',
        count
      });
    });

    // Tag suggestions
    const allTags: string[] = [];
    this.mockProducts.forEach((p: any) => {
      if (p.tags && Array.isArray(p.tags)) {
        allTags.push(...p.tags);
      }
    });
    const uniqueTags = [...new Set(allTags)];
    const tagMatches = uniqueTags.filter((tag: string) => 
      tag.toLowerCase().includes(lowerQuery)
    ).slice(0, 3);

    tagMatches.forEach((tag: string) => {
      suggestions.push({
        id: `tag-${tag}`,
        text: tag,
        type: 'tag'
      });
    });

    return of(suggestions.slice(0, 10));
  }

  // Perform search
  search(filters?: Partial<SearchFilters>): Observable<SearchResult> {
    if (filters) {
      this.updateFilters(filters);
    }

    const currentFilters = this.searchFilters();
    
    // Add to search history
    if (currentFilters.query.trim()) {
      this.addToSearchHistory(currentFilters.query);
      
      // Track search event if not already tracked
      if (currentFilters.query !== this.searchQuery()) {
        this.analyticsService.trackSearch(currentFilters.query.trim());
      }
    }

    return of(this.performSearch(currentFilters));
  }

  private performSearch(filters: SearchFilters): SearchResult {
    let results = [...this.mockProducts];

    // Apply text search
    if (filters.query.trim()) {
      const query = filters.query.toLowerCase();
      results = results.filter((product: any) =>
        product.name.toLowerCase().includes(query) ||
        product.brand.toLowerCase().includes(query) ||
        product.category.toLowerCase().includes(query) ||
        product.description.toLowerCase().includes(query) ||
        (product.tags && product.tags.some((tag: string) => tag.toLowerCase().includes(query)))
      );
    }

    // Apply category filter
    if (filters.categories.length > 0) {
      results = results.filter((product: any) =>
        filters.categories.includes(product.category) ||
        (product.subcategory && filters.categories.includes(product.subcategory))
      );
    }

    // Apply brand filter
    if (filters.brands.length > 0) {
      results = results.filter((product: any) =>
        filters.brands.includes(product.brand)
      );
    }

    // Apply price range filter
    results = results.filter((product: any) =>
      product.price >= filters.priceRange[0] &&
      product.price <= filters.priceRange[1]
    );

    // Apply rating filter
    if (filters.rating > 0) {
      results = results.filter((product: any) => product.rating >= filters.rating);
    }

    // Apply stock filter
    if (filters.inStock) {
      results = results.filter((product: any) => product.inStock);
    }

    // Apply sorting
    this.sortResults(results, filters.sortBy);

    // Generate facets
    const facets = this.generateFacets(results);

    return {
      products: results,
      suggestions: [],
      totalCount: results.length,
      facets
    };
  }

  private sortResults(results: any[], sortBy: string): void {
    switch (sortBy) {
      case 'price-asc':
        results.sort((a, b) => a.price - b.price);
        break;
      case 'price-desc':
        results.sort((a, b) => b.price - a.price);
        break;
      case 'rating':
        results.sort((a, b) => b.rating - a.rating);
        break;
      case 'popularity':
        results.sort((a, b) => b.reviews - a.reviews);
        break;
      case 'name':
        results.sort((a, b) => a.name.localeCompare(b.name));
        break;
      case 'relevance':
      default:
        // Keep original order for relevance
        break;
    }
  }

  private generateFacets(results: any[]) {
    const categories = new Map<string, number>();
    const brands = new Map<string, number>();
    const priceRanges = [
      { min: 0, max: 50, count: 0 },
      { min: 50, max: 100, count: 0 },
      { min: 100, max: 500, count: 0 },
      { min: 500, max: 1000, count: 0 },
      { min: 1000, max: Infinity, count: 0 }
    ];

    results.forEach(product => {
      // Count categories
      categories.set(product.category, (categories.get(product.category) || 0) + 1);
      
      // Count brands
      brands.set(product.brand, (brands.get(product.brand) || 0) + 1);
      
      // Count price ranges
      const priceRange = priceRanges.find(range => 
        product.price >= range.min && product.price < range.max
      );
      if (priceRange) {
        priceRange.count++;
      }
    });

    return {
      categories: Array.from(categories.entries()).map(([name, count]) => ({ name, count })),
      brands: Array.from(brands.entries()).map(([name, count]) => ({ name, count })),
      priceRanges: priceRanges.filter(range => range.count > 0)
    };
  }

  // Search history management
  private addToSearchHistory(query: string): void {
    const history = this.searchHistory();
    const newHistory = [query, ...history.filter(h => h !== query)].slice(0, 10);
    this.searchHistory.set(newHistory);
    this.saveSearchHistory();
  }

  clearSearchHistory(): void {
    this.searchHistory.set([]);
    this.saveSearchHistory();
  }

  private loadSearchHistory(): void {
    try {
      const saved = localStorage.getItem('search-history');
      if (saved) {
        this.searchHistory.set(JSON.parse(saved));
      }
    } catch (error) {
      console.warn('Failed to load search history:', error);
    }
  }

  private saveSearchHistory(): void {
    try {
      localStorage.setItem('search-history', JSON.stringify(this.searchHistory()));
    } catch (error) {
      console.warn('Failed to save search history:', error);
    }
  }

  // Quick search methods
  searchByCategory(category: string): Observable<SearchResult> {
    return this.search({ categories: [category], query: '' });
  }

  searchByBrand(brand: string): Observable<SearchResult> {
    return this.search({ brands: [brand], query: '' });
  }

  searchByTag(tag: string): Observable<SearchResult> {
    return this.search({ query: tag });
  }
}