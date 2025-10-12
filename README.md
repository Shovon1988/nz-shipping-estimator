## 📬 Contact

Developed by [Shovon Saha](https://github.com/Shovon1988)  
📧 Email: shovon@pixelart.net.nz 
💼 LinkedIn: https://www.linkedin.com/in/shovon-saha-09b049356/

##  Features (Detailed)

###  Region‑Based Base Rates
- Each NZ region (Auckland, Northland, Waikato, Canterbury, etc.) has its own configurable base shipping rate.
- Example: Auckland = $8.00, Canterbury = $12.00.
- Rates are defined in the `nzse_get_defaults()` function and can be easily adjusted.

###  Weight‑Based Tiers
- Shipping costs scale with the total cart weight.
- Default tiers:
  - Up to 1kg → no extra charge
  - 1–5kg → +$5.00
  - Over 5kg → +$10.00
- Developers can add or modify tiers in the configuration array.

###  Free Shipping Threshold
- Orders over a set subtotal qualify for free shipping.
- Default: **$100.00**.
- Threshold is configurable in `nzse_get_defaults()`.

###  PO Box Surcharge
- If the customer’s address contains “PO Box” or “P.O. Box”, a surcharge is applied.
- Default: **+$2.00**.
- Prevents under‑charging for courier services that don’t deliver to PO Boxes.

###  Rural Delivery Surcharge
- Uses an authoritative list of NZ rural postcodes (North & South Island).
- If the entered postcode matches (or is within a small tolerance of) a rural code, a surcharge is applied.
- Default: **+$5.00**.
- Includes a fallback heuristic (detects “RD” or “Rural Delivery” in the address) for extra reliability.

###  Embedded Postcode Dataset
- Plugin ships with a JSON dataset mapping **postcode → locality → region → island**.
- No external API calls required → faster, more reliable, and works offline.
- Dataset can be updated by replacing the embedded JSON.

###  Checkout Estimator UI
- Adds a **“Estimate Shipping”** box to the WooCommerce checkout page.
- Customers enter:
  - Region (dropdown)
  - City/Town
  - Postcode
- The plugin instantly calculates and displays the estimated shipping cost via AJAX.

###  AJAX‑Powered Calculation
- No page reloads required.
- Results are displayed dynamically under the estimator box.
- Returns a breakdown including:
  - Locality
  - Region
  - Island (if available)
  - Applied surcharges
  - Final shipping cost
