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
```

## 6. Configuration

### Environment Variables (Backend `.env`)
```
OPENROUTER_API_KEY=your_api_key_here
APP_URL=http://localhost:8000
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
│   │   │   └── TailorController.php
│   │   └── Services/
│   │       ├── ResumeParserService.php
│   │       ├── TailorService.php
│   │       └── PdfGeneratorService.php
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
