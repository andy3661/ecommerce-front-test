import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, ActivatedRoute, Router } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { Meta, Title } from '@angular/platform-browser';
import { SharedModule } from '../../shared/shared.module';
import { MessageService } from 'primeng/api';
import { CartService } from '../../services/cart.service';
import { SeoService } from '../../services/seo.service';

interface Product {
  id: string;
  name: string;
  price: number;
  originalPrice?: number;
  rating: number;
  reviews: number;
  badge?: string;
  category: string;
  subcategory?: string;
  brand: string;
  inStock: boolean;
  imageUrl?: string;
  description?: string;
  tags?: string[];
}

interface Category {
  name: string;
  subcategories: string[];
}

interface FilterOptions {
  categories: string[];
  brands: string[];
  priceRange: [number, number];
  rating: number;
  inStock: boolean;
}

interface SortOption {
  label: string;
  value: string;
}

@Component({
  selector: 'app-product-list',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule, SharedModule],
  templateUrl: './product-list.component.html',
  styleUrls: ['./product-list.component.css'],
  providers: [MessageService]
})
export class ProductListComponent implements OnInit {
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private meta = inject(Meta);
  private titleService = inject(Title);
  private messageService = inject(MessageService);
  private cartService = inject(CartService);
  private seoService = inject(SeoService);

  // View state
  loading = false;
  viewMode: 'grid' | 'list' = 'grid';
  showFilters = false;
  Math = Math; // Make Math available in template

  // Pagination
  currentPage = 1;
  itemsPerPage = 12;
  pageSize = 12; // Alias for itemsPerPage
  totalItems = 0;
  totalProducts = 0; // Alias for totalItems

  // Sort options
  sortOptions: SortOption[] = [
    { label: 'Name A-Z', value: 'name-asc' },
    { label: 'Name Z-A', value: 'name-desc' },
    { label: 'Price: Low to High', value: 'price-asc' },
    { label: 'Price: High to Low', value: 'price-desc' },
    { label: 'Rating: High to Low', value: 'rating-desc' },
    { label: 'Newest First', value: 'newest' },
    { label: 'Best Selling', value: 'popular' }
  ];

  selectedSort = 'name-asc';

  // Categories with subcategories
  categories: Category[] = [
    {
      name: 'Electronics',
      subcategories: ['Smartphones', 'Laptops', 'Headphones', 'Cameras', 'Tablets']
    },
    {
      name: 'Clothing',
      subcategories: ['Men', 'Women', 'Kids', 'Shoes', 'Accessories']
    },
    {
      name: 'Home & Garden',
      subcategories: ['Furniture', 'Decor', 'Kitchen', 'Garden', 'Tools']
    },
    {
      name: 'Sports & Outdoors',
      subcategories: ['Fitness', 'Outdoor', 'Team Sports', 'Water Sports', 'Winter Sports']
    },
    {
      name: 'Books & Media',
      subcategories: ['Books', 'Movies', 'Music', 'Games', 'Software']
    }
  ];

  // Filter state
  filters: FilterOptions = {
    categories: [],
    brands: [],
    priceRange: [0, 1000],
    rating: 0,
    inStock: false
  };

  searchQuery = '';
  selectedCategory = '';
  selectedSubcategory = '';
  selectedBrands: string[] = [];
  selectedRating = 0;
  priceRange = [0, 1000];
  availableBrands: string[] = [];
  brands: string[] = []; // Alias for availableBrands
  ratingOptions = [5, 4, 3, 2, 1];
  maxPrice = 1000;

