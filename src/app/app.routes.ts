import { Routes } from '@angular/router';
import { authGuard, guestGuard, adminGuard } from './guards/auth.guard';

export const routes: Routes = [
  {
    path: '',
    loadComponent: () => import('./pages/home/home.component').then(m => m.HomeComponent),
    title: 'Home - E-commerce Store'
  },
  {
    path: 'products',
    loadComponent: () => import('./pages/products/product-list.component').then(m => m.ProductListComponent),
    title: 'Products - E-commerce Store'
  },
  {
    path: 'products/:id',
    loadComponent: () => import('./pages/products/product-detail.component').then(m => m.ProductDetailComponent),
    title: 'Product Details - E-commerce Store'
  },
  {
    path: 'search',
    loadComponent: () => import('./pages/search/search.component').then(m => m.SearchComponent),
    title: 'Search Results - E-commerce Store'
  },
  {
    path: 'cart',
    loadComponent: () => import('./pages/cart/cart.component').then(m => m.CartComponent),
    title: 'Shopping Cart - E-commerce Store'
  },
  // Authentication routes
  {
    path: 'auth',
    canActivate: [guestGuard],
    children: [
      {
        path: 'login',
        loadComponent: () => import('./pages/auth/login/login.component').then(m => m.LoginComponent),
        title: 'Login - E-commerce Store'
      },
      {
        path: 'register',
        loadComponent: () => import('./pages/auth/register/register.component').then(m => m.RegisterComponent),
        title: 'Create Account - E-commerce Store'
      },
      {
        path: '',
        redirectTo: 'login',
        pathMatch: 'full'
      }
    ]
  },
  // User Account Routes
  {
    path: 'account',
    canActivate: [authGuard],
    children: [
      {
        path: '',
        redirectTo: 'dashboard',
        pathMatch: 'full'
      },
      {
        path: 'dashboard',
        loadComponent: () => import('./pages/account/dashboard/dashboard.component').then(m => m.DashboardComponent),
        title: 'My Account - E-commerce Store'
      },
      {
         path: 'profile',
         loadComponent: () => import('./pages/account/profile/profile.component').then(m => m.ProfileComponent),
         title: 'Profile Settings - E-commerce Store'
       }
       // Orders and Wishlist components to be implemented
      // {
      //   path: 'orders',
      //   loadComponent: () => import('./pages/account/orders/orders.component').then(m => m.OrdersComponent),
      //   title: 'Order History - E-commerce Store'
      // },
      // {
      //   path: 'wishlist',
      //   loadComponent: () => import('./pages/account/wishlist/wishlist.component').then(m => m.WishlistComponent),
      //   title: 'My Wishlist - E-commerce Store'
      // }
    ]
  },
  // Admin routes (to be implemented)
  // {
  //   path: 'admin',
  //   canActivate: [adminGuard],
  //   children: [
  //     {
  //       path: 'dashboard',
  //       loadComponent: () => import('./pages/admin/dashboard/admin-dashboard.component').then(m => m.AdminDashboardComponent),
  //       title: 'Admin Dashboard - E-commerce Store'
  //     },
  //     {
  //       path: 'products',
  //       loadComponent: () => import('./pages/admin/products/admin-products.component').then(m => m.AdminProductsComponent),
  //       title: 'Manage Products - Admin'
  //     },
  //     {
  //       path: 'orders',
  //       loadComponent: () => import('./pages/admin/orders/admin-orders.component').then(m => m.AdminOrdersComponent),
  //       title: 'Manage Orders - Admin'
  //     },
  //     {
  //       path: 'users',
  //       loadComponent: () => import('./pages/admin/users/admin-users.component').then(m => m.AdminUsersComponent),
  //       title: 'Manage Users - Admin'
  //     },
  //     {
  //       path: '',
  //       redirectTo: 'dashboard',
  //       pathMatch: 'full'
  //     }
  //   ]
  // },
  // Checkout flow (protected)
  {
    path: 'checkout',
    canActivate: [authGuard],
    children: [
      // {
      //   path: 'shipping',
      //   loadComponent: () => import('./pages/checkout/shipping/shipping.component').then(m => m.ShippingComponent),
      //   title: 'Shipping Information - Checkout'
      // },
      // {
      //   path: 'payment',
      //   loadComponent: () => import('./pages/checkout/payment/payment.component').then(m => m.PaymentComponent),
      //   title: 'Payment - Checkout'
      // },
      // {
      //   path: 'confirmation',
      //   loadComponent: () => import('./pages/checkout/confirmation/confirmation.component').then(m => m.ConfirmationComponent),
      //   title: 'Order Confirmation - Checkout'
      // },
      {
        path: '',
        redirectTo: 'shipping',
        pathMatch: 'full'
      }
    ]
  },
  // Static pages (to be implemented)
  // {
  //   path: 'terms',
  //   loadComponent: () => import('./pages/legal/terms/terms.component').then(m => m.TermsComponent),
  //   title: 'Terms and Conditions - E-commerce Store'
  // },
  // {
  //   path: 'privacy',
  //   loadComponent: () => import('./pages/legal/privacy/privacy.component').then(m => m.PrivacyComponent),
  //   title: 'Privacy Policy - E-commerce Store'
  // },
  // Fallback route
  {
    path: '**',
    redirectTo: '',
    pathMatch: 'full'
  }
];