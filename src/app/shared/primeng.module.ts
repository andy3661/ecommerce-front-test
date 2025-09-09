import { NgModule } from '@angular/core';

// PrimeNG Modules
import { ButtonModule } from 'primeng/button';
import { CardModule } from 'primeng/card';
import { InputTextModule } from 'primeng/inputtext';
import { PasswordModule } from 'primeng/password';
import { CheckboxModule } from 'primeng/checkbox';
import { RadioButtonModule } from 'primeng/radiobutton';
import { DropdownModule } from 'primeng/dropdown';
import { MultiSelectModule } from 'primeng/multiselect';
import { CalendarModule } from 'primeng/calendar';
import { SliderModule } from 'primeng/slider';
import { DialogModule } from 'primeng/dialog';
import { ConfirmDialogModule } from 'primeng/confirmdialog';
import { ToastModule } from 'primeng/toast';
import { ToolbarModule } from 'primeng/toolbar';
import { TableModule } from 'primeng/table';
import { PaginatorModule } from 'primeng/paginator';
import { ProgressSpinnerModule } from 'primeng/progressspinner';
import { MenubarModule } from 'primeng/menubar';
import { MenuModule } from 'primeng/menu';
import { BreadcrumbModule } from 'primeng/breadcrumb';
import { TabViewModule } from 'primeng/tabview';
import { AccordionModule } from 'primeng/accordion';
import { CarouselModule } from 'primeng/carousel';
import { GalleriaModule } from 'primeng/galleria';
import { ImageModule } from 'primeng/image';
import { RatingModule } from 'primeng/rating';
import { TagModule } from 'primeng/tag';
import { ChipModule } from 'primeng/chip';
import { BadgeModule } from 'primeng/badge';
import { AvatarModule } from 'primeng/avatar';
import { DividerModule } from 'primeng/divider';
import { SkeletonModule } from 'primeng/skeleton';
import { DataViewModule } from 'primeng/dataview';
import { OverlayPanelModule } from 'primeng/overlaypanel';
import { SidebarModule } from 'primeng/sidebar';
import { StepsModule } from 'primeng/steps';
import { InputNumberModule } from 'primeng/inputnumber';
import { InputTextareaModule } from 'primeng/inputtextarea';
import { AutoCompleteModule } from 'primeng/autocomplete';
import { ChipsModule } from 'primeng/chips';
import { ColorPickerModule } from 'primeng/colorpicker';
// import { EditorModule } from 'primeng/editor'; // Requires quill dependency
import { KeyFilterModule } from 'primeng/keyfilter';
import { ListboxModule } from 'primeng/listbox';
import { SelectButtonModule } from 'primeng/selectbutton';
import { ToggleButtonModule } from 'primeng/togglebutton';
import { TreeSelectModule } from 'primeng/treeselect';
import { TriStateCheckboxModule } from 'primeng/tristatecheckbox';

const PRIMENG_MODULES = [
  ButtonModule,
  CardModule,
  InputTextModule,
  PasswordModule,
  CheckboxModule,
  RadioButtonModule,
  DropdownModule,
  MultiSelectModule,
  CalendarModule,
  SliderModule,
  DialogModule,
  ConfirmDialogModule,
  ToastModule,
  ToolbarModule,
  TableModule,
  PaginatorModule,
  ProgressSpinnerModule,
  MenubarModule,
  MenuModule,
  BreadcrumbModule,
  TabViewModule,
  AccordionModule,
  CarouselModule,
  GalleriaModule,
  ImageModule,
  RatingModule,
  TagModule,
  ChipModule,
  BadgeModule,
  AvatarModule,
  DividerModule,
  SkeletonModule,
  DataViewModule,
  OverlayPanelModule,
  SidebarModule,
  StepsModule,
  InputNumberModule,
  InputTextareaModule,
  AutoCompleteModule,
  ChipsModule,
  ColorPickerModule,
  // EditorModule, // Requires quill dependency
  KeyFilterModule,
  ListboxModule,
  SelectButtonModule,
  ToggleButtonModule,
  TreeSelectModule,
  TriStateCheckboxModule
];

@NgModule({
  imports: PRIMENG_MODULES,
  exports: PRIMENG_MODULES
})
export class PrimeNGModule { }