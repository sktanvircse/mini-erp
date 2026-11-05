# 🌐 Mini ERP - Production Deployment Guide

This guide explains how to deploy **Mini ERP** to production:
- **Frontend**: Deployed to **Vercel** (Global Edge CDN, SSL, Zero-Config SPA)
- **Backend**: Deployed to **Railway** or **Render** (Docker, PHP 8.3 Apache)
- **Database**: Managed **MySQL / MariaDB** on Railway or Render

---

## 🏗️ Architecture in Production

```
┌─────────────────────────────────┐
│       Vercel (Frontend)         │
│  https://your-mini-erp.vercel.app│
└───────────────┬─────────────────┘
                │ /api/* proxy rewrite
                ▼
┌─────────────────────────────────┐
│   Railway / Render (Backend)    │
│  https://your-backend.railway.app│
└───────────────┬─────────────────┘
                │ MySQL Connection
                ▼
┌─────────────────────────────────┐
│  Railway / Render (Database)    │
│        MySQL / MariaDB          │
└─────────────────────────────────┘
```

---

## 🚀 Part 1: Deploy Backend & Database (Railway - Recommended)

Railway offers a 1-click deployment with managed MySQL and automatic container build.

### Step 1: Create a Railway Project
1. Log in to [Railway.app](https://railway.app) with your GitHub account.
2. Click **+ New Project** -> **Provision MySQL**.
   - Railway immediately creates a managed MySQL database and generates connection credentials (`MYSQLHOST`, `MYSQLPORT`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLDATABASE`).

### Step 2: Deploy the PHP Backend
1. In the same Railway project, click **+ New** -> **GitHub Repo**.
2. Select your repository: `sktanvircse/mini-erp`.
3. In the service settings:
   - **Root Directory**: Leave as `/` (it will use `backend/Dockerfile`).
   - Click **Variables** and add:
     - `RUN_MIGRATIONS` = `true` *(Automatically seeds tables and GL accounts on first start)*
     - `JWT_SECRET` = *(Generate any random 32-character string)*
     - `CORS_ALLOWED_ORIGINS` = `*`
   - Connect the service to the MySQL database (Railway will automatically link `MYSQLHOST`, `MYSQLUSER`, `MYSQLPASSWORD`, etc.).
4. Click **Settings** -> **Networking** -> **Generate Domain**.
   - You will receive a public HTTPS URL like:
     `https://mini-erp-production-xxxx.up.railway.app`
5. Test your live backend API:
   ```bash
   curl https://mini-erp-production-xxxx.up.railway.app/api/dashboard
   ```

---

## ⚡ Part 2: Deploy Frontend to Vercel

### Step 1: Import Repository to Vercel
1. Log in to [Vercel](https://vercel.com) with your GitHub account.
2. Click **Add New...** -> **Project**.
3. Select your repository: `sktanvircse/mini-erp`.

### Step 2: Configure Project Settings
In the Vercel project configuration screen:
- **Framework Preset**: Other / None
- **Root Directory**: Click *Edit* and select **`frontend`** (or leave as root `/`).
- **Build Command**: `npm run build`
- **Output Directory**: `dist/frontend/browser`
- **Install Command**: `npm install`

### Step 3: Configure Backend API Proxy
Open [`frontend/vercel.json`](frontend/vercel.json) or [`vercel.json`](vercel.json) and update the `destination` URL to your Railway backend URL:
```json
{
  "rewrites": [
    {
      "source": "/api/(.*)",
      "destination": "https://YOUR-RAILWAY-BACKEND-URL/api/$1"
    },
    {
      "source": "/(.*)",
      "destination": "/index.html"
    }
  ]
}
```
*Because of this rewrite, all `/api/*` network calls are proxied securely on the server-side, eliminating CORS issues completely!*

### Step 4: Click Deploy
Vercel will build the Angular 22 app and publish it to a global edge domain:
`https://mini-erp-sktanvircse.vercel.app`

---

## 🐳 Alternative: 1-Command Local or VPS Production (Docker Compose)

To run the entire production stack (Frontend Nginx + Backend Apache + MariaDB) on a Virtual Private Server (AWS, DigitalOcean, Linode, Hetzner, etc.):

```bash
git clone https://github.com/sktanvircse/mini-erp.git
cd mini-erp

# Start all 3 services in production mode
docker compose up -d
```

- **Frontend**: `http://YOUR-SERVER-IP:4200`
- **Backend API**: `http://YOUR-SERVER-IP:8000/api`
- **Database**: Port `3306`

---

## 🔑 Production Demo Logins

| Role | Email | Password |
|:---|:---|:---|
| **System Administrator** | `admin@minierp.com` | `Admin@123` |
| **Operations Manager** | `manager@minierp.com` | `Admin@123` |
| **Chief Accountant** | `accountant@minierp.com` | `Admin@123` |
| **Head Storekeeper** | `store@minierp.com` | `Admin@123` |
| **Sales Executive** | `sales@minierp.com` | `Admin@123` |
