import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { map } from 'rxjs/operators';
import { AnalyticsService } from './analytics.service';

export interface ShippingProvider {
  id: string;
  name: string;
  enabled: boolean;
  config: any;
  countries: string[];
}

export interface ShippingMethod {
  id: string;
  provider: string;
  name: string;
  description: string;
  estimatedDays: string;
  cost: number;
  freeThreshold?: number;
  maxWeight?: number;
  trackingIncluded: boolean;
  icon?: string;
}

export interface ShippingAddress {
  firstName: string;
  lastName: string;
  company?: string;
  address1: string;
  address2?: string;
  city: string;
  state: string;
  postalCode: string;
  country: string;
  phone?: string;
}

export interface ShippingQuote {
  methodId: string;
  cost: number;
  estimatedDays: string;
  provider: string;
  trackingIncluded: boolean;
}

export interface ShippingLabel {
  id: string;
  trackingNumber: string;
  labelUrl: string;
  cost: number;
  provider: string;
  estimatedDelivery: Date;
}

export interface TrackingInfo {
  trackingNumber: string;
  status: 'pending' | 'in_transit' | 'out_for_delivery' | 'delivered' | 'exception';
  estimatedDelivery?: Date;
  actualDelivery?: Date;
  events: TrackingEvent[];
}

export interface TrackingEvent {
  timestamp: Date;
  status: string;
  location: string;
  description: string;
}

@Injectable({
  providedIn: 'root'
})
export class ShippingService {
  private http = inject(HttpClient);
  private analyticsService = inject(AnalyticsService);

  // Shipping providers configuration
  private providers: ShippingProvider[] = [
    {
      id: 'coordinadora',
      name: 'Coordinadora',
      enabled: true,
      countries: ['CO'],
      config: {
        apiKey: 'your_coordinadora_api_key',
        testMode: true
      }
    },
    {
      id: 'servientrega',
      name: 'Servientrega',
      enabled: true,
      countries: ['CO'],
      config: {
        apiKey: 'your_servientrega_api_key',
        testMode: true
      }
    },
    {
      id: 'tcc',
      name: 'TCC',
      enabled: true,
      countries: ['CO'],
      config: {
        apiKey: 'your_tcc_api_key',
        testMode: true
      }
    },
    {
      id: 'dhl',
      name: 'DHL Express',
      enabled: true,
      countries: ['CO', 'MX', 'BR', 'AR', 'CL', 'PE'],
      config: {
        apiKey: 'your_dhl_api_key',
        testMode: true
      }
    },
    {
      id: 'fedex',
      name: 'FedEx',
      enabled: true,
      countries: ['CO', 'MX', 'BR', 'AR', 'CL', 'PE'],
      config: {
        apiKey: 'your_fedex_api_key',
        testMode: true
      }
    },
    {
      id: 'ups',
      name: 'UPS',
      enabled: true,
      countries: ['CO', 'MX', 'BR', 'AR', 'CL', 'PE'],
      config: {
        apiKey: 'your_ups_api_key',
        testMode: true
      }
    }
  ];

