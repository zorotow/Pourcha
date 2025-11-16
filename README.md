# Pourcha - Procurement and Purchasing Platform

A comprehensive multi-tenant SAAS procurement platform with integrated payment processing, supporting Stripe, PayPal, and cryptocurrency payments.

## Features

### Core Features
- **Multi-Tenancy**: Subdomain-based tenant isolation (e.g., `tenant.pourcha.app`)
- **Cross-Platform**: Web app (React), Desktop (Electron), Mobile (Flutter/React Native)
- **RESTful API**: Symfony + API Platform backend
- **JWT Authentication**: Secure token-based authentication

### Procurement Features
- **Dashboard**: Guided requests, popular tasks, supplier stores, notifications, to-do lists
- **Catalog Management**:
  - Browse 180+ items from multiple suppliers
  - Advanced filters (category, brand, price range, supplier, commodity)
  - Search functionality with real-time results
  - Grid/list view toggle, sorting by relevance/price
- **Shopping Cart**:
  - Add items with quantity selection
  - Review cart with line items, suppliers, prices
  - Chart of Accounts selection for billing
  - Delivery address and special instructions
  - Commodity approval workflow
  - Hedged rate support for international payments
- **Requisition Management**:
  - Create requisitions from cart
  - Multi-level approval workflows
  - Track status (draft, submitted, pending, approved, rejected)
  - View history and audit trail
  - On-behalf-of functionality
- **Invoice Processing**:
  - Submit invoices with supplier and amount
  - Attach documents and receipts
  - Approval workflows with delegation
  - Payment tracking and reconciliation
- **Supplier Management**:
  - Multiple supplier types (standard, store, pickup-only)
  - Supplier catalogs with part numbers
  - Store locations and contact information
  - Popular stores showcase (abcam, BioLegend, BOC, etc.)
- **Approval Workflows**:
  - Delegate authority to other users
  - Pending approvals dashboard
  - Approve/reject with comments
  - Notification system for approvers
- **Chart of Accounts**:
  - Cost center, fund, GL account structure
  - Search and filter accounts
  - Assign to line items in cart/requisitions
- **Notifications**:
  - Real-time approval requests
  - Status updates for requisitions/invoices
  - Delegate assignments
  - Payment confirmations

### Payment Features
- **Subscription Plans**:
  - **Free**: Basic features, limited requisitions
  - **Pro**: $9.99/month or $99/year - Full catalog access, integrations
  - **Enterprise**: Custom pricing - Advanced features, priority support

- **Payment Gateways**:
  - **Stripe**: Credit card payments and subscriptions
  - **PayPal**: Recurring and one-time payments
  - **Crypto**: USDT, USDC (Solana), Bitcoin, Monero

### Integration Features
- **External Systems**: Xero, SAP, QuickBooks, Stripe, Plaid
- **Bidirectional Sync**: Push purchase orders, pull payment statuses
- **OAuth 2.0**: Secure authentication for all integrations
- **ISO 27001 Compliant**: Enterprise-grade security

## Tech Stack

### Backend
- **Framework**: Symfony 6.4
- **API**: API Platform 3.2
- **Database**: MySQL 8.0 / PostgreSQL 15
- **Authentication**: Lexik JWT Authentication Bundle
- **Payment SDKs**: Stripe PHP SDK, PayPal REST SDK

### Frontend
- **Framework**: React 18
- **UI Library**: Material-UI (MUI) 5
- **Build Tool**: Vite 5
- **HTTP Client**: Axios
- **Routing**: React Router 6
- **Payment**: Stripe.js, PayPal SDK

## Installation

### Prerequisites
- PHP 8.1+
- Composer
- Node.js 18+
- npm/yarn
- MySQL 8.0 or PostgreSQL 15
- OpenSSL (for JWT keys)

### Backend Setup

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd Pourcha/backend
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Configure environment**
   ```bash
   cp .env .env.local
   # Edit .env.local with your database credentials and API keys
   ```

4. **Generate JWT keys**
   ```bash
   mkdir -p config/jwt
   openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
   openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
   # Set the passphrase in .env.local as JWT_PASSPHRASE
   ```

5. **Create database and run migrations**
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```

6. **Start development server**
   ```bash
   symfony serve -d
   # or
   php -S localhost:8000 -t public/
   ```

### Frontend Setup

1. **Navigate to frontend directory**
   ```bash
   cd ../frontend
   ```

2. **Install dependencies**
   ```bash
   npm install
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   # Edit .env with your API URL and payment gateway keys
   ```

4. **Start development server**
   ```bash
   npm run dev
   ```

5. **Access the application**
   - Frontend: http://localhost:3000
   - Backend API: http://localhost:8000/api

## Configuration

### Environment Variables

#### Backend (.env)
```env
# App
APP_ENV=dev
APP_SECRET=<generate-random-secret>

# Database
DATABASE_URL="mysql://user:password@127.0.0.1:3306/pourcha"

# JWT
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=<your-jwt-passphrase>

# Stripe
STRIPE_SECRET_KEY=sk_test_...
STRIPE_PUBLISHABLE_KEY=pk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# PayPal
PAYPAL_MODE=sandbox
PAYPAL_CLIENT_ID=...
PAYPAL_CLIENT_SECRET=...

# Crypto Wallets
CRYPTO_WALLET_BTC=bc1q...
CRYPTO_WALLET_USDT_SOLANA=...
CRYPTO_WALLET_USDC_SOLANA=...
CRYPTO_WALLET_MONERO=...

