# MOEC-2609 Test Fixes

**Date:** 2025-01-13
**Branch:** `fix/duration-formatting-consistency`

## Changes Made

| File | Change |
|------|--------|
| `StockLocationVerificationActionGroup.xml` | Fixed selector `.stock-locations` → `.stocks-wrapper`, added "Check Availability" button click |
| `GotToProductProductPageActionGroup.xml` | Added PLP→PDP navigation handling when search lands on results page |
| `ValidateLoggedInProductPriceActionGroup.xml` | Made warehouse availability check conditional |
| `MOEC2609-US.xml` | Commented out Step 8 (no-price SKU), Step 13 (sales deals), Scenario 4 (spare part SKU not found) |
| `.env.preprod-us` | Updated `SAMPLE_SKU_2_2609`, added Scenario 3/4 env vars |

## Scenario Status

| Step | Description | Status |
|------|-------------|--------|
| 3 | Accept cookies + validate not-logged-in PDP | ✅ Active |
| 6 | Login + accept terms | ✅ Active |
| 8 | Contact us for pricing (no price SKU) | ⏸️ Skipped |
| 9 | Validate logged-in PDP with price | ✅ Active |
| 13 | Sales deal pricing | ⏸️ Skipped |
| Scenario 3 | Stock location verification | ✅ Active |
| Scenario 4 | Spare parts via PDP | ⏸️ Skipped |

## Temporarily Skipped (Data Issues)

- **Step 8:** `SKU_WITHOUT_PRICE_2609` (44-1403GLD) - empty price box on preprod-us
- **Step 13:** `DEAL_PRODUCT_2609` (3AUA0000004443) - no pricing on preprod-us
- **Scenario 4:** `SPARE_PART_SKU_2609` (1SVR011718R2500) - SKU not found on preprod-us

## Env Variables (preprod-us)

```
SAMPLE_SKU_1_2609=05E936W546G1
SAMPLE_SKU_2_2609=05E936W546G1
SKU_WITHOUT_PRICE_2609=44-1403GLD
STOCK_LOCATION_SKU_2609=3AXD50000309009
SPARE_PART_SKU_2609=1SVR011718R2500
SERVICES_FOR_DRIVES_PLP=/services-for-drives-9aac176111
```

## Key Fixes

### 1. PLP→PDP Navigation
Search sometimes lands on PLP instead of auto-redirecting to PDP. Added handling in `GotToProductProductPageActionGroup`:
```javascript
var searchList = document.querySelector('pis-products-search-list');
if (searchList && searchList.shadowRoot) {
    var productLink = searchList.shadowRoot.querySelector('a.product-name');
    if (productLink) { productLink.click(); }
}
```

### 2. Stock Location Selector
Changed from `.stock-locations` to `.stocks-wrapper` and added click on "Check Availability" button first.

### 3. Conditional Warehouse Check
Products without stock don't show `.warehouse-list`. Made the check conditional to avoid false failures.

## TODO

- [ ] Find valid SKU with "Contact us for pricing" display
- [ ] Find valid SKU with sales deal pricing
- [ ] Find valid spare part SKU in Services for Drives category