  // Available shipping methods
  private shippingMethods: ShippingMethod[] = [
    {
      id: 'coordinadora_standard',
      provider: 'coordinadora',
      name: 'Envío Nacional Estándar',
      description: 'Entrega en 3-5 días hábiles',
      estimatedDays: '3-5 días',
      cost: 8000,
      freeThreshold: 150000,
      maxWeight: 30,
      trackingIncluded: true,
      icon: 'truck'
    },
    {
      id: 'coordinadora_express',
      provider: 'coordinadora',
      name: 'Envío Nacional Express',
      description: 'Entrega en 1-2 días hábiles',
      estimatedDays: '1-2 días',
      cost: 15000,
      maxWeight: 30,
      trackingIncluded: true,
      icon: 'zap'
    },
    {
      id: 'servientrega_standard',
      provider: 'servientrega',
      name: 'Envío Servientrega',
      description: 'Entrega en 2-4 días hábiles',
      estimatedDays: '2-4 días',
      cost: 9000,
      freeThreshold: 120000,
      maxWeight: 25,
      trackingIncluded: true,
      icon: 'truck'
    },
    {
      id: 'tcc_standard',
      provider: 'tcc',
      name: 'TCC Estándar',
      description: 'Entrega en 3-6 días hábiles',
      estimatedDays: '3-6 días',
      cost: 7500,
      freeThreshold: 100000,
      maxWeight: 20,
      trackingIncluded: true,
      icon: 'truck'
    },
    {
      id: 'dhl_express',
      provider: 'dhl',
      name: 'DHL Express',
      description: 'Entrega internacional en 1-3 días',
      estimatedDays: '1-3 días',
      cost: 45000,
      maxWeight: 70,
      trackingIncluded: true,
      icon: 'plane'
    },
    {
      id: 'fedex_international',
      provider: 'fedex',
      name: 'FedEx International',
      description: 'Entrega internacional en 2-5 días',
      estimatedDays: '2-5 días',
      cost: 38000,
      maxWeight: 68,
      trackingIncluded: true,
      icon: 'plane'
    },
    {
      id: 'ups_worldwide',
      provider: 'ups',
      name: 'UPS Worldwide',
      description: 'Entrega internacional en 3-7 días',
      estimatedDays: '3-7 días',
      cost: 42000,
      maxWeight: 70,
      trackingIncluded: true,
      icon: 'plane'
    }
  ];

  // Get available shipping providers
  getProviders(country?: string): ShippingProvider[] {
    let providers = this.providers.filter(p => p.enabled);
    
    if (country) {
      providers = providers.filter(p => p.countries.includes(country.toUpperCase()));
    }
    
    return providers;
  }

  // Get available shipping methods
  getShippingMethods(country: string, weight?: number, orderValue?: number): ShippingMethod[] {
    const availableProviders = this.getProviders(country).map(p => p.id);
    let methods = this.shippingMethods.filter(m => availableProviders.includes(m.provider));

    // Filter by weight if specified
    if (weight) {
      methods = methods.filter(m => !m.maxWeight || weight <= m.maxWeight);
    }

    // Apply free shipping threshold
    if (orderValue) {
      methods = methods.map(method => ({
        ...method,
        cost: method.freeThreshold && orderValue >= method.freeThreshold ? 0 : method.cost
      }));
    }

    return methods;
  }

  // Get shipping quotes
  getShippingQuotes(address: ShippingAddress, weight: number, orderValue: number): Observable<ShippingQuote[]> {
    const methods = this.getShippingMethods(address.country, weight, orderValue);
    
    const quotes: ShippingQuote[] = methods.map(method => ({
      methodId: method.id,
      cost: this.calculateShippingCost(method, address, weight, orderValue),
      estimatedDays: method.estimatedDays,
      provider: method.provider,
      trackingIncluded: method.trackingIncluded
    }));

    // Track shipping quote request
    this.analyticsService.trackEvent({
      event: 'shipping_quote_requested',
      event_category: 'shipping',
      custom_parameters: {
        country: address.country,
        city: address.city,
        weight,
        order_value: orderValue,
        quotes_count: quotes.length
      }
    });

    return of(quotes);
  }

  // Create shipping label
  createShippingLabel(methodId: string, address: ShippingAddress, weight: number, orderData: any): Observable<ShippingLabel> {
    const method = this.shippingMethods.find(m => m.id === methodId);
    if (!method) {
      throw new Error('Shipping method not found');
    }

    const trackingNumber = this.generateTrackingNumber(method.provider);
    const estimatedDelivery = this.calculateEstimatedDelivery(method.estimatedDays);
    
    const label: ShippingLabel = {
      id: `label_${Date.now()}`,
      trackingNumber,
      labelUrl: `https://api.shipping.com/labels/${trackingNumber}.pdf`,
      cost: this.calculateShippingCost(method, address, weight, orderData.total),
      provider: method.provider,
      estimatedDelivery
    };

    // Track label creation
    this.analyticsService.trackEvent({
      event: 'shipping_label_created',
      event_category: 'shipping',
      event_label: method.provider,
      custom_parameters: {
        tracking_number: trackingNumber,
        method_id: methodId,
        destination_country: address.country,
        weight,
        cost: label.cost
      }
    });

    console.log('📦 Shipping label created:', {
      trackingNumber,
      provider: method.provider,
      estimatedDelivery,
      destination: `${address.city}, ${address.country}`
    });

    return of(label);
  }

