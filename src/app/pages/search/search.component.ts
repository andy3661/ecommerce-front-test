import { Component, OnInit, OnDestroy, signal, computed, effect } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { Subject, takeUntil } from 'rxjs';
import { SearchService, SearchFilters, SearchResult } from '../../services/search.service';
import { SearchAutocompleteComponent } from '../../components/search-autocomplete/search-autocomplete.component';

// PrimeNG imports
import { ButtonModule } from 'primeng/button';
import { CheckboxModule } from 'primeng/checkbox';
import { SliderModule } from 'primeng/slider';
import { DropdownModule } from 'primeng/dropdown';
import { ChipModule } from 'primeng/chip';
import { BadgeModule } from 'primeng/badge';
import { SkeletonModule } from 'primeng/skeleton';
import { SidebarModule } from 'primeng/sidebar';
import { DividerModule } from 'primeng/divider';
import { TagModule } from 'primeng/tag';
import { RatingModule } from 'primeng/rating';
import { CardModule } from 'primeng/card';

interface SortOption {
  label: string;
  value: string;
}

@Component({
  selector: 'app-search',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    SearchAutocompleteComponent,
    ButtonModule,
    CheckboxModule,
    SliderModule,
    DropdownModule,
    ChipModule,
    BadgeModule,
    SkeletonModule,
    SidebarModule,
    DividerModule,
    TagModule,
    RatingModule,
    CardModule
  ],
  template: `
    <div class="min-h-screen bg-gray-50">
      <!-- Header -->
      <div class="bg-white border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
          <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">
              Search Results
              <span *ngIf="searchResult()?.totalCount" class="text-lg font-normal text-gray-500 ml-2">
                ({{ searchResult()?.totalCount }} {{ searchResult()?.totalCount === 1 ? 'item' : 'items' }})
              </span>
            </h1>
            
            <!-- Mobile Filter Toggle -->
            <button
              (click)="showMobileFilters = true"
              class="lg:hidden flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
            >
              <i class="pi pi-filter mr-2"></i>
              Filters
              <span *ngIf="activeFiltersCount() > 0" class="ml-2 bg-blue-600 text-white text-xs rounded-full px-2 py-1">
                {{ activeFiltersCount() }}
              </span>
            </button>
          </div>
          
          <!-- Search Bar -->
          <div class="mt-4">
            <app-search-autocomplete></app-search-autocomplete>
          </div>
          
          <!-- Active Filters -->
          <div *ngIf="activeFiltersCount() > 0" class="mt-4 flex flex-wrap gap-2">
            <p-chip
              *ngFor="let filter of activeFilterChips()"
              [label]="filter.label"
              [removable]="true"
              (onRemove)="removeFilter(filter.type, filter.value)"
              styleClass="bg-blue-100 text-blue-800"
            ></p-chip>
            <button
              (click)="clearAllFilters()"
              class="text-sm text-red-600 hover:text-red-800 font-medium"
            >
              Clear all
            </button>
          </div>
        </div>
      </div>
      
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex gap-6">
          <!-- Desktop Filters Sidebar -->
          <div class="hidden lg:block w-64 flex-shrink-0">
            <div class="bg-white rounded-lg shadow-sm border p-6 sticky top-6">
              <h3 class="text-lg font-semibold text-gray-900 mb-4">Filters</h3>
              
              <!-- Categories -->
              <div *ngIf="searchResult()?.facets?.categories?.length" class="mb-6">
                <h4 class="text-sm font-medium text-gray-900 mb-3">Categories</h4>
                <div class="space-y-2">
                  <div *ngFor="let category of searchResult()?.facets?.categories" class="flex items-center">
                    <p-checkbox
                      [inputId]="'cat-' + category.name"
                      [value]="category.name"
                      [(ngModel)]="selectedCategories"
                      (onChange)="onCategoryChange()"
                    ></p-checkbox>
                    <label [for]="'cat-' + category.name" class="ml-2 text-sm text-gray-700 cursor-pointer flex-1">
                      {{ category.name }}
                      <span class="text-gray-500 ml-1">({{ category.count }})</span>
                    </label>
                  </div>
                </div>
              </div>
              
              <!-- Brands -->
              <div *ngIf="searchResult()?.facets?.brands?.length" class="mb-6">
                <h4 class="text-sm font-medium text-gray-900 mb-3">Brands</h4>
                <div class="space-y-2">
                  <div *ngFor="let brand of searchResult()?.facets?.brands" class="flex items-center">
                    <p-checkbox
                      [inputId]="'brand-' + brand.name"
                      [value]="brand.name"
                      [(ngModel)]="selectedBrands"
                      (onChange)="onBrandChange()"
                    ></p-checkbox>
                    <label [for]="'brand-' + brand.name" class="ml-2 text-sm text-gray-700 cursor-pointer flex-1">
                      {{ brand.name }}
                      <span class="text-gray-500 ml-1">({{ brand.count }})</span>
                    </label>
                  </div>
                </div>
              </div>
              
              <!-- Price Range -->
              <div class="mb-6">
                <h4 class="text-sm font-medium text-gray-900 mb-3">Price Range</h4>
                <div class="px-2">
                  <p-slider
                    [(ngModel)]="priceRange"
                    [range]="true"
                    [min]="0"
                    [max]="2000"
                    [step]="10"
                    (onSlideEnd)="onPriceRangeChange()"
                  ></p-slider>
                  <div class="flex justify-between text-sm text-gray-600 mt-2">
                       <span>{{ priceRange[0] }}</span>
                       <span>{{ priceRange[1] }}</span>
                     </div>
                </div>
              </div>
              
              <!-- Rating -->
              <div class="mb-6">
                <h4 class="text-sm font-medium text-gray-900 mb-3">Minimum Rating</h4>
                <p-rating
                  [(ngModel)]="minRating"
                  [cancel]="true"
                  (onRate)="onRatingChange()"
                  (onCancel)="onRatingChange()"
                ></p-rating>
              </div>
              
              <!-- In Stock -->
              <div class="mb-6">
                <div class="flex items-center">
                  <p-checkbox
                    inputId="inStock"
                    [(ngModel)]="inStockOnly"
                    (onChange)="onStockChange()"
                  ></p-checkbox>
                  <label for="inStock" class="ml-2 text-sm text-gray-700 cursor-pointer">
                    In stock only
                  </label>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Main Content -->
          <div class="flex-1">
            <!-- Sort and View Options -->
            <div class="bg-white rounded-lg shadow-sm border p-4 mb-6">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                  <span class="text-sm text-gray-700">Sort by:</span>
                  <p-dropdown
                    [(ngModel)]="selectedSort"
                    [options]="sortOptions"
                    optionLabel="label"
                    optionValue="value"
                    (onChange)="onSortChange()"
                    [style]="{'min-width': '150px'}"
                  ></p-dropdown>
                </div>
                
                <div class="text-sm text-gray-600">
                  Showing {{ searchResult()?.products?.length || 0 }} of {{ searchResult()?.totalCount || 0 }} results
                </div>
              </div>
            </div>
            
            <!-- Loading State -->
            <div *ngIf="isLoading()" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              <div *ngFor="let i of [1,2,3,4,5,6]" class="bg-white rounded-lg shadow-sm border overflow-hidden">
                <p-skeleton height="200px"></p-skeleton>
                <div class="p-4">
                  <p-skeleton height="1.5rem" class="mb-2"></p-skeleton>
                  <p-skeleton height="1rem" width="60%" class="mb-2"></p-skeleton>
                  <p-skeleton height="1.25rem" width="40%"></p-skeleton>
                </div>
              </div>
            </div>
            
            <!-- Products Grid -->
            <div *ngIf="!isLoading() && searchResult()?.products?.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              <div *ngFor="let product of searchResult()?.products" class="bg-white rounded-lg shadow-sm border overflow-hidden hover:shadow-md transition-shadow cursor-pointer"
                   (click)="viewProduct(product.id)">
                <div class="aspect-square overflow-hidden">
                  <img
                    [src]="product.imageUrl"
                    [alt]="product.name"
                    class="w-full h-full object-cover hover:scale-105 transition-transform duration-300"
                  />
                </div>
                <div class="p-4">
                  <h3 class="font-semibold text-gray-900 mb-1 line-clamp-2">{{ product.name }}</h3>
                  <p class="text-sm text-gray-600 mb-2">{{ product.brand }}</p>
                  
                  <div class="flex items-center mb-2">
                    <p-rating
                      [ngModel]="product.rating"
                      [readonly]="true"
                      [cancel]="false"
                      [style]="{'font-size': '0.875rem'}"
                    ></p-rating>
                    <span class="text-sm text-gray-600 ml-2">({{ product.reviews }})</span>
                  </div>
                  
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                      <span class="text-lg font-bold text-gray-900">{{ product.price }}</span>
                      <span *ngIf="product.originalPrice" class="text-sm text-gray-500 line-through">
                        {{ product.originalPrice }}
                      </span>
                    </div>
                    
                    <p-tag
                      *ngIf="product.inStock"
                      value="In Stock"
                      severity="success"
                      [style]="{'font-size': '0.75rem'}"
                    ></p-tag>
                    <p-tag
                      *ngIf="!product.inStock"
                      value="Out of Stock"
                      severity="danger"
                      [style]="{'font-size': '0.75rem'}"
                    ></p-tag>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- No Results -->
            <div *ngIf="!isLoading() && searchResult()?.products?.length === 0" class="text-center py-12">
              <i class="pi pi-search text-4xl text-gray-400 mb-4"></i>
              <h3 class="text-lg font-medium text-gray-900 mb-2">No products found</h3>
              <p class="text-gray-600 mb-4">Try adjusting your search or filters to find what you're looking for.</p>
              <button
                (click)="clearAllFilters()"
                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
              >
                Clear all filters
              </button>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Mobile Filters Sidebar -->
      <p-sidebar
        [(visible)]="showMobileFilters"
        position="left"
        [style]="{'width': '300px'}"
        header="Filters"
      >
        <div class="space-y-6">
          <!-- Mobile Categories -->
          <div *ngIf="searchResult()?.facets?.categories?.length">
            <h4 class="text-sm font-medium text-gray-900 mb-3">Categories</h4>
            <div class="space-y-2">
              <div *ngFor="let category of searchResult()?.facets?.categories" class="flex items-center">
                <p-checkbox
                  [inputId]="'mobile-cat-' + category.name"
                  [value]="category.name"
                  [(ngModel)]="selectedCategories"
                  (onChange)="onCategoryChange()"
                ></p-checkbox>
                <label [for]="'mobile-cat-' + category.name" class="ml-2 text-sm text-gray-700 cursor-pointer flex-1">
                  {{ category.name }}
                  <span class="text-gray-500 ml-1">({{ category.count }})</span>
                </label>
              </div>
            </div>
          </div>
          
          <!-- Mobile Brands -->
          <div *ngIf="searchResult()?.facets?.brands?.length">
            <h4 class="text-sm font-medium text-gray-900 mb-3">Brands</h4>
            <div class="space-y-2">
              <div *ngFor="let brand of searchResult()?.facets?.brands" class="flex items-center">
                <p-checkbox
                  [inputId]="'mobile-brand-' + brand.name"
                  [value]="brand.name"
                  [(ngModel)]="selectedBrands"
                  (onChange)="onBrandChange()"
                ></p-checkbox>
                <label [for]="'mobile-brand-' + brand.name" class="ml-2 text-sm text-gray-700 cursor-pointer flex-1">
                  {{ brand.name }}
                  <span class="text-gray-500 ml-1">({{ brand.count }})</span>
                </label>
              </div>
            </div>
          </div>
          
          <!-- Mobile Price Range -->
          <div>
            <h4 class="text-sm font-medium text-gray-900 mb-3">Price Range</h4>
            <div class="px-2">
              <p-slider
                [(ngModel)]="priceRange"
                [range]="true"
                [min]="0"
                [max]="2000"
                [step]="10"
                (onSlideEnd)="onPriceRangeChange()"
              ></p-slider>
              <div class="flex justify-between text-sm text-gray-600 mt-2">
                <span>{{ priceRange[0] }}</span>
                <span>{{ priceRange[1] }}</span>
              </div>
            </div>
          </div>
          
          <!-- Mobile Rating -->
          <div>
            <h4 class="text-sm font-medium text-gray-900 mb-3">Minimum Rating</h4>
            <p-rating
              [(ngModel)]="minRating"
              [cancel]="true"
              (onRate)="onRatingChange()"
              (onCancel)="onRatingChange()"
            ></p-rating>
          </div>
          
          <!-- Mobile In Stock -->
          <div>
            <div class="flex items-center">
              <p-checkbox
                inputId="mobileInStock"
                [(ngModel)]="inStockOnly"
                (onChange)="onStockChange()"
              ></p-checkbox>
              <label for="mobileInStock" class="ml-2 text-sm text-gray-700 cursor-pointer">
                In stock only
              </label>
            </div>
          </div>
        </div>
      </p-sidebar>
    </div>
  `,
  styles: [`
    .line-clamp-2 {
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
  `]
})
export class SearchComponent implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  
  // Signals
  searchResult = signal<SearchResult | null>(null);
  isLoading = signal(false);
  
  // Filter states
  selectedCategories: string[] = [];
  selectedBrands: string[] = [];
  priceRange: number[] = [0, 2000];
  minRating: number = 0;
  inStockOnly: boolean = false;
  selectedSort: string = 'relevance';
  
  // UI states
  showMobileFilters = false;
  
  // Sort options
  sortOptions: SortOption[] = [
    { label: 'Relevance', value: 'relevance' },
    { label: 'Price: Low to High', value: 'price-asc' },
    { label: 'Price: High to Low', value: 'price-desc' },
    { label: 'Customer Rating', value: 'rating' },
    { label: 'Popularity', value: 'popularity' },
    { label: 'Name A-Z', value: 'name' }
  ];
  
  // Computed signals
  activeFiltersCount = computed(() => {
    let count = 0;
    if (this.selectedCategories.length > 0) count += this.selectedCategories.length;
    if (this.selectedBrands.length > 0) count += this.selectedBrands.length;
    if (this.priceRange[0] > 0 || this.priceRange[1] < 2000) count++;
    if (this.minRating > 0) count++;
    if (this.inStockOnly) count++;
    return count;
  });
  
  activeFilterChips = computed(() => {
    const chips: Array<{label: string, type: string, value: any}> = [];
    
    this.selectedCategories.forEach(cat => {
      chips.push({ label: `Category: ${cat}`, type: 'category', value: cat });
    });
    
    this.selectedBrands.forEach(brand => {
      chips.push({ label: `Brand: ${brand}`, type: 'brand', value: brand });
    });
    
    if (this.priceRange[0] > 0 || this.priceRange[1] < 2000) {
      chips.push({ 
        label: `Price: $${this.priceRange[0]} - $${this.priceRange[1]}`, 
        type: 'price', 
        value: this.priceRange 
      });
    }
    
    if (this.minRating > 0) {
      chips.push({ 
        label: `Rating: ${this.minRating}+ stars`, 
        type: 'rating', 
        value: this.minRating 
      });
    }
    
    if (this.inStockOnly) {
      chips.push({ label: 'In Stock Only', type: 'stock', value: true });
    }
    
    return chips;
  });
  
  constructor(
    private searchService: SearchService,
    private route: ActivatedRoute,
    private router: Router
  ) {}
  
  ngOnInit(): void {
    // Listen to query params
    this.route.queryParams.pipe(
      takeUntil(this.destroy$)
    ).subscribe(params => {
      this.handleQueryParams(params);
    });
  }
  
  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }
  
  private handleQueryParams(params: any): void {
    const filters: Partial<SearchFilters> = {
      query: params['q'] || '',
      categories: params['category'] ? [params['category']] : [],
      brands: params['brand'] ? [params['brand']] : [],
      sortBy: params['sort'] || 'relevance'
    };
    
    // Update local filter states
    this.selectedCategories = filters.categories || [];
    this.selectedBrands = filters.brands || [];
    this.selectedSort = filters.sortBy || 'relevance';
    
    this.performSearch(filters);
  }
  
  private performSearch(filters?: Partial<SearchFilters>): void {
    this.isLoading.set(true);
    
    const searchFilters: Partial<SearchFilters> = {
      categories: this.selectedCategories,
      brands: this.selectedBrands,
      priceRange: [this.priceRange[0], this.priceRange[1]],
      rating: this.minRating,
      inStock: this.inStockOnly,
      sortBy: this.selectedSort,
      ...filters
    };
    
    this.searchService.search(searchFilters).pipe(
      takeUntil(this.destroy$)
    ).subscribe(result => {
      this.searchResult.set(result);
      this.isLoading.set(false);
    });
  }
  
  // Filter change handlers
  onCategoryChange(): void {
    this.performSearch();
    this.updateQueryParams();
  }
  
  onBrandChange(): void {
    this.performSearch();
    this.updateQueryParams();
  }
  
  onPriceRangeChange(): void {
    this.performSearch();
    this.updateQueryParams();
  }
  
  onRatingChange(): void {
    this.performSearch();
    this.updateQueryParams();
  }
  
  onStockChange(): void {
    this.performSearch();
    this.updateQueryParams();
  }
  
  onSortChange(): void {
    this.performSearch();
    this.updateQueryParams();
  }
  
  private updateQueryParams(): void {
    const queryParams: any = {};
    
    const currentQuery = this.searchService.currentQuery();
    if (currentQuery) {
      queryParams['q'] = currentQuery;
    }
    
    if (this.selectedCategories.length === 1) {
      queryParams['category'] = this.selectedCategories[0];
    }
    
    if (this.selectedBrands.length === 1) {
      queryParams['brand'] = this.selectedBrands[0];
    }
    
    if (this.selectedSort !== 'relevance') {
      queryParams['sort'] = this.selectedSort;
    }
    
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams,
      queryParamsHandling: 'merge'
    });
  }
  
  removeFilter(type: string, value: any): void {
    switch (type) {
      case 'category':
        this.selectedCategories = this.selectedCategories.filter(c => c !== value);
        break;
      case 'brand':
        this.selectedBrands = this.selectedBrands.filter(b => b !== value);
        break;
      case 'price':
        this.priceRange = [0, 2000];
        break;
      case 'rating':
        this.minRating = 0;
        break;
      case 'stock':
        this.inStockOnly = false;
        break;
    }
    
    this.performSearch();
    this.updateQueryParams();
  }
  
  clearAllFilters(): void {
    this.selectedCategories = [];
    this.selectedBrands = [];
    this.priceRange = [0, 2000];
    this.minRating = 0;
    this.inStockOnly = false;
    this.selectedSort = 'relevance';
    
    this.searchService.clearFilters();
    this.performSearch();
    
    // Clear query params except search query
    const currentQuery = this.searchService.currentQuery();
    const queryParams = currentQuery ? { q: currentQuery } : {};
    
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams,
      replaceUrl: true
    });
  }
  
  viewProduct(productId: string): void {
    this.router.navigate(['/product', productId]);
  }
}