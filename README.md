# POSify – Laravel 11 Backend API
**The Most Complete POS Backend for Butcheries, Gas Stations & Retail Shops in Kenya/East Africa**

Multi-branch | Real-time Dashboard | M-Pesa STK & C2B | Credit Sales | Loyalty Cards | Bill Payments | Warehouse Dispatch

Frontend repo: https://github.com/Davie1007/king_of_the_grill_frontend  
Backend repo: https://github.com/Davie1007/king_of_the_grill_backend 

---
### Features

- Multi-branch architecture with full data isolation
- Laravel Sanctum SPA authentication (perfect for React/Vite)
- Full M-Pesa Daraja integration (STK Push + C2B + Callbacks)
- Real-time broadcasting (Pusher / Soketi)
- Credit sales & partial repayments
- Loyalty card system (register/top-up/redeem
- Butchery & Gas specialized inventory modules
- Central warehouse → branch dispatch system
- Utility bill payments (KPLC, Water, Airtime, etc.)
- Advanced analytics & business intelligence endpoints
- Employee productivity tracking & branch transfers
- Global search, notifications, health checks
- PDF receipts, Excel exports ready

---
### Tech Stack

- Laravel 11.x
- PHP 8.2+
- MySQL / PostgreSQL
- Redis + Horizon (queues)
- Laravel Sanctum
- Laravel Broadcasting (Pusher or Soketi)
- Laravel Sail / Docker ready

---
### Installation (Laravel Sail – Recommended)

```bash
git clone https://github.com/yourusername/posify-laravel-api.git
cd symo_biz

cp .env.example .env
./vendor/bin/sail up -d

sail artisan key:generate
sail artisan migrate --seed
sail artisan storage:link
sail artisan horizon:install

# Start queue worker
sail artisan horizon
