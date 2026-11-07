<script setup lang="ts">
import { ref, type Ref } from 'vue';
import type { DateValue } from '@internationalized/date';
import { DateFormatter, getLocalTimeZone, today } from '@internationalized/date';
import { Head } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import { documentation } from '@/routes/superadmin';
import type { DateRange } from 'reka-ui';
import { RangeCalendar } from '@/components/ui/range-calendar';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import DataTable from '@/components/payments/data-table.vue';

import { Card, CardHeader, CardContent, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
    TooltipProvider,
} from '@/components/ui/tooltip';
import {
    Alert,
    AlertTitle,
    AlertDescription,
} from '@/components/ui/alert';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { InputOTP, InputOTPGroup, InputOTPSeparator, InputOTPSlot } from '@/components/ui/input-otp';
import {
    Info,
    AlertTriangle,
    CheckCircle2,
    Trash2,
    Plus,
    Download,
    MoreHorizontal,
    ChevronsUpDown,
    CheckIcon, ChevronsUpDownIcon, XIcon, CalendarIcon
} from '@lucide/vue';
import { cn } from '@/lib/utils';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxGroup,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxList,
    ComboboxTrigger,
} from '@/components/ui/combobox';

const roleOptions = [
    { value: 'superadmin', label: 'Superadmin' },
    { value: 'admin', label: 'Admin' },
    { value: 'user', label: 'User' },
    { value: 'moderator', label: 'Moderator' },
    { value: 'editor', label: 'Editor' },
];
const selectedRole = ref<(typeof roleOptions)[number]>();
const selectedRoles = ref<typeof roleOptions>([]);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                href: documentation(),
                title: 'Documentation',
            },
        ],
    },
});

// --- Live state for demo components ---
const textValue = ref('');
const textareaValue = ref('');
const checkboxValue = ref<boolean | 'indeterminate'>(false);
const selectValue = ref('');
const switchValue = ref(false); // <-- add this line
const otpValue = ref('');
const collapsibleOpen = ref(false);
const dialogOpen = ref(false);
const sheetOpen = ref(false);

function showToastSuccess() {
    toast.success('Changes saved successfully.');
}
function showToastError() {
    toast.error('Something went wrong.');
}
function showToastInfo() {
    toast('Heads up — this is an info toast.');
}


function removeRole(roleValue: string) {
    selectedRoles.value = selectedRoles.value.filter((r) => r.value !== roleValue);
}


const confirmDeleteOpen = ref(false);
const validationDemo = ref({ valid: 'user@example.com', invalid: 'not-an-email' });

function handleConfirmDelete() {
    confirmDeleteOpen.value = false;
    toast.success('Item deleted.');
}


const dateDemoPlaceholder = today(getLocalTimeZone());
const dateDemoValue = ref() as Ref<DateValue>;
const dateFormatter = new DateFormatter('en-US', { dateStyle: 'long' });


const rangeStart = today(getLocalTimeZone());
const rangeEnd = rangeStart.add({ days: 7 });
const dateRangeDemo = ref({ start: rangeStart, end: rangeEnd }) as Ref<DateRange>;
const userStatus = ref('active');
</script>

