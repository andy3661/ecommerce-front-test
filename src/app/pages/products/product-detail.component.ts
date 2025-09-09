import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, ActivatedRoute } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { Meta, Title } from '@angular/platform-browser';
import { CartService } from '../../services/cart.service';
import { SeoService } from '../../services/seo.service';
import { AnalyticsService } from '../../services/analytics.service';
import { MessageService } from 'primeng/api';

interface ProductVariant {
  id: string;
  name: string;
  value: string;
  price?: number;
  inStock: boolean;
  imageUrl?: string;
}

interface ProductImage {
  id: string;
  url: string;
  alt: string;
  isMain: boolean;
}

interface Product {
  id: string;
  name: string;
  description: string;
  shortDescription: string;
  price: number;
  originalPrice?: number;
  rating: number;
  reviews: number;
  reviewsCount: number;
  brand: string;
  category: string;
  sku: string;
  inStock: boolean;
  stockQuantity: number;
  images: ProductImage[];
  variants: {
    colors: ProductVariant[];
    sizes: ProductVariant[];
  };
  features: string[];
  specifications: { [key: string]: string };
  relatedProducts: any[];
}

@Component({
  selector: 'app-product-detail',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule],
  providers: [MessageService],
  template: `
    <div class="min-h-screen bg-white" *ngIf="product">
      <!-- Breadcrumbs -->
      <div class="bg-gray-50 border-b">
        <div class="container-custom py-4">
          <nav class="flex items-center space-x-2 text-sm">
            <a routerLink="/" class="text-gray-500 hover:text-primary-600">Home</a>
            <i class="pi pi-chevron-right text-gray-400 text-xs"></i>
            <a routerLink="/products" class="text-gray-500 hover:text-primary-600">Products</a>
            <i class="pi pi-chevron-right text-gray-400 text-xs"></i>
            <a [routerLink]="['/category', product.category.toLowerCase()]" class="text-gray-500 hover:text-primary-600">{{ product.category }}</a>
            <i class="pi pi-chevron-right text-gray-400 text-xs"></i>
            <span class="text-gray-900 font-medium">{{ product.name }}</span>
          </nav>
        </div>
      </div>

      <div class="container-custom py-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
          <!-- Product Images -->
          <div class="space-y-4">
            <!-- Main Image -->
            <div class="aspect-square bg-gray-100 rounded-2xl overflow-hidden">
              <div class="w-full h-full bg-gradient-to-br from-gray-200 to-gray-300 flex items-center justify-center">
                <i class="pi pi-image text-8xl text-gray-400"></i>
              </div>
            </div>
            
            <!-- Thumbnail Images -->
            <div class="grid grid-cols-4 gap-4">
              <div *ngFor="let image of product.images.slice(0, 4)" 
                   class="aspect-square bg-gray-100 rounded-lg overflow-hidden cursor-pointer border-2 hover:border-primary-500 transition-colors"
                   [class.border-primary-500]="selectedImageId === image.id"
                   (click)="selectImage(image.id)">
                <div class="w-full h-full bg-gradient-to-br from-gray-200 to-gray-300 flex items-center justify-center">
                  <i class="pi pi-image text-2xl text-gray-400"></i>
                </div>
              </div>
            </div>
          </div>

          <!-- Product Info -->
          <div class="space-y-6">
            <!-- Product Title & Rating -->
            <div>
              <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ product.name }}</h1>
              <p class="text-lg text-gray-600 mb-4">{{ product.shortDescription }}</p>
              
              <div class="flex items-center space-x-4 mb-4">
                <div class="flex items-center space-x-1">
                  <span *ngFor="let star of getStarArray(product.rating)" 
                        class="text-yellow-400"
                        [ngClass]="{
                          'pi pi-star-fill': star === 1,
                          'pi pi-star': star === 0
                        }">
                  </span>
                </div>
                <span class="text-sm text-gray-600">
                  {{ product.rating }} ({{ product.reviewsCount }} reviews)
                </span>
              </div>
              
              <p class="text-sm text-gray-500">SKU: {{ product.sku }} | Brand: {{ product.brand }}</p>
            </div>

            <!-- Price -->
            <div class="border-t border-b border-gray-200 py-6">
              <div class="flex items-center space-x-4">
                <span class="text-3xl font-bold text-gray-900">
                  {{ '$' + product.price }}
                </span>
                <span *ngIf="product.originalPrice" 
                      class="text-xl text-gray-500 line-through">
                  {{ '$' + product.originalPrice.toFixed(2) }}
                </span>
                <span *ngIf="product.originalPrice" 
                      class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm font-medium">
                  Save {{ '$' + (product.originalPrice - product.price).toFixed(2) }}
                </span>
              </div>
              
              <div class="mt-2 flex items-center space-x-2">
                <span class="text-sm" [ngClass]="product.inStock ? 'text-green-600' : 'text-red-600'">
                  <i class="pi" [ngClass]="product.inStock ? 'pi-check-circle' : 'pi-times-circle'"></i>
                  {{ product.inStock ? 'In Stock' : 'Out of Stock' }}
                </span>
                <span *ngIf="product.inStock && product.stockQuantity <= 10" class="text-sm text-orange-600">
                  (Only {{ product.stockQuantity }} left)
                </span>
              </div>
            </div>

            <!-- Variants -->
            <div class="space-y-4">
              <!-- Color Selection -->
              <div *ngIf="product.variants.colors.length > 0">
                <label class="block text-sm font-medium text-gray-700 mb-3">
                  Color: <span class="font-normal">{{ selectedColor?.name || 'Select a color' }}</span>
                </label>
                <div class="flex flex-wrap gap-3">
                  <button *ngFor="let color of product.variants.colors"
                          (click)="selectColor(color)"
                          [disabled]="!color.inStock"
                          class="w-12 h-12 rounded-full border-2 transition-all duration-200 relative"
                          [class]="getColorButtonClass(color)"
                          [title]="color.name">
                    <span class="sr-only">{{ color.name }}</span>
                    <div class="w-full h-full rounded-full" 
                         [style.background-color]="color.value">
                    </div>
                    <i *ngIf="!color.inStock" class="pi pi-times absolute inset-0 flex items-center justify-center text-white text-xs"></i>
                  </button>
                </div>
              </div>

              <!-- Size Selection -->
              <div *ngIf="product.variants.sizes.length > 0">
                <label class="block text-sm font-medium text-gray-700 mb-3">
                  Size: <span class="font-normal">{{ selectedSize?.name || 'Select a size' }}</span>
                </label>
                <div class="flex flex-wrap gap-3">
                  <button *ngFor="let size of product.variants.sizes"
                          (click)="selectSize(size)"
                          [disabled]="!size.inStock"
                          class="px-4 py-2 border rounded-lg transition-all duration-200"
                          [class]="getSizeButtonClass(size)">
                    {{ size.name }}
                  </button>
                </div>
              </div>
            </div>

            <!-- Quantity & Add to Cart -->
            <div class="space-y-4">
              <div class="flex items-center space-x-4">
                <label class="text-sm font-medium text-gray-700">Quantity:</label>
                <div class="flex items-center border border-gray-300 rounded-lg">
                  <button (click)="decreaseQuantity()" 
                          [disabled]="quantity <= 1"
                          class="px-3 py-2 text-gray-600 hover:text-gray-800 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="pi pi-minus"></i>
                  </button>
                  <input [(ngModel)]="quantity" 
                         type="number" 
                         min="1" 
                         [max]="product.stockQuantity"
                         class="w-16 px-3 py-2 text-center border-0 focus:outline-none">
                  <button (click)="increaseQuantity()" 
                          [disabled]="quantity >= product.stockQuantity"
                          class="px-3 py-2 text-gray-600 hover:text-gray-800 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="pi pi-plus"></i>
                  </button>
                </div>
              </div>

              <div class="flex flex-col sm:flex-row gap-4">
                <button (click)="addToCart()" 
                        [disabled]="!product.inStock || !canAddToCart()"
                        class="flex-1 btn-primary py-4 text-lg rounded-xl disabled:opacity-50 disabled:cursor-not-allowed">
                  <i class="pi pi-shopping-cart mr-2"></i>
                  Add to Cart
                </button>
                <button (click)="addToWishlist()" 
                        class="btn-outline py-4 px-6 rounded-xl">
                  <i class="pi pi-heart mr-2"></i>
                  Wishlist
                </button>
              </div>
            </div>

            <!-- Features -->
            <div *ngIf="product.features.length > 0" class="border-t border-gray-200 pt-6">
              <h3 class="text-lg font-semibold text-gray-900 mb-4">Key Features</h3>
              <ul class="space-y-2">
                <li *ngFor="let feature of product.features" class="flex items-start space-x-2">
                  <i class="pi pi-check text-green-600 mt-1 text-sm"></i>
                  <span class="text-gray-700">{{ feature }}</span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Product Details Tabs -->
        <div class="mt-16">
          <div class="border-b border-gray-200">
            <nav class="flex space-x-8">
              <button *ngFor="let tab of tabs" 
                      (click)="activeTab = tab.id"
                      class="py-4 px-1 border-b-2 font-medium text-sm transition-colors"
                      [class]="activeTab === tab.id ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
                {{ tab.label }}
              </button>
            </nav>
          </div>

          <div class="py-8">
            <!-- Description Tab -->
            <div *ngIf="activeTab === 'description'" class="prose max-w-none">
              <h3 class="text-xl font-semibold text-gray-900 mb-4">Product Description</h3>
              <p class="text-gray-700 leading-relaxed">{{ product.description }}</p>
            </div>

            <!-- Specifications Tab -->
            <div *ngIf="activeTab === 'specifications'">
              <h3 class="text-xl font-semibold text-gray-900 mb-6">Specifications</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div *ngFor="let spec of getSpecifications()" class="flex justify-between py-3 border-b border-gray-200">
                  <span class="font-medium text-gray-900">{{ spec.key }}</span>
                  <span class="text-gray-700">{{ spec.value }}</span>
                </div>
              </div>
            </div>

            <!-- Reviews Tab -->
            <div *ngIf="activeTab === 'reviews'">
              <h3 class="text-xl font-semibold text-gray-900 mb-6">Customer Reviews</h3>
              
              <!-- Reviews Summary -->
              <div class="bg-gray-50 rounded-xl p-6 mb-8">
                <div class="flex items-center space-x-6">
                  <div class="text-center">
                    <div class="text-4xl font-bold text-gray-900">{{ product.rating }}</div>
                    <div class="flex items-center justify-center space-x-1 mt-2">
                      <span *ngFor="let star of getStarArray(product.rating)" 
                            class="text-yellow-400"
                            [ngClass]="{
                              'pi pi-star-fill': star === 1,
                              'pi pi-star': star === 0
                            }">
                      </span>
                    </div>
                    <div class="text-sm text-gray-600 mt-1">{{ product.reviewsCount }} reviews</div>
                  </div>
                  
                  <div class="flex-1">
                    <div *ngFor="let rating of [5,4,3,2,1]" class="flex items-center space-x-3 mb-2">
                      <span class="text-sm text-gray-600 w-8">{{ rating }}</span>
                      <i class="pi pi-star-fill text-yellow-400 text-xs"></i>
                      <div class="flex-1 bg-gray-200 rounded-full h-2">
                        <div class="bg-yellow-400 h-2 rounded-full" [style.width.%]="getReviewPercentage(rating)"></div>
                      </div>
                      <span class="text-sm text-gray-600 w-8">{{ getReviewCount(rating) }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Individual Reviews -->
              <div class="space-y-6">
                <div *ngFor="let review of sampleReviews" class="border-b border-gray-200 pb-6">
                  <div class="flex items-start space-x-4">
                    <div class="w-10 h-10 bg-primary-100 rounded-full flex items-center justify-center">
                      <span class="text-primary-600 font-semibold">{{ review.author.charAt(0) }}</span>
                    </div>
                    <div class="flex-1">
                      <div class="flex items-center space-x-2 mb-2">
                        <span class="font-medium text-gray-900">{{ review.author }}</span>
                        <div class="flex items-center space-x-1">
                          <span *ngFor="let star of getStarArray(review.rating)" 
                                class="text-yellow-400 text-sm"
                                [ngClass]="{
                                  'pi pi-star-fill': star === 1,
                                  'pi pi-star': star === 0
                                }">
                          </span>
                        </div>
                        <span class="text-sm text-gray-500">{{ review.date }}</span>
                      </div>
                      <p class="text-gray-700">{{ review.comment }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Related Products -->
        <div *ngIf="product.relatedProducts.length > 0" class="mt-16">
          <h2 class="text-2xl font-bold text-gray-900 mb-8">Related Products</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <div *ngFor="let relatedProduct of product.relatedProducts" 
                 class="card group cursor-pointer transform hover:scale-105 transition-all duration-300 hover:shadow-strong">
              
              <!-- Product Image -->
              <div class="relative overflow-hidden bg-gray-100 aspect-square">
                <div class="w-full h-full bg-gradient-to-br from-gray-200 to-gray-300 flex items-center justify-center">
                  <i class="pi pi-image text-4xl text-gray-400"></i>
                </div>
              </div>
              
              <!-- Product Info -->
              <div class="p-4">
                <h3 class="font-semibold text-gray-900 mb-2 line-clamp-2">{{ relatedProduct.name }}</h3>
                <div class="flex items-center justify-between">
                  <span class="text-lg font-bold text-gray-900">{{ '$' + relatedProduct.price.toFixed(2) }}</span>
                  <a [routerLink]="['/products', relatedProduct.id]" 
                     class="text-primary-600 hover:text-primary-700 font-medium text-sm">
                    View
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div *ngIf="!product" class="min-h-screen flex items-center justify-center">
      <div class="text-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600 mx-auto mb-4"></div>
        <p class="text-gray-600">Loading product details...</p>
      </div>
    </div>

    <!-- JSON-LD Schema -->
    <script type="application/ld+json" [innerHTML]="getProductSchema()"></script>
  `
})
export class ProductDetailComponent implements OnInit {
  private route = inject(ActivatedRoute);
  private meta = inject(Meta);
  private titleService = inject(Title);
  private cartService = inject(CartService);
  private seoService = inject(SeoService);
  private analyticsService = inject(AnalyticsService);
  private messageService = inject(MessageService);

