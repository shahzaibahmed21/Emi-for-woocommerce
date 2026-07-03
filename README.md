# EMI & Lead Checkout (WooCommerce)

Ek hi plugin me do features, khaas tor par Cement/retail store ke liye jahan online payment nahi chahiye, sirf leads + EMI plans chahiye.

## Features

### 1) EMI Packages (per product, admin-defined)

- Har product ke **edit page → Product data → General** tab me **EMI Packages**.
- **"+ Add Package"** se jitne chahe packages banayein. Har package me 3 fields:
  - **Months**
  - **Downpayment** (us package ka apna downpayment)
  - **Total Payment**
- Regular price ki zaroorat nahi — product sirf in packages se purchasable ho jata hai. (Agar regular price set na ho to "From {lowest total}" price dikhega.)
- Frontend (single product page) par har package ke liye automatically:
  - `Monthly = (Total Payment − Downpayment) ÷ Months` (no interest — simple division).
- Sirf package options dikhte hain (koi "No EMI" / regular-price option nahi). Pehla package default selected.
- Package select karne par us package ki **total payment line item par set** ho jati hai (checkout Total reflect karta hai) aur **downpayment + monthly × months** breakdown cart/checkout/order me show hota hai.

### 2) Payment-free "Lead" Checkout

- Checkout par **koi payment method / payment box nahi**.
- Customer standard WooCommerce **billing form** bharta hai (Naam, Phone, Email, Address, City, etc.).
- "Submit Order" dabate hi order ban jata hai aur **WooCommerce → Orders** me normal order (status: *Processing*) ki tarah aa jata hai — yeh aapki **lead** hai.
- Delivery = **shop pickup** (shipping fields/charges off).
- Har aisa order par ek note + `_emicc_lead = yes` meta lagta hai taa-ke leads pehchaan saken.

### 3) Styling Settings

- Admin sidebar me **EMI & Checkout** menu → styling options:
  - Font size, Border radius
  - Title color, Text color, Accent color (selected + amount)
  - Box background, Option background, Border color, Hover border color
- Yeh sab product page wale EMI box par live apply hote hain (CSS variables ke zariye).

## Installation

1. Yeh folder `wp-content/plugins/` me rakhein (yahin hai).
2. WordPress Admin → **Plugins** → **EMI & Lead Checkout** → *Activate*.
3. WooCommerce active hona zaroori hai.

## Files

```
emi-checkout.php                 Main bootstrap (constants, EMI calc helper, asset load)
includes/class-emicc-emi.php     EMI: admin field, product display, cart/order meta
includes/class-emicc-checkout.php Payment-free lead checkout
assets/css/emi-checkout.css      EMI UI styling
assets/js/emi-checkout.js        Selected-plan highlight
```

## Notes

- EMI sirf installment **information/plan** hai — koi paisa online charge nahi hota (poora store payment-free hai).
- Original WooCommerce checkout hi use hota hai (sirf payment hata diya gaya), is liye orders normal tarah WooCommerce reports/admin me aate hain.
- HPOS (High-Performance Order Storage) compatible.
