import { Injectable, inject } from '@angular/core';
import { DOCUMENT } from '@angular/common';

export interface ProductSchema {
  '@context': string;
  '@type': string;
  name: string;
  description: string;
  image: string[];
  brand: {
    '@type': string;
    name: string;
  };
  offers: {
    '@type': string;
    price: string;
    priceCurrency: string;
    availability: string;
    url: string;
  };
  aggregateRating?: {
    '@type': string;
    ratingValue: number;
    reviewCount: number;
  };
  sku: string;
  category: string;
}

export interface OrganizationSchema {
  '@context': string;
  '@type': string;
  name: string;
  url: string;
  logo: string;
  description: string;
  address?: {
    '@type': string;
    streetAddress: string;
    addressLocality: string;
    addressRegion: string;
    postalCode: string;
    addressCountry: string;
  };
  contactPoint?: {
    '@type': string;
    telephone: string;
    contactType: string;
  };
  sameAs?: string[];
}

export interface BreadcrumbSchema {
  '@context': string;
  '@type': string;
  itemListElement: Array<{
    '@type': string;
    position: number;
    name: string;
    item: string;
  }>;
}

export interface WebsiteSchema {
  '@context': string;
  '@type': string;
  name: string;
  url: string;
  description: string;
  potentialAction: {
    '@type': string;
    target: {
      '@type': string;
      urlTemplate: string;
    };
    'query-input': string;
  };
}

@Injectable({
  providedIn: 'root'
})
export class StructuredDataService {
  private document = inject(DOCUMENT);
  private readonly baseUrl = 'https://yourstore.com';

  /**
   * Add structured data to the page
   */
  addStructuredData(data: any, id?: string): void {
    const script = this.document.createElement('script');
    script.type = 'application/ld+json';
    script.text = JSON.stringify(data);
    
    if (id) {
      script.id = id;
      // Remove existing script with same ID
      const existing = this.document.getElementById(id);
      if (existing) {
        existing.remove();
      }
    }
    
    this.document.head.appendChild(script);
  }

  /**
   * Remove structured data by ID
   */
  removeStructuredData(id: string): void {
    const script = this.document.getElementById(id);
    if (script) {
      script.remove();
    }
  }

  /**
   * Generate product structured data
   */
  generateProductSchema(product: {
    id: string;
    name: string;
    description: string;
    images: string[];
    brand: string;
    price: number;
    currency: string;
    inStock: boolean;
    sku: string;
    category: string;
    rating?: {
      value: number;
      count: number;
    };
  }): ProductSchema {
    const schema: ProductSchema = {
      '@context': 'https://schema.org',
      '@type': 'Product',
      name: product.name,
      description: product.description,
      image: product.images,
      brand: {
        '@type': 'Brand',
        name: product.brand
      },
      offers: {
        '@type': 'Offer',
        price: product.price.toString(),
        priceCurrency: product.currency,
        availability: product.inStock 
          ? 'https://schema.org/InStock' 
          : 'https://schema.org/OutOfStock',
        url: `${this.baseUrl}/products/${product.id}`
      },
      sku: product.sku,
      category: product.category
    };

    if (product.rating && product.rating.count > 0) {
      schema.aggregateRating = {
        '@type': 'AggregateRating',
        ratingValue: product.rating.value,
        reviewCount: product.rating.count
      };
    }

    return schema;
  }

  /**
   * Generate organization structured data
   */
  generateOrganizationSchema(): OrganizationSchema {
    return {
      '@context': 'https://schema.org',
      '@type': 'Organization',
      name: 'E-commerce Store',
      url: this.baseUrl,
      logo: `${this.baseUrl}/assets/logo.png`,
      description: 'Premium online store offering high-quality products with fast shipping and excellent customer service.',
      address: {
        '@type': 'PostalAddress',
        streetAddress: '123 Commerce Street',
        addressLocality: 'Business City',
        addressRegion: 'BC',
        postalCode: '12345',
        addressCountry: 'US'
      },
      contactPoint: {
        '@type': 'ContactPoint',
        telephone: '+1-555-123-4567',
        contactType: 'customer service'
      },
      sameAs: [
        'https://facebook.com/yourstore',
        'https://twitter.com/yourstore',
        'https://instagram.com/yourstore'
      ]
    };
  }

  /**
   * Generate breadcrumb structured data
   */
  generateBreadcrumbSchema(breadcrumbs: Array<{ name: string; url: string }>): BreadcrumbSchema {
    return {
      '@context': 'https://schema.org',
      '@type': 'BreadcrumbList',
      itemListElement: breadcrumbs.map((crumb, index) => ({
        '@type': 'ListItem',
        position: index + 1,
        name: crumb.name,
        item: `${this.baseUrl}${crumb.url}`
      }))
    };
  }

  /**
   * Generate website structured data with search functionality
   */
  generateWebsiteSchema(): WebsiteSchema {
    return {
      '@context': 'https://schema.org',
      '@type': 'WebSite',
      name: 'E-commerce Store',
      url: this.baseUrl,
      description: 'Premium online store offering high-quality products',
      potentialAction: {
        '@type': 'SearchAction',
        target: {
          '@type': 'EntryPoint',
          urlTemplate: `${this.baseUrl}/products?search={search_term_string}`
        },
        'query-input': 'required name=search_term_string'
      }
    };
  }

  /**
   * Add product structured data to page
   */
  addProductStructuredData(product: any): void {
    const schema = this.generateProductSchema(product);
    this.addStructuredData(schema, 'product-schema');
  }

  /**
   * Add organization structured data to page
   */
  addOrganizationStructuredData(): void {
    const schema = this.generateOrganizationSchema();
    this.addStructuredData(schema, 'organization-schema');
  }

  /**
   * Add breadcrumb structured data to page
   */
  addBreadcrumbStructuredData(breadcrumbs: Array<{ name: string; url: string }>): void {
    const schema = this.generateBreadcrumbSchema(breadcrumbs);
    this.addStructuredData(schema, 'breadcrumb-schema');
  }

  /**
   * Add website structured data to page
   */
  addWebsiteStructuredData(): void {
    const schema = this.generateWebsiteSchema();
    this.addStructuredData(schema, 'website-schema');
  }

  /**
   * Clear all structured data
   */
  clearAllStructuredData(): void {
    const schemas = ['product-schema', 'organization-schema', 'breadcrumb-schema', 'website-schema'];
    schemas.forEach(id => this.removeStructuredData(id));
  }
}