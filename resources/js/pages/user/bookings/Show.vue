
<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { index } from '@/routes/user/bookings';
import { Button } from '@/components/ui/button';
import PickupMap from '@/components/maps/PickupMap.vue';
import TourRoute from '@/components/tours/TourRoute.vue';
import {
  Carousel,
  CarouselContent,
  CarouselItem,
  CarouselNext,
  CarouselPrevious,
} from '@/components/ui/carousel';
import {
  ArrowLeft,
  Bus,
  CalendarDays,
  CheckCircle2,
  Clock3,
  CreditCard,
  ExternalLink,
  FileText,
  Globe,
  ImageOff,
  MapPin,
  Phone,
  Receipt,
  Sparkles,
  Ticket,
  Trash2,
  UserRound,
  Users,
  XCircle,
} from '@lucide/vue';
import WeatherAdvisoryCard, { type WeatherForecastData } from '@/components/weather/WeatherAdvisoryCard.vue';

type TourTime = {
  id: number;
  tour_date_id: number;
  time: string;
  description: string | null;
};

type Package = {
  id: number;
  package_name: string;
  destination: string;
  description: string | null;
  price: number | string;
  status: string;
  stops?: string[];
  island_count?: number;
  image?: string[] | null;
  image_urls?: string[];
};

type TourDate = {
  id: number;
  package_id: number;
  tour_date: string;
  capacity: number;
  package: Package;
  times: TourTime[];
  weather_forecast?: WeatherForecastData | null;
};

type PickupLocation = {
  id: number;
  name: string;
  address?: string;
  description?: string;
  status?: string;
};

type PickupSchedule = {
  id: number;
  tour_date_id: number;
  pickup_location_id: number;
  pickup_time: string;
  pickup_location: PickupLocation | null;
};

type Payment = {
  id: number;
  booking_id: number;
  amount: number | string;
  payment_method: string | null;
  payment_status: string;
  transaction_reference: string | null;
  notes: string | null;
  paid_at: string | null;
  created_at: string | null;
  updated_at: string | null;
};

type Booking = {
  id: number;
  user_id: number;
  tour_date_id: number;
  pickup_schedule_id: number;
  pickup_latitude?: number | string | null;
  pickup_longitude?: number | string | null;
  number_of_guests: number;
  phone_number: string;
  nationality: string;
  special_request: string | null;
  booking_status: string;
  qr_token: string | null;
  created_at: string;
  updated_at: string;
  booked_unit_price: number | string | null;
  booked_total: number | string | null;
  price_snapshot_source: string | null;
  package?: Package | null;
  tour_date: TourDate;
  pickup_schedule: PickupSchedule;
  payments: Payment[];
};

type Capacity = {
  total: number;
  booked_guests: number;
  remaining_guests: number;
};

type CaseRequest = {
  id: number;
  reason: string;
  requested_resolution: string;
  details: string;
  status: string;
  admin_note: string | null;
};

type RefundRequestInfo = {
  id: number;
  payment_id: number;
  category: string | null;
  category_label: string;
  reason: string;
  status: string;
  status_label: string;
  rejection_reason: string | null;
  created_at: string | null;
  reviewed_at: string | null;
  processed_at: string | null;
};

const props = defineProps<{
  booking: Booking;
  capacity: Capacity;
  caseRequest?: CaseRequest | null;
  refundRequest?: RefundRequestInfo | null;
}>();

const problemForm = useForm({
  reason: 'operator_no_show',
  requested_resolution: 'reschedule',
  details: '',
});

function submitProblemReport(): void {
  problemForm.post(`/user/bookings/${props.booking.id}/cases`, {
    preserveScroll: true,
  });
}

const hasOpenRefund = computed(() =>
  ['pending', 'approved', 'refunded_test'].includes(props.refundRequest?.status ?? ''),
);

const canReportProblem = computed(() => {
  const paid = (props.booking.payments ?? []).some((payment) =>
    ['paid', 'partial'].includes(payment.payment_status),
  );

  if (!paid || !['confirmed', 'completed'].includes(props.booking.booking_status)) {
    return false;
  }

  if (problemForm.requested_resolution === 'refund') {
    return (props.booking.payments ?? []).some((payment) => payment.payment_status === 'paid')
      && !hasOpenRefund.value;
  }

  return props.caseRequest?.status !== 'submitted';
});

