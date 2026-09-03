---
name: Kios Nizam
colors:
    surface: "#fcf9f8"
    surface-dim: "#dcd9d9"
    surface-bright: "#fcf9f8"
    surface-container-lowest: "#ffffff"
    surface-container-low: "#f6f3f2"
    surface-container: "#f0edec"
    surface-container-high: "#ebe7e7"
    surface-container-highest: "#e5e2e1"
    on-surface: "#1c1b1b"
    on-surface-variant: "#3f4943"
    inverse-surface: "#313030"
    inverse-on-surface: "#f3f0ef"
    outline: "#6f7973"
    outline-variant: "#bfc9c1"
    surface-tint: "#1d6b4f"
    primary: "#005239"
    on-primary: "#ffffff"
    primary-container: "#1e6b4f"
    on-primary-container: "#9ee9c5"
    inverse-primary: "#8cd6b3"
    secondary: "#8e4e14"
    on-secondary: "#ffffff"
    secondary-container: "#ffab69"
    on-secondary-container: "#783d01"
    tertiary: "#005148"
    on-tertiary: "#ffffff"
    tertiary-container: "#006b60"
    on-tertiary-container: "#83ebdb"
    error: "#ba1a1a"
    on-error: "#ffffff"
    error-container: "#ffdad6"
    on-error-container: "#93000a"
    primary-fixed: "#a7f2ce"
    primary-fixed-dim: "#8cd6b3"
    on-primary-fixed: "#002115"
    on-primary-fixed-variant: "#005139"
    secondary-fixed: "#ffdcc4"
    secondary-fixed-dim: "#ffb780"
    on-secondary-fixed: "#2f1400"
    on-secondary-fixed-variant: "#6f3800"
    tertiary-fixed: "#8cf5e4"
    tertiary-fixed-dim: "#6fd8c8"
    on-tertiary-fixed: "#00201c"
    on-tertiary-fixed-variant: "#005048"
    background: "#fcf9f8"
    on-background: "#1c1b1b"
    surface-variant: "#e5e2e1"
typography:
    display-lg:
        fontFamily: Hanken Grotesk
        fontSize: 32px
        fontWeight: "700"
        lineHeight: 40px
        letterSpacing: -0.02em
    headline-md:
        fontFamily: Hanken Grotesk
        fontSize: 24px
        fontWeight: "600"
        lineHeight: 32px
    headline-sm:
        fontFamily: Hanken Grotesk
        fontSize: 18px
        fontWeight: "600"
        lineHeight: 24px
    body-lg:
        fontFamily: Hanken Grotesk
        fontSize: 16px
        fontWeight: "400"
        lineHeight: 24px
    body-md:
        fontFamily: Hanken Grotesk
        fontSize: 14px
        fontWeight: "400"
        lineHeight: 20px
    label-caps:
        fontFamily: Hanken Grotesk
        fontSize: 12px
        fontWeight: "600"
        lineHeight: 16px
        letterSpacing: 0.05em
    data-mono:
        fontFamily: JetBrains Mono
        fontSize: 13px
        fontWeight: "500"
        lineHeight: 18px
rounded:
    sm: 0.125rem
    DEFAULT: 0.25rem
    md: 0.375rem
    lg: 0.5rem
    xl: 0.75rem
    full: 9999px
spacing:
    sidebar-width: 260px
    header-height: 72px
    gutter: 24px
    container-padding: 32px
    card-gap: 20px
    stack-sm: 8px
    stack-md: 16px
---

## Brand & Style

The design system is engineered for efficiency, reliability, and professional authority in the retail and service sector. The brand personality is grounded and sophisticated, utilizing a deep forest green to evoke stability and growth.

We utilize a **Modern Corporate** style with a focus on high-information density and extreme legibility. The aesthetic is characterized by:

- **Precision:** Mathematical spacing and strict alignment to a 4px baseline grid.
- **Clarity:** A card-based architecture that creates distinct physical boundaries between data sets (metrics, tables, and actions).
- **Subtlety:** Using tonal shifts rather than heavy lines to separate sections, ensuring the interface remains lightweight and fast-feeling.
- **Trust:** A high-contrast color palette that provides immediate visual feedback and meets accessibility standards for long-duration usage.

## Colors

The palette is anchored by a sophisticated **#1E6B4F Deep Emerald**, representing the brand's core identity.