  product: Product | null = null;
  selectedImageId = '';
  selectedColor: ProductVariant | null = null;
  selectedSize: ProductVariant | null = null;
  quantity = 1;
  activeTab = 'description';

  tabs = [
    { id: 'description', label: 'Description' },
    { id: 'specifications', label: 'Specifications' },
    { id: 'reviews', label: 'Reviews' }
  ];

  sampleReviews = [
    {
      author: 'John Smith',
      rating: 5,
      date: '2024-01-15',
      comment: 'Excellent product! Exactly as described and fast shipping.'
    },
    {
      author: 'Sarah Johnson',
      rating: 4,
      date: '2024-01-10',
      comment: 'Good quality, though delivery took a bit longer than expected.'
    },
    {
      author: 'Mike Wilson',
      rating: 5,
      date: '2024-01-05',
      comment: 'Outstanding value for money. Highly recommended!'
    }
  ];

  ngOnInit() {
    this.route.params.subscribe(params => {
      const productId = params['id'];
      this.loadProduct(productId);
    });
  }

  loadProduct(id: string) {
    // Simulate API call - replace with actual service
    setTimeout(() => {
      this.product = {
        id: id,
        name: 'Premium Wireless Headphones',
        description: 'Experience superior sound quality with our premium wireless headphones. Featuring advanced noise cancellation technology, premium materials, and long-lasting battery life. Perfect for music lovers, professionals, and anyone who demands the best audio experience.',
        shortDescription: 'Premium wireless headphones with noise cancellation',
        price: 299.99,
        originalPrice: 399.99,
        rating: 4.5,
        reviews: 128,
        reviewsCount: 128,
        brand: 'TechBrand',
        category: 'Electronics',
        sku: 'TB-WH-001',
        inStock: true,
        stockQuantity: 15,
        images: [
          { id: '1', url: '', alt: 'Main product image', isMain: true },
          { id: '2', url: '', alt: 'Side view', isMain: false },
          { id: '3', url: '', alt: 'Detail view', isMain: false },
          { id: '4', url: '', alt: 'Packaging', isMain: false }
        ],
        variants: {
          colors: [
            { id: 'black', name: 'Midnight Black', value: '#000000', inStock: true },
            { id: 'white', name: 'Pearl White', value: '#FFFFFF', inStock: true },
            { id: 'blue', name: 'Ocean Blue', value: '#1E40AF', inStock: false }
          ],
          sizes: [
            { id: 'regular', name: 'Regular', value: 'regular', inStock: true },
            { id: 'large', name: 'Large', value: 'large', inStock: true }
          ]
        },
        features: [
          'Active Noise Cancellation',
          '30-hour battery life',
          'Premium leather ear cushions',
          'Bluetooth 5.0 connectivity',
          'Quick charge: 5 min = 3 hours playback',
          'Foldable design for portability'
        ],
        specifications: {
          'Driver Size': '40mm',
          'Frequency Response': '20Hz - 20kHz',
          'Impedance': '32 Ohms',
          'Battery Life': '30 hours',
          'Charging Time': '2 hours',
          'Weight': '250g',
          'Connectivity': 'Bluetooth 5.0, 3.5mm jack',
          'Warranty': '2 years'
        },
        relatedProducts: [
          { id: '2', name: 'Wireless Earbuds Pro', price: 199.99 },
          { id: '3', name: 'Gaming Headset', price: 149.99 },
          { id: '4', name: 'Studio Monitor Headphones', price: 349.99 },
          { id: '5', name: 'Portable Speaker', price: 89.99 }
        ]
      };
      
      this.selectedImageId = this.product.images[0]?.id || '';
      this.setMetaTags();
      this.trackProductView();
    }, 500);
  }

