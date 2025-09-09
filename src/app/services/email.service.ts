import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { AnalyticsService } from './analytics.service';

export interface EmailTemplate {
  id: string;
  name: string;
  subject: string;
  htmlContent: string;
  textContent: string;
  variables: string[];
}

export interface EmailData {
  to: string;
  subject: string;
  templateId?: string;
  variables?: { [key: string]: any };
  htmlContent?: string;
  textContent?: string;
}

export interface TransactionalEmailConfig {
  apiKey: string;
  apiUrl: string;
  fromEmail: string;
  fromName: string;
}

@Injectable({
  providedIn: 'root'
})
export class EmailService {
  private http = inject(HttpClient);
  private analyticsService = inject(AnalyticsService);

  // Configuration - In production, these should come from environment variables
  private config: TransactionalEmailConfig = {
    apiKey: 'your-sendgrid-api-key',
    apiUrl: 'https://api.sendgrid.com/v3/mail/send',
    fromEmail: 'noreply@yourdomain.com',
    fromName: 'Your E-commerce Store'
  };

  // Send email using template
  sendTemplateEmail(templateId: string, to: string, variables: { [key: string]: any }): Observable<any> {
    const emailData: EmailData = {
      to,
      subject: `Email from ${this.config.fromName}`,
      templateId,
      variables
    };

    return this.sendEmail(emailData);
  }

  // Send custom email
  sendEmail(emailData: EmailData): Observable<any> {
    // Track email event
    this.analyticsService.trackEvent({
      event: 'email_sent',
      event_category: 'communication',
      event_label: emailData.templateId || 'custom',
      custom_parameters: {
        recipient: emailData.to,
        subject: emailData.subject
      }
    });

    // Simulate email sending
    return this.simulateEmailSend(emailData);
  }

  // Welcome email
  sendWelcomeEmail(userEmail: string, firstName: string): Observable<any> {
    const emailData: EmailData = {
      to: userEmail,
      subject: `Welcome to ${this.config.fromName}!`,
      htmlContent: `
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
          <h1 style="color: #333;">Welcome to ${this.config.fromName}!</h1>
          <p>Hi ${firstName},</p>
          <p>Thank you for joining our community! We're excited to have you on board.</p>
          <p>Here's what you can do next:</p>
          <ul>
            <li>Browse our latest products</li>
            <li>Set up your profile</li>
            <li>Subscribe to our newsletter for exclusive deals</li>
          </ul>
          <a href="${window.location.origin}" style="background-color: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 20px 0;">Start Shopping</a>
          <p>If you have any questions, feel free to contact our support team.</p>
          <p>Best regards,<br>The ${this.config.fromName} Team</p>
        </div>
      `,
      textContent: `Welcome to ${this.config.fromName}!\n\nHi ${firstName},\n\nThank you for joining our community!\n\nVisit our store: ${window.location.origin}\n\nBest regards,\nThe ${this.config.fromName} Team`
    };

    return this.sendEmail(emailData);
  }

  // Order confirmation email
  sendOrderConfirmationEmail(userEmail: string, orderData: any): Observable<any> {
    const emailData: EmailData = {
      to: userEmail,
      subject: `Order Confirmation #${orderData.orderNumber}`,
      htmlContent: `
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
          <h1 style="color: #333;">Order Confirmation</h1>
          <p>Hi ${orderData.firstName},</p>
          <p>Thank you for your order! We've received your order and are preparing it for shipment.</p>
          
          <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h3 style="margin-top: 0;">Order Details</h3>
            <p><strong>Order Number:</strong> ${orderData.orderNumber}</p>
            <p><strong>Order Date:</strong> ${new Date().toLocaleDateString()}</p>
            <p><strong>Total Amount:</strong> $${orderData.total.toFixed(2)}</p>
          </div>
          
          <h3>Items Ordered:</h3>
          <div style="border: 1px solid #dee2e6; border-radius: 4px;">
            ${orderData.items.map((item: any) => `
              <div style="padding: 15px; border-bottom: 1px solid #dee2e6;">
                <strong>${item.name}</strong><br>
                Quantity: ${item.quantity} | Price: ${item.price}
              </div>
            `).join('')}
          </div>
          
          <div style="background-color: #e7f3ff; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h3 style="margin-top: 0;">Shipping Information</h3>
            <p>${orderData.shippingAddress}</p>
            <p><strong>Estimated Delivery:</strong> ${orderData.estimatedDelivery}</p>
          </div>
          
          <p>Thank you for shopping with us!</p>
          <p>Best regards,<br>The ${this.config.fromName} Team</p>
        </div>
      `,
      textContent: `Order Confirmation #${orderData.orderNumber}\n\nHi ${orderData.firstName},\n\nThank you for your order!\n\nOrder Number: ${orderData.orderNumber}\nTotal Amount: $${orderData.total.toFixed(2)}\n\nBest regards,\nThe ${this.config.fromName} Team`
    };

    return this.sendEmail(emailData);
  }