function formatDate(date: string | null): string {
  if (!date) return '—';
  const dateOnly = date.slice(0, 10);
  const parsed = new Date(`${dateOnly}T00:00:00`);

  if (Number.isNaN(parsed.getTime())) return date;

  return parsed.toLocaleDateString('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

function formatTime(time: string | null | undefined): string {
  if (!time) return '—';

  const match = time.match(/^(\d{1,2}):(\d{2})/);

  if (match) {
    const hours = Number(match[1]);
    const minutes = Number(match[2]);

    if (hours >= 0 && hours <= 23 && minutes >= 0 && minutes <= 59) {
      const period = hours >= 12 ? 'PM' : 'AM';
      const hour12 = hours % 12 === 0 ? 12 : hours % 12;
      return `${hour12}:${String(minutes).padStart(2, '0')} ${period}`;
    }
  }

  const parsed = new Date(time);

  if (!Number.isNaN(parsed.getTime())) {
    return parsed.toLocaleTimeString('en-US', {
      hour: 'numeric',
      minute: '2-digit',
    });
  }

  return time;
}

function formatPrice(price: number | string): string {
  return Number(price).toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

// Use the saved booking quote, not the live package price.
function packageTotal(): number | null {
  const raw = props.booking.booked_total;

  if (raw === null || raw === undefined || raw === '') {
    return null;
  }

  const value = Number(raw);

  return Number.isFinite(value) && value > 0 ? value : null;
}

function savedUnitPrice(): number | null {
  const raw = props.booking.booked_unit_price;

  if (raw !== null && raw !== undefined && raw !== '') {
    const amount = Number(raw);

    if (Number.isFinite(amount) && amount > 0) {
      return amount;
    }
  }

  const total = packageTotal();

  return total !== null && props.booking.number_of_guests > 0
    ? total / props.booking.number_of_guests
    : null;
}

function needsPriceReview(): boolean {
  return (
    packageTotal() === null ||
    props.booking.price_snapshot_source === 'legacy_estimate'
  );
}

function formatMoney(value: number | null): string {
  return value === null ? 'Needs review' : `₱${formatPrice(value)}`;
}

// Count only recorded paid and partial payment transactions.
function amountReceived(): number {
  const receivedCents = (props.booking.payments ?? [])
    .filter((payment) =>
      ['paid', 'partial'].includes(payment.payment_status),
    )
    .reduce((sum, payment) => {
      const amount = Number(payment.amount);

      return sum + (
        Number.isFinite(amount) && amount > 0
          ? Math.round(amount * 100)
          : 0
      );
    }, 0);

  return receivedCents / 100;
}

function remainingBalance(): number | null {
  const total = packageTotal();

  return total === null
    ? null
    : Math.max(
        0,
        Math.round((total - amountReceived()) * 100) / 100,
      );
}

function hasOutstandingBalance(): boolean {
  const remaining = remainingBalance();
  return remaining !== null && remaining > 0;
}

// Calculate the status for the whole booking,
// not just the first PayMongo payment.
function overallPaymentStatus():
  'paid' | 'partial' | 'pending' | 'unpaid' | 'review' {
  if (needsPriceReview()) return 'review';

  const total = packageTotal();
  const received = amountReceived();

  if (total !== null && received >= total - 0.005) {
    return 'paid';
  }

  if (received > 0) return 'partial';

  if ((props.booking.payments ?? []).some(
    (payment) => payment.payment_status === 'pending',
  )) {
    return 'pending';
  }

  return 'unpaid';
}

function overallPaymentLabel(): string {
  switch (overallPaymentStatus()) {
    case 'paid':
      return 'Fully Paid';
    case 'partial':
      return 'Partially Paid';
    case 'pending':
      return 'Awaiting Payment';
    case 'review':
      return 'Price Needs Review';
    default:
      return 'Unpaid';
  }
}

function formatDateTime(date: string | null): string {
  if (!date) return '—';

  const parsed = new Date(date);
  if (Number.isNaN(parsed.getTime())) return date;

  return parsed.toLocaleString('en-US', {
    dateStyle: 'medium',
    timeStyle: 'short',
  });
}

function statusBadgeClass(status: string) {
  switch (status) {
    case 'confirmed':
      return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20';
    case 'cancelled':
      return 'bg-destructive/10 text-destructive border-destructive/20';
    case 'completed':
      return 'bg-primary/10 text-primary border-primary/20';
    case 'pending':
      return 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border-orange-500/20';
    default:
      return 'bg-muted text-muted-foreground border-border';
  }
}

function statusLabel(status: string): string {
  return status.charAt(0).toUpperCase() + status.slice(1);
}

function paymentStatusBadgeClass(status?: string) {
  switch (status?.toLowerCase()) {
    case 'paid':
    case 'confirmed':
      return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20';
    case 'partial':
      return 'bg-sky-500/10 text-sky-700 dark:text-sky-300 border-sky-500/20';
    case 'pending':
    case 'review':
      return 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border-orange-500/20';
    case 'unpaid':
      return 'bg-muted text-muted-foreground border-border';
    case 'rejected':
    case 'cancelled':
    case 'failed':
      return 'bg-destructive/10 text-destructive border-destructive/20';
    default:
      return 'bg-muted text-muted-foreground border-border';
  }
}

function capacityPercentage(): number {
  if (props.capacity.total <= 0) return 0;

  return Math.min(
    100,
    Math.round(
      (props.capacity.booked_guests / props.capacity.total) * 100,
    ),
  );
}

function isFull(): boolean {
  return props.capacity.remaining_guests <= 0;
}

function packageImageUrls(): string[] {
  return props.booking.package?.image_urls ||
    props.booking.tour_date?.package?.image_urls ||
    [];
}

function currentPackage(): Package | null {
  return props.booking.package ||
    props.booking.tour_date?.package ||
    null;
}

function openImage(url: string) {
  window.open(url, '_blank');
}

defineOptions({
  layout: {
    breadcrumbs: [
      {
        href: index(),
        title: 'Bookings',
      },
      {
        href: '#',
        title: 'Booking Details',
      },
    ],
  },
});
</script>

<template>
  <Head :title="`Booking #${booking.id}`" />

  <div class="tourist-page">
    <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
      <div class="absolute left-1/2 top-[-10rem] h-[28rem] w-[42rem] -translate-x-1/2 rounded-full bg-primary/10 blur-3xl" />
      <div class="absolute right-[-10rem] top-64 h-72 w-72 rounded-full bg-primary/5 blur-3xl" />
    </div>

    <div class="mx-auto max-w-7xl px-5 py-8 lg:px-8">
      <div class="mb-6 flex items-center justify-between">
        <Link
          :href="index()"
          class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground transition hover:text-foreground"
        >
          <ArrowLeft class="h-4 w-4" />
          <span>Back to Bookings</span>
        </Link>

        <div class="inline-flex items-center gap-2 rounded-full border bg-background/80 px-3 py-1 text-xs font-medium backdrop-blur shadow-xs">
          <Sparkles class="h-3.5 w-3.5 text-primary" />
          <span>Reservation Manifest</span>
        </div>
      </div>

      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div class="flex flex-wrap items-center gap-2.5">
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">
              Booking #{{ booking.id }}
            </h1>

            <span
              class="inline-flex items-center rounded-full border px-3 py-0.5 text-xs font-semibold"
              :class="statusBadgeClass(booking.booking_status)"
            >
              {{ statusLabel(booking.booking_status) }}
            </span>
          </div>

          <p class="mt-1 text-sm text-muted-foreground">
            Trip details, schedule, pickup, and payment status.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
          <Link
            v-if="booking.booking_status === 'confirmed' && booking.qr_token"
            :href="`/user/bookings/${booking.id}/qr`"
          >
            <Button size="sm" class="rounded-xl shadow-xs">
              <Ticket class="mr-2 h-4 w-4" />
              <span>View Boarding Ticket</span>
            </Button>
          </Link>

          <Link
            v-else-if="booking.booking_status === 'pending' && !needsPriceReview()"
            :href="`/user/bookings/${booking.id}/payment`"
          >
            <Button size="sm" class="rounded-xl shadow-xs">
              <CreditCard class="mr-2 h-4 w-4" />
              <span>
                {{ booking.payments?.length ? 'Update payment' : 'Continue to payment' }}
              </span>
            </Button>
          </Link>
        </div>
      </div>

      <div
        v-if="booking.booking_status === 'cancelled'"
        class="mt-6 flex flex-col gap-3 rounded-2xl border border-destructive/20 bg-destructive/5 p-4 text-xs backdrop-blur sm:flex-row sm:items-center sm:justify-between"
      >
        <div class="flex items-center gap-3">
          <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-destructive/10 text-destructive">
            <XCircle class="h-5 w-5" />
          </div>

          <div>
            <p class="font-bold text-foreground">
              This reservation is cancelled
            </p>

            <p class="text-muted-foreground">
              Reserved slots have been released. You can permanently
              delete this booking from your list.
            </p>
          </div>
        </div>

        <Link :href="index()">
          <Button
            size="sm"
            variant="outline"
            class="rounded-xl border-destructive/30 text-destructive hover:bg-destructive/10"
          >
            <Trash2 class="mr-2 h-4 w-4" />
            <span>Manage / Delete in Bookings</span>
          </Button>
        </Link>
      </div>

      <div class="mt-8 space-y-8">
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
          <div class="rounded-2xl border bg-card/60 p-5 shadow-xs backdrop-blur">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                Reference
              </span>

              <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <Ticket class="h-4 w-4" />
              </div>
            </div>

            <p class="mt-3 text-2xl font-extrabold tracking-tight">
              #{{ booking.id }}
            </p>

            <p class="mt-1 text-xs text-muted-foreground">
              Reservation reference code
            </p>
          </div>

          <div class="rounded-2xl border bg-card/60 p-5 shadow-xs backdrop-blur">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                Guests
              </span>

              <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <Users class="h-4 w-4" />
              </div>
            </div>

            <p class="mt-3 text-2xl font-extrabold tracking-tight">
              {{ booking.number_of_guests }}
            </p>

            <p class="mt-1 text-xs text-muted-foreground">
              {{ booking.number_of_guests === 1 ? 'Registered guest' : 'Total guests in party' }}
            </p>
          </div>

          <div class="rounded-2xl border bg-card/60 p-5 shadow-xs backdrop-blur">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                Trip Status
              </span>

              <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <CalendarDays class="h-4 w-4" />
              </div>
            </div>

            <div class="mt-3">
              <span
                class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold"
                :class="statusBadgeClass(booking.booking_status)"
              >
                {{ statusLabel(booking.booking_status) }}
              </span>
            </div>

            <p class="mt-1 text-xs text-muted-foreground">
              Current itinerary state
            </p>
          </div>

          <div class="rounded-2xl border bg-card/60 p-5 shadow-xs backdrop-blur">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                Payment
              </span>

              <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <CreditCard class="h-4 w-4" />
              </div>
            </div>

            <div class="mt-3">
              <span
                class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold"
                :class="paymentStatusBadgeClass(overallPaymentStatus())"
              >
                {{ overallPaymentLabel() }}
              </span>
            </div>

            <p class="mt-1 text-xs text-muted-foreground">
              {{
                needsPriceReview()
                  ? 'Verify the original agreed price'
                  : 'Calculated from recorded payments'
              }}
            </p>
          </div>
        </div>

        <section class="rounded-3xl border bg-card/60 p-6 shadow-sm backdrop-blur sm:p-8">
          <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
              <MapPin class="h-5 w-5" />
            </div>

            <div>
              <h2 class="text-base font-bold text-foreground sm:text-lg">
                Tour Package Overview
              </h2>

              <p class="text-xs text-muted-foreground">
                Selected itinerary specifications, highlights, and media preview
              </p>
            </div>
          </div>

          <div class="mt-6 grid gap-8 lg:grid-cols-12 lg:items-start">
            <div class="lg:col-span-7">
              <Carousel v-if="packageImageUrls().length" class="w-full">
                <CarouselContent>
                  <CarouselItem
                    v-for="(url, imageIndex) in packageImageUrls()"
                    :key="imageIndex"
                  >
                    <div class="group relative aspect-video overflow-hidden rounded-2xl border bg-muted/40 shadow-xs">
                      <img
                        :src="url"
                        :alt="currentPackage()?.package_name"
                        class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                      />

                      <div class="absolute inset-0 flex items-center justify-center bg-black/20 opacity-0 transition duration-200 group-hover:opacity-100">
                        <button
                          type="button"
                          class="inline-flex items-center gap-1.5 rounded-full bg-background/90 px-3.5 py-1.5 text-xs font-semibold text-foreground shadow-xs backdrop-blur transition hover:bg-background"
                          @click="openImage(url)"
                        >
                          <ExternalLink class="h-3.5 w-3.5 text-primary" />
                          View Full Size
                        </button>
                      </div>

                      <span class="absolute bottom-3 right-3 rounded-full bg-black/60 px-2.5 py-1 text-xs font-medium text-white backdrop-blur">
                        {{ imageIndex + 1 }} / {{ packageImageUrls().length }}
                      </span>
                    </div>
                  </CarouselItem>
                </CarouselContent>

                <template v-if="packageImageUrls().length > 1">
                  <CarouselPrevious
                    class="left-3 rounded-xl border-border/80 bg-background/80 text-foreground backdrop-blur"
                  />

                  <CarouselNext
                    class="right-3 rounded-xl border-border/80 bg-background/80 text-foreground backdrop-blur"
                  />
                </template>
              </Carousel>

              <div
                v-else
                class="flex aspect-video flex-col items-center justify-center rounded-2xl border border-dashed bg-muted/20 text-center text-muted-foreground"
              >
                <ImageOff class="h-8 w-8" />
                <p class="mt-2 text-xs font-medium">
                  No package images available
                </p>
              </div>
            </div>

            <div class="space-y-4 lg:col-span-5">
              <div>
                <div class="flex items-center gap-2">
                  <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    Selected Package
                  </span>

                  <span
                    v-if="currentPackage()?.status"
                    class="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold text-primary"
                  >
                    {{ currentPackage()?.status?.toUpperCase() }}
                  </span>
                </div>

                <h3 class="mt-1 text-xl font-bold tracking-tight text-foreground sm:text-2xl">
                  {{ currentPackage()?.package_name ?? 'Island Hopping Package' }}
                </h3>

                <p class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                  <MapPin class="h-3.5 w-3.5 shrink-0 text-primary" />
                  <span>
                    {{ currentPackage()?.destination ?? 'Surigao' }}
                  </span>
                </p>

                <TourRoute
                  class="mt-4"
                  :stops="currentPackage()?.stops ?? []"
                  :island-count="currentPackage()?.island_count ?? 0"
                />
              </div>

              <p
                v-if="currentPackage()?.description"
                class="whitespace-pre-line text-xs leading-relaxed text-muted-foreground"
              >
                {{ currentPackage()?.description }}
              </p>

              <div class="space-y-2.5 rounded-2xl border bg-background/60 p-4.5 backdrop-blur">
                <div class="flex items-baseline justify-between">
                  <span class="text-xs font-medium text-muted-foreground">
                    {{
                      needsPriceReview()
                        ? 'Estimated Rate Per Guest'
                        : 'Booked Rate Per Guest'
                    }}
                  </span>

                  <span class="text-lg font-bold text-foreground">
                    {{ formatMoney(savedUnitPrice()) }}
                  </span>
                </div>

                <div class="flex items-center justify-between border-t pt-2.5 text-xs">
                  <span class="text-muted-foreground">Party Size</span>

                  <span class="font-semibold text-foreground">
                    {{ booking.number_of_guests }}
                    {{ booking.number_of_guests === 1 ? 'Guest' : 'Guests' }}
                  </span>
                </div>

                <div class="flex items-center justify-between border-t pt-2.5 text-xs">
                  <span class="font-bold text-foreground">
                    {{
                      needsPriceReview()
                        ? 'Estimated Booking Total'
                        : 'Saved Booking Total'
                    }}
                  </span>

                  <span class="text-base font-extrabold text-primary">
                    {{ formatMoney(packageTotal()) }}
                  </span>
                </div>

                <p
                  v-if="needsPriceReview()"
                  class="rounded-xl border border-amber-500/30 bg-amber-500/5 p-3 text-xs text-amber-700 dark:text-amber-400"
                >
                  The original price of this booking needs verification
                  against its receipt or booking record. The amount shown
                  is not a verified original quote.
                </p>
              </div>
            </div>
          </div>
        </section>

        <WeatherAdvisoryCard
          :forecast="booking.tour_date.weather_forecast"
          :tour-date="booking.tour_date.tour_date"
          :destination="currentPackage()?.destination ?? 'Surigao'"
          :package-name="currentPackage()?.package_name ?? 'Island Tour'"
        />

        <section class="rounded-3xl border bg-card/60 p-6 shadow-sm backdrop-blur sm:p-8">
          <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
              <CalendarDays class="h-5 w-5" />
            </div>

            <div>
              <h2 class="text-base font-bold text-foreground sm:text-lg">
                Tour & Schedule Information
              </h2>

              <p class="text-xs text-muted-foreground">
                Dates, reserved seats, and travel capacity breakdown
              </p>
            </div>
          </div>

          <div class="mt-6 grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border bg-background/60 p-4 backdrop-blur">
              <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                <CalendarDays class="h-3.5 w-3.5 text-primary" />
                Tour Date
              </p>

              <p class="mt-2 text-base font-bold text-foreground">
                {{ formatDate(booking.tour_date.tour_date) }}
              </p>
            </div>

            <div class="rounded-2xl border bg-background/60 p-4 backdrop-blur">
              <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                <Users class="h-3.5 w-3.5 text-primary" />
                Your Party
              </p>

              <p class="mt-2 text-base font-bold text-foreground">
                {{ booking.number_of_guests }}
                {{ booking.number_of_guests === 1 ? 'Guest' : 'Guests' }}
              </p>
            </div>

            <div class="rounded-2xl border bg-background/60 p-4 backdrop-blur">
              <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                <Users class="h-3.5 w-3.5 text-primary" />
                Tour Capacity
              </p>

              <p class="mt-2 text-base font-bold text-foreground">
                {{ capacity.total }} Slots Total
              </p>
            </div>

            <div
              class="rounded-2xl border p-4 backdrop-blur"
              :class="
                isFull()
                  ? 'border-destructive/30 bg-destructive/10'
                  : 'bg-background/60'
              "
            >
              <p
                class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider"
                :class="isFull() ? 'text-destructive' : 'text-muted-foreground'"
              >
                <CheckCircle2 class="h-3.5 w-3.5" />
                Remaining Slots
              </p>

              <p
                class="mt-2 text-base font-bold"
                :class="isFull() ? 'text-destructive' : 'text-foreground'"
              >
                {{
                  isFull()
                    ? 'Fully Booked'
                    : `${capacity.remaining_guests} Slots Open`
                }}
              </p>
            </div>
          </div>

          <div class="mt-6 rounded-2xl border bg-muted/20 p-5 backdrop-blur">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <p class="text-xs font-bold text-foreground">
                  Overall Date Capacity
                </p>

                <p class="mt-0.5 text-xs text-muted-foreground">
                  {{ capacity.booked_guests }} of {{ capacity.total }}
                  total guests currently registered
                </p>
              </div>

              <span class="text-xs font-bold text-foreground">
                {{ capacityPercentage() }}% filled
              </span>
            </div>

            <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-muted">
              <div
                class="h-full rounded-full transition-all duration-300"
                :class="isFull() ? 'bg-destructive' : 'bg-primary'"
                :style="{ width: `${capacityPercentage()}%` }"
              />
            </div>
          </div>

          <div v-if="booking.tour_date.times.length" class="mt-6">
            <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
              <Clock3 class="h-3.5 w-3.5 text-primary" />
              Tour Times
            </p>

            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              <div
                v-for="time in booking.tour_date.times"
                :key="time.id"
                class="rounded-2xl border bg-background/60 p-4 backdrop-blur"
              >
                <p class="text-sm font-bold text-foreground">
                  {{ formatTime(time.time) }}
                </p>

                <p
                  v-if="time.description"
                  class="mt-0.5 text-xs text-muted-foreground"
                >
                  {{ time.description }}
                </p>
              </div>
            </div>
          </div>

          <div class="mt-6 border-t pt-6">
            <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
              <Bus class="h-3.5 w-3.5 text-primary" />
              Designated Pickup Schedule
            </p>

            <div class="mt-3 rounded-2xl border bg-background/60 p-5 backdrop-blur">
              <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <p class="text-base font-bold text-foreground">
                    {{
                      booking.pickup_schedule?.pickup_location?.name
                      ?? 'Assigned Pickup Point'
                    }}
                  </p>

                  <p
                    v-if="booking.pickup_schedule?.pickup_location?.address"
                    class="mt-0.5 text-xs text-muted-foreground"
                  >
                    {{ booking.pickup_schedule.pickup_location.address }}
                  </p>
                </div>

                <div class="inline-flex items-center gap-2 rounded-xl border bg-muted/40 px-3.5 py-2 text-xs font-semibold text-foreground">
                  <Clock3 class="h-4 w-4 text-primary" />
                  <span>
                    {{
                      formatTime(booking.pickup_schedule?.pickup_time)
                      || 'Time Pending'
                    }}
                  </span>
                </div>
              </div>

              <PickupMap
                v-if="booking.pickup_latitude && booking.pickup_longitude"
                class="mt-4"
                :latitude="booking.pickup_latitude"
                :longitude="booking.pickup_longitude"
                label="Your pinned pickup spot"
              />
            </div>
          </div>
        </section>

        <section class="rounded-3xl border bg-card/60 p-6 shadow-sm backdrop-blur sm:p-8">
          <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
              <UserRound class="h-5 w-5" />
            </div>

            <div>
              <h2 class="text-base font-bold text-foreground sm:text-lg">
                Lead Traveler Information
              </h2>

              <p class="text-xs text-muted-foreground">
                Primary guest contact details for dispatch coordination
              </p>
            </div>
          </div>

          <div class="mt-6 grid grid-cols-1 gap-3.5 sm:grid-cols-2">
            <div class="rounded-2xl border bg-background/60 p-4 backdrop-blur">
              <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                <Phone class="h-3.5 w-3.5 text-primary" />
                Phone Number
              </p>

              <p class="mt-2 text-base font-bold text-foreground">
                {{ booking.phone_number }}
              </p>
            </div>

            <div class="rounded-2xl border bg-background/60 p-4 backdrop-blur">
              <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                <Globe class="h-3.5 w-3.5 text-primary" />
                Nationality
              </p>

              <p class="mt-2 text-base font-bold text-foreground">
                {{ booking.nationality }}
              </p>
            </div>
          </div>

          <div
            v-if="booking.special_request"
            class="mt-4 rounded-2xl border bg-background/60 p-5 backdrop-blur"
          >
            <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
              <FileText class="h-3.5 w-3.5 text-primary" />
              Special Request / Dietary Notes
            </p>

            <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-foreground">
              {{ booking.special_request }}
            </p>
          </div>
        </section>

        <section class="rounded-3xl border bg-card/60 p-6 shadow-sm backdrop-blur sm:p-8">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
              <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <CreditCard class="h-5 w-5" />
              </div>

              <div>
                <h2 class="text-base font-bold text-foreground sm:text-lg">
                  Payment
                </h2>

                <p class="text-xs text-muted-foreground">
                  Amount, method, and payment status
                </p>
              </div>
            </div>

            <Link
              v-if="
                booking.booking_status === 'pending' &&
                booking.payments.length > 0 &&
                !needsPriceReview()
              "
              :href="`/user/bookings/${booking.id}/payment`"
            >
              <Button
                size="sm"
                variant="outline"
                class="rounded-xl border-border/80"
              >
                <CreditCard class="mr-2 h-4 w-4 text-primary" />
                <span>Update payment</span>
              </Button>
            </Link>
          </div>

          <div
            v-if="!booking.payments.length"
            class="mt-6 rounded-2xl border border-dashed bg-muted/20 p-8 text-center"
          >
            <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-muted text-muted-foreground">
              <Receipt class="h-5 w-5" />
            </div>

            <p class="mt-3 text-sm font-semibold">
              No payment recorded
            </p>

            <p class="mt-1 text-xs text-muted-foreground">
              Continue to payment when you are ready. GCash goes
              through PayMongo. Cash on-site is recorded by the boat operator.
            </p>

            <div class="mt-4">
              <Link
                v-if="
                  booking.booking_status === 'pending' &&
                  !needsPriceReview()
                "
                :href="`/user/bookings/${booking.id}/payment`"
              >
                <Button size="sm" class="rounded-xl">
                  <CreditCard class="mr-2 h-4 w-4" />
                  <span>Submit Payment Now</span>
                </Button>
              </Link>

              <p
                v-else-if="needsPriceReview()"
                class="mt-3 text-xs text-amber-700 dark:text-amber-400"
              >
                The original booking amount must be verified before paying.
              </p>
            </div>
          </div>

          <div v-else class="mt-6 space-y-6">
            <div
              v-if="needsPriceReview()"
              class="rounded-xl border border-amber-500/30 bg-amber-500/5 p-3 text-xs text-amber-700 dark:text-amber-400"
            >
              This is a legacy or missing booking-price snapshot.
              Verify the original quote and recorded payments
              before collecting more money.
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
              <div class="rounded-2xl border bg-background/60 p-4">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                  {{
                    needsPriceReview()
                      ? 'Estimated booking total'
                      : 'Saved booking total'
                  }}
                </p>

                <p class="mt-1 text-lg font-extrabold text-foreground">
                  {{ formatMoney(packageTotal()) }}
                </p>
              </div>

              <div class="rounded-2xl border bg-background/60 p-4">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                  Amount received
                </p>

                <p class="mt-1 text-lg font-extrabold text-foreground">
                  ₱{{ formatPrice(amountReceived()) }}
                </p>
              </div>

              <div class="rounded-2xl border bg-background/60 p-4">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                  Remaining balance
                </p>

                <p class="mt-1 text-lg font-extrabold text-foreground">
                  {{ formatMoney(remainingBalance()) }}
                </p>
              </div>
            </div>

            <div
              v-if="hasOutstandingBalance()"
              class="rounded-2xl border border-border/80 bg-muted/20 p-4 text-xs text-muted-foreground"
            >
              <p class="font-semibold text-foreground">
                Payment policy
              </p>

              <p class="mt-1">
                This older reservation still has a recorded balance.
                New online bookings are paid in full by GCash and do not add a cash balance.
              </p>
            </div>

            <div
              v-if="canReportProblem || props.caseRequest || props.refundRequest"
              class="rounded-2xl border border-border/80 bg-muted/20 p-4 text-xs text-muted-foreground"
              data-test="problem-report-panel"
            >
              <p class="font-semibold text-foreground">
                Report incident / Request refund
              </p>
              <p class="mt-1">
                Eligible only for operator/boat no-show or unsafe weather under the cancellation policy.
                Change of mind is not refundable. A refund request goes to your tour operator for review.
                PayMongo Test Mode never returns real money automatically.
              </p>

              <p v-if="props.refundRequest" class="mt-3 text-foreground" data-test="refund-status">
                Refund status: {{ props.refundRequest.status_label }}
                <span v-if="props.refundRequest.category_label">
                  · {{ props.refundRequest.category_label }}
                </span>
              </p>
              <p v-if="props.refundRequest?.reason" class="mt-1">
                Your details: {{ props.refundRequest.reason }}
              </p>
              <p v-if="props.refundRequest?.rejection_reason" class="mt-1 text-rose-600 dark:text-rose-300">
                Operator note: {{ props.refundRequest.rejection_reason }}
              </p>

              <p v-if="props.caseRequest" class="mt-3 text-foreground">
                Latest reschedule report: {{ props.caseRequest.reason.replaceAll('_', ' ') }}
                · {{ props.caseRequest.status }}
              </p>
              <p v-if="props.caseRequest?.admin_note" class="mt-1">
                {{ props.caseRequest.admin_note }}
              </p>

              <form
                v-if="canReportProblem"
                class="mt-3 space-y-3"
                data-test="problem-report-form"
                @submit.prevent="submitProblemReport"
              >
                <label class="block">
                  What happened
                  <select
                    v-model="problemForm.reason"
                    class="mt-1 h-10 w-full rounded-xl border bg-background px-3 text-sm text-foreground"
                    data-test="problem-category"
                  >
                    <option value="operator_no_show">Operator or boat did not arrive</option>
                    <option value="weather">Unsafe weather cancellation</option>
                  </select>
                </label>
                <label class="block">
                  What do you need
                  <select
                    v-model="problemForm.requested_resolution"
                    class="mt-1 h-10 w-full rounded-xl border bg-background px-3 text-sm text-foreground"
                    data-test="problem-resolution"
                  >
                    <option value="reschedule">Reschedule</option>
                    <option value="refund">Full refund review</option>
                  </select>
                </label>
                <textarea
                  v-model="problemForm.details"
                  required
                  minlength="10"
                  rows="3"
                  class="w-full rounded-xl border bg-background px-3 py-2 text-sm text-foreground"
                  placeholder="Describe what happened"
                  data-test="problem-details"
                />
                <p v-if="problemForm.errors.details" class="text-destructive">
                  {{ problemForm.errors.details }}
                </p>
                <p v-if="problemForm.errors.reason" class="text-destructive">
                  {{ problemForm.errors.reason }}
                </p>
                <Button
                  type="submit"
                  size="sm"
                  :disabled="problemForm.processing"
                  data-test="submit-problem-report"
                >
                  Submit
                </Button>
              </form>
            </div>

            <div
              v-for="payment in booking.payments"
              :key="payment.id"
              class="overflow-hidden rounded-2xl border bg-background/60 backdrop-blur"
            >
              <div class="flex flex-col gap-3 border-b bg-muted/20 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                    Amount
                  </p>

                  <p class="mt-0.5 text-2xl font-extrabold tracking-tight text-foreground">
                    ₱{{ formatPrice(payment.amount) }}
                  </p>
                </div>

                <span
                  class="inline-flex w-fit items-center rounded-full border px-3 py-1 text-xs font-semibold"
                  :class="paymentStatusBadgeClass(payment.payment_status)"
                >
                  {{ statusLabel(payment.payment_status) }}
                </span>
              </div>

              <div class="p-6">
                <div class="space-y-4">
                  <div class="rounded-xl border bg-card/60 p-3.5">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                      Payment Method
                    </p>

                    <p class="mt-1 text-sm font-bold text-foreground">
                      {{ payment.payment_method ?? '—' }}
                    </p>
                  </div>

                  <div class="rounded-xl border bg-card/60 p-3.5">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                      Transaction Reference
                    </p>

                    <p class="mt-1 break-all font-mono text-xs font-bold text-foreground">
                      {{ payment.transaction_reference ?? '—' }}
                    </p>
                  </div>

                  <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-xl border bg-card/60 p-3.5">
                      <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                        Paid At
                      </p>

                      <p class="mt-1 text-xs font-medium text-foreground">
                        {{ formatDateTime(payment.paid_at) }}
                      </p>
                    </div>

                    <div class="rounded-xl border bg-card/60 p-3.5">
                      <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                        Submitted At
                      </p>

                      <p class="mt-1 text-xs font-medium text-foreground">
                        {{ formatDateTime(payment.created_at) }}
                      </p>
                    </div>
                  </div>

                  <div
                    v-if="payment.notes"
                    class="rounded-xl border bg-card/60 p-3.5 text-xs"
                  >
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                      Payment Remarks
                    </p>

                    <p class="mt-1 whitespace-pre-line text-foreground">
                      {{ payment.notes }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section class="rounded-3xl border bg-card/60 p-6 shadow-sm backdrop-blur sm:p-8">
          <h2 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
            Booking Timeline Audit
          </h2>

          <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border bg-background/60 p-4 backdrop-blur">
              <p class="text-xs text-muted-foreground">
                Booking ID
              </p>

              <p class="mt-1 text-sm font-bold text-foreground">
                #{{ booking.id }}
              </p>
            </div>

            <div class="rounded-2xl border bg-background/60 p-4 backdrop-blur">
              <p class="text-xs text-muted-foreground">
                Created Date
              </p>

              <p class="mt-1 text-xs font-medium text-foreground">
                {{ formatDateTime(booking.created_at) }}
              </p>
            </div>

            <div class="rounded-2xl border bg-background/60 p-4 backdrop-blur">
              <p class="text-xs text-muted-foreground">
                Last Updated
              </p>

              <p class="mt-1 text-xs font-medium text-foreground">
                {{ formatDateTime(booking.updated_at) }}
              </p>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>
