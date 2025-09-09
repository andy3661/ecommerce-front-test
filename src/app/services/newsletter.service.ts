import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { EmailService } from './email.service';
import { AnalyticsService } from './analytics.service';

export interface NewsletterSubscriber {
  id: string;
  email: string;
  firstName?: string;
  lastName?: string;
  subscriptionDate: Date;
  isActive: boolean;
  preferences: {
    productUpdates: boolean;
    promotions: boolean;
    newsletter: boolean;
    orderUpdates: boolean;
  };
  tags: string[];
}

export interface NewsletterCampaign {
  id: string;
  name: string;
  subject: string;
  htmlContent: string;
  textContent: string;
  scheduledDate?: Date;
  sentDate?: Date;
  status: 'draft' | 'scheduled' | 'sent' | 'cancelled';
  recipients: string[];
  stats: {
    sent: number;
    opened: number;
    clicked: number;
    unsubscribed: number;
  };
}

export interface SubscriptionData {
  email: string;
  firstName?: string;
  lastName?: string;
  preferences?: {
    productUpdates?: boolean;
    promotions?: boolean;
    newsletter?: boolean;
    orderUpdates?: boolean;
  };
  source?: string;
}

@Injectable({
  providedIn: 'root'
})
export class NewsletterService {
  private http = inject(HttpClient);
  private emailService = inject(EmailService);
  private analyticsService = inject(AnalyticsService);

  private subscribers: NewsletterSubscriber[] = [];
  private campaigns: NewsletterCampaign[] = [];

  // Subscribe to newsletter
  subscribe(data: SubscriptionData): Observable<{ success: boolean; error?: string }> {
    try {
      // Validate email
      if (!this.isValidEmail(data.email)) {
        return of({ success: false, error: 'Please enter a valid email address' });
      }

      // Check if already subscribed
      const existingSubscriber = this.subscribers.find(sub => sub.email === data.email);
      if (existingSubscriber) {
        if (existingSubscriber.isActive) {
          return of({ success: false, error: 'Email is already subscribed to our newsletter' });
        } else {
          // Reactivate subscription
          existingSubscriber.isActive = true;
          existingSubscriber.subscriptionDate = new Date();
          if (data.preferences) {
            existingSubscriber.preferences = { ...existingSubscriber.preferences, ...data.preferences };
          }
        }
      } else {
        // Create new subscriber
        const newSubscriber: NewsletterSubscriber = {
          id: this.generateId(),
          email: data.email,
          firstName: data.firstName,
          lastName: data.lastName,
          subscriptionDate: new Date(),
          isActive: true,
          preferences: {
            productUpdates: data.preferences?.productUpdates ?? true,
            promotions: data.preferences?.promotions ?? true,
            newsletter: data.preferences?.newsletter ?? true,
            orderUpdates: data.preferences?.orderUpdates ?? true
          },
          tags: [data.source || 'website']
        };
        this.subscribers.push(newSubscriber);
      }

      // Send confirmation email
      this.emailService.sendNewsletterConfirmationEmail(
        data.email,
        data.firstName || 'Subscriber'
      ).subscribe({
        next: () => console.log('Newsletter confirmation email sent'),
        error: (error) => console.error('Failed to send confirmation email:', error)
      });

      // Track subscription event
      this.analyticsService.trackEvent({
        event: 'newsletter_signup',
        event_category: 'engagement',
        event_label: data.source || 'website',
        custom_parameters: {
          email: data.email,
          source: data.source
        }
      });

      // Save to localStorage (in production, save to backend)
      this.saveSubscribers();

      return of({ success: true });
    } catch (error) {
      console.error('Newsletter subscription error:', error);
      return of({ success: false, error: 'Failed to subscribe to newsletter' });
    }
  }

  // Unsubscribe from newsletter
  unsubscribe(email: string, token?: string): Observable<{ success: boolean; error?: string }> {
    try {
      const subscriber = this.subscribers.find(sub => sub.email === email);
      if (!subscriber) {
        return of({ success: false, error: 'Email not found in our newsletter list' });
      }

      // In production, verify unsubscribe token
      subscriber.isActive = false;

      // Track unsubscribe event
      this.analyticsService.trackEvent({
        event: 'newsletter_unsubscribe',
        event_category: 'engagement',
        event_label: 'unsubscribe',
        custom_parameters: {
          email: email
        }
      });

      this.saveSubscribers();
      return of({ success: true });
    } catch (error) {
      console.error('Newsletter unsubscribe error:', error);
      return of({ success: false, error: 'Failed to unsubscribe from newsletter' });
    }
  }

