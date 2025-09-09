import { Component, ElementRef, ViewChild, signal, computed, effect, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { Subject, takeUntil, debounceTime, distinctUntilChanged, switchMap } from 'rxjs';
import { SearchService, SearchSuggestion } from '../../services/search.service';

// PrimeNG imports
import { AutoCompleteModule } from 'primeng/autocomplete';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { OverlayPanelModule } from 'primeng/overlaypanel';
import { ChipModule } from 'primeng/chip';
import { DividerModule } from 'primeng/divider';
import { BadgeModule } from 'primeng/badge';

@Component({
  selector: 'app-search-autocomplete',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    AutoCompleteModule,
    ButtonModule,
    InputTextModule,
    OverlayPanelModule,
    ChipModule,
    DividerModule,
    BadgeModule
  ],
  template: `
    <div class="relative w-full max-w-2xl">
      <!-- Search Input -->
      <div class="relative flex items-center">
        <input
          #searchInput
          type="text"
          [ngModel]="searchQuery()"
        (ngModelChange)="onSearchChange($event)"
          (input)="onSearchInput($event)"
          (focus)="onInputFocus()"
          (blur)="onInputBlur()"
          (keydown)="onKeyDown($event)"
          placeholder="Search products, brands, categories..."
          class="w-full px-4 py-3 pl-12 pr-16 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
          autocomplete="off"
        />
        
        <!-- Search Icon -->
        <i class="pi pi-search absolute left-4 text-gray-400 text-sm"></i>
        
        <!-- Clear Button -->
        <button
          *ngIf="searchQuery()"
          (click)="clearSearch()"
          class="absolute right-12 p-1 text-gray-400 hover:text-gray-600 transition-colors"
          type="button"
        >
          <i class="pi pi-times text-xs"></i>
        </button>
        
        <!-- Search Button -->
        <button
          (click)="performSearch()"
          class="absolute right-2 p-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
          type="button"
        >
          <i class="pi pi-search text-xs"></i>
        </button>
      </div>
      
      <!-- Suggestions Dropdown -->
      <div
        *ngIf="showSuggestions() && (suggestions().length > 0 || recentSearches().length > 0 || popularSearches().length > 0)"
        class="absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-50 max-h-96 overflow-y-auto"
      >
        <!-- Loading State -->
        <div *ngIf="isLoading()" class="p-4 text-center text-gray-500">
          <i class="pi pi-spinner pi-spin mr-2"></i>
          Searching...
        </div>
        
        <!-- Suggestions -->
        <div *ngIf="!isLoading() && suggestions().length > 0">
          <div class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide border-b">
            Suggestions
          </div>
          <div
            *ngFor="let suggestion of suggestions(); let i = index"
            (click)="selectSuggestion(suggestion)"
            (mouseenter)="highlightedIndex.set(i)"
            [class.bg-blue-50]="highlightedIndex() === i"
            class="flex items-center px-4 py-3 hover:bg-gray-50 cursor-pointer transition-colors"
          >
            <!-- Product Image -->
            <img
              *ngIf="suggestion.type === 'product' && suggestion.imageUrl"
              [src]="suggestion.imageUrl"
              [alt]="suggestion.text"
              class="w-8 h-8 rounded object-cover mr-3"
            />
            
            <!-- Type Icon -->
            <div *ngIf="suggestion.type !== 'product'" class="w-8 h-8 flex items-center justify-center mr-3">
              <i [class]="getSuggestionIcon(suggestion.type)" class="text-gray-400"></i>
            </div>
            
            <!-- Suggestion Content -->
            <div class="flex-1">
              <div class="text-sm font-medium text-gray-900">{{ suggestion.text }}</div>
              <div class="text-xs text-gray-500 capitalize">{{ suggestion.type }}</div>
            </div>
            
            <!-- Count Badge -->
            <span
              *ngIf="suggestion.count"
              class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-full"
            >
              {{ suggestion.count }}
            </span>
          </div>
        </div>
        
        <!-- Recent Searches -->
        <div *ngIf="!isLoading() && !searchQuery() && recentSearches().length > 0">
          <div class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide border-b">
            Recent Searches
          </div>
          <div
            *ngFor="let search of recentSearches()"
            (click)="selectRecentSearch(search)"
            class="flex items-center px-4 py-3 hover:bg-gray-50 cursor-pointer transition-colors"
          >
            <i class="pi pi-clock text-gray-400 mr-3"></i>
            <span class="text-sm text-gray-900">{{ search }}</span>
          </div>
        </div>
        
        <!-- Popular Searches -->
        <div *ngIf="!isLoading() && !searchQuery() && popularSearches().length > 0">
          <div class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide border-b">
            Popular Searches
          </div>
          <div
            *ngFor="let search of popularSearches()"
            (click)="selectPopularSearch(search)"
            class="flex items-center px-4 py-3 hover:bg-gray-50 cursor-pointer transition-colors"
          >
            <i class="pi pi-star text-gray-400 mr-3"></i>
            <span class="text-sm text-gray-900">{{ search }}</span>
          </div>
        </div>
        
        <!-- No Results -->
        <div *ngIf="!isLoading() && searchQuery() && suggestions().length === 0" class="p-4 text-center text-gray-500">
          <i class="pi pi-search mr-2"></i>
          No suggestions found for "{{ searchQuery() }}"
        </div>
      </div>
    </div>
  `,
  styles: [`
    :host {
      display: block;
      position: relative;
    }
    
    .suggestion-item:hover {
      background-color: #f8fafc;
    }
    
    .suggestion-item.highlighted {
      background-color: #eff6ff;
    }
  `]
})
export class SearchAutocompleteComponent implements OnInit, OnDestroy {
  @ViewChild('searchInput') searchInput!: ElementRef<HTMLInputElement>;
  
