import { Injectable, Inject, PLATFORM_ID } from '@angular/core';
import { isPlatformBrowser } from '@angular/common';
import { Router, NavigationEnd } from '@angular/router';
import { filter } from 'rxjs/operators';

export interface AnalyticsEvent {
  event: string;
  event_category?: string;
  event_label?: string;
  value?: number;
  custom_parameters?: { [key: string]: any };
}

export interface EcommerceEvent {
  event: string;
  ecommerce: {
    currency?: string;
    value?: number;
    transaction_id?: string;
    items?: any[];
    item_list_id?: string;
    item_list_name?: string;
  };
}

export interface Product {
  item_id: string;
  item_name: string;
  item_category: string;
  item_category2?: string;
  item_brand?: string;
  price: number;
  quantity?: number;
  currency?: string;
}

@Injectable({
  providedIn: 'root'
})
export class AnalyticsService {
  private gtmId = 'GTM-XXXXXXX'; // Replace with your GTM ID
  private ga4Id = 'G-XXXXXXXXXX'; // Replace with your GA4 ID
  private metaPixelId = '1234567890123456'; // Replace with your Meta Pixel ID
  private tiktokPixelId = 'XXXXXXXXXXXXXXXXX'; // Replace with your TikTok Pixel ID
  private hotjarId = 1234567; // Replace with your Hotjar ID
  private clarityId = 'abcdefghij'; // Replace with your Clarity ID

  constructor(
    @Inject(PLATFORM_ID) private platformId: Object,
    private router: Router
  ) {
    if (isPlatformBrowser(this.platformId)) {
      this.initializeAnalytics();
      this.setupRouteTracking();
    }
  }

  private initializeAnalytics(): void {
    this.loadGTM();
    this.loadGA4();
    this.loadMetaPixel();
    this.loadTikTokPixel();
    this.loadHotjar();
    this.loadClarity();
  }

  private loadGTM(): void {
    // Google Tag Manager
    const script = document.createElement('script');
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtm.js?id=${this.gtmId}`;
    document.head.appendChild(script);

    // GTM Data Layer
    (window as any).dataLayer = (window as any).dataLayer || [];
    (window as any).dataLayer.push({
      'gtm.start': new Date().getTime(),
      event: 'gtm.js'
    });

    // GTM NoScript fallback
    const noscript = document.createElement('noscript');
    const iframe = document.createElement('iframe');
    iframe.src = `https://www.googletagmanager.com/ns.html?id=${this.gtmId}`;
    iframe.height = '0';
    iframe.width = '0';
    iframe.style.display = 'none';
    iframe.style.visibility = 'hidden';
    noscript.appendChild(iframe);
    document.body.appendChild(noscript);
  }

