# Nozule — Feature Roadmap

> Last updated: 2026-02-20 | Plugin version: 1.2.0

## Status Legend

| Symbol | Meaning |
|--------|---------|
| Done | Implemented and released |
| In Progress | Currently being implemented |
| Planned | Scheduled for implementation |
| Future | Not yet scheduled |

---

## Phase 1 — Core PMS (Syria Launch)

| Ref | Feature | Status | Notes |
|-----|---------|--------|-------|
| NZL-001 | Housekeeping Module | Done | v1.0.0 |
| NZL-002 | Night Audit | Done | v1.0.0 |
| NZL-003 | Invoice/Folio Generation | Done | v1.0.0 |
| NZL-004 | Check-in / Check-out Workflow | Done | v1.0.0 |
| NZL-005 | Group Bookings | Done | v1.0.0 |
| NZL-006 | Promo Codes / Discounts | Done | v1.0.0 |
| NZL-007 | Guest Messaging (Email) | Done | v1.0.0 |
| NZL-008 | Multi-Currency Support | Done | v1.0.0 |
| NZL-009 | Basic Payment Gateway | Future | Stripe/PayPal for international guests |
| NZL-010 | Guest ID/Passport Storage | Done | v1.0.0 |
| NZL-011 | Maintenance Work Orders | Future | Ticketing for room repairs, staff assignment |
| NZL-012 | Mobile PWA | Done | v1.0.0 |

---

## Phase 2 — Channel & Revenue (Syria + GCC Expansion)

| Ref | Feature | Status | Notes |
|-----|---------|--------|-------|
| NZL-013 | OTA Channel Sync (Booking.com) | Done | v1.1.0 |
| NZL-014 | OTA Channel Sync (Agoda/Expedia) | Future | Extend ChannelSyncService client registry |
| NZL-015 | Dynamic Pricing Engine | Done | v1.1.0 |
| NZL-016 | Metasearch (Google Hotel Ads) | Done | v1.1.0 |
| NZL-017 | Rate Restrictions | Done | v1.1.0 |
| NZL-018 | Tour Operator Module | Future | Contract rates, allotments, commissions |
| NZL-019 | Multi-Property Support | Future | Central dashboard, property_id in tables |
| NZL-020 | Review Solicitation | Done | v1.1.0 |
| NZL-021 | GCC Payment Gateways | Future | PayTabs, Geidea, STC Pay, Apple Pay |
| NZL-022 | Hijri Calendar | Future | Display alongside Gregorian |
| NZL-023 | WhatsApp Messaging | Done | v1.1.0 |
| NZL-024 | Contactless Check-in | Future | Digital registration, ID upload, e-signature |

---

## Phase 3 — KSA Compliance & Enterprise

| Ref | Feature | Status | Notes |
|-----|---------|--------|-------|
| NZL-025 | ZATCA E-Invoicing (Fatoorah) | Planned | Compliance tab placeholder added; pending full integration |
| NZL-026 | Shomoos Integration | Planned | Compliance tab placeholder added; pending API integration |
| NZL-027 | NTMP Reporting | Future | Saudi Ministry of Tourism data submission |
| NZL-028 | Mada Payment Support | Future | Saudi debit card network |
| NZL-029 | Nusuk Masar Integration | Future | Hajj/Umrah accommodation documentation |
| NZL-030 | Balady Municipal Integration | Future | Municipality compliance reporting |
| NZL-031 | Hajj/Umrah Season Management | Future | Extreme seasonality, zone pricing |
| NZL-032 | VAT 15% Compliance | Planned | Country profile seeds 15% VAT for SA; invoice QR pending |

---

## Phase 4 — Market Leadership

| Ref | Feature | Status | Notes |
|-----|---------|--------|-------|
| NZL-033 | AI Demand Forecasting | Done | v1.3.0 — Algorithmic forecasting with seasonal decomposition |
| NZL-034 | GDS Connectivity | Future | Amadeus/Sabre integration |
| NZL-035 | Digital Room Keys | Future | Bluetooth mobile keys |
| NZL-036 | Loyalty Program | Done | v1.3.0 — Tiers, points, rewards, guest portal |
| NZL-037 | POS Integration | Done | v1.3.0 — Outlet/item management, room folio posting |
| NZL-038 | Staff Scheduling | Future | Shift management, labor tracking |
| NZL-039 | Competitive Rate Shopping | Done | v1.3.0 — Competitor monitoring, parity alerts |
| NZL-040 | Open Developer Portal | Future | API docs, OAuth, webhooks |
| NZL-041 | White-Label / Multi-Brand | Done | v1.3.0 — Brand theming, custom colors, logo |
| NZL-042 | Sustainability Tracking | Future | Energy/resource per room, Vision 2030 |

---

## Implementation Notes

### Country-Aware Features
All features check `operating_country` (SY/SA) to adjust behavior:
- Currency display, tax rules, feature visibility
- SA: unified pricing, VAT 15%, ZATCA/Shomoos placeholders
- SY: dual pricing (Syrian/non-Syrian), tourism + city tax

### Architecture Pattern
Each module follows: **Module (bootstrap) -> Repository -> Service -> Controller**
- PSR-4 autoload: `Nozule\` -> `includes/`
- Frontend: Alpine.js 3.x + Tailwind CSS CDN
- API: WP REST under `nozule/v1`
- i18n: WordPress gettext (.po/.mo) + JS NozuleI18n

### Upcoming Priorities (Suggested Order)
1. NZL-025/026/032 — KSA compliance (required for Saudi market entry)
2. NZL-018 — Tour operator module (revenue driver)
3. NZL-009/021 — Payment gateways (monetization)
4. NZL-019 — Multi-property (enterprise upsell)
5. NZL-014 — Additional OTA channels
6. NZL-022 — Hijri calendar (cultural requirement)
7. NZL-024 — Contactless check-in (post-COVID standard)
8. NZL-011 — Maintenance work orders
9. Remaining Phase 4 features