  // Shipping notification email
  sendShippingNotificationEmail(userEmail: string, shippingData: any): Observable<any> {
    const emailData: EmailData = {
      to: userEmail,
      subject: `Your order #${shippingData.orderNumber} has shipped!`,
      htmlContent: `
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
          <h1 style="color: #333;">Your Order Has Shipped!</h1>
          <p>Hi ${shippingData.firstName},</p>
          <p>Great news! Your order #${shippingData.orderNumber} has been shipped and is on its way to you.</p>
          
          <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h3 style="margin-top: 0;">Shipping Details</h3>
            <p><strong>Tracking Number:</strong> ${shippingData.trackingNumber}</p>
            <p><strong>Carrier:</strong> ${shippingData.carrier}</p>
            <p><strong>Estimated Delivery:</strong> ${shippingData.estimatedDelivery}</p>
          </div>
          
          <a href="${shippingData.trackingUrl}" style="background-color: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 20px 0;">Track Your Package</a>
          
          <p>You'll receive another email when your package is delivered.</p>
          <p>Thank you for your business!</p>
          <p>Best regards,<br>The ${this.config.fromName} Team</p>
        </div>
      `,
      textContent: `Your order #${shippingData.orderNumber} has shipped!\n\nTracking Number: ${shippingData.trackingNumber}\nCarrier: ${shippingData.carrier}\n\nTrack your package: ${shippingData.trackingUrl}\n\nBest regards,\nThe ${this.config.fromName} Team`
    };

    return this.sendEmail(emailData);
  }

  // Password reset email
  sendPasswordResetEmail(userEmail: string, firstName: string, resetToken: string): Observable<any> {
    const emailData: EmailData = {
      to: userEmail,
      subject: 'Reset your password',
      htmlContent: `
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
          <h1 style="color: #333;">Reset Your Password</h1>
          <p>Hi ${firstName},</p>
          <p>We received a request to reset your password. Click the button below to create a new password:</p>
          
          <a href="${window.location.origin}/reset-password?token=${resetToken}" style="background-color: #dc3545; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 20px 0;">Reset Password</a>
          
          <p>This link will expire in 24 hours for security reasons.</p>
          <p>If you didn't request this password reset, please ignore this email.</p>
          <p>Best regards,<br>The ${this.config.fromName} Team</p>
        </div>
      `,
      textContent: `Reset Your Password\n\nHi ${firstName},\n\nReset your password: ${window.location.origin}/reset-password?token=${resetToken}\n\nThis link will expire in 24 hours.\n\nBest regards,\nThe ${this.config.fromName} Team`
    };

    return this.sendEmail(emailData);
  }

  // Abandoned cart email
  sendAbandonedCartEmail(userEmail: string, cartData: any): Observable<any> {
    const emailData: EmailData = {
      to: userEmail,
      subject: 'You left something in your cart',
      htmlContent: `
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
          <h1 style="color: #333;">Don't Forget Your Items!</h1>
          <p>Hi ${cartData.firstName},</p>
          <p>You left some great items in your cart. Complete your purchase before they're gone!</p>
          
          <div style="border: 1px solid #dee2e6; border-radius: 4px; margin: 20px 0;">
            ${cartData.items.map((item: any) => `
              <div style="padding: 15px; border-bottom: 1px solid #dee2e6;">
                <strong>${item.name}</strong><br>
                <span style="color: #666;">${item.price}</span>
              </div>
            `).join('')}
          </div>
          
          <div style="text-align: center; margin: 30px 0;">
            <p style="font-size: 18px; margin-bottom: 20px;"><strong>Total: $${cartData.total.toFixed(2)}</strong></p>
            <a href="${window.location.origin}/checkout" style="background-color: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 4px; display: inline-block; font-size: 16px;">Complete Your Purchase</a>
          </div>
          
          <p style="text-align: center; color: #666; font-size: 14px;">This offer expires in 24 hours</p>
          <p>Best regards,<br>The ${this.config.fromName} Team</p>
        </div>
      `,
      textContent: `Don't Forget Your Items!\n\nHi ${cartData.firstName},\n\nYou left items in your cart.\n\nTotal: $${cartData.total.toFixed(2)}\n\nComplete your purchase: ${window.location.origin}/checkout\n\nBest regards,\nThe ${this.config.fromName} Team`
    };

    return this.sendEmail(emailData);
  }

  // Newsletter subscription confirmation
  sendNewsletterConfirmationEmail(userEmail: string, firstName: string): Observable<any> {
    const emailData: EmailData = {
      to: userEmail,
      subject: 'Welcome to our newsletter!',
      htmlContent: `
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
          <h1 style="color: #333;">Welcome to Our Newsletter!</h1>
          <p>Hi ${firstName},</p>
          <p>Thank you for subscribing to our newsletter! You'll be the first to know about:</p>
          <ul>
            <li>New product launches</li>
            <li>Exclusive discounts and promotions</li>
            <li>Industry insights and tips</li>
          </ul>
          <p>We promise to only send you valuable content and never spam your inbox.</p>
          <p>Best regards,<br>The ${this.config.fromName} Team</p>
        </div>
      `,
      textContent: `Welcome to Our Newsletter!\n\nHi ${firstName},\n\nThank you for subscribing to our newsletter!\n\nBest regards,\nThe ${this.config.fromName} Team`
    };

    return this.sendEmail(emailData);
  }

  private simulateEmailSend(emailData: EmailData): Observable<any> {
    // Simulate API call delay
    console.log('📧 Email sent:', {
      to: emailData.to,
      subject: emailData.subject,
      timestamp: new Date().toISOString()
    });

    return of({ success: true, messageId: `msg_${Date.now()}` });
  }
}