  selectImage(imageId: string) {
    this.selectedImageId = imageId;
  }

  selectColor(color: ProductVariant) {
    if (color.inStock) {
      this.selectedColor = color;
    }
  }

  selectSize(size: ProductVariant) {
    if (size.inStock) {
      this.selectedSize = size;
    }
  }

  getCurrentPrice(): number {
    let price = this.product?.price || 0;
    
    // Add variant price modifications if any
    if (this.selectedColor?.price) {
      price += this.selectedColor.price;
    }
    if (this.selectedSize?.price) {
      price += this.selectedSize.price;
    }
    
    return price;
  }

  getColorButtonClass(color: ProductVariant): string {
    const baseClass = 'border-2 transition-all duration-200';
    const selectedClass = this.selectedColor?.id === color.id ? 'border-primary-500 ring-2 ring-primary-200' : 'border-gray-300';
    const disabledClass = !color.inStock ? 'opacity-50 cursor-not-allowed' : 'hover:border-primary-400';
    
    return `${baseClass} ${selectedClass} ${disabledClass}`;
  }

  getSizeButtonClass(size: ProductVariant): string {
    const baseClass = 'border transition-all duration-200';
    const selectedClass = this.selectedSize?.id === size.id ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-gray-300 text-gray-700';
    const disabledClass = !size.inStock ? 'opacity-50 cursor-not-allowed bg-gray-100' : 'hover:border-primary-400';
    
    return `${baseClass} ${selectedClass} ${disabledClass}`;
  }

