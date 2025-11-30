# SymoBiz Application Flow Documentation

## Overview
SymoBiz is a multi-branch inventory and sales management system with integrated M-Pesa payment processing. It supports multiple business types (Butchery, Gas, Drinks, Retail) and handles both cash and credit sales.

---

## 1. Core Architecture

### Technology Stack
- **Backend**: Laravel 12 (PHP 8.2+)
- **Database**: SQLite (development) / MySQL (production)
- **Authentication**: Laravel Sanctum (token-based)
- **Real-time**: Laravel Reverb + Pusher (WebSocket broadcasting)
- **Payment Gateway**: Safaricom M-Pesa (Daraja API)

### Key Design Patterns
- **Multi-tenancy**: Branch-based isolation
- **Event-Driven**: Payment events broadcast to frontend
- **Service Layer**: M-Pesa logic abstracted into services
- **Repository Pattern**: Models handle data access

---

## 2. Data Models & Relationships

### Core Entities

#### Branch
- Represents a physical business location
- Has a `type` (Butchery, Gas, Drinks, Retail)
- Can have a dedicated `DarajaApp` (M-Pesa credentials)
- Falls back to `.env` M-Pesa credentials if no app assigned
- Has optional `tillNumber` for Buy Goods (vs Paybill)

#### User
- Authenticated via Sanctum tokens
- Has a `role` (Admin, Manager, Cashier)
- Can be linked to an `Employee` record

#### Employee
- Belongs to a `Branch`
- Has `status` (Active, Suspended)
- Tracks performance metrics

#### InventoryItem
- Belongs to a `Branch`
- Has multiple price tiers (`price`, `price2`, `price3`)
- Tracks `stock` levels (decimal for fractional quantities)
- `is_butchery` flag for business type
- `buying_price` for profit calculations

#### Sale
- Records a completed transaction
- `payment_method`: Cash, M-Pesa, Credit, Card
- `payment_status`: Paid, Pending, Partial
- Links to `Payment` record (for M-Pesa)
- Can link to `CreditSale` for credit transactions

#### SaleItem
- Line items for a sale
- Links `Sale` → `InventoryItem`
- Records `quantity` and `price` at time of sale

#### CreditSale
- Tracks customer debt
- **Merges by phone number** (normalized to 254XXXXXXXX)
- `total_amount` accumulates across multiple sales
- `amount_paid` tracks repayments
- `balance` computed accessor

#### CreditRepayment
- Records payments against credit
- Links to `CreditSale`
- Can be M-Pesa or Cash

#### Payment
- Universal payment record for M-Pesa transactions
- `transaction_id`: M-Pesa receipt number
- `used`: Boolean flag (prevents double-spending)
- `cart_ref`: Links to pending cart in cache
- Can be matched to `Sale` or `CreditRepayment`

#### DarajaApp
- M-Pesa API credentials per branch
- `consumer_key`, `consumer_secret`, `shortcode`, `passkey`
- `environment`: sandbox or live
- `tillNumber`: For Buy Goods transactions

---

## 3. Business Flows

### 3.1 Inventory Management

#### Adding Inventory
```
1. User selects branch
2. POST /api/branches/{branch}/inventory
3. Validates: name, price, buying_price, stock, unit
4. Sets is_butchery based on branch type
5. Stores image (optional)
6. Returns created item
```

#### Stock Updates
```
1. PUT /api/branches/{branch}/inventory/{item}
2. Updates price, stock, or other fields
3. Validates stock >= 0
4. Returns updated item
```

#### Stock Depletion (Automatic)
```
Triggered by: Sale creation
1. For each SaleItem:
   - Fetch InventoryItem
   - Decrement stock by quantity
   - Save item
2. If stock < 0: Transaction rolls back
```

---

### 3.2 Sales Processing

