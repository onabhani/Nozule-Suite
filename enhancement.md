# Nozule — Feature Roadmap & Enhancement Checklist

## Phase 1 — Core PMS (Syria Launch)

- [x] **NZL-001** — Housekeeping Module
  - Room status workflow (dirty / clean / inspected / out-of-order)
  - Task assignment to staff
  - Auto-set dirty on checkout
  - Priority levels and notes
  - Admin dashboard with filterable task list

- [x] **NZL-002** — Night Audit
  - One-click daily close
  - Post room charges to folios
  - Reconcile daily totals
  - Generate daily summary report
  - Lock previous day from edits

- [x] **NZL-003** — Invoice / Folio Generation
  - Multiple configurable tax types (name, name_ar, rate, percentage/fixed)
  - Folio per stay with line items (room charges, extras, taxes)
  - Bilingual Arabic/English invoice
  - Print / PDF export
  - QR code support (future ZATCA readiness)

- [x] **NZL-004** — Check-in / Check-out Workflow
  - Front desk check-in flow with room assignment
  - Guest ID / passport collection and storage
  - Digital registration card
  - Checkout with balance settlement
  - Auto-create folio on check-in
  - Integration with housekeeping (auto-dirty on checkout)

- [x] **NZL-005** — Group Bookings
  - Block allocation for tour groups
  - Rooming list management
  - Group folio with shared/split billing
  - Bulk check-in / check-out
  - Agency / tour operator tracking

- [ ] **NZL-006** — Promo Codes / Discounts
  - Coupon codes for direct booking engine
  - Percentage / fixed amount discounts
  - Date-range validity
  - Usage limits

- [ ] **NZL-007** — Guest Messaging (Email)
  - Automated confirmation emails
  - Pre-arrival information emails
  - Post-checkout thank-you emails
  - Customizable email templates

- [ ] **NZL-008** — Multi-Currency Support
  - SYP primary + USD support
  - Auto-conversion rates
  - Currency per booking
  - Exchange rate management

- [ ] **NZL-009** — Basic Payment Gateway
  - Stripe integration for online bookings
  - PayPal integration
  - Secure card tokenization

- [ ] **NZL-010** — Guest ID / Passport Storage
  - Scan / upload ID documents
  - Nationality tracking
  - Passport number and expiry fields
  - Document type classification

- [ ] **NZL-011** — Maintenance Work Orders
  - Ticketing system for room repairs
  - Assign to maintenance staff
  - Track status (open / in-progress / resolved)
  - Link to room and priority levels

- [ ] **NZL-012** — Mobile PWA
  - Progressive web app for front desk staff
  - Check-in / check-out on mobile
  - Housekeeping status updates
  - New booking push notifications

---

## Phase 2 — Channel & Revenue (Syria + GCC Expansion)

- [ ] **NZL-013** — OTA Channel Sync (Booking.com)
  - Two-way API sync for rates, availability, reservations
  - Real-time inventory updates
  - Reservation pull and status sync

- [ ] **NZL-014** — OTA Channel Sync (Agoda / Expedia)
  - Expand to additional OTAs
  - Unified channel dashboard

- [ ] **NZL-015** — Dynamic Pricing Engine
  - Auto-adjust rates by occupancy thresholds
  - Day-of-week pricing rules
  - Event-based rate overrides

- [ ] **NZL-016** — Metasearch (Google Hotel Ads)
  - Free booking links on Google Maps
  - Paid CPC campaign support

- [ ] **NZL-017** — Rate Restrictions
  - Min / max stay rules
  - Closed-to-arrival (CTA)
  - Closed-to-departure (CTD)
  - Stop-sell per channel

- [ ] **NZL-018** — Tour Operator Module
  - Contract rates with date ranges
  - Allotments with release dates
  - Commission tracking
  - Operator portal / login

- [ ] **NZL-019** — Multi-Property Support
  - Central dashboard for all properties
  - Property ID in all tables
  - Cross-property guest profiles
  - Consolidated reporting

- [ ] **NZL-020** — Review Solicitation
  - Post-checkout automated review requests
  - Google / TripAdvisor integration
  - Reputation dashboard

- [ ] **NZL-021** — GCC Payment Gateways
  - PayTabs integration
  - Geidea integration
  - Amazon Payment Services
  - Apple Pay / STC Pay

- [ ] **NZL-022** — Hijri Calendar
  - Display Hijri dates alongside Gregorian
  - Hijri date in reports and invoices
  - Guest-facing Hijri support

- [ ] **NZL-023** — WhatsApp Messaging
  - Automated WhatsApp messages via Business API
  - Pre-arrival, in-stay, and checkout notifications
  - Template message management

- [ ] **NZL-024** — Contactless Check-in
  - Digital guest registration before arrival
  - ID / passport upload via link
  - Room selection and preferences
  - Digital signature

---

## Phase 3 — KSA Compliance & Enterprise

- [ ] **NZL-025** — ZATCA E-Invoicing (Fatoorah)
  - Phase 2 compliance
  - B2B real-time clearance via API
  - B2C reporting within 24 hours
  - XML format with electronic signature
  - QR code on all invoices

- [ ] **NZL-026** — Shomoos Integration
  - Auto-transmit guest identity data to Saudi security on check-in
  - Mandatory per Royal Decree

- [ ] **NZL-027** — NTMP Reporting
  - Auto-post occupancy and booking data to Saudi Ministry of Tourism

- [ ] **NZL-028** — Mada Payment Support
  - Saudi debit card network integration
  - Card-present transaction support

- [ ] **NZL-029** — Nusuk Masar Integration
  - Pilgrim accommodation documentation
  - Hajj / Umrah visa issuance support

- [ ] **NZL-030** — Balady Municipal Integration
  - Municipality compliance reporting

- [ ] **NZL-031** — Hajj / Umrah Season Management
  - Extreme seasonality pricing (5–10x normal)
  - Zone-based pricing (Makkah zones)
  - Full-stay mandatory booking enforcement

- [ ] **NZL-032** — VAT 15% Compliance
  - Tax-inclusive display pricing
  - Fraction approximation
  - Arabic-mandatory invoices with QR

---

## Phase 4 — Market Leadership

- [ ] **NZL-033** — AI Demand Forecasting
  - Predict pricing based on events and seasonality
  - Historical data analysis
  - Machine learning model

- [ ] **NZL-034** — GDS Connectivity
  - Amadeus / Sabre integration
  - Corporate travel agent bookings

- [ ] **NZL-035** — Digital Room Keys
  - Mobile key via Bluetooth
  - Compatible door lock integration

- [ ] **NZL-036** — Loyalty Program
  - Tiers, points, rewards
  - Cross-property redemption
  - Member-only rates

- [ ] **NZL-037** — POS Integration
  - Restaurant, minibar, spa charges
  - Post charges to room folio
  - Departmental revenue tracking

- [ ] **NZL-038** — Staff Scheduling
  - Shift management
  - Labor cost tracking
  - Overtime alerts

- [ ] **NZL-039** — Competitive Rate Shopping
  - Monitor competitor pricing on OTAs
  - Rate parity alerts
  - Market positioning dashboard

- [ ] **NZL-040** — Open Developer Portal
  - API documentation site
  - OAuth app registration
  - Webhook marketplace
  - Third-party plugin system

- [ ] **NZL-041** — White-Label / Multi-Brand
  - Customizable branding per property chain
  - Brand standard enforcement
  - Custom domain support

- [ ] **NZL-042** — Sustainability Tracking
  - Energy / resource consumption per room
  - Carbon footprint reporting
  - Aligned with Vision 2030 goals