<template>

    <Head title="Documentation" />

    <div class="px-4 py-6">
        <div class="w-full max-w-4xl">

            <Heading title="Superadmin Documentation"
                description="Reusable UI components available across the admin panel" />

            <section class="mt-6 space-y-6">

                <!-- Buttons -->
                <Card>
                    <CardHeader>
                        <CardTitle>Buttons</CardTitle>
                        <CardDescription>Variants and sizes available for the Button component</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div>
                            <p class="mb-2 text-sm font-medium">Variants</p>
                            <div class="flex flex-wrap gap-3">
                                <Button variant="default">Default</Button>
                                <Button variant="secondary">Secondary</Button>
                                <Button variant="outline">Outline</Button>
                                <Button variant="ghost">Ghost</Button>
                                <Button variant="destructive">Destructive</Button>
                                <Button variant="link">Link</Button>
                            </div>
                        </div>

                        <div>
                            <p class="mb-2 text-sm font-medium">With Icons</p>
                            <div class="flex flex-wrap gap-3">
                                <Button>
                                    <Plus class="mr-2 h-4 w-4" />
                                    Add New
                                </Button>
                                <Button variant="destructive">
                                    <Trash2 class="mr-2 h-4 w-4" />
                                    Delete
                                </Button>
                                <Button variant="outline">
                                    <Download class="mr-2 h-4 w-4" />
                                    Export
                                </Button>
                            </div>
                        </div>

                        <div>
                            <p class="mb-2 text-sm font-medium">Sizes</p>
                            <div class="flex flex-wrap items-center gap-3">
                                <Button size="sm">Small</Button>
                                <Button size="default">Default</Button>
                                <Button size="lg">Large</Button>
                                <Button size="icon">
                                    <Plus class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>

                        <div>
                            <p class="mb-2 text-sm font-medium">States</p>
                            <div class="flex flex-wrap gap-3">
                                <Button disabled>Disabled</Button>
                                <Button disabled>
                                    <Spinner class="mr-2 h-4 w-4" />
                                    Loading
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Text Inputs -->
                <Card>
                    <CardHeader>
                        <CardTitle>Text Inputs</CardTitle>
                        <CardDescription>Input and Textarea fields with labels</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="demo-input">Text Input</Label>
                                <Input id="demo-input" v-model="textValue" placeholder="Enter some text..." />
                                <p class="text-xs text-muted-foreground">Value: {{ textValue || '(empty)' }}</p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="demo-input-disabled">Disabled Input</Label>
                                <Input id="demo-input-disabled" placeholder="Can't type here" disabled />
                            </div>

                            <div class="grid gap-2 md:col-span-2">
                                <Label for="demo-textarea">Textarea</Label>
                                <Textarea id="demo-textarea" v-model="textareaValue"
                                    placeholder="Write a longer description..." rows="4" />
                                <p class="text-xs text-muted-foreground">{{ textareaValue.length }} characters</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Checkbox & Select -->
                <Card>
                    <CardHeader>
                        <CardTitle>Checkbox & Select</CardTitle>
                        <CardDescription>Selection controls</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div class="grid gap-3">
                                <Label>Checkbox</Label>
                                <div class="flex items-center gap-2">
                                    <Checkbox id="demo-checkbox" v-model="checkboxValue" />
                                    <Label for="demo-checkbox" class="font-normal">
                                        I agree to the terms and conditions
                                    </Label>
                                </div>
                                <p class="text-xs text-muted-foreground">Checked: {{ checkboxValue }}</p>
                            </div>

                            <div class="grid gap-3">
                                <Label for="demo-select">Select</Label>
                                <Select v-model="selectValue">
                                    <SelectTrigger id="demo-select" class="w-full">
                                        <SelectValue placeholder="Choose a role" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="admin">Admin</SelectItem>
                                        <SelectItem value="editor">Editor</SelectItem>
                                        <SelectItem value="viewer">Viewer</SelectItem>
                                    </SelectContent>
                                </Select>
                                <p class="text-xs text-muted-foreground">Selected: {{ selectValue || '(none)' }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>


                <!-- Combobox -->
                <Card>
                    <CardHeader>
                        <CardTitle>Combobox</CardTitle>
                        <CardDescription>Searchable select — useful for long option lists (roles, permissions, users)
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-3 max-w-xs">
                            <Label>Assign Role</Label>
                            <Combobox v-model="selectedRole" by="label">
                                <ComboboxAnchor as-child>
                                    <ComboboxTrigger as-child>
                                        <Button variant="outline" class="w-full justify-between">
                                            {{ selectedRole?.label ?? 'Select role...' }}
                                            <ChevronsUpDownIcon class="h-4 w-4 opacity-50" />
                                        </Button>
                                    </ComboboxTrigger>
                                </ComboboxAnchor>
                                <ComboboxList>
                                    <ComboboxInput placeholder="Search role..." />
                                    <ComboboxEmpty>No role found.</ComboboxEmpty>
                                    <ComboboxGroup>
                                        <ComboboxItem v-for="role in roleOptions" :key="role.value" :value="role">
                                            {{ role.label }}
                                            <ComboboxItemIndicator>
                                                <CheckIcon class="h-4 w-4" />
                                            </ComboboxItemIndicator>
                                        </ComboboxItem>
                                    </ComboboxGroup>
                                </ComboboxList>
                            </Combobox>
                            <p class="text-xs text-muted-foreground">
                                Selected: {{ selectedRole?.label ?? '(none)' }}
                            </p>
                        </div>
                    </CardContent>
                </Card>


                <!-- Combobox (Multi-select) -->
                <Card>
                    <CardHeader>
                        <CardTitle>Combobox (Multi-select)</CardTitle>
                        <CardDescription>Select multiple roles or permissions at once</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-3 max-w-sm">
                            <Label>Assign Roles</Label>
                            <Combobox v-model="selectedRoles" by="label" multiple>
                                <ComboboxAnchor as-child>
                                    <ComboboxTrigger as-child>
                                        <Button variant="outline" class="w-full justify-between font-normal">
                                            <span class="truncate">
                                                {{selectedRoles.length
                                                    ? selectedRoles.map(r => r.label).join(', ')
                                                    : 'Select roles...'}}
                                            </span>
                                            <ChevronsUpDownIcon class="h-4 w-4 shrink-0 opacity-50" />
                                        </Button>
                                    </ComboboxTrigger>
                                </ComboboxAnchor>
                                <ComboboxList>
                                    <ComboboxInput placeholder="Search roles..." />
                                    <ComboboxEmpty>No role found.</ComboboxEmpty>
                                    <ComboboxGroup>
                                        <ComboboxItem v-for="role in roleOptions" :key="role.value" :value="role">
                                            {{ role.label }}
                                            <ComboboxItemIndicator>
                                                <CheckIcon class="h-4 w-4" />
                                            </ComboboxItemIndicator>
                                        </ComboboxItem>
                                    </ComboboxGroup>
                                </ComboboxList>
                            </Combobox>

                            <div v-if="selectedRoles.length" class="flex flex-wrap gap-2">
                                <Badge v-for="role in selectedRoles" :key="role.value" variant="secondary"
                                    class="gap-1 pr-1">
                                    {{ role.label }}
                                    <button type="button" class="ml-0.5 rounded-full p-0.5 hover:bg-muted-foreground/20"
                                        :aria-label="`Remove ${role.label}`" @click="removeRole(role.value)">
                                        <XIcon class="h-3 w-3" />
                                    </button>
                                </Badge>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                {{ selectedRoles.length }} role(s) selected
                            </p>
                        </div>
                    </CardContent>
                </Card>


                <!-- Date Picker -->
                <Card>
                    <CardHeader>
                        <CardTitle>Date Picker</CardTitle>
                        <CardDescription>Popover calendar for selecting a single date</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-2 max-w-xs">
                            <Label>Date</Label>
                            <Popover v-slot="{ close }">
                                <PopoverTrigger as-child>
                                    <Button variant="outline"
                                        :class="cn('w-[240px] justify-start text-left font-normal', !dateDemoValue && 'text-muted-foreground')">
                                        <CalendarIcon class="mr-2 h-4 w-4" />
                                        {{ dateDemoValue ?
                                            dateFormatter.format(dateDemoValue.toDate(getLocalTimeZone())) : 'Pick a date'
                                        }}
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-auto p-0" align="start">
                                    <Calendar v-model="dateDemoValue" :default-placeholder="dateDemoPlaceholder"
                                        layout="month-and-year" initial-focus @update:model-value="close" />
                                </PopoverContent>
                            </Popover>
                        </div>
                    </CardContent>
                </Card>



                <!-- Date Range Picker -->
                <Card>
                    <CardHeader>
                        <CardTitle>Date Range Picker</CardTitle>
                        <CardDescription>Select a start and end date — useful for filters and reports</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-2">
                            <Label>Date Range</Label>
                            <RangeCalendar v-model="dateRangeDemo" class="rounded-md border shadow-sm"
                                :number-of-months="2" disable-days-outside-current-view />
                            <p class="text-xs text-muted-foreground">
                                {{ dateRangeDemo.start?.toString() }} — {{ dateRangeDemo.end?.toString() }}
                            </p>
                        </div>
                    </CardContent>
                </Card>


                <!-- Radio Group -->
                <Card>
                    <CardHeader>
                        <CardTitle>Radio Group</CardTitle>
                        <CardDescription>Single choice from a set of mutually exclusive options</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-3">
                            <Label>User Status</Label>
                            <RadioGroup v-model="userStatus">
                                <div class="flex items-center space-x-2">
                                    <RadioGroupItem id="status-active" value="active" />
                                    <Label for="status-active" class="font-normal">Active</Label>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <RadioGroupItem id="status-inactive" value="inactive" />
                                    <Label for="status-inactive" class="font-normal">Inactive</Label>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <RadioGroupItem id="status-suspended" value="suspended" />
                                    <Label for="status-suspended" class="font-normal">Suspended</Label>
                                </div>
                            </RadioGroup>
                            <p class="text-xs text-muted-foreground">Selected: {{ userStatus }}</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Switch -->
                <Card>
                    <CardHeader>
                        <CardTitle>Switch</CardTitle>
                        <CardDescription>On/off toggle</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="demo-switch">Switch</Label>
                                <Switch id="demo-switch" v-model="switchValue" />
                                <p class="text-xs text-muted-foreground">Value: {{ switchValue }}</p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="demo-switch-disabled">Disabled Switch</Label>
                                <Switch id="demo-switch-disabled" v-model="switchValue" disabled />
                            </div>
                        </div>
                    </CardContent>                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   
                </Card>


                <!-- OTP / PIN Input -->
                <Card>
                    <CardHeader>
                        <CardTitle>OTP / PIN Input</CardTitle>
                        <CardDescription>Used for verification codes (vue-input-otp)</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-3">
                            <Label>6-digit code</Label>
                            <InputOTP v-model="otpValue" :maxlength="6" v-slot="{ slots }">
                                <InputOTPGroup>
                                    <InputOTPSlot v-for="(slot, index) in slots.slice(0, 3)" :key="index" :index="index"
                                        v-bind="slot" />
                                </InputOTPGroup>
                                <InputOTPSeparator />
                                <InputOTPGroup>
                                    <InputOTPSlot v-for="(slot, index) in slots.slice(3, 6)" :key="index + 3"
                                        :index="index + 3" v-bind="slot" />
                                </InputOTPGroup>
                            </InputOTP>
                            <p class="text-xs text-muted-foreground">Value: {{ otpValue || '(empty)' }}</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Badges & Avatars -->
                <Card>
                    <CardHeader>
                        <CardTitle>Badges & Avatars</CardTitle>
                        <CardDescription>Status labels and user avatars</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div>
                            <p class="mb-2 text-sm font-medium">Badges</p>
                            <div class="flex flex-wrap gap-3">
                                <Badge variant="default">Default</Badge>
                                <Badge variant="secondary">Secondary</Badge>
                                <Badge variant="outline">Outline</Badge>
                                <Badge variant="destructive">Destructive</Badge>
                            </div>
                        </div>
                        <div>
                            <p class="mb-2 text-sm font-medium">Avatars</p>
                            <div class="flex items-center gap-3">
                                <Avatar>
                                    <AvatarImage src="https://github.com/shadcn.png" alt="User" />
                                    <AvatarFallback>CN</AvatarFallback>
                                </Avatar>
                                <Avatar>
                                    <AvatarFallback>JD</AvatarFallback>
                                </Avatar>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Alerts -->
                <Card>
                    <CardHeader>
                        <CardTitle>Alerts</CardTitle>
                        <CardDescription>Inline messages for feedback and warnings</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <Alert>
                            <Info class="h-4 w-4" />
                            <AlertTitle>Heads up</AlertTitle>
                            <AlertDescription>This is a default informational alert.</AlertDescription>
                        </Alert>

                        <Alert variant="destructive">
                            <AlertTriangle class="h-4 w-4" />
                            <AlertTitle>Error</AlertTitle>
                            <AlertDescription>Something went wrong. Please try again.</AlertDescription>
                        </Alert>
                    </CardContent>
                </Card>

                <!-- Toasts (vue-sonner) -->
                <Card>
                    <CardHeader>
                        <CardTitle>Toasts</CardTitle>
                        <CardDescription>Temporary notifications (vue-sonner)</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="flex flex-wrap gap-3">
                            <Button variant="outline" @click="showToastInfo">Show Info Toast</Button>
                            <Button variant="outline" @click="showToastSuccess">Show Success Toast</Button>
                            <Button variant="outline" @click="showToastError">Show Error Toast</Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Dialog & Sheet -->
                <Card>
                    <CardHeader>
                        <CardTitle>Dialog & Sheet</CardTitle>
                        <CardDescription>Modal and slide-over panels</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="flex flex-wrap gap-3">
                            <Dialog v-model:open="dialogOpen">
                                <DialogTrigger as-child>
                                    <Button variant="outline">Open Dialog</Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Confirm Action</DialogTitle>
                                        <DialogDescription>
                                            Are you sure you want to proceed? This action cannot be undone.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <DialogFooter>
                                        <Button variant="outline" @click="dialogOpen = false">Cancel</Button>
                                        <Button variant="destructive" @click="dialogOpen = false">Confirm</Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>

                            <Sheet v-model:open="sheetOpen">
                                <SheetTrigger as-child>
                                    <Button variant="outline">Open Sheet</Button>
                                </SheetTrigger>
                                <SheetContent>
                                    <SheetHeader>
                                        <SheetTitle>Edit Details</SheetTitle>
                                        <SheetDescription>
                                            Make changes here. Click save when you're done.
                                        </SheetDescription>
                                    </SheetHeader>
                                </SheetContent>
                            </Sheet>
                        </div>
                    </CardContent>
                </Card>

                <!-- Dropdown Menu & Tooltip -->
                <Card>
                    <CardHeader>
                        <CardTitle>Dropdown Menu & Tooltip</CardTitle>
                        <CardDescription>Contextual menus and hover hints</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="flex flex-wrap items-center gap-3">
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="outline" size="icon">
                                        <MoreHorizontal class="h-4 w-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem>Edit</DropdownMenuItem>
                                    <DropdownMenuItem>Duplicate</DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem class="text-destructive">Delete</DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>

                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button variant="outline">Hover me</Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>This is a tooltip</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </div>
                    </CardContent>
                </Card>

                <!-- Breadcrumb -->
                <Card>
                    <CardHeader>
                        <CardTitle>Breadcrumb</CardTitle>
                        <CardDescription>Page navigation trail</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Breadcrumb>
                            <BreadcrumbList>
                                <BreadcrumbItem>
                                    <BreadcrumbLink href="#">Dashboard</BreadcrumbLink>
                                </BreadcrumbItem>
                                <BreadcrumbSeparator />
                                <BreadcrumbItem>
                                    <BreadcrumbLink href="#">Superadmin</BreadcrumbLink>
                                </BreadcrumbItem>
                                <BreadcrumbSeparator />
                                <BreadcrumbItem>
                                    <BreadcrumbPage>Documentation</BreadcrumbPage>
                                </BreadcrumbItem>
                            </BreadcrumbList>
                        </Breadcrumb>
                    </CardContent>
                </Card>

                <!-- Collapsible -->
                <Card>
                    <CardHeader>
                        <CardTitle>Collapsible</CardTitle>
                        <CardDescription>Expand/collapse a content section</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Collapsible v-model:open="collapsibleOpen" class="w-full space-y-2">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium">Advanced settings</p>
                                <CollapsibleTrigger as-child>
                                    <Button variant="ghost" size="icon">
                                        <ChevronsUpDown class="h-4 w-4" />
                                    </Button>
                                </CollapsibleTrigger>
                            </div>
                            <CollapsibleContent class="space-y-2 rounded-md border px-4 py-3 text-sm">
                                Hidden content revealed when expanded — useful for optional fields.
                            </CollapsibleContent>
                        </Collapsible>
                    </CardContent>
                </Card>

                <!-- Table -->
                <Card>
                    <CardHeader>
                        <CardTitle>Table</CardTitle>
                        <CardDescription>Tabular data display</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Role</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow>
                                    <TableCell>Juan Dela Cruz</TableCell>
                                    <TableCell>Admin</TableCell>
                                    <TableCell>
                                        <Badge>Active</Badge>
                                    </TableCell>
                                </TableRow>
                                <TableRow>
                                    <TableCell>Maria Santos</TableCell>
                                    <TableCell>Editor</TableCell>
                                    <TableCell>
                                        <Badge variant="secondary">Pending</Badge>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <!-- Data Table (TanStack) -->
                <Card>
                    <CardHeader>
                        <CardTitle>Data Table</CardTitle>
                        <CardDescription>Sortable, filterable, paginated table with row selection and per-row actions
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <DataTable />
                    </CardContent>
                </Card>

                <!-- Skeleton -->
                <Card>
                    <CardHeader>
                        <CardTitle>Skeleton</CardTitle>
                        <CardDescription>Loading placeholder</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-center gap-4">
                            <Skeleton class="h-12 w-12 rounded-full" />
                            <div class="space-y-2">
                                <Skeleton class="h-4 w-[200px]" />
                                <Skeleton class="h-4 w-[150px]" />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Separator -->
                <Card>
                    <CardHeader>
                        <CardTitle>Separator</CardTitle>
                        <CardDescription>Visual divider between sections</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm">Content above</p>
                        <Separator class="my-4" />
                        <p class="text-sm">Content below</p>
                    </CardContent>
                </Card>



                <!-- Confirm Delete Pattern -->
                <Card>
                    <CardHeader>
                        <CardTitle>Confirm Delete Pattern</CardTitle>
                        <CardDescription>Standard destructive-action confirmation dialog</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Dialog v-model:open="confirmDeleteOpen">
                            <DialogTrigger as-child>
                                <Button variant="destructive">
                                    <Trash2 class="mr-2 h-4 w-4" />
                                    Delete Item
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Are you absolutely sure?</DialogTitle>
                                    <DialogDescription>
                                        This action cannot be undone. This will permanently delete the
                                        record and remove it from our servers.
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter>
                                    <Button variant="outline" @click="confirmDeleteOpen = false">Cancel</Button>
                                    <Button variant="destructive" @click="handleConfirmDelete">
                                        Yes, delete it
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </CardContent>
                </Card>

                <!-- Form Validation Pattern -->
                <Card>
                    <CardHeader>
                        <CardTitle>Form Validation Pattern</CardTitle>
                        <CardDescription>Standard field error state with message</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="demo-valid">Email (valid)</Label>
                                <Input id="demo-valid" v-model="validationDemo.valid" type="email" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="demo-invalid" class="text-destructive">Email (invalid)</Label>
                                <Input id="demo-invalid" v-model="validationDemo.invalid" type="email"
                                    aria-invalid="true" class="border-destructive" />
                                <p class="text-xs text-destructive">Please enter a valid email address.</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Stat / KPI Cards -->
                <Card>
                    <CardHeader>
                        <CardTitle>Stat Cards</CardTitle>
                        <CardDescription>Dashboard summary card pattern</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <Card>
                                <CardHeader class="pb-2">
                                    <CardDescription>Total Users</CardDescription>
                                    <CardTitle class="text-2xl">1,204</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p class="text-xs text-muted-foreground">+12% from last month</p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader class="pb-2">
                                    <CardDescription>Active Roles</CardDescription>
                                    <CardTitle class="text-2xl">18</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p class="text-xs text-muted-foreground">Across 4 departments</p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader class="pb-2">
                                    <CardDescription>Pending Requests</CardDescription>
                                    <CardTitle class="text-2xl">7</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <Badge variant="secondary">Needs review</Badge>
                                </CardContent>
                            </Card>
                        </div>
                    </CardContent>
                </Card>

                <!-- Empty State -->
                <Card>
                    <CardHeader>
                        <CardTitle>Empty State</CardTitle>
                        <CardDescription>Shown when a list or table has no data</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div
                            class="flex flex-col items-center justify-center gap-3 rounded-md border border-dashed py-12 text-center">
                            <Info class="h-8 w-8 text-muted-foreground" />
                            <div>
                                <p class="text-sm font-medium">No records found</p>
                                <p class="text-xs text-muted-foreground">Get started by creating a new item.</p>
                            </div>
                            <Button size="sm">
                                <Plus class="mr-2 h-4 w-4" />
                                Create New
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Loading Skeleton Table -->
                <Card>
                    <CardHeader>
                        <CardTitle>Loading Skeleton (Table)</CardTitle>
                        <CardDescription>Placeholder shown while table data is fetching</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div v-for="i in 4" :key="i" class="flex items-center gap-4">
                            <Skeleton class="h-10 w-10 rounded-full" />
                            <Skeleton class="h-4 flex-1" />
                            <Skeleton class="h-4 w-20" />
                            <Skeleton class="h-4 w-16" />
                        </div>
                    </CardContent>
                </Card>

            </section>

        </div>
    </div>

</template>