  private destroy$ = new Subject<void>();
  private searchSubject = new Subject<string>();
  
  // Signals
  searchQuery = signal('');
  suggestions = signal<SearchSuggestion[]>([]);
  showSuggestions = signal(false);
  isLoading = signal(false);
  highlightedIndex = signal(-1);
  
  // Computed signals from search service
  recentSearches = computed(() => this.searchService.recentSearches());
  popularSearches = computed(() => this.searchService.popularTerms());
  
  constructor(
    private searchService: SearchService,
    private router: Router
  ) {
    // Set up search debouncing
    this.searchSubject.pipe(
      debounceTime(300),
      distinctUntilChanged(),
      switchMap(query => {
        if (query.length >= 2) {
          this.isLoading.set(true);
          return this.searchService.getSuggestions(query);
        }
        return [];
      }),
      takeUntil(this.destroy$)
    ).subscribe(suggestions => {
      this.suggestions.set(suggestions);
      this.isLoading.set(false);
      this.highlightedIndex.set(-1);
    });
  }
  
  ngOnInit(): void {
    // Initialize with current search query from service
    const currentQuery = this.searchService.currentQuery();
    if (currentQuery) {
      this.searchQuery.set(currentQuery);
    }
  }
  
  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }
  
  onSearchInput(event: Event): void {
    const target = event.target as HTMLInputElement;
    const query = target.value;
    this.searchQuery.set(query);
    this.searchSubject.next(query);
    this.showSuggestions.set(true);
  }
  
  onInputFocus(): void {
    this.showSuggestions.set(true);
    if (!this.searchQuery() && this.recentSearches().length === 0 && this.popularSearches().length === 0) {
      this.showSuggestions.set(false);
    }
  }
  
  onInputBlur(): void {
    // Delay hiding suggestions to allow for clicks
    setTimeout(() => {
      this.showSuggestions.set(false);
    }, 200);
  }
  
  onKeyDown(event: KeyboardEvent): void {
    const suggestionsCount = this.suggestions().length;
    
    switch (event.key) {
      case 'ArrowDown':
        event.preventDefault();
        const nextIndex = this.highlightedIndex() < suggestionsCount - 1 
          ? this.highlightedIndex() + 1 
          : 0;
        this.highlightedIndex.set(nextIndex);
        break;
        
      case 'ArrowUp':
        event.preventDefault();
        const prevIndex = this.highlightedIndex() > 0 
          ? this.highlightedIndex() - 1 
          : suggestionsCount - 1;
        this.highlightedIndex.set(prevIndex);
        break;
        
      case 'Enter':
        event.preventDefault();
        if (this.highlightedIndex() >= 0 && this.suggestions().length > 0) {
          this.selectSuggestion(this.suggestions()[this.highlightedIndex()]);
        } else {
          this.performSearch();
        }
        break;
        
      case 'Escape':
        this.showSuggestions.set(false);
        this.searchInput.nativeElement.blur();
        break;
    }
  }
  
  onSearchChange(value: string): void {
    this.searchQuery.set(value);
  }
  
  clearSearch(): void {
    this.searchQuery.set('');
    this.suggestions.set([]);
    this.showSuggestions.set(false);
    this.searchInput.nativeElement.focus();
  }
  
  performSearch(): void {
    const query = this.searchQuery().trim();
    if (query) {
      this.searchService.setQuery(query);
      this.showSuggestions.set(false);
      this.router.navigate(['/search'], { queryParams: { q: query } });
    }
  }
  
  selectSuggestion(suggestion: SearchSuggestion): void {
    this.searchQuery.set(suggestion.text);
    this.showSuggestions.set(false);
    
    switch (suggestion.type) {
      case 'product':
        // Navigate to product detail
        const productId = suggestion.id.replace('product-', '');
        this.router.navigate(['/product', productId]);
        break;
        
      case 'category':
        // Navigate to category page
        this.searchService.searchByCategory(suggestion.text).subscribe();
        this.router.navigate(['/search'], { 
          queryParams: { category: suggestion.text } 
        });
        break;
        
      case 'brand':
        // Navigate to brand page
        this.searchService.searchByBrand(suggestion.text).subscribe();
        this.router.navigate(['/search'], { 
          queryParams: { brand: suggestion.text } 
        });
        break;
        
      case 'tag':
        // Search by tag
        this.searchService.setQuery(suggestion.text);
        this.router.navigate(['/search'], { 
          queryParams: { q: suggestion.text } 
        });
        break;
    }
  }
  
  selectRecentSearch(search: string): void {
    this.searchQuery.set(search);
    this.searchService.setQuery(search);
    this.showSuggestions.set(false);
    this.router.navigate(['/search'], { queryParams: { q: search } });
  }
  
  selectPopularSearch(search: string): void {
    this.searchQuery.set(search);
    this.searchService.setQuery(search);
    this.showSuggestions.set(false);
    this.router.navigate(['/search'], { queryParams: { q: search } });
  }
  
  getSuggestionIcon(type: string): string {
    switch (type) {
      case 'category':
        return 'pi pi-folder';
      case 'brand':
        return 'pi pi-tag';
      case 'tag':
        return 'pi pi-hashtag';
      default:
        return 'pi pi-search';
    }
  }
}