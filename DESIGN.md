---
name: BetterAuth Demo
description: Calm registration surface for application-owned authentication.
colors:
  graphite: "#2b2c31"
  surface: "#f8f8fb"
  surface-dark: "#292a30"
  violet: "#7550c9"
  violet-deep: "#5635a5"
  success: "#247a52"
  danger: "#b43a4a"
typography:
  body:
    fontFamily: "ui-sans-serif, system-ui, sans-serif"
    fontSize: "16px"
    fontWeight: 400
    lineHeight: 1.5
    letterSpacing: "normal"
rounded: { sm: "6px", md: "10px", lg: "16px" }
spacing: { sm: "8px", md: "16px", lg: "24px", xl: "40px" }
components:
  button-primary: { backgroundColor: "{colors.violet}", textColor: "#ffffff", rounded: "{rounded.sm}", padding: "12px 16px" }
  input: { backgroundColor: "{colors.surface}", textColor: "{colors.graphite}", rounded: "{rounded.sm}", padding: "10px 12px" }
---

## Overview

**Creative North Star: "Quiet technical confidence."** A developer registers in
daylight on a laptop or phone, checks a concrete contract, and moves on. The
interface is compact, legible, and deliberately absent of dashboard theatre.

**Key Characteristics:** graphite ground, violet reserved for decisions, native
technical sans, and only state-explaining movement.

## Colors

Graphite neutrals carry almost all surface area; violet is limited to primary
actions, focus, and selected state. Canonical values use OKLCH in implementation
(`oklch(21% .01 270)` graphite; `oklch(55% .19 295)` violet).

**The Ten Percent Rule.** Do use violet only for action and state. Do not use it
as a decorative field, gradient, or dashboard accent.

## Typography

Use the technical system sans stack. Product labels stay at a stable rem scale;
reading copy remains under 70ch. Headings earn their weight through density,
not a display face.

## Elevation

Use one low, neutral shadow only to separate the registration task from the
page. Borders remain one pixel and quiet. No glass, glow, or colored side stripe.

## Components

Buttons and inputs are at least 44px high, have visible violet focus, and work
without hover. Mobile starts as one column; 768px introduces breathing room,
1024px permits the two-column product layout. Safe-area padding and reduced
motion are always active.

## Do's and Don'ts

### Do:
- **Do** use graphite neutrals and `#7550c9` only for decisions and focus.
- **Do** make failure copy specific, generic where account enumeration matters.
- **Do** preserve keyboard order, contrast, and 44px touch targets.

### Don't:
- **Don't** build a fake terminal or decorative SaaS dashboard.
- **Don't** use purple gradients, glass surfaces, hero metrics, or colored side stripes.
- **Don't** animate layout or ignore reduced-motion preferences.
