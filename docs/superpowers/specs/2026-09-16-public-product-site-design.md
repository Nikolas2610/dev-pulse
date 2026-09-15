# DevPulse Public Product Site Design

## Goal

Turn the existing welcome screen into a credible public product website for engineering managers and distributed product teams while preserving the current DevPulse identity.

## Scope

The public site contains four Inertia pages:

- `/` - homepage and primary registration path
- `/product` - the standup workflow from prompt to summary
- `/integrations` - Slack, Discord, Jira, and Bitbucket capabilities for guests; signed-in users retain the existing redirect to `/integrations/slack`
- `/about` - product principles and positioning

Pricing, fabricated customer claims, invented company history, and documentation are outside this release.

## Architecture

A shared `PublicLayout` owns the public navigation, mobile menu, footer, authentication-aware calls to action, and the dark brand canvas. Public-only building blocks live under `resources/js/components/public` and page-specific compositions remain in their page files so the site does not collapse into repeated templates.

The existing authenticated application shell is unchanged. `resources/js/app.tsx` treats all four public page names as layout-free because each page applies `PublicLayout` explicitly.

## Visual System

- Preserve the DevPulse logo and current dark navy, cyan, and lime identity.
- Use `#07131F` as the primary canvas, cyan `#22D3EE` as the sole interactive accent, and lime `#A3E635` only for positive semantic signals.
- Use cold off-white and slate text. Avoid pure black and pure white.
- Use a consistent 12px panel radius, 8px control radius, and compact buttons.
- Keep the existing sans-serif personality and strengthen hierarchy through size, weight, and spacing.
- Lock every public page to the dark theme.

## Page Composition

### Homepage

An asymmetric hero leads with the product outcome and a real product-derived visual. Supporting sections explain the prompt-to-summary flow, blocker visibility, integration coverage, and the registration action. Layout families must vary between sections.

### Product

Explain the daily workflow as four concrete stages: schedule prompts, collect responses, surface blockers, and publish summaries. Connect each stage to actual DevPulse capabilities.

### Integrations

Explain Slack, Discord, Jira, and Bitbucket without implying unsupported capabilities. Communication integrations deliver prompts and summaries; work integrations provide issue and review context.

### About

Describe the product philosophy: fewer status meetings, clearer written context, and earlier blocker visibility. Do not invent founders, offices, funding, customers, or history.

## Interaction and Accessibility

- Navigation remains a single line on desktop and becomes an accessible disclosure menu on mobile.
- CTAs use one consistent intent label: `Start free`. Login remains secondary.
- Signed-in users see `Open dashboard` instead of registration CTAs.
- Motion is limited to CSS entry and reveal transitions that communicate hierarchy. `prefers-reduced-motion` disables them.
- All interactive elements have visible focus states and WCAG AA contrast.
- Mobile layouts below 768px explicitly collapse to one column.

## Content Rules

- No invented metrics, testimonials, customer logos, pricing, or security claims.
- No em-dash or en-dash characters in visible copy.
- Hero copy remains within two headline lines and 20 words of supporting text.
- No generic three-equal-card feature rows, decorative status dots, scroll prompts, version labels, or fake dashboard screenshots.

## Verification

- Feature tests verify all public routes render the intended Inertia components for guests.
- Feature tests verify authenticated visitors can load the public pages.
- TypeScript, ESLint, formatting, PHP tests, and the Vite production build must pass.
- Browser review covers desktop and mobile widths, navigation, both guest and signed-in CTA states where practical, and reduced-motion behavior.
