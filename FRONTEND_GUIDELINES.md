# Tailwind CSS Layout Positioning and Sizing Guide

## 1. Container Positioning

Use Tailwind CSS utility classes to control where a container appears.

### Left Aligned

```html
<div class="max-w-xl">
    ...
</div>
```

The container stays on the left side.

### Centered Left and Right

```html
<div class="mx-auto max-w-xl">
    ...
</div>
```

`mx-auto` automatically adds equal left and right margins, centering the container horizontally.

### Full Width

```html
<div class="w-full">
    ...
</div>
```

The container uses the available width.

---

## 2. Common Width Sizes

Tailwind provides predefined `max-width` values.

| Class       | Approximate Maximum Width |
| ----------- | ------------------------: |
| `max-w-xs`  |             20rem / 320px |
| `max-w-sm`  |             24rem / 384px |
| `max-w-md`  |             28rem / 448px |
| `max-w-lg`  |             32rem / 512px |
| `max-w-xl`  |             36rem / 576px |
| `max-w-2xl` |             42rem / 672px |
| `max-w-3xl` |             48rem / 768px |
| `max-w-4xl` |             56rem / 896px |
| `max-w-5xl` |            64rem / 1024px |
| `max-w-6xl` |            72rem / 1152px |
| `max-w-7xl` |            80rem / 1280px |

### Example

```html
<div class="mx-auto max-w-xl">
    ...
</div>
```

This creates a container that is at most **576px wide** and is centered.

---

## 3. Recommended Form Sizes

For common CRUD pages:

### Small Form

```html
<div class="mx-auto w-full max-w-md">
```

Good for:

* Login
* Password forms
* Simple settings
* Small forms

### Standard CRUD Form

```html
<div class="mx-auto w-full max-w-xl">
```

Good for:

* Create Role
* Create User
* Create Category
* Create Permission

### Large CRUD Form

```html
<div class="mx-auto w-full max-w-2xl">
```

Good for:

* Multiple fields
* Longer descriptions
* More complex forms

### Very Large Form

```html
<div class="mx-auto w-full max-w-4xl">
```

Good for:

* Detailed records
* Multi-section forms
* Large administrative forms

---

## 4. Width vs Maximum Width

### `w-full`

```html
<div class="w-full">
```

The element takes the full available width.

### `max-w-xl`

```html
<div class="max-w-xl">
```

The element cannot grow beyond `576px`.

### Recommended Combination

```html
<div class="w-full max-w-xl">
```

This means:

> Use all available width when necessary, but never become wider than 576px.

For centered layouts:

```html
<div class="mx-auto w-full max-w-xl">
```

This is usually the best choice for CRUD forms.

---

## 5. Horizontal Centering

### Using `mx-auto`

```html
<div class="mx-auto max-w-xl">
    ...
</div>
```

This is the simplest approach.

### Using Flexbox

```html
<div class="flex justify-center">
    <div class="w-full max-w-xl">
        ...
    </div>
</div>
```

`justify-center` centers the child horizontally.

---

## 6. Vertical + Horizontal Centering

To center a form both horizontally and vertically:

```html
<div class="flex min-h-screen items-center justify-center">
    <div class="w-full max-w-xl">
        ...
    </div>
</div>
```

* `flex` → enables Flexbox
* `items-center` → vertical alignment
* `justify-center` → horizontal alignment
* `min-h-screen` → minimum height of the viewport

For an admin page where the layout already has a header/sidebar, avoid `min-h-screen` if it causes the form to be too low.

---

## 7. Padding

Use padding to create space around the content.

```html
<div class="px-4 py-6">
```

* `px-4` → left/right padding
* `py-6` → top/bottom padding

Common sizes:

| Class  | Size |
| ------ | ---: |
| `p-2`  |  8px |
| `p-3`  | 12px |
| `p-4`  | 16px |
| `p-5`  | 20px |
| `p-6`  | 24px |
| `p-8`  | 32px |
| `p-10` | 40px |
| `p-12` | 48px |

---

## 8. Gap Between Elements

Use `gap-*` for spacing between flex/grid children.

```html
<div class="flex flex-col gap-6">
```

Common values:

```text
gap-2   → 8px
gap-3   → 12px
gap-4   → 16px
gap-5   → 20px
gap-6   → 24px
gap-8   → 32px
```

For form fields, `gap-6` is a good default.

---

## 9. CRUD Page Recommended Layout

