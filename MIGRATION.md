# Architectural Evolution: React & Node.js Migration Plan

This document outlines the strategic roadmap for evolving the current native PHP/MySQL monolith into a modern, decoupled, API-first architecture using **React/Next.js** on the frontend and **Node.js (TypeScript)** on the backend, while preserving the existing MySQL database schemas.

---

## 🗺️ Architectural Evolution

The migration follows a phased, low-risk, decoupled strategy to ensure maximum uptime, zero SEO degradation, and gradual code translation.

```mermaid
graph TD
    subgraph Phase 0: Monolith (Current)
        PHP_M[PHP Monolith / Server Render] -->|Direct Query| MySQL[(MySQL DB)]
    end

    subgraph Phase 1: Hybrid (Transitional)
        React_FE[React/Next.js Frontend] -->|JSON API Requests| PHP_API[PHP Light API Layer]
        PHP_API -->|PDO Queries| MySQL
    end

    subgraph Phase 2: Fully Decoupled (Target)
        Nextjs_FE[Next.js Headless FE] -->|HTTP REST/GraphQL| Node_API[Node.js / Express or NestJS]
        Node_API -->|ORM: Prisma / TypeORM| MySQL
    end
```

---

## 📈 Phased Migration Roadmap

### Phase 1: Decoupling the Frontend (PHP API + React)
Instead of rewrite-all-at-once, we keep the stable database, business rules, and upload configurations inside PHP, but refactor the UI.

1. **API Refactoring**: 
   - Convert current controllers in PHP into lightweight REST API endpoints in the `/api/` folder.
   - Return structured JSON payloads (e.g., product lists, category trees) instead of HTML templates.
   - Replace PHP sessions in API routing with stateless JWT (JSON Web Tokens) or maintain secure HttpOnly session cookies.
2. **React/Next.js Bootstrap**:
   - Initialize a Next.js application in TypeScript.
   - **Why Next.js?** Since this is a product catalog website, pure React SPAs (Client-Side Rendered) will lose SEO rankings. Next.js supports **Static Site Generation (SSG)** and **Incremental Static Regeneration (ISR)** to compile fast, crawler-friendly HTML pages.
3. **Consuming API**:
   - Develop frontend components (product cards, galleries, filters) consuming the `/api/products` endpoints.

### Phase 2: Migrating the Backend (PHP -> Node.js)
Once the frontend is fully decoupled, we can replace the PHP API layer with Node.js without touching the React code.

1. **Framework Selection**: Use **Express.js** (for simplicity/lightweight setup) or **NestJS** (for large enterprise architectural modularity).
2. **Database Layer (ORM)**: Use **Prisma ORM** or **TypeORM** in TypeScript.
   - Introspect the existing MySQL schema to generate TypeScript models automatically (`prisma db pull`). This guarantees zero database migration downtime and preserves original IDs, foreign keys, and SEO indexes.
3. **Rewrite Endpoints**: Translate PHP controller logic (prepared statements, file checks, audit logging) into Node.js controllers/services.
4. **Switch Traffic**: Repoint Next.js API requests to the Node.js port.

---

## ⚡ API-First Refactoring Strategy

To prepare our current codebase for this future evolution:
- **Consistent Schema Mapping**: Keep using exact table structures. Node.js ORMs map perfectly to traditional MySQL tables.
- **Stateless Asset Storage**: Keep uploaded files in `/uploads/` under a public route or migrate to an S3-compatible cloud storage bucket (AWS S3, Cloudflare R2). This ensures that when the backend shifts to Node.js, the image URLs remain unchanged.
- **RESTful Routing Convention**: Keep routes organized:
  - `GET /api/products` (List with filters/search)
  - `GET /api/products/:slug` (Detail)
  - `POST /api/contact` (Submission)
  - `POST /api/auth/login` (Admin Auth)

---

## ⚠️ Risk Analysis & Mitigation

### 1. Search Engine Optimization (SEO) Drop
* **Risk**: Switching to React Client-Side Rendering (CSR) might cause Googlebot and other crawlers to see blank screens during indexing, losing search result rankings.
* **Mitigation**: Use **Next.js** for static pre-rendering (SSG) for product details `/product/[slug]`. Utilize ISR (`revalidate: 3600`) so that when administrators add/edit products, the static page automatically updates in the background without rebuilds.

### 2. Session vs. Token Management
* **Risk**: Node.js APIs usually run stateless (JWT) while our PHP code uses session cookies (`PHPSESSID`). Toggling them causes login drops.
* **Mitigation**: Implement **JWT-in-Cookie** (storing JWTs inside HTTP-only, secure, SameSite cookies). Both PHP and Node.js can read, verify, and write these cookies, ensuring a seamless user authentication flow during hybrid stages.

### 3. File Paths and Media Upload Security
* **Risk**: Node.js and PHP run on different directory structures, leading to broken image paths.
* **Mitigation**: Mount uploads to a shared volume, serve them via Apache/Nginx reverse proxy directly from `/uploads/` directory, or decouple files completely to a Content Delivery Network (CDN) / Object Storage.

---

## 🛠️ Recommended Tech Stack Evolution

| Component | Current Monolith | Transitional Stage | Target Stack |
| :--- | :--- | :--- | :--- |
| **Frontend** | Native PHP + HTML5 + CSS3 | Next.js (TS) + TailwindCSS | Next.js (TS) + TailwindCSS |
| **Backend** | Native PHP 8+ | PHP 8+ (JSON REST API) | Node.js (NestJS or Express) |
| **Database** | MySQL 5.7+ / MariaDB | MySQL 5.7+ / MariaDB | MySQL 5.7+ / MariaDB |
| **ORM / Query**| PDO Prepared Statements | PDO Prepared Statements | Prisma ORM or TypeORM |
| **Auth** | PHP Session | JWT in Secure Cookie | JWT in Secure Cookie |
| **Storage** | Local `/uploads` | Local `/uploads` / S3 | S3-Compatible Cloud Storage |
