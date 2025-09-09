import { Component, OnInit, inject, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Router } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { CartService, CartItem } from '../../services/cart.service';
import { SharedModule } from '../../shared/shared.module';
import { MessageService } from 'primeng/api';

@Component({
  selector: 'app-cart',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule, SharedModule],
  providers: [MessageService],
  templateUrl: './cart.component.html',
  styleUrls: ['./cart.component.css']
})
export class CartComponent implements OnInit {
  cartService = inject(CartService);
  private messageService = inject(MessageService);
  private router = inject(Router);
  
  freeShippingThreshold = 50;

  ngOnInit() {
    // Component initialization
  }

  trackByItemId(index: number, item: CartItem): string {
    return item.id;
  }

  removeItem(itemId: string): void {
    const item = this.cartService.getItem(itemId);
    if (item) {
      this.cartService.removeItem(itemId);
      this.messageService.add({
        severity: 'success',
        summary: 'Item Removed',
        detail: `${item.name} has been removed from your cart`
      });
    }
  }

  updateQuantity(itemId: string, newQuantity: number): void {
    const success = this.cartService.updateQuantity(itemId, newQuantity);
    
    if (!success && newQuantity > 0) {
      this.messageService.add({
        severity: 'warn',
        summary: 'Quantity Limit',
        detail: 'Cannot exceed available stock quantity'
      });
    }
  }

  onQuantityInput(itemId: string, event: any): void {
    const newQuantity = parseInt(event.target.value, 10);
    if (!isNaN(newQuantity) && newQuantity > 0) {
      this.updateQuantity(itemId, newQuantity);
    }
  }

  onImageError(event: any): void {
    event.target.style.display = 'none';
  }

  getTotalSavings(): number {
    return this.cartService.getTotalSavings();
  }

  getEstimatedDelivery(): Date {
    return this.cartService.getEstimatedDelivery();
  }

  isCartValid(): boolean {
    return this.cartService.validateCart().isValid;
  }

  proceedToCheckout(): void {
    const validation = this.cartService.validateCart();
    
    if (!validation.isValid) {
      validation.errors.forEach(error => {
        this.messageService.add({
          severity: 'error',
          summary: 'Cart Error',
          detail: error
        });
      });
      return;
    }

    // TODO: Navigate to checkout page
    this.messageService.add({
      severity: 'info',
      summary: 'Checkout',
      detail: 'Checkout functionality will be implemented next'
    });
  }
}