For a standard Laravel + Vue + Inertia CRUD create page:

```vue
<div class="px-4 py-6">
    <div class="mx-auto w-full max-w-xl">

        <Button
            as-child
            variant="ghost"
            size="sm"
            class="mb-4 -ml-2"
        >
            <Link :href="index()">
                <ArrowLeft class="mr-2 h-4 w-4" />
                Back to Roles
            </Link>
        </Button>

        <Card>
            <CardHeader>
                <Heading
                    title="Create Role"
                    description="Add a new role to the system"
                />
            </CardHeader>

            <CardContent>
                <!-- Form -->
            </CardContent>
        </Card>

    </div>
</div>
```

### Why this is recommended

```text
px-4
 ↓
Adds responsive horizontal padding

py-6
 ↓
Adds vertical spacing

mx-auto
 ↓
Centers the content horizontally

w-full
 ↓
Allows the form to shrink on small screens

max-w-xl
 ↓
Prevents the form from becoming too wide
```

This gives you a **responsive, centered CRUD form** without making it excessively wide.

---

## 10. Quick Reference

### Position

```text
Left                → max-w-xl
Center              → mx-auto max-w-xl
Full width          → w-full
Horizontal center   → flex justify-center
Full center         → flex items-center justify-center
```

### Width

```text
Small               → max-w-md
Standard            → max-w-xl
Large               → max-w-2xl
Very large          → max-w-4xl
Full                → w-full
```

### Recommended CRUD Defaults

```html
<div class="px-4 py-6">
    <div class="mx-auto w-full max-w-xl">
        ...
    </div>
</div>
```

For most Laravel + Vue CRUD **Create/Edit pages**, `w-full max-w-xl mx-auto` is a good starting point. Adjust to `max-w-2xl` when the form contains many fields.

___________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________________


# Frontend Guidelines

## Grid and Column Layout

Use Tailwind CSS Grid utilities to control how form fields and content are arranged into columns.

### 1 Column

Use `grid-cols-1` for a single-column layout.

```html
<div class="grid grid-cols-1 gap-6">
    ...
</div>
```

Best for:

* Simple forms
* Mobile layouts
* Forms with large fields
* Long text areas

---

### 2 Columns

Use `grid-cols-2` for a two-column layout.

```html
<div class="grid grid-cols-2 gap-6">
    ...
</div>
```

Example:

```text
┌──────────────────┬──────────────────┐
│ Full Name        │ Email            │
├──────────────────┼──────────────────┤
│ Role             │ Password         │
└──────────────────┴──────────────────┘
```

---

### 3 Columns

Use `grid-cols-3` for a three-column layout.

```html
<div class="grid grid-cols-3 gap-6">
    ...
</div>
```

Example:

```text
┌──────────────┬──────────────┬──────────────┐
│ Full Name    │ Email        │ Role         │
├──────────────┼──────────────┼──────────────┤
│ Password     │ Confirm Pass │              │
└──────────────┴──────────────┴──────────────┘
```

This is useful for wider desktop forms.

---

### 4 Columns

Use `grid-cols-4` for four-column layouts.

```html
<div class="grid grid-cols-4 gap-6">
    ...
</div>
```

Best for:

* Dashboards
* Statistics
* Small data fields
* Cards
* Compact information

Example:

```text
┌────────┬────────┬────────┬────────┐
│ Name   │ Email  │ Role   │ Status │
└────────┴────────┴────────┴────────┘
```

---

## Responsive Columns

For CRUD forms, avoid using a fixed number of columns on every screen size.

Instead, use responsive breakpoints:

```html
<div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
    ...
</div>
```

This means:

| Screen        | Columns |
| ------------- | ------: |
| Mobile        |       1 |
| Medium (`md`) |       2 |
| Large (`lg`)  |       3 |

This is the recommended approach for most forms.

---

## Recommended CRUD Form Layout

For a form such as **Create User**:

```text
Desktop

┌───────────────────────────────────────────────┐
│ Create User                                   │
│ Add a new user to the system                  │
│                                               │
│ Full Name       │ Email          │ Role       │
│                 │                │            │
│ Password        │ Confirm Pass   │             │
│                                               │
│                          Cancel   Create User │
└───────────────────────────────────────────────┘
```

Use:

```html
<div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
    ...
</div>
```

### Example