  increaseQuantity() {
    if (this.product && this.quantity < this.product.stockQuantity) {
      this.quantity++;
    }
  }

  decreaseQuantity() {
    if (this.quantity > 1) {
      this.quantity--;
    }
  }

  canAddToCart(): boolean {
    if (!this.product) return false;
    
    // Check if required variants are selected
    if (this.product.variants.colors.length > 0 && !this.selectedColor) {
      return false;
    }
    if (this.product.variants.sizes.length > 0 && !this.selectedSize) {
      return false;
    }
    
    return true;
  }

  addToCart() {
    if (!this.canAddToCart()) return;
    
    const variant = {
      color: this.selectedColor,
      size: this.selectedSize
    };
    
    const success = this.cartService.addItem(this.product, this.quantity, variant);
    
    if (success) {
      this.trackAddToCart();
      this.messageService.add({
        severity: 'success',
        summary: 'Added to Cart',
        detail: `${this.product?.name} has been added to your cart`
      });
    } else {
      this.messageService.add({
        severity: 'warn',
        summary: 'Cannot Add to Cart',
        detail: 'Maximum quantity reached for this item'
      });
    }
  }

  addToWishlist() {
    // Implement add to wishlist logic
    console.log('Adding to wishlist:', this.product);
    alert('Product added to wishlist!');
  }

