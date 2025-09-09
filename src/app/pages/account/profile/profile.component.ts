import { Component, OnInit, inject, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators, FormsModule } from '@angular/forms';
import { SecurityService } from '../../../services/security.service';
import { CardModule } from 'primeng/card';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { PasswordModule } from 'primeng/password';
import { CalendarModule } from 'primeng/calendar';
import { DropdownModule } from 'primeng/dropdown';
import { InputSwitchModule } from 'primeng/inputswitch';
import { DividerModule } from 'primeng/divider';
import { ToastModule } from 'primeng/toast';
import { MessageService } from 'primeng/api';
import { AvatarModule } from 'primeng/avatar';
import { FileUploadModule } from 'primeng/fileupload';
import { TabViewModule } from 'primeng/tabview';
import { ConfirmDialogModule } from 'primeng/confirmdialog';
import { ConfirmationService } from 'primeng/api';

interface UserProfile {
  firstName: string;
  lastName: string;
  email: string;
  phone?: string;
  dateOfBirth?: Date;
  gender?: string;
  avatar?: string;
}

interface NotificationSettings {
  emailMarketing: boolean;
  emailOrders: boolean;
  emailSecurity: boolean;
  smsMarketing: boolean;
  smsOrders: boolean;
  pushNotifications: boolean;
}

interface Address {
  id: string;
  type: 'billing' | 'shipping';
  firstName: string;
  lastName: string;
  company?: string;
  address1: string;
  address2?: string;
  city: string;
  state: string;
  zipCode: string;
  country: string;
  isDefault: boolean;
}

@Component({
  selector: 'app-profile',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    FormsModule,
    CardModule,
    ButtonModule,
    InputTextModule,
    PasswordModule,
    CalendarModule,
    DropdownModule,
    InputSwitchModule,
    DividerModule,
    ToastModule,
    AvatarModule,
    FileUploadModule,
    TabViewModule,
    ConfirmDialogModule
  ],
  providers: [MessageService, ConfirmationService],
  templateUrl: './profile.component.html',
  styleUrls: ['./profile.component.scss']
})
export class ProfileComponent implements OnInit {
  private fb = inject(FormBuilder);
  private securityService = inject(SecurityService);
  private messageService = inject(MessageService);
  private confirmationService = inject(ConfirmationService);

  user = computed(() => this.securityService.getCurrentUser());
  isLoading = signal(false);
  isPasswordLoading = signal(false);
  
  profileForm!: FormGroup;
  passwordForm!: FormGroup;
  notificationSettings = signal<NotificationSettings>({
    emailOrders: true,
    emailMarketing: false,
    emailSecurity: true,
    smsOrders: false,
    smsMarketing: false,
    pushNotifications: true
  });
  
  maxDate = new Date();
  
  addresses = signal<Address[]>([]);
  
  genderOptions = [
    { label: 'Prefer not to say', value: '' },
    { label: 'Male', value: 'male' },
    { label: 'Female', value: 'female' },
    { label: 'Other', value: 'other' }
  ];

  ngOnInit() {
    this.initializeForms();
    this.loadUserProfile();
    this.loadAddresses();
  }

  private initializeForms() {
    this.profileForm = this.fb.group({
      firstName: ['', [Validators.required, Validators.minLength(2)]],
      lastName: ['', [Validators.required, Validators.minLength(2)]],
      email: ['', [Validators.required, Validators.email]],
      phone: [''],
      dateOfBirth: [''],
      gender: ['']
    });

    this.passwordForm = this.fb.group({
      currentPassword: ['', [Validators.required]],
      newPassword: ['', [Validators.required, Validators.minLength(8)]],
      confirmPassword: ['', [Validators.required]]
    }, {
      validators: this.passwordMatchValidator
    });
  }

  private passwordMatchValidator(form: FormGroup) {
    const newPassword = form.get('newPassword')?.value;
    const confirmPassword = form.get('confirmPassword')?.value;
    
    if (newPassword && confirmPassword && newPassword !== confirmPassword) {
      form.get('confirmPassword')?.setErrors({ passwordMismatch: true });
      return { passwordMismatch: true };
    }
    
    return null;
  }

  private loadUserProfile() {
    const currentUser = this.user();
    if (currentUser) {
      this.profileForm.patchValue({
        firstName: currentUser.firstName || '',
        lastName: currentUser.lastName || '',
        email: currentUser.email || '',
        phone: '', // Will be loaded from user profile API
        dateOfBirth: null, // Will be loaded from user profile API
        gender: '' // Will be loaded from user profile API
      });
    }
  }

