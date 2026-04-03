# AI Resume Tailor — Specification

## 1. Project Overview

**Name**: AI Resume Tailor
**Type**: Full-stack mobile application (Flutter + Laravel)
**Core Functionality**: AI-powered resume customization tool that tailors a user's base resume PDF for specific job applications, generating a tailored resume and cover letter in seconds.

## 2. Technology Stack & Choices

### Frontend
- **Framework**: Flutter 3.x (Dart)
- **State Management**: Riverpod
- **Key Packages**:
  - `file_picker` — PDF selection
  - `http` + `http_parser` — API calls
  - `flutter_spinkit` — Loading animations
  - `share_plus` — Share/download files
  - `flutter_pdfview` — PDF preview
  - `url_launcher` — Open URLs
- **Target Platforms**: Android, iOS, Web

### Backend
- **Framework**: Laravel 8.x (PHP 8.2+)
- **Key Packages**:
  - `smalot/pdfparser` — PDF text extraction
  - `barryvdh/laravel-dompdf` — PDF generation
  - `guzzlehttp/guzzle` — HTTP client
  - `stripe/stripe-php` — Stripe payment/subscription integration
- **LLM**: OpenRouter API (supports OpenAI, Anthropic, Meta, etc.)

### Architecture
- REST API with JSON responses
- File storage: Laravel `storage/app/` directory
- Mobile app communicates with backend via HTTP

## 3. Feature List

### Mobile App
- [ ] Home screen with hero CTA
- [ ] PDF resume upload (file picker)
- [ ] Job description input (job title, company, description text)
- [ ] Animated processing screen with progress
- [ ] Results screen with tailored resume + cover letter
- [ ] Download/share functionality for generated PDFs
- [ ] Cover letter preview screen

### Backend API
- [ ] `POST /api/resume/upload` — Upload PDF resume
- [ ] `POST /api/tailor` — Generate tailored resume + cover letter
- [ ] `GET /api/tailored/{id}` — Get tailoring result
- [ ] `GET /api/download/{path}` — Download generated PDF
- [ ] `GET /api/health` — Health check
- [ ] `GET /api/subscription` — Get current subscription plan and usage
- [ ] `POST /api/subscription/upgrade` — Upgrade to paid plan via Stripe Checkout
- [ ] `POST /api/subscription/create-portal-session` — Create Stripe Customer Portal session
- [ ] `POST /api/webhooks/stripe` — Handle Stripe webhook events

### Core Services (Backend)
- [ ] `ResumeParserService` — Extract text from PDF
- [ ] `TailorService` — OpenRouter LLM integration
- [ ] `PdfGeneratorService` — Generate styled PDFs

## 4. UI/UX Design Direction

### Visual Style
- Material Design 3
- Clean, professional, trust-inspiring
- Blue color scheme (`#1E3A8A` primary, `#3B82F6` accent)

### Color Scheme
- Primary: Deep Blue `#1E3A8A`
- Accent: Bright Blue `#3B82F6`
- Success: Green `#10B981`
- Purple accent for cover letter: `#8B5CF6`

### Layout
- Linear flow: Home → Upload → Job Details → Processing → Results
- Card-based content presentation
- Gradient headers on Home and Processing screens

## 5. Backend API Design

### Endpoints

| Method | URI | Description |
|--------|-----|-------------|
| GET | `/api/health` | Health check |
| POST | `/api/resume/upload` | Upload PDF (multipart/form-data) |
| GET | `/api/resume/{id}` | Get resume info |
| POST | `/api/tailor` | Generate tailored resume + cover letter |
| GET | `/api/tailored/{id}` | Get tailoring result |
| GET | `/api/download/{path}` | Download file |
| GET | `/api/subscription` | Get current subscription plan, status, usage |
| POST | `/api/subscription/upgrade` | Upgrade to paid plan (body: { "plan": "basic" | "pro" }) |
| POST | `/api/subscription/create-portal-session` | Create Stripe Customer Portal session URL |
| POST | `/api/webhooks/stripe` | Stripe webhook event handler |

### Request/Response Examples

**POST /api/resume/upload**
```json
// Request: multipart/form-data with 'resume' field
// Response:
{
  "success": true,
  "data": {
    "resume_id": "uuid",
    "original_filename": "resume.pdf",
    "stored_path": "resumes/uuid.pdf"
  }
}
```

**POST /api/tailor**
```json
// Request:
{
  "resume_id": "uuid",
  "job_title": "Software Engineer",
  "company": "Acme Corp",
  "job_description": "We are looking for..."
}
// Response:
{
  "success": true,
  "data": {
    "result_id": "uuid",
    "tailored_resume_url": "http://.../api/download/tailored/...",
    "cover_letter_url": "http://.../api/download/tailored/..."
  }
}

// Error: Usage limit reached (HTTP 402)
{
  "success": false,
  "message": "Monthly request limit reached.",
  "error": "payment_required",
  "upgrade_url": "https://billing.stripe.com/..."
}
```

## 6. Configuration

### Environment Variables (Backend `.env`)
```
OPENROUTER_API_KEY=your_api_key_here
APP_URL=http://localhost:8000
STRIPE_SECRET_KEY=your_stripe_secret_key
STRIPE_WEBHOOK_SECRET=your_stripe_webhook_secret
STRIPE_PRICE_BASIC=price_...  # $4.99/month price ID from Stripe Dashboard
STRIPE_PRICE_PRO=price_...   # $14.99/month price ID from Stripe Dashboard
```

### API Base URL (Flutter)
```
http://10.0.2.2:8000/api  # Android emulator
http://localhost:8000/api # Web/iOS simulator
```

## 7. Project Structure

```
AI-Resume-Tailor/
├── backend/
│   ├── app/
│   │   ├── Http/Controllers/
│   │   │   ├── ResumeController.php
│   │   │   ├── TailorController.php
│   │   │   ├── SubscriptionController.php
│   │   │   └── WebhookController.php
│   │   ├── Models/
│   │   │   ├── Subscription.php
│   │   │   └── SubscriptionUsageLog.php
│   │   └── Services/
│   │       ├── ResumeParserService.php
│   │       ├── TailorService.php
│   │       ├── PdfGeneratorService.php
│   │       └── StripeService.php
│   ├── routes/api.php
│   ├── storage/app/resumes/
│   ├── storage/app/tailored/
│   └── storage/app/results/
├── frontend/
│   └── lib/
│       ├── main.dart
│       ├── screens/
│       │   ├── home_screen.dart
│       │   ├── upload_resume_screen.dart
│       │   ├── job_description_screen.dart
│       │   ├── processing_screen.dart
│       │   ├── results_screen.dart
│       │   └── cover_letter_screen.dart
│       ├── services/
│       │   └── api_service.dart
│       └── widgets/
└── SPEC.md
```

## 8. Subscription Plans

| Tier | Price | Monthly Requests | Features |
|------|-------|-----------------|----------|
| Free | $0/mo | 3 tailors/mo | Full tailoring + cover letter |
| Basic | $4.99/mo | 20 tailors/mo | Full tailoring + cover letter |
| Pro | $14.99/mo | Unlimited | Full tailoring + cover letter + priority |

### Stripe Integration
- Products and prices are configured manually in the Stripe Dashboard
- Upgrade flow uses Stripe Checkout Session
- Subscription management uses Stripe Customer Portal
- Webhooks sync subscription status with local database
