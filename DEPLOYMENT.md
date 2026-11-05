# 🌐 Mini ERP - 100% Free Production Deployment Guide

Since Railway free trial expired, here is the **100% completely free forever stack** (No credit card needed):

| Component | Platform | Free Tier Benefits |
|:---|:---|:---|
| **Frontend** | **[Vercel](https://vercel.com)** | Unlimited deployments, global edge CDN, automated SSL. |
| **Backend API** | **[Render.com](https://render.com)** | Free Web Service with Docker support. |
| **MySQL Database** | **[TiDB Cloud](https://tidbcloud.com)** or **[Aiven](https://aiven.io)** | 5 GB Free Serverless MySQL forever, instant SSL connection. |

---

## 🏗️ Free Production Architecture

```
┌──────────────────────────────────────────────┐
│             Vercel (Frontend)                │
│       https://your-app.vercel.app            │
└──────────────────────┬───────────────────────┘
                       │ /api/* proxy rewrite
                       ▼
┌──────────────────────────────────────────────┐
│          Render.com (Backend API)            │
│     https://mini-erp-backend.onrender.com    │
└──────────────────────┬───────────────────────┘
                       │ SSL Connection
                       ▼
┌──────────────────────────────────────────────┐
│        TiDB Cloud (Serverless MySQL)         │
│             5 GB Free Forever                │
└──────────────────────────────────────────────┘
```

---

## 🚀 Step 1: Create Free MySQL Database on TiDB Cloud (2 Minutes)

1. Sign up for free at **[tidbcloud.com](https://tidbcloud.com)** (use Google or GitHub login, no credit card required).
2. Click **Create Cluster** ➔ Choose **Serverless (Free Forever, 5GB)**.
3. Click **Create**. Within 10 seconds, your cluster is ready!
4. Click **Connect** ➔ In the popup:
   - Generate your password and copy the connection parameters:
     - **Host**: e.g., `gateway01.us-east-1.prod.aws.tidbcloud.com`
     - **Port**: `4000`
     - **User**: e.g., `xxxxxx.root`
     - **Password**: `your-generated-password`
     - **Database**: `mini_erp` (or leave default `test`)

---

## ⚡ Step 2: Deploy PHP Backend on Render.com (100% Free)

1. Sign up for free at **[render.com](https://render.com)** with your GitHub account.
2. Click **New +** ➔ **Web Service**.
3. Select your repository: **`sktanvircse/mini-erp`**.
4. Configure service details:
   - **Name**: `mini-erp-backend`
   - **Region**: Oregon (US West) or Ohio (US East)
   - **Branch**: `main`
   - **Runtime**: **Docker**
   - **Dockerfile Path**: `backend/Dockerfile`
   - **Docker Context**: `.`
   - **Instance Type**: **Free**
5. Scroll down to **Environment Variables** and add:
   - `DB_HOST` = *(Your TiDB Host from Step 1)*
   - `DB_PORT` = `4000`
   - `DB_DATABASE` = `mini_erp`
   - `DB_USERNAME` = *(Your TiDB User from Step 1)*
   - `DB_PASSWORD` = *(Your TiDB Password from Step 1)*
   - `MYSQL_SSL` = `true`
   - `RUN_MIGRATIONS` = `true` *(Automatically seeds all 48 tables and balanced accounting vouchers on first start)*
   - `JWT_SECRET` = `super_secret_production_jwt_key_2026_antigravity`
   - `CORS_ALLOWED_ORIGINS` = `*`
6. Click **Create Web Service**.
   - Render will build your Docker image and boot your PHP 8.3 Apache API.
   - When finished, Render gives you a live HTTPS URL like:
     `https://mini-erp-backend.onrender.com`
7. Test it: Open `https://mini-erp-backend.onrender.com/api/dashboard` (returns HTTP 200/401 auth check).

---

## 🎨 Step 3: Deploy Frontend on Vercel (100% Free)

1. Open [`frontend/vercel.json`](frontend/vercel.json) or [`vercel.json`](vercel.json) in your repository and update the destination URL with your Render backend URL:
   ```json
   {
     "rewrites": [
       {
         "source": "/api/(.*)",
         "destination": "https://mini-erp-backend.onrender.com/api/$1"
       },
       {
         "source": "/(.*)",
         "destination": "/index.html"
       }
     ]
   }
   ```
2. Go to **[vercel.com](https://vercel.com)** ➔ Click **Add New...** ➔ **Project**.
3. Import your repository: **`sktanvircse/mini-erp`**.
4. Settings:
   - **Root Directory**: `frontend`
   - **Build Command**: `npm run build`
   - **Output Directory**: `dist/frontend/browser`
5. Click **Deploy**!
   - Vercel will build and host your Angular 22 app on a high-speed CDN:
     `https://mini-erp-sktanvircse.vercel.app`

---

## 🔑 Production Login Credentials

- **Email**: `admin@minierp.com`
- **Password**: `Admin@123`
