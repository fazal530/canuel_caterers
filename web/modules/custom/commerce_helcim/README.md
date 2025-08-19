# Commerce Helcim Payment Gateway

This module provides comprehensive Helcim payment gateway integration for Drupal Commerce 3, featuring both hosted payment pages and HelcimJS on-site payment processing.

## Available Payment Methods

### 1. Helcim HelcimJS (NEW - Recommended)
- **On-site payment processing** using HelcimJS API
- **Secure tokenization** - card data never touches your server
- **Better user experience** - no redirect required
- **Real-time validation** and card formatting
- **Mobile responsive** payment forms
- **PCI compliant** client-side tokenization

### 2. Helcim `Hosted Pages` (Existing)

* Helcim accepts only CAD currency.
* There are two necessary fields during a new payment method adding at
  `admin/commerce/config/payment-gateways`: Payment Page URL with token and
  secret hash` (can be found in _"Field Settings"_ of Payment Pages
  administration section).
* Helcim allows only static Drupal URLs for success and cancel redirects during
  transactions. It will be:
  - `https://<your-domain>/commerce-helcim/cancel`
  - `https://<your-domain>/commerce-helcim/success`
* `Test mode` seems currently not working on Helcim's side.
  Test card numbers don't go through.
* If Hosted Page fields are marked and "read-only" on the admin page, they won't
  accept values from Drupal's payment module.
* Helcim plans to roll out API updates (according to their support).

## Setup Instructions

### Your Helcim Credentials:
```
API Token: 0eece218c3e3339420c5e0
Secret Key: b7e1982189c7a81aef1abfaa70df397538f6f620
```

### Installation:
1. Enable the module: `drush en commerce_helcim -y`
2. Clear cache: `drush cr`
3. Go to `/admin/commerce/config/payment-gateways`
4. Add new payment gateway
5. Select "Helcim (HelcimJS)" for on-site payments
6. Enter your API Token and Secret Key
7. Set mode (Test/Live)

### HelcimJS Configuration:
- **API Token**: `0eece218c3e3339420c5e0`
- **Secret Key**: `b7e1982189c7a81aef1abfaa70df397538f6f620`
- **Terminal ID**: Optional
- **Mode**: Test (for development) / Live (for production)

## Testing

### Test Cards for HelcimJS:
- **Visa**: 4111111111111111
- **MasterCard**: 5555555555554444
- **Amex**: 378282246310005
- **Discover**: 6011111111111117

### Test Details:
- **Expiry**: Any future date (e.g., 12/25)
- **CVV**: Any 3-4 digit number (e.g., 123)
- **Name**: Any name

## Features Comparison

| Feature | HelcimJS | Hosted Page |
|---------|----------|-------------|
| User Experience | Seamless on-site | Redirect required |
| PCI Compliance | ✅ Tokenized | ✅ Hosted |
| Mobile Friendly | ✅ Responsive | ✅ Responsive |
| Customization | ✅ Full control | ❌ Limited |
| Setup Complexity | Medium | Simple |
| **Recommended** | ✅ **Yes** | For simple setups |

## Security

- **SSL Required**: HTTPS enforced for all transactions
- **PCI Compliance**: Card data tokenized client-side
- **Secure API**: All communications encrypted
- **No Card Storage**: Card details never stored on your server