  // Track shipment
  trackShipment(trackingNumber: string): Observable<TrackingInfo> {
    // Simulate tracking information
    const trackingInfo: TrackingInfo = {
      trackingNumber,
      status: 'in_transit',
      estimatedDelivery: new Date(Date.now() + 3 * 24 * 60 * 60 * 1000), // 3 days from now
      events: [
        {
          timestamp: new Date(Date.now() - 2 * 24 * 60 * 60 * 1000),
          status: 'picked_up',
          location: 'Bogotá, CO',
          description: 'Paquete recogido del remitente'
        },
        {
          timestamp: new Date(Date.now() - 1 * 24 * 60 * 60 * 1000),
          status: 'in_transit',
          location: 'Centro de distribución Bogotá',
          description: 'Paquete en tránsito'
        },
        {
          timestamp: new Date(),
          status: 'out_for_delivery',
          location: 'Centro de distribución destino',
          description: 'Paquete en reparto'
        }
      ]
    };

    // Track shipment tracking request
    this.analyticsService.trackEvent({
      event: 'shipment_tracked',
      event_category: 'shipping',
      custom_parameters: {
        tracking_number: trackingNumber,
        status: trackingInfo.status
      }
    });

    return of(trackingInfo);
  }

  // Calculate shipping zones
  getShippingZone(address: ShippingAddress): string {
    const country = address.country.toUpperCase();
    const city = address.city.toLowerCase();

    // Colombian zones
    if (country === 'CO') {
      const mainCities = ['bogotá', 'medellín', 'cali', 'barranquilla', 'cartagena'];
      return mainCities.includes(city) ? 'zone_1' : 'zone_2';
    }

    // International zones
    const latinAmerica = ['MX', 'BR', 'AR', 'CL', 'PE', 'EC', 'VE', 'UY', 'PY'];
    if (latinAmerica.includes(country)) {
      return 'zone_3';
    }

    return 'zone_4'; // Rest of world
  }

  // Validate shipping address
  validateAddress(address: ShippingAddress): Observable<{ valid: boolean; suggestions?: ShippingAddress[] }> {
    // Simulate address validation
    const isValid = address.address1.length > 5 && 
                   address.city.length > 2 && 
                   address.postalCode.length > 3;

    return of({ valid: isValid });
  }

  // Private helper methods
  private calculateShippingCost(method: ShippingMethod, address: ShippingAddress, weight: number, orderValue: number): number {
    let cost = method.cost;

    // Apply free shipping threshold
    if (method.freeThreshold && orderValue >= method.freeThreshold) {
      return 0;
    }

    // Weight-based pricing
    if (weight > 5) {
      cost += (weight - 5) * 1000; // Additional cost per kg
    }

    // Zone-based pricing
    const zone = this.getShippingZone(address);
    switch (zone) {
      case 'zone_2':
        cost *= 1.2;
        break;
      case 'zone_3':
        cost *= 2.5;
        break;
      case 'zone_4':
        cost *= 4.0;
        break;
    }

    return Math.round(cost);
  }

  private generateTrackingNumber(provider: string): string {
    const prefix = provider.substring(0, 3).toUpperCase();
    const timestamp = Date.now().toString().slice(-8);
    const random = Math.random().toString(36).substring(2, 6).toUpperCase();
    return `${prefix}${timestamp}${random}`;
  }

  private calculateEstimatedDelivery(estimatedDays: string): Date {
    // Parse estimated days (e.g., "3-5 días" -> 4 days average)
    const match = estimatedDays.match(/(\d+)(?:-(\d+))?/);
    if (!match) return new Date(Date.now() + 3 * 24 * 60 * 60 * 1000);

    const minDays = parseInt(match[1]);
    const maxDays = match[2] ? parseInt(match[2]) : minDays;
    const avgDays = Math.ceil((minDays + maxDays) / 2);

    return new Date(Date.now() + avgDays * 24 * 60 * 60 * 1000);
  }
}