#### Cash Sale Flow
```
1. Frontend: POST /api/sales
   {
     "branch": 1,
     "payment_method": "Cash",
     "items": [
       {"item": 5, "quantity": 2, "price": 100}
     ]
   }

2. Backend (SaleController::store):
   - Validates stock availability
   - Creates Sale record
   - Creates SaleItem records
   - Decrements inventory stock
   - Returns sale with receipt

3. Frontend: Displays receipt
```

#### Credit Sale Flow
```
1. Frontend: POST /api/sales
   {
     "branch": 1,
     "payment_method": "Credit",
     "customer_name": "John Doe",
     "customer_id_number": "12345678",
     "customer_telephone_number": "0712345678",
     "items": [...]
   }

2. Backend:
   - Normalizes phone: 0712345678 → 254712345678
   - Searches for existing CreditSale with same phone
   - If found: Adds to total_amount
   - If not: Creates new CreditSale
   - Creates Sale record (linked to CreditSale)
   - Decrements stock

3. Result: Customer has accumulated debt
```

#### Credit Repayment Flow
```
1. Frontend: POST /api/credit-sales/{id}/pay
   {
     "amount": 500,
     "payment_method": "M-Pesa" or "Cash"
   }

2. Backend:
   - Creates CreditRepayment record
   - Increments CreditSale.amount_paid
   - If M-Pesa: Creates Payment record
   - Returns updated balance
```

---

### 3.3 M-Pesa Payment Flows

#### 3.3.1 STK Push (Lipa Na M-Pesa)

**Scenario**: Customer pays via phone prompt

```
┌─────────────┐
│  Frontend   │
└──────┬──────┘
       │ 1. POST /api/sales/mpesa/start
       │    {branch, items, phone}
       ▼
┌─────────────────────┐
│  SaleController     │
│  ::startMpesaPayment│
└──────┬──────────────┘
       │ 2. Instantiate DynamicMpesaService(branch_id)
       │ 3. Cache cart data (cart_ref)
       │ 4. Call mpesaService->stkPush(phone, amount, cart_ref)
       ▼
┌─────────────────────┐
│ DynamicMpesaService │
└──────┬──────────────┘
       │ 5. Determine till number:
       │    - If branch has DarajaApp: use app.shortcode
       │    - Else: use branch.tillNumber or env shortcode
       │ 6. Set TransactionType:
       │    - CustomerBuyGoodsOnline (if till ≠ shortcode)
       │    - CustomerPayBillOnline (if same)
       │ 7. POST to M-Pesa API
       ▼
┌─────────────┐
│   M-Pesa    │
└──────┬──────┘
       │ 8. Sends STK prompt to customer phone
       │ 9. Customer enters PIN
       │ 10. M-Pesa processes payment
       │ 11. POST /api/mpesa/stkpush/callback
       ▼
┌─────────────────────┐
│  MpesaController    │
│  ::stkCallback      │
└──────┬──────────────┘
       │ 12. Extract: amount, mpesaRef, phone
       │ 13. Pull cart from cache (using cart_ref)
       │ 14. Call handleMpesaPayment()
       ▼
┌─────────────────────┐
│  handleMpesaPayment │
└──────┬──────────────┘
       │ 15. Validate amount matches cart
       │ 16. Check stock availability
       │ 17. Create Sale record
       │ 18. Create SaleItem records
       │ 19. Decrement inventory stock
       │ 20. Mark Payment as used=true
       │ 21. Broadcast PaymentReceived event
       ▼
┌─────────────┐
│  Frontend   │
└─────────────┘
       22. Receives event via WebSocket
       23. Clears cart
       24. Shows receipt
```

**Key Points**:
- Cart is cached for 10 minutes
- Payment record created with `used=false`
- If callback fails, payment can be verified later via `/api/mpesa/verify`

#### 3.3.2 C2B (Customer to Business)

**Scenario**: Customer pays to till number manually