  // Products data
  products: Product[] = [
    {
      id: '1',
      name: 'Premium Wireless Headphones',
      price: 299.99,
      originalPrice: 399.99,
      rating: 4.5,
      reviews: 128,
      badge: 'Sale',
      category: 'Electronics',
      subcategory: 'Headphones',
      brand: 'TechBrand',
      inStock: true,
      description: 'High-quality wireless headphones with noise cancellation',
      tags: ['wireless', 'noise-cancelling', 'premium']
    },
    {
      id: '2',
      name: 'Smart Fitness Watch',
      price: 199.99,
      rating: 4.3,
      reviews: 89,
      badge: 'New',
      category: 'Electronics',
      subcategory: 'Tablets',
      brand: 'FitTech',
      inStock: true,
      description: 'Advanced fitness tracking with heart rate monitor',
      tags: ['fitness', 'smart', 'health']
    },
    {
      id: '3',
      name: 'Organic Cotton T-Shirt',
      price: 29.99,
      rating: 4.7,
      reviews: 245,
      category: 'Clothing',
      subcategory: 'Men',
      brand: 'EcoWear',
      inStock: false,
      description: 'Comfortable organic cotton t-shirt',
      tags: ['organic', 'cotton', 'eco-friendly']
    },
    {
      id: '4',
      name: 'Professional Camera Lens',
      price: 899.99,
      rating: 4.8,
      reviews: 67,
      badge: 'Popular',
      category: 'Electronics',
      subcategory: 'Cameras',
      brand: 'PhotoPro',
      inStock: true,
      description: 'Professional grade camera lens for photography',
      tags: ['professional', 'photography', 'lens']
    },
    {
      id: '5',
      name: 'Ergonomic Office Chair',
      price: 449.99,
      originalPrice: 599.99,
      rating: 4.4,
      reviews: 156,
      badge: 'Sale',
      category: 'Home & Garden',
      subcategory: 'Furniture',
      brand: 'ComfortPlus',
      inStock: true,
      description: 'Ergonomic office chair with lumbar support',
      tags: ['ergonomic', 'office', 'comfort']
    },
    {
      id: '6',
      name: 'Stainless Steel Water Bottle',
      price: 24.99,
      rating: 4.6,
      reviews: 312,
      category: 'Sports & Outdoors',
      subcategory: 'Fitness',
      brand: 'HydroLife',
      inStock: true,
      description: 'Insulated stainless steel water bottle',
      tags: ['stainless-steel', 'insulated', 'eco-friendly']
    },
    {
      id: '7',
      name: 'Gaming Laptop',
      price: 1299.99,
      rating: 4.2,
      reviews: 94,
      badge: 'New',
      category: 'Electronics',
      subcategory: 'Laptops',
      brand: 'GameTech',
      inStock: true,
      description: 'High-performance gaming laptop with RTX graphics',
      tags: ['gaming', 'laptop', 'high-performance']
    },
    {
      id: '8',
      name: 'Yoga Mat Premium',
      price: 79.99,
      rating: 4.5,
      reviews: 203,
      category: 'Sports & Outdoors',
      subcategory: 'Fitness',
      brand: 'YogaLife',
      inStock: true,
      description: 'Premium non-slip yoga mat',
      tags: ['yoga', 'fitness', 'non-slip']
    }
  ];

  filteredProducts: Product[] = [];
  paginatedProducts: Product[] = [];

  ngOnInit() {
    this.initializeData();
    this.setMetaTags();
    this.loadFiltersFromUrl();
  }

  initializeData() {
    this.availableBrands = [...new Set(this.products.map(p => p.brand))].sort();
    this.brands = this.availableBrands; // Keep alias in sync
    this.maxPrice = Math.max(...this.products.map(p => p.price));
    this.filters.priceRange = [0, this.maxPrice];
    this.applyFilters();
  }

  loadFiltersFromUrl() {
    this.route.queryParams.subscribe(params => {
      if (params['category']) {
        this.filters.categories = [params['category']];
      }
      if (params['search']) {
        this.searchQuery = params['search'];
      }
      if (params['sort']) {
        this.selectedSort = params['sort'];
      }
      this.applyFilters();
    });
  }

  onSearchChange() {
    this.currentPage = 1;
    this.applyFilters();
    this.updateUrl();
  }

  onCategoryChange(category: string, checked: boolean) {
    if (checked) {
      this.filters.categories.push(category);
    } else {
      this.filters.categories = this.filters.categories.filter(c => c !== category);
    }
    this.currentPage = 1;
    this.applyFilters();
    this.updateUrl();
  }

  onBrandChange(brand: string, checked: boolean) {
    if (checked) {
      this.filters.brands.push(brand);
    } else {
      this.filters.brands = this.filters.brands.filter(b => b !== brand);
    }
    this.currentPage = 1;
    this.applyFilters();
  }

  onPriceRangeChange() {
    this.currentPage = 1;
    this.applyFilters();
  }

  onRatingChange(rating: number) {
    this.filters.rating = rating;
    this.currentPage = 1;
    this.applyFilters();
  }

  onInStockChange(inStock: boolean) {
    this.filters.inStock = inStock;
    this.currentPage = 1;
    this.applyFilters();
  }

  onSortChange() {
    this.applyFilters();
    this.updateUrl();
  }

  onViewModeChange(mode: 'grid' | 'list') {
    this.viewMode = mode;
  }

  onPageChange(event: any) {
    this.currentPage = event.page + 1;
    this.updatePagination();
  }

  applyFilters() {
    let filtered = [...this.products];

    // Search filter
    if (this.searchQuery.trim()) {
      const query = this.searchQuery.toLowerCase();
      filtered = filtered.filter(product => 
        product.name.toLowerCase().includes(query) ||
        product.brand.toLowerCase().includes(query) ||
        product.category.toLowerCase().includes(query) ||
        (product.description && product.description.toLowerCase().includes(query)) ||
        (product.tags && product.tags.some(tag => tag.toLowerCase().includes(query)))
      );
    }

    // Category filter
    if (this.filters.categories.length > 0) {
      filtered = filtered.filter(product => 
        this.filters.categories.includes(product.category) ||
        (product.subcategory && this.filters.categories.includes(product.subcategory))
      );
    }

    // Brand filter
    if (this.filters.brands.length > 0) {
      filtered = filtered.filter(product => 
        this.filters.brands.includes(product.brand)
      );
    }

    // Price range filter
    filtered = filtered.filter(product => 
      product.price >= this.filters.priceRange[0] && 
      product.price <= this.filters.priceRange[1]
    );

    // Rating filter
    if (this.filters.rating > 0) {
      filtered = filtered.filter(product => product.rating >= this.filters.rating);
    }

    // In stock filter
    if (this.filters.inStock) {
      filtered = filtered.filter(product => product.inStock);
    }

    this.filteredProducts = filtered;
    this.totalItems = filtered.length;
    this.totalProducts = this.totalItems; // Keep alias in sync
    this.pageSize = this.itemsPerPage; // Keep alias in sync
    this.sortProducts();
    this.updatePagination();
  }