  // Update subscription preferences
  updatePreferences(email: string, preferences: Partial<NewsletterSubscriber['preferences']>): Observable<{ success: boolean; error?: string }> {
    try {
      const subscriber = this.subscribers.find(sub => sub.email === email && sub.isActive);
      if (!subscriber) {
        return of({ success: false, error: 'Subscriber not found' });
      }

      subscriber.preferences = { ...subscriber.preferences, ...preferences };
      this.saveSubscribers();

      return of({ success: true });
    } catch (error) {
      console.error('Update preferences error:', error);
      return of({ success: false, error: 'Failed to update preferences' });
    }
  }

  // Get subscriber by email
  getSubscriber(email: string): NewsletterSubscriber | null {
    return this.subscribers.find(sub => sub.email === email && sub.isActive) || null;
  }

  // Get all active subscribers
  getActiveSubscribers(): NewsletterSubscriber[] {
    return this.subscribers.filter(sub => sub.isActive);
  }

  // Create newsletter campaign
  createCampaign(campaignData: Omit<NewsletterCampaign, 'id' | 'stats' | 'status'>): string {
    const campaign: NewsletterCampaign = {
      ...campaignData,
      id: this.generateId(),
      status: 'draft',
      stats: {
        sent: 0,
        opened: 0,
        clicked: 0,
        unsubscribed: 0
      }
    };

    this.campaigns.push(campaign);
    this.saveCampaigns();
    return campaign.id;
  }

  // Send campaign
  sendCampaign(campaignId: string): Observable<{ success: boolean; error?: string }> {
    try {
      const campaign = this.campaigns.find(c => c.id === campaignId);
      if (!campaign) {
        return of({ success: false, error: 'Campaign not found' });
      }

      if (campaign.status !== 'draft' && campaign.status !== 'scheduled') {
        return of({ success: false, error: 'Campaign cannot be sent in current status' });
      }

      // Get recipients
      const recipients = campaign.recipients.length > 0 
        ? this.subscribers.filter(sub => campaign.recipients.includes(sub.email) && sub.isActive)
        : this.getActiveSubscribers();

      if (recipients.length === 0) {
        return of({ success: false, error: 'No active subscribers found' });
      }

      // Send emails to all recipients
      recipients.forEach(subscriber => {
        const personalizedContent = this.personalizeContent(campaign.htmlContent, subscriber);
        const personalizedSubject = this.personalizeContent(campaign.subject, subscriber);

        this.emailService.sendEmail({
          to: subscriber.email,
          subject: personalizedSubject,
          htmlContent: personalizedContent,
          textContent: campaign.textContent
        }).subscribe({
          next: () => {
            campaign.stats.sent++;
            console.log(`Campaign email sent to ${subscriber.email}`);
          },
          error: (error) => console.error(`Failed to send campaign email to ${subscriber.email}:`, error)
        });
      });

      // Update campaign status
      campaign.status = 'sent';
      campaign.sentDate = new Date();
      this.saveCampaigns();

      // Track campaign sent event
      this.analyticsService.trackEvent({
        event: 'newsletter_campaign_sent',
        event_category: 'marketing',
        event_label: campaign.name,
        custom_parameters: {
          campaign_id: campaignId,
          recipients_count: recipients.length
        }
      });

      return of({ success: true });
    } catch (error) {
      console.error('Send campaign error:', error);
      return of({ success: false, error: 'Failed to send campaign' });
    }
  }

  // Get campaign by ID
  getCampaign(campaignId: string): NewsletterCampaign | null {
    return this.campaigns.find(c => c.id === campaignId) || null;
  }

  // Get all campaigns
  getCampaigns(): NewsletterCampaign[] {
    return this.campaigns;
  }

  // Send promotional email to subscribers
  sendPromotionalEmail(subject: string, content: string, targetTags?: string[]): Observable<{ success: boolean; sent: number }> {
    try {
      let recipients = this.getActiveSubscribers().filter(sub => sub.preferences.promotions);
      
      if (targetTags && targetTags.length > 0) {
        recipients = recipients.filter(sub => 
          sub.tags.some(tag => targetTags.includes(tag))
        );
      }

      let sentCount = 0;
      recipients.forEach(subscriber => {
        const personalizedContent = this.personalizeContent(content, subscriber);
        const personalizedSubject = this.personalizeContent(subject, subscriber);

        this.emailService.sendEmail({
          to: subscriber.email,
          subject: personalizedSubject,
          htmlContent: personalizedContent
        }).subscribe({
          next: () => {
            sentCount++;
            console.log(`Promotional email sent to ${subscriber.email}`);
          },
          error: (error) => console.error(`Failed to send promotional email to ${subscriber.email}:`, error)
        });
      });

      // Track promotional email event
      this.analyticsService.trackEvent({
        event: 'promotional_email_sent',
        event_category: 'marketing',
        event_label: 'promotion',
        custom_parameters: {
          recipients_count: recipients.length,
          target_tags: targetTags
        }
      });

      return of({ success: true, sent: recipients.length });
    } catch (error) {
      console.error('Send promotional email error:', error);
      return of({ success: false, sent: 0 });
    }
  }

