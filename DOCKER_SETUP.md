# DevelopmentTranslation Bridge - Local Development Setup

## Quick Start with Docker

This setup provides a complete local WordPress development environment using Docker.

---

## Prerequisites

- **Docker Desktop** installed and running
- **Node.js** 20.19+ (for the React visual interface)
- **Git** (already have this)

---

## Setup Instructions

### 1. Start WordPress Server

From the project root directory:

```bash
docker-compose up -d
```

This will start:
- **WordPress** on `http://localhost:8080`
- **MySQL** on port 3306
- **phpMyAdmin** on `http://localhost:8081` (optional database GUI)

### 2. Install WordPress

1. Open browser to `http://localhost:8080`
2. Select language: **English**
3. Click **Let's go!**
4. Database info is pre-configured, click **Submit**
5. Click **Run the installation**
6. Fill in site information:
   - Site Title: `DevelopmentTranslation Bridge Dev`
   - Username: `admin` (or your choice)
   - Password: Create a strong password
   - Email: Your email
7. Click **Install WordPress**
8. Log in with your credentials

### 3. Activate the Theme

1. Go to **Appearance** → **Themes**
2. Find **DevelopmentTranslation Bridge**
3. Click **Activate**

### 4. Start the Visual Interface Dev Server

In a **new terminal window**:

```bash
cd admin/
npm run dev
```

The Vite dev server runs on `http://localhost:3000`

### 5. Access the Visual Interface

1. In WordPress admin, look for **Visual Interface** in the left menu
2. Click it to open the full-screen editor
3. The interface loads with HMR (Hot Module Replacement) from Vite

---

## Development Workflow

### Terminal 1: WordPress (Docker)
```bash
docker-compose up -d
# Runs in background
```

### Terminal 2: Visual Interface (Vite)
```bash
cd admin/
npm run dev
# Runs on http://localhost:3000
```

### Access Points

| Service | URL | Purpose |
|---------|-----|---------|
| WordPress | http://localhost:8080 | Main WordPress site |
| WP Admin | http://localhost:8080/wp-admin | Admin dashboard |
| Visual Interface | WP Admin → Visual Interface | React app with HMR |
| phpMyAdmin | http://localhost:8081 | Database management |
| REST API | http://localhost:8080/wp-json/devtb/v2/ | API endpoints |

---

## Useful Commands

### Docker Management

```bash
# Start WordPress
docker-compose up -d

# Stop WordPress
docker-compose down

# View logs
docker-compose logs -f wordpress

# Restart WordPress (after code changes)
docker-compose restart wordpress

# Stop and remove everything (including database)
docker-compose down -v
```

### Database Management

```bash
# Access MySQL directly
docker exec -it devtb-mysql mysql -u wordpress -pwordpress wordpress

# Backup database
docker exec devtb-mysql mysqldump -u wordpress -pwordpress wordpress > backup.sql

# Restore database
docker exec -i devtb-mysql mysql -u wordpress -pwordpress wordpress < backup.sql
```

### WordPress CLI (WP-CLI)

```bash
# Access WordPress container
docker exec -it devtb-wordpress bash

# Inside container, use WP-CLI
wp --allow-root plugin list
wp --allow-root theme list
wp --allow-root user list
```

---

## File Structure

The theme is **mounted** into the WordPress container:

```
Local: /Users/coryhubbell/Desktop/Code Projects/Wordpress-bootstrap-claude/
         ↓ (mounted to)
Container: /var/www/html/wp-content/themes/development-translation-bridge/
```

**This means:**
- ✅ Edit files locally in your IDE
- ✅ Changes appear immediately in WordPress
- ✅ No need to copy files

---

## Testing the Visual Interface

### Test Translation

1. Access **Visual Interface** in WordPress admin
2. Paste this HTML in the source editor:

```html
<div class="container">
  <div class="row">
    <div class="col-md-6">
      <h1>Hello World</h1>
      <p>Testing the Translation Bridge</p>
      <button class="btn btn-primary">Click Me</button>
    </div>
  </div>
</div>
```

3. Source Framework: **Bootstrap**
4. Target Framework: **Elementor**
5. Click **Translate**

### Expected Result

- Right panel shows Elementor JSON
- Live preview renders the HTML
- Browser console shows API calls

---

## Troubleshooting

### WordPress won't start

```bash
# Check if ports are already in use
lsof -i :8080  # WordPress
lsof -i :3306  # MySQL

# Use different ports if needed (edit docker-compose.yml)
```

### Can't see the theme

```bash
# Check theme is mounted
docker exec -it devtb-wordpress ls -la /var/www/html/wp-content/themes/

# Should see: development-translation-bridge/
```

### Translation not working

```bash
# Check WordPress logs
docker-compose logs -f wordpress

# Check if REST API is accessible
curl http://localhost:8080/wp-json/devtb/v2/frameworks
```

### Visual Interface shows blank page

1. Check Vite dev server is running (`http://localhost:3000`)
2. Check browser console for errors
3. Verify `WP_DEBUG` is enabled (see logs)

---

## Clean Slate Reset

To start completely fresh:

```bash
# Stop and remove everything
docker-compose down -v

# Remove all volumes (this deletes the database!)
docker volume rm $(docker volume ls -q | grep devtb)

# Start fresh
docker-compose up -d

# Reinstall WordPress (go to http://localhost:8080)
```

---

## Production Build

When ready to test production build:

```bash
# Build the React app
cd admin/
npm run build

# In WordPress, the built assets will be served from admin/dist/
# Turn off WP_DEBUG in docker-compose.yml to test production mode
```

---

## Next Steps

1. ✅ Start Docker WordPress: `docker-compose up -d`
2. ✅ Install WordPress: `http://localhost:8080`
3. ✅ Activate theme
4. ✅ Start Vite dev server: `npm run dev`
5. ✅ Access Visual Interface in WP Admin
6. 🚀 Start translating!

---

**DevelopmentTranslation Bridge** - Local Development Environment
Ready to build! 💪
