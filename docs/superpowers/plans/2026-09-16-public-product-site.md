# DevPulse Public Product Site Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a premium four-page public product website for DevPulse while preserving the current brand and authenticated application.

**Architecture:** Add named Laravel routes that render four focused Inertia pages. A shared React `PublicLayout` and a small set of public marketing components provide navigation, footer, CTA behavior, visual tokens, and responsive behavior without changing the authenticated application shell.

**Tech Stack:** Laravel 13, Inertia 3, React 19, TypeScript, Tailwind CSS 4, PHPUnit

**Spec:** `docs/superpowers/specs/2026-09-16-public-product-site-design.md`

## Global Constraints

- Preserve the existing DevPulse logo and navy, cyan, and lime identity.
- Use cyan `#22D3EE` as the interactive accent and lime `#A3E635` only for positive semantic signals.
- Keep every public page dark and responsive with an explicit single-column mobile fallback below 768px.
- Use `Start free` for registration intent and `Open dashboard` for signed-in users.
- Do not add pricing, customer claims, invented metrics, fabricated history, or unsupported integration claims.
- Visible copy contains zero em-dash or en-dash characters.
- Respect `prefers-reduced-motion` and visible keyboard focus.

---

### Task 1: Register and verify the public routes

**Files:**
- Create: `tests/Feature/PublicSiteTest.php`
- Modify: `routes/web.php`

**Interfaces:**
- Produces named routes `home`, `product`, `integrations.public`, and `about`.

- [ ] **Step 1: Write the failing route tests**

Add PHPUnit cases that request `/`, `/product`, `/integrations`, and `/about`, assert HTTP 200, and assert the corresponding Inertia components `welcome`, `public/product`, `public/integrations`, and `public/about`.

- [ ] **Step 2: Run the focused test and verify RED**

Run: `php artisan test tests/Feature/PublicSiteTest.php`

Expected: the three new public routes fail with 404 responses.

- [ ] **Step 3: Add the named routes**

Register the three supporting `Route::inertia` definitions next to the existing home route.

- [ ] **Step 4: Run the focused test and verify GREEN**

Run: `php artisan test tests/Feature/PublicSiteTest.php`

Expected: all public route tests pass.

### Task 2: Build the shared public shell and homepage

**Files:**
- Create: `resources/js/components/public/public-layout.tsx`
- Create: `resources/js/components/public/public-logo.tsx`
- Create: `resources/js/components/public/pulse-visual.tsx`
- Modify: `resources/js/pages/welcome.tsx`
- Modify: `resources/js/app.tsx`
- Modify: `resources/css/app.css`

**Interfaces:**
- `PublicLayout({ children, current, authUser })` renders shared public navigation and footer.
- `PulseVisual()` renders a product-derived prompt-to-summary illustration using semantic HTML and CSS.

- [ ] **Step 1: Extend the public route test with homepage landmarks**

Assert that the home response remains successful for guests and authenticated users. The server-level test protects public access while the production build will verify client component contracts.

- [ ] **Step 2: Implement the shared public layout**

Add accessible desktop and mobile navigation, authentication-aware CTA links, the existing brand mark, footer navigation, and visible focus styles.

- [ ] **Step 3: Replace the welcome page composition**

Build the asymmetric hero, workflow proof, blocker narrative, integrations bridge, and final CTA with varied section layouts and concise product-accurate copy.

- [ ] **Step 4: Add public design tokens and motion safeguards**

Add scoped public utility classes, responsive behavior, entry motion, and `prefers-reduced-motion` overrides to `app.css`.

- [ ] **Step 5: Verify types and build**

Run: `npm.cmd run types:check`

Run: `npm.cmd run build`

Expected: both commands exit 0.

### Task 3: Build Product, Integrations, and About pages

**Files:**
- Create: `resources/js/pages/public/product.tsx`
- Create: `resources/js/pages/public/integrations.tsx`
- Create: `resources/js/pages/public/about.tsx`

**Interfaces:**
- Each page consumes `PublicLayout` and the shared authentication page prop.

- [ ] **Step 1: Implement Product**

Compose a compact hero, four-stage workflow, manager outcome section, and final CTA. Use distinct section layouts and actual DevPulse capabilities.

- [ ] **Step 2: Implement Integrations**

Separate communication and work-context integrations. Describe only supported Slack, Discord, Jira, and Bitbucket behavior.

- [ ] **Step 3: Implement About**

Explain the product principles and product boundaries without fabricated company details.

- [ ] **Step 4: Verify routes, types, and build**

Run: `php artisan test tests/Feature/PublicSiteTest.php`

Run: `npm.cmd run types:check`

Run: `npm.cmd run build`

Expected: all commands exit 0.

### Task 4: Browser review and final verification

**Files:**
- Modify only files with issues found during review.

**Interfaces:**
- No new public interfaces.

- [ ] **Step 1: Run formatting and lint checks**

Run: `npm.cmd run format:check`

Run: `npm.cmd run lint:check`

Expected: both commands exit 0 without modifying files.

- [ ] **Step 2: Review in a real browser**

Open all four pages at desktop and mobile widths. Check navigation, CTA contrast, wrapping, overflow, section rhythm, focus states, and reduced motion.

- [ ] **Step 3: Run the complete verification suite**

Run: `npm.cmd run types:check`

Run: `npm.cmd run build`

Run: `php artisan test`

Expected: all commands exit 0.

