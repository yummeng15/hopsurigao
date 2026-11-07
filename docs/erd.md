# Island Hopping & Tour Reservation System — Database ERD

This document details the Entity Relationship Diagram (ERD) and relational schema for the **Island Hopping Reservation System**.

> [!TIP]
> The visual diagram file is saved in Draw.io format at:
> - [`erd.drawio`](file:///d:/www/island-hopping-reservation-system/erd.drawio) (Project Root)
> - [`docs/erd.drawio`](file:///d:/www/island-hopping-reservation-system/docs/erd.drawio)
> 
> You can open either file in [draw.io / diagrams.net](https://app.diagrams.net/) or in VS Code with the Draw.io Integration extension.

---

## 1. Mermaid Entity-Relationship Diagram

```mermaid
erDiagram
    ROLES ||--o{ PERMISSION_ROLE : "has"
    PERMISSIONS ||--o{ PERMISSION_ROLE : "assigned to"
    ROLES ||--o{ USERS : "defines role for"
    USERS ||--o{ NOTIFICATIONS : "receives"
    USERS ||--o{ BOOKINGS : "places"

    PACKAGES ||--o{ INCLUSIONS : "contains"
    PACKAGES ||--o{ REMINDERS : "contains"
    PACKAGES ||--o{ TOUR_DATES : "scheduled on"

    TOUR_DATES ||--o{ TIMES : "has time slots"
    TOUR_DATES ||--|| WEATHER_FORECASTS : "has forecast for"
    TOUR_DATES ||--o{ PICKUP_SCHEDULES : "has pickup options"
    PICKUP_LOCATIONS ||--o{ PICKUP_SCHEDULES : "assigned to"

    TOUR_DATES ||--o{ BOOKINGS : "booked on"
    PICKUP_SCHEDULES ||--o{ BOOKINGS : "pickup selected in"
    BOOKINGS ||--o{ PAYMENTS : "paid via"

    ROLES {
        bigint id PK
        string name
        text description
        timestamp created_at
        timestamp updated_at
    }

    PERMISSIONS {
        bigint id PK
        string name
        text description
        timestamp created_at
        timestamp updated_at
    }

    PERMISSION_ROLE {
        bigint id PK
        bigint role_id FK
        bigint permission_id FK
        timestamp created_at
        timestamp updated_at
    }

    USERS {
        bigint id PK
        bigint role_id FK
        string name
        string email UK
        timestamp email_verified_at
        string password
        text two_factor_secret
        text two_factor_recovery_codes
        timestamp two_factor_confirmed_at
        boolean is_protected
        string remember_token
        timestamp created_at
        timestamp updated_at
    }

    PACKAGES {
        bigint id PK
        string package_name
        string destination
        decimal latitude
        decimal longitude
        text description
        decimal price
        enum status "active, inactive"
        timestamp created_at
        timestamp updated_at
    }

    INCLUSIONS {
        bigint id PK
        bigint package_id FK
        text description
        timestamp created_at
        timestamp updated_at
    }

    REMINDERS {
        bigint id PK
        bigint package_id FK
        text description
        timestamp created_at
        timestamp updated_at
    }

    TOUR_DATES {
        bigint id PK
        bigint package_id FK
        date tour_date
        unsigned_int capacity
        timestamp created_at
        timestamp updated_at
    }

    TIMES {
        bigint id PK
        bigint tour_date_id FK
        time time
        string description
        timestamp created_at
        timestamp updated_at
    }

    WEATHER_FORECASTS {
        bigint id PK
        bigint tour_date_id FK
        date forecast_date
        string location
        decimal latitude
        decimal longitude
        decimal temperature_min
        decimal temperature_max
        decimal temperature_current
        string weather_condition
        int weather_code
        int rainfall_probability
        decimal rainfall
        decimal wind_speed
        decimal wind_gusts
        int wind_direction
        int humidity
        enum advisory_level "green, yellow, orange, red"
        string advisory_badge
        text advisory_message
        boolean is_outdated
        timestamp fetched_at
        timestamp created_at
        timestamp updated_at
    }

    PICKUP_LOCATIONS {
        bigint id PK
        string name
        string address
        text description
        enum status "active, inactive"
        timestamp created_at
        timestamp updated_at
    }

    PICKUP_SCHEDULES {
        bigint id PK
        bigint tour_date_id FK
        bigint pickup_location_id FK
        time pickup_time
        timestamp created_at
        timestamp updated_at
    }

    BOOKINGS {
        bigint id PK
        bigint user_id FK
        bigint tour_date_id FK
        bigint pickup_schedule_id FK
        unsigned_int number_of_guests
        string phone_number
        string nationality
        text special_request
        enum booking_status "pending, confirmed, cancelled, completed"
        string qr_token UK
        boolean is_archived
        timestamp archived_at
        timestamp created_at
        timestamp updated_at
    }

    PAYMENTS {
        bigint id PK
        bigint booking_id FK
        decimal amount
        string payment_method
        enum payment_status "pending, paid, failed, refunded, cancelled"
        string transaction_reference
        text notes
        timestamp paid_at
        timestamp created_at
        timestamp updated_at
    }

    NOTIFICATIONS {
        uuid id PK
        string type
        string notifiable_type
        unsigned_bigint notifiable_id
        text data
        timestamp read_at
        timestamp created_at
        timestamp updated_at
    }
```

---

## 2. Table Schemas & Relational Matrix

### A. Access Control & Users
1. **`roles`**: System roles (`Superadmin`, `Admin`, `User`).
2. **`permissions`**: Granular system permissions.
3. **`permission_role`**: Many-to-Many pivot mapping between roles and permissions.
4. **`users`**: Customer, operator, and administrator accounts.

### B. Tour Catalog
5. **`packages`**: Island hopping tour packages (price, description, GPS coordinates, status).
6. **`inclusions`**: Itemized tour inclusions per package.
7. **`reminders`**: Safety and preparation guidelines per package.

### C. Schedule, Logistics & Weather Intelligence
8. **`tour_dates`**: Specific dates when packages operate with maximum guest capacity.
9. **`times`**: Daily departure and activity times for scheduled tour dates.
10. **`weather_forecasts`**: Daily real-time Open-Meteo marine/weather forecasts and advisory risk ratings (`green`, `yellow`, `orange`, `red`).
11. **`pickup_locations`**: Master list of tourist pickup points/ports.
12. **`pickup_schedules`**: Tour-date specific pickup times assigned to pickup locations.

### D. Reservations, Payments & Archives
13. **`bookings`**: Tourist reservations with party size, contact info, status (`pending`, `confirmed`, `cancelled`, `completed`), digital QR pass token, and archival fields (`is_archived`, `archived_at`).
14. **`payments`**: Transaction records, payment methods, proofs of payment, and status (`pending`, `paid`, `cancelled`, etc.).
15. **`notifications`**: System notifications for reservations, confirmations, weather alerts, and cancellations.