  private loadGA4(): void {
    // Google Analytics 4
    const script = document.createElement('script');
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtag/js?id=${this.ga4Id}`;
    document.head.appendChild(script);

    (window as any).gtag = (window as any).gtag || function() {
      ((window as any).gtag.q = (window as any).gtag.q || []).push(arguments);
    };
    (window as any).gtag('js', new Date());
    (window as any).gtag('config', this.ga4Id, {
      page_title: document.title,
      page_location: window.location.href
    });
  }

  private loadMetaPixel(): void {
    // Meta (Facebook) Pixel
    const script = document.createElement('script');
    script.innerHTML = `
      !function(f,b,e,v,n,t,s)
      {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
      n.callMethod.apply(n,arguments):n.queue.push(arguments)};
      if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
      n.queue=[];t=b.createElement(e);t.async=!0;
      t.src=v;s=b.getElementsByTagName(e)[0];
      s.parentNode.insertBefore(t,s)}(window, document,'script',
      'https://connect.facebook.net/en_US/fbevents.js');
      fbq('init', '${this.metaPixelId}');
      fbq('track', 'PageView');
    `;
    document.head.appendChild(script);

    // Meta Pixel NoScript fallback
    const noscript = document.createElement('noscript');
    const img = document.createElement('img');
    img.height = 1;
    img.width = 1;
    img.style.display = 'none';
    img.src = `https://www.facebook.com/tr?id=${this.metaPixelId}&ev=PageView&noscript=1`;
    noscript.appendChild(img);
    document.body.appendChild(noscript);
  }

  private loadTikTokPixel(): void {
    // TikTok Pixel
    const script = document.createElement('script');
    script.innerHTML = `
      !function (w, d, t) {
        w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=document.createElement("script");o.type="text/javascript",o.async=!0,o.src=i+"?sdkid="+e+"&lib="+t;var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};
        ttq.load('${this.tiktokPixelId}');
        ttq.page();
      }(window, document, 'ttq');
    `;
    document.head.appendChild(script);
  }

  private loadHotjar(): void {
    // Hotjar
    const script = document.createElement('script');
    script.innerHTML = `
      (function(h,o,t,j,a,r){
        h.hj=h.hj||function(){(h.hj.q=h.hj.q||[]).push(arguments)};
        h._hjSettings={hjid:${this.hotjarId},hjsv:6};
        a=o.getElementsByTagName('head')[0];
        r=o.createElement('script');r.async=1;
        r.src=t+h._hjSettings.hjid+j+h._hjSettings.hjsv;
        a.appendChild(r);
      })(window,document,'https://static.hotjar.com/c/hotjar-','.js?sv=');
    `;
    document.head.appendChild(script);
  }

  private loadClarity(): void {
    // Microsoft Clarity
    const script = document.createElement('script');
    script.innerHTML = `
      (function(c,l,a,r,i,t,y){
        c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
        t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
        y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
      })(window, document, "clarity", "script", "${this.clarityId}");
    `;
    document.head.appendChild(script);
  }

  private setupRouteTracking(): void {
    this.router.events
      .pipe(filter(event => event instanceof NavigationEnd))
      .subscribe((event: NavigationEnd) => {
        this.trackPageView(event.urlAfterRedirects);
      });
  }

  // Page View Tracking
  trackPageView(url: string): void {
    if (!isPlatformBrowser(this.platformId)) return;

    // GA4
    if ((window as any).gtag) {
      (window as any).gtag('config', this.ga4Id, {
        page_path: url,
        page_title: document.title,
        page_location: window.location.href
      });
    }

    // GTM
    if ((window as any).dataLayer) {
      (window as any).dataLayer.push({
        event: 'page_view',
        page_path: url,
        page_title: document.title,
        page_location: window.location.href
      });
    }

    // Meta Pixel
    if ((window as any).fbq) {
      (window as any).fbq('track', 'PageView');
    }

    // TikTok Pixel
    if ((window as any).ttq) {
      (window as any).ttq.page();
    }
  }

  // Generic Event Tracking
  trackEvent(event: AnalyticsEvent): void {
    if (!isPlatformBrowser(this.platformId)) return;

    // GA4
    if ((window as any).gtag) {
      (window as any).gtag('event', event.event, {
        event_category: event.event_category,
        event_label: event.event_label,
        value: event.value,
        ...event.custom_parameters
      });
    }

    // GTM
    if ((window as any).dataLayer) {
      (window as any).dataLayer.push({
        event: event.event,
        event_category: event.event_category,
        event_label: event.event_label,
        value: event.value,
        ...event.custom_parameters
      });
    }
  }

  // E-commerce Event Tracking
  trackEcommerceEvent(event: EcommerceEvent): void {
    if (!isPlatformBrowser(this.platformId)) return;

    // GA4 Enhanced Ecommerce
    if ((window as any).gtag) {
      (window as any).gtag('event', event.event, event.ecommerce);
    }

    // GTM
    if ((window as any).dataLayer) {
      (window as any).dataLayer.push({
        event: event.event,
        ecommerce: event.ecommerce
      });
    }
  }

  // Product View
  trackProductView(product: Product): void {
    this.trackEcommerceEvent({
      event: 'view_item',
      ecommerce: {
        currency: product.currency || 'USD',
        value: product.price,
        items: [product]
      }
    });

    // Meta Pixel
    if ((window as any).fbq) {
      (window as any).fbq('track', 'ViewContent', {
        content_type: 'product',
        content_ids: [product.item_id],
        content_name: product.item_name,
        content_category: product.item_category,
        value: product.price,
        currency: product.currency || 'USD'
      });
    }

    // TikTok Pixel
    if ((window as any).ttq) {
      (window as any).ttq.track('ViewContent', {
        content_type: 'product',
        content_id: product.item_id,
        content_name: product.item_name,
        content_category: product.item_category,
        value: product.price,
        currency: product.currency || 'USD'
      });
    }
  }

  // Add to Cart
  trackAddToCart(product: Product): void {
    this.trackEcommerceEvent({
      event: 'add_to_cart',
      ecommerce: {
        currency: product.currency || 'USD',
        value: product.price * (product.quantity || 1),
        items: [product]
      }
    });

    // Meta Pixel
    if ((window as any).fbq) {
      (window as any).fbq('track', 'AddToCart', {
        content_type: 'product',
        content_ids: [product.item_id],
        content_name: product.item_name,
        value: product.price * (product.quantity || 1),
        currency: product.currency || 'USD'
      });
    }

    // TikTok Pixel
    if ((window as any).ttq) {
      (window as any).ttq.track('AddToCart', {
        content_type: 'product',
        content_id: product.item_id,
        value: product.price * (product.quantity || 1),
        currency: product.currency || 'USD'
      });
    }
  }

  // Begin Checkout
  trackBeginCheckout(items: Product[], value: number, currency: string = 'USD'): void {
    this.trackEcommerceEvent({
      event: 'begin_checkout',
      ecommerce: {
        currency,
        value,
        items
      }
    });

    // Meta Pixel
    if ((window as any).fbq) {
      (window as any).fbq('track', 'InitiateCheckout', {
        content_type: 'product',
        content_ids: items.map(item => item.item_id),
        value,
        currency
      });
    }

    // TikTok Pixel
    if ((window as any).ttq) {
      (window as any).ttq.track('InitiateCheckout', {
        content_type: 'product',
        content_ids: items.map(item => item.item_id),
        value,
        currency
      });
    }
  }

  // Purchase
  trackPurchase(transactionId: string, items: Product[], value: number, currency: string = 'USD'): void {
    this.trackEcommerceEvent({
      event: 'purchase',
      ecommerce: {
        transaction_id: transactionId,
        currency,
        value,
        items
      }
    });

    // Meta Pixel
    if ((window as any).fbq) {
      (window as any).fbq('track', 'Purchase', {
        content_type: 'product',
        content_ids: items.map(item => item.item_id),
        value,
        currency
      });
    }

    // TikTok Pixel
    if ((window as any).ttq) {
      (window as any).ttq.track('CompletePayment', {
        content_type: 'product',
        content_ids: items.map(item => item.item_id),
        value,
        currency
      });
    }
  }

  // Search
  trackSearch(searchTerm: string): void {
    this.trackEvent({
      event: 'search',
      event_category: 'engagement',
      event_label: searchTerm,
      custom_parameters: {
        search_term: searchTerm
      }
    });

    // Meta Pixel
    if ((window as any).fbq) {
      (window as any).fbq('track', 'Search', {
        search_string: searchTerm
      });
    }

    // TikTok Pixel
    if ((window as any).ttq) {
      (window as any).ttq.track('Search', {
        query: searchTerm
      });
    }
  }

  // User Registration
  trackRegistration(method: string = 'email'): void {
    this.trackEvent({
      event: 'sign_up',
      event_category: 'engagement',
      custom_parameters: {
        method
      }
    });

    // Meta Pixel
    if ((window as any).fbq) {
      (window as any).fbq('track', 'CompleteRegistration');
    }

    // TikTok Pixel
    if ((window as any).ttq) {
      (window as any).ttq.track('CompleteRegistration');
    }
  }

  // User Login
  trackLogin(method: string = 'email'): void {
    this.trackEvent({
      event: 'login',
      event_category: 'engagement',
      custom_parameters: {
        method
      }
    });
  }

  // Custom Conversion Events
  trackCustomConversion(eventName: string, value?: number, currency: string = 'USD'): void {
    this.trackEvent({
      event: eventName,
      event_category: 'conversion',
      value,
      custom_parameters: {
        currency
      }
    });

    // Meta Pixel Custom Event
    if ((window as any).fbq) {
      (window as any).fbq('trackCustom', eventName, {
        value,
        currency
      });
    }

    // TikTok Pixel Custom Event
    if ((window as any).ttq) {
      (window as any).ttq.track(eventName, {
        value,
        currency
      });
    }
  }
}