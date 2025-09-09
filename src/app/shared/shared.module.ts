import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

// Custom modules
import { PrimeNGModule } from './primeng.module';
import { CdkModule } from './cdk.module';

// Services
import { MessageService } from 'primeng/api';
import { ConfirmationService } from 'primeng/api';

const ANGULAR_MODULES = [
  CommonModule,
  FormsModule,
  ReactiveFormsModule,
  RouterModule
];

const UI_MODULES = [
  PrimeNGModule,
  CdkModule
];

@NgModule({
  imports: [
    ...ANGULAR_MODULES,
    ...UI_MODULES
  ],
  exports: [
    ...ANGULAR_MODULES,
    ...UI_MODULES
  ],
  providers: [
    MessageService,
    ConfirmationService
  ]
})
export class SharedModule { }