# Plan Pricing (in cents)
PLAN_PRO_MONTHLY_PRICE=999
PLAN_PRO_YEARLY_PRICE=9900
```

#### Frontend (.env)
```env
VITE_API_URL=http://localhost:8000/api
VITE_STRIPE_PUBLISHABLE_KEY=pk_test_...
VITE_PAYPAL_CLIENT_ID=...
```

## API Documentation

### Authentication
```bash
# Login
POST /api/login_check
{
  "email": "user@example.com",
  "password": "password"
}

Response:
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

### Subscription Management
```bash
# Get current subscription
GET /api/subscription
Authorization: Bearer <token>

# Cancel subscription
POST /api/subscription/cancel
Authorization: Bearer <token>

# Get payment history
GET /api/subscription/history
Authorization: Bearer <token>
```

### Checkout & Payments
```bash
# Get available plans
GET /api/checkout/plans

# Create Stripe checkout
POST /api/checkout/pro
{
  "gateway": "stripe",
  "interval": "monthly"
}

# Create PayPal checkout
POST /api/checkout/pro
{
  "gateway": "paypal",
  "interval": "yearly"
}

# Create Crypto payment
POST /api/checkout/pro
{
  "gateway": "crypto",
  "interval": "monthly",
  "crypto_currency": "USDT_SOLANA"
}

# Get crypto wallets
GET /api/checkout/crypto/wallets
```

### Webhooks
```bash
# Stripe webhook
POST /api/webhooks/stripe
Headers: stripe-signature

# PayPal webhook
POST /api/webhooks/paypal
```

## Payment Gateway Setup

### Stripe
1. Create account at https://stripe.com
2. Get API keys from Dashboard > Developers > API keys
3. Create webhook endpoint: `https://yourapp.com/api/webhooks/stripe`
4. Add webhook events:
   - `checkout.session.completed`
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
   - `invoice.payment_succeeded`
   - `invoice.payment_failed`
5. Copy webhook secret to `.env`

### PayPal
1. Create account at https://developer.paypal.com
2. Create REST API app
3. Get Client ID and Secret
4. Create billing plans for Pro (monthly/yearly)
5. Configure webhook URL: `https://yourapp.com/api/webhooks/paypal`
6. Subscribe to events:
   - `BILLING.SUBSCRIPTION.ACTIVATED`
   - `BILLING.SUBSCRIPTION.CANCELLED`
   - `PAYMENT.SALE.COMPLETED`

### Crypto Payments
1. Generate wallet addresses for each supported cryptocurrency
2. Add wallet addresses to `.env`
3. For automated verification, integrate with NowPayments or similar
4. Manual verification: Admin verifies transactions and updates payment status

## Development

### Database Migrations
```bash
# Create new migration
php bin/console make:migration

# Run migrations
php bin/console doctrine:migrations:migrate

# Rollback
php bin/console doctrine:migrations:migrate prev
```

### Testing Payments

#### Stripe Test Cards
- Success: `4242 4242 4242 4242`
- Decline: `4000 0000 0000 0002`
- 3D Secure: `4000 0025 0000 3155`

#### PayPal Sandbox
- Use sandbox accounts from https://developer.paypal.com/dashboard

#### Crypto Simulation
- Use "Simulate Payment" button in crypto checkout for testing

## Deployment

### Backend (Symfony)
```bash
# Build for production
composer install --no-dev --optimize-autoloader
php bin/console cache:clear --env=prod

# Set permissions
chown -R www-data:www-data var/
chmod -R 775 var/
```

### Frontend (React)
```bash
# Build for production
npm run build

# Serve static files
# Deploy dist/ folder to CDN or web server
```

### Environment Setup
1. Set `APP_ENV=prod` in backend `.env`
2. Configure database with production credentials
3. Update CORS settings for production domain
4. Set up SSL/TLS certificates
5. Configure webhook URLs with production domain

## Security

- **JWT Tokens**: Stored in localStorage, use HttpOnly cookies for production
- **OAuth 2.0**: All external API integrations use OAuth 2.0
- **ISO 27001**: Compliance for data encryption, audit logs, RBAC
- **Webhook Verification**: Stripe signatures, PayPal event validation
- **HTTPS**: Required for all payment processing

## Multi-Tenancy

Tenants are identified by subdomain:
- `tenant1.pourcha.app` → Tenant 1
- `tenant2.pourcha.app` → Tenant 2

Data isolation is achieved through:
- Tenant-specific database schemas or separate databases
- API requests include tenant context from subdomain
- Integration settings are tenant-configurable

## Troubleshooting

### Backend Issues
```bash
# Clear cache
php bin/console cache:clear

# Check logs
tail -f var/log/dev.log

# Test database connection
php bin/console doctrine:schema:validate
```

### Frontend Issues
```bash
# Clear node_modules
rm -rf node_modules package-lock.json
npm install

# Check API connectivity
curl http://localhost:8000/api/checkout/plans
```

### Payment Issues
- **Stripe**: Check webhook logs in Stripe Dashboard
- **PayPal**: Verify IPN messages in PayPal Developer Dashboard
- **Crypto**: Ensure wallet addresses are correct and network fees are included

## License

Proprietary - All Rights Reserved

## Support

For issues or questions:
- Email: support@pourcha.app
- Documentation: https://docs.pourcha.app
- GitHub Issues: <repository-url>/issues

## Roadmap

- [ ] Mobile app (Flutter)
- [ ] Desktop app (Electron)
- [ ] Advanced analytics dashboard
- [ ] Additional payment gateways
- [ ] ERP integrations (Oracle, Microsoft Dynamics)
- [ ] Multi-currency support
- [ ] Automated tax calculations
- [ ] Invoice OCR and parsing

---

**Built with Symfony, React, and modern payment technologies.**