  // Send product update email
  sendProductUpdateEmail(productData: any, targetTags?: string[]): Observable<{ success: boolean; sent: number }> {
    try {
      let recipients = this.getActiveSubscribers().filter(sub => sub.preferences.productUpdates);
      
      if (targetTags && targetTags.length > 0) {
        recipients = recipients.filter(sub => 
          sub.tags.some(tag => targetTags.includes(tag))
        );
      }

      const subject = `New Product Alert: ${productData.name}`;
      const content = `
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
          <h1 style="color: #333;">New Product Available!</h1>
          <p>Hi {{firstName}},</p>
          <p>We're excited to introduce our latest product:</p>
          
          <div style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 20px 0;">
            <img src="${productData.image}" alt="${productData.name}" style="width: 100%; max-width: 300px; height: auto;">
            <h2 style="color: #333; margin: 15px 0 10px 0;">${productData.name}</h2>
            <p style="color: #666; margin-bottom: 15px;">${productData.description}</p>
            <p style="font-size: 24px; color: #e74c3c; font-weight: bold; margin: 15px 0;">$${productData.price}</p>
            <a href="${window.location.origin}/products/${productData.id}" style="background-color: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;">Shop Now</a>
          </div>
          
          <p>Don't miss out on this amazing addition to our collection!</p>
          <p>Best regards,<br>The Your E-commerce Store Team</p>
        </div>
      `;

      let sentCount = 0;
      recipients.forEach(subscriber => {
        const personalizedContent = this.personalizeContent(content, subscriber);
        const personalizedSubject = this.personalizeContent(subject, subscriber);

        this.emailService.sendEmail({
          to: subscriber.email,
          subject: personalizedSubject,
          htmlContent: personalizedContent
        }).subscribe({
          next: () => {
            sentCount++;
            console.log(`Product update email sent to ${subscriber.email}`);
          },
          error: (error) => console.error(`Failed to send product update email to ${subscriber.email}:`, error)
        });
      });

      // Track product update email event
      this.analyticsService.trackEvent({
        event: 'product_update_email_sent',
        event_category: 'marketing',
        event_label: 'product_update',
        custom_parameters: {
          product_id: productData.id,
          recipients_count: recipients.length
        }
      });

      return of({ success: true, sent: recipients.length });
    } catch (error) {
      console.error('Send product update email error:', error);
      return of({ success: false, sent: 0 });
    }
  }

  // Initialize service
  init(): void {
    this.loadSubscribers();
    this.loadCampaigns();
  }

  // Private helper methods
  private isValidEmail(email: string): boolean {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }

  private generateId(): string {
    return `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
  }

  private personalizeContent(content: string, subscriber: NewsletterSubscriber): string {
    return content
      .replace(/{{firstName}}/g, subscriber.firstName || 'Valued Customer')
      .replace(/{{lastName}}/g, subscriber.lastName || '')
      .replace(/{{email}}/g, subscriber.email)
      .replace(/{{unsubscribeUrl}}/g, `${window.location.origin}/unsubscribe?email=${encodeURIComponent(subscriber.email)}`);
  }

  private saveSubscribers(): void {
    try {
      localStorage.setItem('newsletter_subscribers', JSON.stringify(this.subscribers));
    } catch (error) {
      console.error('Failed to save subscribers:', error);
    }
  }

  private loadSubscribers(): void {
    try {
      const stored = localStorage.getItem('newsletter_subscribers');
      if (stored) {
        this.subscribers = JSON.parse(stored);
      }
    } catch (error) {
      console.error('Failed to load subscribers:', error);
      this.subscribers = [];
    }
  }

  private saveCampaigns(): void {
    try {
      localStorage.setItem('newsletter_campaigns', JSON.stringify(this.campaigns));
    } catch (error) {
      console.error('Failed to save campaigns:', error);
    }
  }

  private loadCampaigns(): void {
    try {
      const stored = localStorage.getItem('newsletter_campaigns');
      if (stored) {
        this.campaigns = JSON.parse(stored);
      }
    } catch (error) {
      console.error('Failed to load campaigns:', error);
      this.campaigns = [];
    }
  }
}