```
┌─────────────┐
│  Frontend   │
└──────┬──────┘
       │ 1. POST /api/mpesa/c2b/initiate
       │    {branch, items, tillNumber, amount}
       ▼
┌─────────────────────┐
│  MpesaController    │
│  ::initiateC2BPayment│
└──────┬──────────────┘
       │ 2. Generate cart_ref (unique ID)
       │ 3. Cache cart data
       │ 4. Create Payment record (used=false)
       │ 5. Return: "Pay KES X to Till Y with Ref Z"
       ▼
┌─────────────┐
│  Customer   │
└──────┬──────┘
       │ 6. Opens M-Pesa on phone
       │ 7. Lipa Kwa M-Pesa → Buy Goods
       │ 8. Enters Till Number
       │ 9. Enters Amount
       │ 10. Enters Account Number (cart_ref)
       │ 11. Confirms payment
       ▼
┌─────────────┐
│   M-Pesa    │
└──────┬──────┘
       │ 12. POST /api/mpesa/c2b/confirmation
       │     {TransID, TransAmount, MSISDN, BillRefNumber}
       ▼
┌─────────────────────┐
│  MpesaController    │
│  ::c2bConfirmation  │
└──────┬──────────────┘
       │ 13. Check for duplicate (TransID)
       │ 14. Normalize phone number
       │ 15. Try to match pending cart:
       │     - Amount matches
       │     - Phone matches (optional)
       │     - BillRefNumber matches cart_ref
       │ 16. If matched: handleMpesaPayment()
       │ 17. If not: Store payment for manual verification
       ▼
┌─────────────────────┐
│  handleMpesaPayment │
└──────┬──────────────┘
       │ (Same as STK flow)
       │ 18. Create Sale
       │ 19. Decrement stock
       │ 20. Broadcast PaymentReceived
       ▼
┌─────────────┐
│  Frontend   │
└─────────────┘
       21. Receives event
       22. Shows receipt
```

**Fallback**: If cart not found, payment is stored with `used=false` and can be verified later.

#### 3.3.3 Payment Verification (Manual)

**Scenario**: Payment received but not auto-matched

```
1. Frontend: POST /api/mpesa/verify
   {
     "transaction_id": "QWE123456",
     "context": "cart",
     "cart": {...}
   }

2. Backend (MpesaController::verifyTransaction):
   - Fetch Payment by transaction_id
   - Check if already used
   - Validate amount matches cart
   - Create Sale
   - Mark Payment as used=true
   - Broadcast PaymentReceived

3. Frontend: Receives event, shows receipt
```

---

### 3.4 Event Broadcasting

#### PaymentReceived Event
```javascript
// Broadcast to: payments.{branch_id}
{
  type: 'sale' or 'credit',
  amount: 1000,
  transaction_id: 'QWE123456',
  receipt: {
    receipt_no: 'QWE123456',
    branch: {name: 'Main Branch'},
    timestamp: '2025-11-27 01:00:00',
    items: [...],
    payment_method: 'M-Pesa',
    total: 1000,
    sale_id: 42
  },
  sale_id: 42,
  cart_ref: 'cart_abc123'
}
```

#### PaymentFailed Event
```javascript
// Broadcast to: payments.{cart_ref}
{
  type: 'payment_failed',
  cart_ref: 'cart_abc123',
  error_code: 1032,
  error_message: 'Request cancelled by user'
}
```

---

## 4. Dynamic M-Pesa Service

### Branch-Specific Credentials

```
Branch A:
  - Has DarajaApp (shortcode: 174379, till: 5678901)
  - Uses: CustomerBuyGoodsOnline

Branch B:
  - No DarajaApp
  - Falls back to .env (shortcode: 600000)
  - Uses branch.tillNumber if set
  - Uses: CustomerPayBillOnline or CustomerBuyGoodsOnline
```

### Transaction Type Logic
```php
if ($partyB !== $businessShortCode) {
    $transactionType = 'CustomerBuyGoodsOnline'; // Till Number
} else {
    $transactionType = 'CustomerPayBillOnline'; // Paybill
}
```