  private async loadAddresses() {
    try {
      // Mock addresses data
      await this.delay(500);
      this.addresses.set([
        {
          id: '1',
          type: 'billing',
          firstName: 'John',
          lastName: 'Doe',
          address1: '123 Main St',
          city: 'New York',
          state: 'NY',
          zipCode: '10001',
          country: 'US',
          isDefault: true
        },
        {
          id: '2',
          type: 'shipping',
          firstName: 'John',
          lastName: 'Doe',
          address1: '456 Oak Ave',
          city: 'Brooklyn',
          state: 'NY',
          zipCode: '11201',
          country: 'US',
          isDefault: false
        }
      ]);
    } catch (error) {
      console.error('Error loading addresses:', error);
    }
  }

  async onSubmitProfile() {
    if (this.profileForm.valid) {
      this.isLoading.set(true);
      try {
        // Simulate API call
        await this.delay(1000);
        
        const formData = this.profileForm.value;
        console.log('Updating profile:', formData);
        
        this.messageService.add({
          severity: 'success',
          summary: 'Success',
          detail: 'Profile updated successfully'
        });
      } catch (error) {
        this.messageService.add({
          severity: 'error',
          summary: 'Error',
          detail: 'Failed to update profile'
        });
      } finally {
        this.isLoading.set(false);
      }
    } else {
      this.markFormGroupTouched(this.profileForm);
    }
  }

  async onSubmitPassword() {
    if (this.passwordForm.valid) {
      this.isPasswordLoading.set(true);
      try {
        // Simulate API call
        await this.delay(1000);
        
        const formData = this.passwordForm.value;
        console.log('Updating password');
        
        this.passwordForm.reset();
        this.messageService.add({
          severity: 'success',
          summary: 'Success',
          detail: 'Password updated successfully'
        });
      } catch (error) {
        this.messageService.add({
          severity: 'error',
          summary: 'Error',
          detail: 'Failed to update password'
        });
      } finally {
        this.isPasswordLoading.set(false);
      }
    } else {
      this.markFormGroupTouched(this.passwordForm);
    }
  }

  async onNotificationChange(setting: keyof NotificationSettings, value: boolean) {
    const current = this.notificationSettings();
    this.notificationSettings.set({ ...current, [setting]: value });
    
    try {
      // Simulate API call
      await this.delay(300);
      console.log('Notification settings updated:', this.notificationSettings());
    } catch (error) {
      // Revert on error
      this.notificationSettings.set({ ...current, [setting]: !value });
      this.messageService.add({
        severity: 'error',
        summary: 'Error',
        detail: 'Failed to update notification settings'
      });
    }
  }

  onAvatarUpload(event: any) {
    const file = event.files[0];
    if (file) {
      // Handle avatar upload
      console.log('Uploading avatar:', file);
      this.messageService.add({
        severity: 'success',
        summary: 'Success',
        detail: 'Avatar uploaded successfully'
      });
    }
  }

  deleteAccount() {
    this.confirmationService.confirm({
      message: 'Are you sure you want to delete your account? This action cannot be undone.',
      header: 'Delete Account',
      icon: 'pi pi-exclamation-triangle',
      acceptButtonStyleClass: 'p-button-danger',
      accept: () => {
        this.messageService.add({
          severity: 'info',
          summary: 'Account Deletion',
          detail: 'Account deletion request submitted'
        });
      }
    });
  }

  private markFormGroupTouched(formGroup: FormGroup) {
    Object.keys(formGroup.controls).forEach(key => {
      const control = formGroup.get(key);
      control?.markAsTouched();
      if (control instanceof FormGroup) {
        this.markFormGroupTouched(control);
      }
    });
  }

  private delay(ms: number): Promise<void> {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  isFieldInvalid(form: FormGroup, fieldName: string): boolean {
    const field = form.get(fieldName);
    return !!(field && field.invalid && (field.dirty || field.touched));
  }

  getFieldError(form: FormGroup, fieldName: string): string {
    const field = form.get(fieldName);
    if (field && field.errors && (field.dirty || field.touched)) {
      if (field.errors['required']) {
        return `${this.getFieldLabel(fieldName)} is required`;
      }
      if (field.errors['email']) {
        return 'Please enter a valid email address';
      }
      if (field.errors['minlength']) {
        return `${this.getFieldLabel(fieldName)} must be at least ${field.errors['minlength'].requiredLength} characters`;
      }
      if (field.errors['passwordMismatch']) {
        return 'Passwords do not match';
      }
    }
    return '';
  }

  private getFieldLabel(fieldName: string): string {
    const labels: { [key: string]: string } = {
      firstName: 'First name',
      lastName: 'Last name',
      email: 'Email',
      phone: 'Phone',
      currentPassword: 'Current password',
      newPassword: 'New password',
      confirmPassword: 'Confirm password'
    };
    return labels[fieldName] || fieldName;
  }

  getUserInitials(): string {
    const user = this.user();
    if (user?.firstName && user?.lastName) {
      return `${user.firstName.charAt(0)}${user.lastName.charAt(0)}`.toUpperCase();
    }
    return user?.email?.charAt(0).toUpperCase() || 'U';
  }
}