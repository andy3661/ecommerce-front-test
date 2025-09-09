import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { Meta, Title } from '@angular/platform-browser';
import { SharedModule } from '../../shared/shared.module';

@Component({
  selector: 'app-home',
  standalone: true,
  imports: [CommonModule, RouterModule, SharedModule],
  templateUrl: './home.component.html',
  styleUrls: ['./home.component.css']
})
export class HomeComponent implements OnInit {
  private meta = inject(Meta);
  private title = inject(Title);
  
  featuredProducts = [
    {
      id: 1,
      name: 'Premium Wireless Headphones',
      price: 299.99,
      originalPrice: 399.99,
      image: '/assets/images/headphones.jpg',
      rating: 4.8,
      reviews: 124,
      badge: 'Sale'
    },
    {
      id: 2,
      name: 'Smart Fitness Watch',
      price: 199.99,
      image: '/assets/images/smartwatch.jpg',
      rating: 4.6,
      reviews: 89,
      badge: 'New'
    },
    {
      id: 3,
      name: 'Laptop Backpack Pro',
      price: 79.99,
      image: '/assets/images/backpack.jpg',
      rating: 4.7,
      reviews: 156,
      badge: 'Popular'
    },
    {
      id: 4,
      name: 'Wireless Charging Pad',
      price: 49.99,
      image: '/assets/images/charger.jpg',
      rating: 4.5,
      reviews: 67
    }
  ];
  
  categories = [
    {
      name: 'Electronics',
      slug: 'electronics',
      image: '/assets/images/electronics.jpg',
      productCount: 245
    },
    {
      name: 'Fashion',
      slug: 'fashion',
      image: '/assets/images/fashion.jpg',
      productCount: 189
    },
    {
      name: 'Home & Garden',
      slug: 'home-garden',
      image: '/assets/images/home-garden.jpg',
      productCount: 156
    },
    {
      name: 'Sports & Outdoors',
      slug: 'sports-outdoors',
      image: '/assets/images/sports.jpg',
      productCount: 134
    }
  ];
  
  ngOnInit() {
    this.setMetaTags();
  }
  
  private setMetaTags() {
    this.title.setTitle('Home - E-commerce Store | Quality Products Online');
    this.meta.updateTag({ 
      name: 'description', 
      content: 'Discover quality products at great prices. Shop electronics, fashion, home goods and more with fast shipping and excellent customer service.' 
    });
    this.meta.updateTag({ 
      name: 'keywords', 
      content: 'online shopping, electronics, fashion, home goods, quality products, fast shipping' 
    });
    this.meta.updateTag({ 
      property: 'og:title', 
      content: 'E-commerce Store - Quality Products Online' 
    });
    this.meta.updateTag({ 
      property: 'og:description', 
      content: 'Discover quality products at great prices. Shop electronics, fashion, home goods and more.' 
    });
    this.meta.updateTag({ 
      property: 'og:type', 
      content: 'website' 
    });
  }
  
  getStarArray(rating: number): number[] {
    const fullStars = Math.floor(rating);
    const hasHalfStar = rating % 1 !== 0;
    const stars = Array(fullStars).fill(1);
    if (hasHalfStar) stars.push(0.5);
    while (stars.length < 5) stars.push(0);
    return stars;
  }
}