```vue
<div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">

    <div class="grid gap-2">
        <Label for="name">Full Name</Label>
        <Input
            id="name"
            name="name"
            placeholder="Juan Dela Cruz"
        />
    </div>

    <div class="grid gap-2">
        <Label for="email">Email</Label>
        <Input
            id="email"
            name="email"
            type="email"
            placeholder="email@example.com"
        />
    </div>

    <div class="grid gap-2">
        <Label for="role">Select a Role</Label>
        <!-- Select -->
    </div>

    <div class="grid gap-2">
        <Label for="password">Password</Label>
        <Input
            id="password"
            name="password"
            type="password"
        />
    </div>

    <div class="grid gap-2">
        <Label for="password_confirmation">
            Confirm Password
        </Label>
        <Input
            id="password_confirmation"
            name="password_confirmation"
            type="password"
        />
    </div>

</div>
```

---

## Column Spanning

Sometimes a field should take more than one column.

Use `col-span-*`.

### Span 2 Columns

```html
<div class="col-span-2">
    ...
</div>
```

### Span 3 Columns

```html
<div class="col-span-3">
    ...
</div>
```

Example:

```html
<div class="grid grid-cols-3 gap-6">

    <div>
        Full Name
    </div>

    <div>
        Email
    </div>

    <div>
        Role
    </div>

    <div class="col-span-2">
        Special Request
    </div>

</div>
```

Result:

```text
┌────────────┬────────────┬────────────┐
│ Full Name  │ Email      │ Role       │
├────────────┴────────────┼────────────┤
│ Special Request          │            │
└──────────────────────────┴────────────┘
```

---

## Responsive Column Spanning

Column spans can also change based on screen size.

```html
<div class="col-span-1 md:col-span-2 lg:col-span-3">
    ...
</div>
```

Meaning:

```text
Mobile       → 1 column
Medium       → 2 columns
Large        → 3 columns
```

This is useful for:

* Description
* Address
* Notes
* Special requests
* Textareas

---

## Grid Gap

Use `gap-*` to control spacing between columns and rows.

```html
<div class="grid grid-cols-3 gap-6">
```

Common values:

| Class   | Size |
| ------- | ---: |
| `gap-2` |  8px |
| `gap-3` | 12px |
| `gap-4` | 16px |
| `gap-5` | 20px |
| `gap-6` | 24px |
| `gap-8` | 32px |

### Recommended

For forms:

```html
gap-6
```

For compact layouts:

```html
gap-4
```

---

## Common Grid Patterns

### Standard CRUD

```html
<div class="grid grid-cols-1 gap-6 md:grid-cols-2">
```

Use for most create/edit forms.

### Wide CRUD

```html
<div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
```

Use when there are many fields.

### Dashboard Cards

```html
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
```

Use for statistics and summary cards.

### Large Data Layout

```html
<div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
```

Use when the page has multiple sections or panels.

---

## Recommended Breakpoint Pattern

For most project forms, use:

```text
grid-cols-1
      ↓
    sm:grid-cols-2
      ↓
    lg:grid-cols-3
```

Example:

```html
<div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
```

This provides:

```text
Mobile
┌───────────────┐
│ Field         │
├───────────────┤
│ Field         │
├───────────────┤
│ Field         │
└───────────────┘

Tablet
┌───────────┬───────────┐
│ Field     │ Field     │
├───────────┼───────────┤
│ Field     │ Field     │
└───────────┴───────────┘

Desktop
┌─────────┬─────────┬─────────┐
│ Field   │ Field   │ Field   │
├─────────┼─────────┼─────────┤
│ Field   │ Field   │ Field   │
└─────────┴─────────┴─────────┘
```

## Quick Reference

| Layout     | Tailwind       |
| ---------- | -------------- |
| 1 column   | `grid-cols-1`  |
| 2 columns  | `grid-cols-2`  |
| 3 columns  | `grid-cols-3`  |
| 4 columns  | `grid-cols-4`  |
| 5 columns  | `grid-cols-5`  |
| 6 columns  | `grid-cols-6`  |
| 12 columns | `grid-cols-12` |

### Recommended CRUD Default

```html
<div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
```

### Recommended Dashboard Default

```html
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
```

### Recommended Large Field

```html
<div class="col-span-full">
```

Use `col-span-full` when a field should occupy the entire grid width.

For example:

```html
<div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">

    <div>
        Full Name
    </div>

    <div>
        Email
    </div>

    <div>
        Role
    </div>

    <div class="col-span-full">
        Description
    </div>

</div>
```


