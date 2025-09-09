import { Component, OnInit, inject, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { SecurityService } from '../../../services/security.service';
import { CardModule } from 'primeng/card';
import { ButtonModule } from 'primeng/button';
import { AvatarModule } from 'primeng/avatar';
import { BadgeModule } from 'primeng/badge';
import { DividerModule } from 'primeng/divider';
import { ProgressBarModule } from 'primeng/progressbar';
import { ChipModule } from 'primeng/chip';
import { SkeletonModule } from 'primeng/skeleton';

interface UserStats {
  totalOrders: number;
  pendingOrders: number;
  completedOrders: number;
  totalSpent: number;
  loyaltyPoints: number;
  wishlistItems: number;
}

interface RecentOrder {
  id: string;
  date: Date;
  status: 'pending' | 'processing' | 'shipped' | 'delivered' | 'cancelled';
  total: number;
  items: number;
}

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [
    CommonModule,
    RouterModule,
    CardModule,
    ButtonModule,
    AvatarModule,
    BadgeModule,
    DividerModule,
    ProgressBarModule,
    ChipModule,
    SkeletonModule
  ],
  templateUrl: './dashboard.component.html',
  styleUrls: ['./dashboard.component.scss']
})
export class DashboardComponent implements OnInit {
  private securityService = inject(SecurityService);
  Math = Math;

  user = computed(() => this.securityService.getCurrentUser());
  isLoading = signal(true);
  userStats = signal<UserStats>({
    totalOrders: 0,
    pendingOrders: 0,
    completedOrders: 0,
    totalSpent: 0,
    loyaltyPoints: 0,
    wishlistItems: 0
  });
  recentOrders = signal<RecentOrder[]>([]);

  ngOnInit() {
    this.loadDashboardData();
  }

  private async loadDashboardData() {
    try {
      // Simulate API calls with mock data
      await this.delay(1000);
      
      // Mock user statistics
      this.userStats.set({
        totalOrders: 12,
        pendingOrders: 2,
        completedOrders: 10,
        totalSpent: 1250.99,
        loyaltyPoints: 850,
        wishlistItems: 5
      });

      // Mock recent orders
      this.recentOrders.set([
        {
          id: 'ORD-2024-001',
          date: new Date('2024-01-15'),
          status: 'delivered',
          total: 89.99,
          items: 3
        },
        {
          id: 'ORD-2024-002',
          date: new Date('2024-01-20'),
          status: 'shipped',
          total: 156.50,
          items: 2
        },
        {
          id: 'ORD-2024-003',
          date: new Date('2024-01-25'),
          status: 'processing',
          total: 245.75,
          items: 4
        }
      ]);
    } catch (error) {
      console.error('Error loading dashboard data:', error);
    } finally {
      this.isLoading.set(false);
    }
  }

  private delay(ms: number): Promise<void> {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  getStatusClass(status: string): string {
    switch (status.toLowerCase()) {
      case 'pending':
        return 'bg-yellow-100 text-yellow-800';
      case 'processing':
        return 'bg-blue-100 text-blue-800';
      case 'shipped':
        return 'bg-green-100 text-green-800';
      case 'delivered':
        return 'bg-green-100 text-green-800';
      case 'cancelled':
        return 'bg-red-100 text-red-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  }

  getStatusIcon(status: string): string {
    switch (status) {
      case 'delivered': return 'pi pi-check-circle';
      case 'shipped': return 'pi pi-truck';
      case 'processing': return 'pi pi-clock';
      case 'pending': return 'pi pi-hourglass';
      case 'cancelled': return 'pi pi-times-circle';
      default: return 'pi pi-info-circle';
    }
  }

  getLoyaltyLevel(): { level: string; progress: number; nextLevel: string } {
    const points = this.userStats().loyaltyPoints;
    
    if (points < 500) {
      return { level: 'Bronze', progress: (points / 500) * 100, nextLevel: 'Silver' };
    } else if (points < 1000) {
      return { level: 'Silver', progress: ((points - 500) / 500) * 100, nextLevel: 'Gold' };
    } else if (points < 2000) {
      return { level: 'Gold', progress: ((points - 1000) / 1000) * 100, nextLevel: 'Platinum' };
    } else {
      return { level: 'Platinum', progress: 100, nextLevel: 'Max Level' };
    }
  }

  formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(amount);
  }

  formatDate(date: Date): string {
    return new Intl.DateTimeFormat('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    }).format(date);
  }

  getUserInitials(): string {
    const user = this.user();
    if (user?.firstName && user?.lastName) {
      return `${user.firstName.charAt(0)}${user.lastName.charAt(0)}`.toUpperCase();
    }
    return user?.email?.charAt(0).toUpperCase() || 'U';
  }

  getUserDisplayName(): string {
    const user = this.user();
    if (user?.firstName && user?.lastName) {
      return `${user.firstName} ${user.lastName}`;
    }
    return user?.email || 'User';
  }
}