  getStarArray(rating: number): number[] {
    const stars = [];
    const fullStars = Math.floor(rating);
    
    for (let i = 0; i < 5; i++) {
      stars.push(i < fullStars ? 1 : 0);
    }
    
    return stars;
  }

  getSpecifications(): { key: string; value: string }[] {
    if (!this.product) return [];
    
    return Object.entries(this.product.specifications).map(([key, value]) => ({
      key,
      value
    }));
  }

  getReviewPercentage(rating: number): number {
    // Mock data for review distribution
    const distribution: { [key: number]: number } = {
      5: 60,
      4: 25,
      3: 10,
      2: 3,
      1: 2
    };
    
    return distribution[rating] || 0;
  }

  getReviewCount(rating: number): number {
    if (!this.product) return 0;
    
    const percentage = this.getReviewPercentage(rating);
    return Math.round((percentage / 100) * this.product.reviewsCount);
  }

  getProductSchema(): string {
    if (!this.product) return '';
    
    const schema = {
      '@context': 'https://schema.org/',
      '@type': 'Product',
      'name': this.product.name,
      'description': this.product.description,
      'brand': {
        '@type': 'Brand',
        'name': this.product.brand
      },
      'sku': this.product.sku,
      'offers': {
        '@type': 'Offer',
        'price': this.getCurrentPrice(),
        'priceCurrency': 'USD',
        'availability': this.product.inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        'seller': {
          '@type': 'Organization',
          'name': 'E-commerce Store'
        }
      },
      'aggregateRating': {
        '@type': 'AggregateRating',
        'ratingValue': this.product.rating,
        'reviewCount': this.product.reviewsCount,
        'bestRating': 5,
        'worstRating': 1
      }
    };
    
    return JSON.stringify(schema);
  }