  sortProducts() {
    this.filteredProducts.sort((a, b) => {
      switch (this.selectedSort) {
        case 'name-asc':
          return a.name.localeCompare(b.name);
        case 'name-desc':
          return b.name.localeCompare(a.name);
        case 'price-asc':
          return a.price - b.price;
        case 'price-desc':
          return b.price - a.price;
        case 'rating-desc':
          return b.rating - a.rating;
        case 'popular':
          return b.reviews - a.reviews;
        case 'newest':
          return b.id.localeCompare(a.id);
        default:
          return 0;
      }
    });
  }

  updatePagination() {
    const startIndex = (this.currentPage - 1) * this.itemsPerPage;
    const endIndex = startIndex + this.itemsPerPage;
    this.paginatedProducts = this.filteredProducts.slice(startIndex, endIndex);
  }

  updateUrl() {
    const queryParams: any = {};
    
    if (this.searchQuery.trim()) {
      queryParams.search = this.searchQuery;
    }
    
    if (this.filters.categories.length > 0) {
      queryParams.category = this.filters.categories[0];
    }
    
    if (this.selectedSort !== 'name-asc') {
      queryParams.sort = this.selectedSort;
    }

    this.router.navigate([], {
      relativeTo: this.route,
      queryParams,
      queryParamsHandling: 'merge'
    });
  }

  clearFilters() {
    this.searchQuery = '';
    this.filters = {
      categories: [],
      brands: [],
      priceRange: [0, this.maxPrice],
      rating: 0,
      inStock: false
    };
    this.selectedSort = 'name-asc';
    this.currentPage = 1;
    this.applyFilters();
    this.updateUrl();
    
    this.messageService.add({
      severity: 'info',
      summary: 'Filters Cleared',
      detail: 'All filters have been reset'
    });
  }

  toggleFilters() {
    this.showFilters = !this.showFilters;
  }

  getStarArray(rating: number): number[] {
    const stars = [];
    const fullStars = Math.floor(rating);
    const hasHalfStar = rating % 1 !== 0;
    
    for (let i = 0; i < fullStars; i++) {
      stars.push(1);
    }
    
    if (hasHalfStar) {
      stars.push(0.5);
    }
    
    while (stars.length < 5) {
      stars.push(0);
    }
    
    return stars;
  }

  getDiscountPercentage(originalPrice: number, currentPrice: number): number {
    return Math.round(((originalPrice - currentPrice) / originalPrice) * 100);
  }

  addToCart(product: Product) {
    const success = this.cartService.addItem(product, 1);
    
    if (success) {
      this.messageService.add({
        severity: 'success',
        summary: 'Added to Cart',
        detail: `${product.name} has been added to your cart`
      });
    } else {
      this.messageService.add({
        severity: 'warn',
        summary: 'Cannot Add to Cart',
        detail: 'Maximum quantity reached for this item'
      });
    }
  }

  addToWishlist(product: Product) {
    // TODO: Implement wishlist functionality
    this.messageService.add({
      severity: 'info',
      summary: 'Added to Wishlist',
      detail: `${product.name} has been added to your wishlist`
    });
  }

  toggleWishlist(product: Product) {
    // TODO: Implement wishlist toggle functionality
    this.messageService.add({
      severity: 'info',
      summary: 'Wishlist Updated',
      detail: `${product.name} wishlist status updated`
    });
  }

  onImageError(event: any) {
    // Set a default placeholder image when image fails to load
    event.target.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMjAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0xMDAgMTAwTDEwMCAxMDBaIiBzdHJva2U9IiM5Q0E0QUYiIHN0cm9rZS13aWR0aD0iMiIvPgo8L3N2Zz4K';
  }

  getBadgeSeverity(badge?: string): 'success' | 'secondary' | 'info' | 'warning' | 'danger' | 'contrast' | undefined {
    switch (badge) {
      case 'Sale':
        return 'danger';
      case 'New':
        return 'success';
      case 'Popular':
        return 'info';
      default:
        return 'secondary';
    }
  }

  setViewMode(mode: 'grid' | 'list') {
    this.viewMode = mode;
    this.updateUrl();
  }

  onFilterChange() {
    this.currentPage = 1;
    this.applyFilters();
  }

  setMetaTags() {
    // Use the new SEO service for comprehensive SEO optimization
    this.seoService.setBasicSEO({
      title: 'Products - Premium E-commerce Store',
      description: 'Browse our extensive collection of premium products with advanced filtering and search capabilities.',
      keywords: 'products, shopping, e-commerce, electronics, clothing, furniture',
      type: 'website'
    });
  }

}