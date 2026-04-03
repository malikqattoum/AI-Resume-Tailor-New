# AI Resume Tailor - Production Deployment Guide

## Overview

This document outlines the steps required to deploy the AI Resume Tailor application to production.

## Backend (Laravel)

### 1. Environment Configuration

Copy `.env.example` to `.env` and configure the following:

```env
# Application
APP_NAME="AI Resume Tailor"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-api-domain.com

# Generate new app key
APP_KEY=base64:GENERATED_KEY

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ai_resume_tailor
DB_USERNAME=your_db_user
DB_PASSWORD=your_secure_password

# Cache & Queue (use Redis in production)
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# Logging
LOG_LEVEL=info

# OpenRouter API Key (Required)
OPENROUTER_API_KEY=sk_or_your_actual_key

# CORS - Your production domains
CORS_ALLOWED_ORIGINS=https://your-frontend-domain.com,https://your-app.com

# Sanctum
SANCTUM_STATEFUL_DOMAINS=your-frontend-domain.com,your-app.com
```

### 2. Generate Application Key

```bash
cd backend
php artisan key:generate
```

### 3. Run Migrations

```bash
php artisan migrate
```

### 4. Create Storage Link

```bash
php artisan storage:link
```

### 5. Configure Web Server (Nginx/Apache)

**Nginx:**
```nginx
server {
    listen 80;
    server_name api.yourdomain.com;
    root /path/to/backend/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### 6. Security Checklist

- [ ] Set `APP_DEBUG=false`
- [ ] Use HTTPS with valid SSL certificate
- [ ] Configure firewall rules
- [ ] Use non-root database user with limited privileges
- [ ] Set proper file permissions (storage/ should be writable)
- [ ] Configure rate limiting at web server level

## Frontend (Flutter)

### 1. Configure Production API URL

For Android release builds, set the API base URL:

```dart
// In lib/services/api_service.dart
static const String baseUrl = 'https://your-api-domain.com/api';
```

Or use environment variables:
```dart
static const String baseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'http://10.0.2.2:8000/api',
);
```

### 2. Build for Production

```bash
cd frontend

# Android
flutter build apk --release

# iOS
flutter build ios --release
```

### 3. Configure CORS on Backend

Make sure your production domains are in `CORS_ALLOWED_ORIGINS`:

```env
CORS_ALLOWED_ORIGINS=https://your-frontend-domain.com,https://your-app.com
```

### 4. Update App Store / Play Store Listings

- Privacy Policy URL (required)
- Terms of Service URL
- Support contact email

## API Endpoints

### Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/register` | Register new user |
| POST | `/api/auth/login` | Login user |
| POST | `/api/auth/logout` | Logout (requires auth) |
| GET | `/api/auth/user` | Get current user |

### Resume Operations (Requires Auth)

| Method | Endpoint | Rate Limit | Description |
|--------|----------|-------------|-------------|
| POST | `/api/resume/upload` | 20/min | Upload PDF resume |
| GET | `/api/resume/{id}` | - | Get resume details |

### Tailoring (Requires Auth)

| Method | Endpoint | Rate Limit | Description |
|--------|----------|-------------|-------------|
| POST | `/api/tailor` | 5/min | Generate tailored resume |
| GET | `/api/tailored/{id}` | - | Get tailoring result |
| GET | `/api/download/{opaque_id}` | 30/min | Download PDF |

## Rate Limiting

The following rate limits are enforced:

- **Resume Upload**: 20 requests per minute per user
- **Tailor Request**: 5 requests per minute per user (expensive operation)
- **Download**: 30 requests per minute per user

## Security Features

1. **Authentication**: Laravel Sanctum with bearer tokens
2. **Opaque Download IDs**: Internal storage paths are never exposed
3. **User Isolation**: Users can only access their own resumes and results
4. **Input Validation**: All inputs validated and sanitized
5. **Rate Limiting**: Prevents abuse of expensive operations

## Monitoring

Set up monitoring for:

- API response times
- Error rates (especially 5xx)
- OpenRouter API costs/usage
- Storage usage
- Database connections

## Troubleshooting

### Common Issues

1. **CORS Errors**: Ensure frontend domain is in `CORS_ALLOWED_ORIGINS`
2. **401 Unauthorized**: Token expired or not included in request
3. **Rate Limited (429)**: Wait before retrying, implement exponential backoff
4. **PDF Generation Failed**: Check storage permissions and disk space