  setMetaTags() {
    if (!this.product) return;
    
    // Use the new SEO service for comprehensive SEO optimization
    this.seoService.setProductSEO({
      id: this.product.id,
      name: this.product.name,
      description: this.product.description,
      shortDescription: this.product.shortDescription,
      price: this.getCurrentPrice(),
      currency: 'USD',
      brand: this.product.brand,
      category: this.product.category,
      sku: this.product.sku,
      inStock: this.product.inStock,
      images: this.product.images?.map(img => img.url) || [],
      rating: {
        value: this.product.rating,
        count: this.product.reviewsCount
      },
      slug: this.product.id // Using ID as slug for now
    });
  }

  private trackProductView(): void {
    if (this.product) {
      this.analyticsService.trackProductView({
        item_id: this.product.id,
        item_name: this.product.name,
        item_category: this.product.category,
        item_brand: this.product.brand,
        price: this.getCurrentPrice(),
        currency: 'USD'
      });
    }
  }

  private trackAddToCart(): void {
    if (this.product) {
      this.analyticsService.trackAddToCart({
        item_id: this.product.id,
        item_name: this.product.name,
        item_category: this.product.category,
        item_brand: this.product.brand,
        price: this.getCurrentPrice(),
        quantity: this.quantity,
        currency: 'USD'
      });
    }
  }
}