- **Primary (#1E6B4F):** Used for primary actions, active navigation states, and brand-defining accents.
- **Secondary (#F4A261):** A warm amber reserved for warnings, low-stock alerts, and urgent pending tasks to provide high visual contrast against the green.
- **Success/Tertiary (#2A9D8F):** A lighter teal for positive growth metrics and completed transaction states.
- **Neutral/Background:** We use a cool-toned off-white (#F8FAF9) for the main dashboard area to reduce eye strain, while using pure white (#FFFFFF) for cards to create a slight elevation "lift."
- **Typography:** Deep Charcoal (#121212) is used for headings to ensure maximum contrast, while medium grays are used for secondary labels.

## Typography

The design system employs **Hanken Grotesk** as the primary typeface for its sharp, contemporary geometry and professional clarity.

- **Hierarchy:** Large display sizes are used for monetary values (e.g., total sales), ensuring they are legible from a distance.
- **Data Display:** **JetBrains Mono** is introduced specifically for tabular data, SKU numbers, and stock counts to ensure vertical alignment of digits, making price lists and inventory counts easier to scan.
- **Responsive Scaling:** On mobile devices, `display-lg` should scale down to 24px (`headline-md`) to ensure metrics do not overflow card containers.

## Layout & Spacing

This design system uses a **Fixed-Fluid Hybrid** layout.

- **Sidebar:** A fixed 260px left-hand navigation sidebar that persists across the application.
- **Main Canvas:** A fluid content area with a maximum inner container width of 1440px to prevent excessive line lengths on ultra-wide monitors.
- **Grid:** A 12-column grid system is used within the main canvas. Metric cards typically span 3 columns (4 per row), while primary tables span all 12.
- **Breakpoints:**
    - **Desktop (1024px+):** 12 columns, 32px margins.
    - **Tablet (768px - 1023px):** 6 columns, 24px margins. Sidebar collapses to an icon-only "rail" (72px).
    - **Mobile (<767px):** 4 columns, 16px margins. Sidebar becomes a hidden drawer menu.

## Elevation & Depth

To maintain a clean and modern professional aesthetic, the design system avoids heavy shadows in favor of **Tonal Layering** and **Ghost Borders**.

1. **Level 0 (Background):** The `#F8FAF9` surface is the lowest layer.
2. **Level 1 (Cards & Sidebar):** Pure white surfaces with a subtle `1px` stroke in `#E2E8F0` (light gray). A very soft, highly diffused shadow (0px 4px 12px rgba(0,0,0,0.03)) is applied to cards to provide separation from the background.
3. **Level 2 (Modals & Popovers):** Higher elevation with a more pronounced shadow (0px 12px 24px rgba(0,0,0,0.08)) and a semi-transparent backdrop blur (8px) to focus user attention.
4. **Interactive States:** Buttons and clickable cards shift slightly in tone (darken 5%) rather than moving "up" or "down" in Z-space, maintaining a flat, professional feel.

## Shapes

The shape language is **Soft (0.25rem)**. This provides a balance between the precision of a professional tool and the approachability of a modern SaaS product.

- **Standard Elements:** Buttons, input fields, and small tags use `rounded` (4px).
- **Containers:** Large dashboard cards and the sidebar use `rounded-lg` (8px) to soften the overall layout.
- **Special Elements:** Search bars and "Open Cashier" buttons may use `rounded-xl` (12px) to signify a distinct call-to-action or a global utility.

## Components

### Buttons

- **Primary:** Solid `#1E6B4F` with white text. High contrast, bold weight.
- **Secondary/Outline:** Bordered with `#1E6B4F`, transparent background.
- **Alert:** Solid `#F4A261` with dark text for critical warnings.

### Dashboard Cards

- Metric cards must feature a `label-caps` title at the top, a `display-lg` value in the center, and an optional "trend" indicator (up/down arrow) at the bottom.
- Backgrounds should be pure white unless highlighting a critical state (e.g., a "Low Stock" card might have a very pale amber tint).

### Data Tables

- Header rows use a light gray background (`#F1F5F9`) with `label-caps` typography.
- Row hover states use a subtle green tint (`#F0F7F4`) to help the eye track across long lines.
- Numerical columns (Stock, Price) must be right-aligned and use the `data-mono` font.

### Sidebar Navigation

- Icons should be 20px, outlined style.
- The active state is indicated by a vertical 4px "pill" on the left edge and a text color change to the primary green.

### Input Fields

- Use a subtle light gray border that thickens and changes to the primary green on focus.
- Labels are always positioned above the field for clarity in data-entry-heavy workflows.
