import { NgModule } from '@angular/core';

// Angular CDK Modules
import { OverlayModule } from '@angular/cdk/overlay';
import { PortalModule } from '@angular/cdk/portal';
import { ScrollingModule } from '@angular/cdk/scrolling';
import { DragDropModule } from '@angular/cdk/drag-drop';
import { ClipboardModule } from '@angular/cdk/clipboard';
import { A11yModule } from '@angular/cdk/a11y';
import { BidiModule } from '@angular/cdk/bidi';
import { ObserversModule } from '@angular/cdk/observers';
import { PlatformModule } from '@angular/cdk/platform';
import { TextFieldModule } from '@angular/cdk/text-field';
import { CdkTableModule } from '@angular/cdk/table';
import { CdkTreeModule } from '@angular/cdk/tree';
import { CdkAccordionModule } from '@angular/cdk/accordion';
import { CdkStepperModule } from '@angular/cdk/stepper';
import { LayoutModule } from '@angular/cdk/layout';

const CDK_MODULES = [
  OverlayModule,
  PortalModule,
  ScrollingModule,
  DragDropModule,
  ClipboardModule,
  A11yModule,
  BidiModule,
  ObserversModule,
  PlatformModule,
  TextFieldModule,
  CdkTableModule,
  CdkTreeModule,
  CdkAccordionModule,
  CdkStepperModule,
  LayoutModule
];

@NgModule({
  imports: CDK_MODULES,
  exports: CDK_MODULES
})
export class CdkModule { }