---

## 5. Reporting & Analytics

### Available Endpoints

#### Sales Reports
- `GET /api/analytics/sales/grouped` - Sales by period
- `GET /api/analytics/products/distribution` - Top products
- `GET /api/analytics/payments/grouped` - Payment methods breakdown
- `GET /api/analytics/financials/revenue-expense-profit` - P&L
- `GET /api/analytics/stock/turnover` - Inventory turnover
- `GET /api/analytics/customers/new-returning` - Customer retention

#### Branch Statistics
- `GET /api/branches/{branch}/statistics` - Branch performance
- `GET /api/branches/{branch}/sales/statistics` - Sales metrics
- `GET /api/inventory/performance/{branchId}` - Product performance

---

## 6. Security & Authorization

### Authentication
```
1. POST /api/auth/token
   {email, password}
   
2. Returns: Sanctum token

3. All subsequent requests:
   Authorization: Bearer {token}
```

### Authorization Policies
- Branch access: User must be assigned to branch
- Inventory: Can only view/edit own branch inventory
- Sales: Can only create sales for own branch
- Reports: Branch-scoped by default

---

## 7. Edge Cases & Error Handling

### Stock Validation
```
- Pre-check before sale creation
- Rollback entire transaction if any item out of stock
- Returns 400 with specific item details
```

### Payment Duplicate Protection
```
- Check Payment.transaction_id uniqueness
- If exists and used=true: Reject
- If exists and used=false: Allow verification
```

### Amount Mismatch
```
- STK/C2B: Validate ±0.01 tolerance
- Underpayment: Reject with error
- Overpayment: Log warning, accept
```

### Cache Expiry
```
- Pending carts: 10 minutes TTL
- M-Pesa access token: 50 minutes TTL
- If cart expired: Payment stored for manual verification
```

---

## 8. Database Schema Highlights

### Key Migrations
- `2025_11_26_210000_add_sale_id_to_credit_sales_table.php` - **Critical fix** (added during restoration)
- `2025_11_11_072603_create_daraja_apps_table.php` - Multi-till support
- `2025_10_02_100253_normalize_credit_sales_phones.php` - Phone normalization
- `2025_10_01_090529_create_payments_table.php` - Universal payment tracking

### Indexes
- `payments.transaction_id` - Unique
- `credit_sales.customer_phone` - For merging
- `sales.mpesa_ref` - For callback matching

---

## 9. Configuration

### Environment Variables
```env
# M-Pesa (Fallback)
MPESA_CONSUMER_KEY=xxx
MPESA_CONSUMER_SECRET=xxx
MPESA_SHORTCODE=174379
MPESA_PASSKEY=xxx
MPESA_ENV=sandbox

# Broadcasting
PUSHER_APP_ID=xxx
PUSHER_APP_KEY=xxx
PUSHER_APP_SECRET=xxx
REVERB_APP_ID=xxx
```

### Config Files
- `config/mpesa.php` - Callback URLs
- `config/broadcasting.php` - Pusher/Reverb setup

---

## 10. Testing

### Manual Verification Script
```bash
php tests/manual_verification.php
```

**Tests**:
1. Credit sales merging
2. M-Pesa STK push
3. M-Pesa callback processing
4. PaymentReceived event
5. PaymentFailed event

---

## Summary

SymoBiz is a robust, multi-branch POS system with:
- ✅ Real-time M-Pesa integration (STK + C2B)
- ✅ Credit sales with phone-based consolidation
- ✅ Multi-till support per branch
- ✅ WebSocket event broadcasting
- ✅ Comprehensive reporting
- ✅ Stock management with automatic depletion
- ✅ Duplicate payment protection
- ✅ Graceful fallback for failed payments

The system is production-ready with all previous functionality preserved and enhanced with dynamic M-Pesa capabilities.
