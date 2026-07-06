# EMI & Lead Checkout (WooCommerce)

A single plugin with two core features, designed especially for cement and retail stores where **online payments are not required**—only customer leads and EMI plans.

## Features

### 1) EMI Packages (Admin-defined per Product)

- On each product's **Edit Product → Product Data → General** tab, an **EMI Packages** section is available.
- Click **"+ Add Package"** to create as many EMI plans as needed. Each package contains three fields:
  - **Months**
  - **Down Payment**
  - **Total Payment**
- A regular product price is **not required**. Products can be purchased using only the configured EMI packages. If no regular price is set, the storefront displays **"From {lowest total payment}"**.
- On the single product page, every package automatically displays:
  - `Monthly = (Total Payment − Down Payment) ÷ Months`
- This is a simple installment calculation with **no interest**.
- Only EMI package options are displayed (no regular purchase / "No EMI" option).
- The first package is selected by default.
- When a package is selected:
  - The package's **Total Payment** becomes the product line item price.
  - Cart, checkout, and order pages display the EMI breakdown:
    - Down Payment
    - Monthly Payment × Number of Months

### 2) Payment-free Lead Checkout

- The checkout page contains **no payment methods or payment section**.
- Customers complete the standard WooCommerce billing form:
  - Name
  - Phone
  - Email
  - Address
  - City
  - Other billing details
- Clicking **Submit Order** creates a normal WooCommerce order with the **Processing** status.
- Orders appear under **WooCommerce → Orders** like standard WooCommerce orders and act as customer leads.
- Delivery method is **Shop Pickup** only (shipping fields and shipping charges are disabled).
- Every lead order automatically receives:
  - An order note
  - `_emicc_lead = yes` order meta for easy identification

### 3) Styling Settings

- A dedicated **EMI & Checkout** menu is added to the WordPress admin.
- Customize the EMI box appearance using:
  - Font Size
  - Border Radius
  - Title Color
  - Text Color
  - Accent Color (selected option & highlighted amounts)
  - Box Background
  - Option Background
  - Border Color
  - Hover Border Color
- All styling is applied live using CSS variables.

## Installation

1. Place this plugin inside `wp-content/plugins/`.
2. Go to **WordPress Admin → Plugins**.
3. Activate **EMI & Lead Checkout**.
4. WooCommerce must already be installed and activated.

## Files

```
emi-checkout.php                 Main bootstrap (constants, EMI helper, asset loading)
includes/class-emicc-emi.php     EMI admin fields, frontend display, cart & order integration
includes/class-emicc-checkout.php Payment-free lead checkout
assets/css/emi-checkout.css      EMI UI styling
assets/js/emi-checkout.js        Selected package highlighting
```

## Notes

- EMI packages are for installment planning only. No online payments are collected.
- The standard WooCommerce checkout is used with only the payment section removed.
- Orders behave like normal WooCommerce orders and appear in reports and the admin dashboard.
- Fully compatible with **WooCommerce HPOS (High-Performance Order Storage)**.
