# Numan Freight brand color tokens

## Source of truth

The official vector logo in `public/brand/numanfreight-full.svg` uses **sky blue `#2D9CDB`** and **deep navy `#0F294A`**. Use the shared semantic tokens in `resources/css/app.css`; do not repeat logo hex values inside pages.

| Role | Light scheme | Dark scheme | Class / token |
| --- | --- | --- | --- |
| Logo sky | `#2D9CDB` | `#2D9CDB` | `text-brand-sky`, `bg-brand-sky` |
| Logo navy | `#0F294A` | `#0F294A` | `bg-brand-navy` |
| Readable sky-colored text | `#0E6DA5` | `#80C9F0` | `text-brand-sky-strong` |
| Sky-tinted surface | `#EAF6FD` | `#112D42` | `bg-brand-sky-soft` |
| Page | `#F7FAFC` | `#081724` | `bg-background` |
| Card / popover | `#FFFFFF` | `#102536` | `bg-card`, `bg-popover` |
| Primary action | `#0F294A` + white text | `#2D9CDB` + navy text | `bg-primary text-primary-foreground` |
| Focus | `#0E6DA5` | `#79C8F0` | `ring-ring`, `border-ring` |
| Destructive | `#B42318` + white | `#E6665D` + dark text | `bg-destructive text-destructive-foreground` |

## Rules

- Use `Button`, `Badge`, `Card`, `Input`, and the other existing shared UI components. Their semantic classes carry the theme into dashboards, freight listings, forms, and admin.
- Use `text-foreground`, `text-muted-foreground`, `border-border`, `bg-card`, `bg-secondary`, and `bg-accent` for most interface regions.
- Reserve logo sky for a restrained accent, icons, selected states, links, or dark-mode primary actions. In light mode, use `text-brand-sky-strong` for small readable text; raw `text-brand-sky` is not sufficiently contrasted on the light canvas.
- Do not recolor status meanings (warning, success, error) to brand blue. Keep destructive actions semantic.
- Keep the existing compact layouts, sidebar, typography and control sizes. Do not introduce gradients, glow, extra cards or hover movement as part of a color-token change.
- Use the provided white wordmark asset on dark surfaces, never the navy wordmark on dark cards.

## Basic contrast checks

Computed from the WCAG relative-luminance contrast equation:

- Light primary `#0F294A` on white: **14.62:1**
- Dark primary `#2D9CDB` on `#081724`: **5.95:1**
- Light muted foreground `#536678` on white: **5.93:1**
- Dark muted foreground `#B1C3D2` on dark card `#102536`: **8.66:1**
- Light readable sky `#0E6DA5` on `#F7FAFC`: **5.34:1**

These are token-pair calculations, **not** a substitute for browser accessibility acceptance. Before merging, check light/dark buttons and links, freight list and detail, personal dashboard, admin sidebar, forms with invalid fields, focus rings, and mobile headers at narrow widths and 200% zoom. Existing hard-coded page-specific status colors and historical components should be audited separately.
