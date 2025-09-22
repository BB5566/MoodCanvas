# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

MoodCanvas is an AI-powered emotional diary application that combines traditional journaling with modern AI technology. It transforms written emotions into visual art through intelligent image generation and emotional analysis, creating a unique journaling experience.

## Development Commands

Since this is a PHP project with minimal build tooling, development is primarily file-based:

### Local Development
```bash
# Start PHP development server
php -S localhost:8000

# Check PHP syntax
php -l path/to/file.php

# Install dependencies
composer install

# Database setup (manual via phpMyAdmin or MySQL CLI)
# No migration system - database schema is managed manually
```

### Testing
- No automated test framework is configured
- Manual testing through web interface
- Check logs in `/logs/` directory for debugging

## Architecture Overview

### Core Structure
- **Entry Point**: `index.php` redirects to `public/index.php`
- **MVC Pattern**: Custom implementation with manual routing
- **Database**: MySQL with PDO, no ORM
- **AI Integration**: Adapter pattern for multiple AI services
- **Session Management**: PHP sessions with database validation

### Key Directories
```
app/
├── controllers/     # MVC controllers (Auth, Diary, AI)
├── models/         # Data models and AI adapters
└── views/          # PHP template files

config/
├── config.php      # Main configuration and environment loading
└── autoloader.php  # Custom class autoloader

public/             # Web root with assets and main entry point
logs/              # Application and error logs
```

### Routing System
Simple query-parameter based routing in `public/index.php`:
- `?action=login` → AuthController::login()
- `?action=dashboard` → DiaryController::dashboard()
- `?action=api_generate_image` → AIController::generateImage()

### Database Schema
Core tables managed manually:
- `users` - User authentication and profiles
- `diaries` - Journal entries with mood scores and metadata
- No migration system - schema changes require manual SQL

### AI Service Architecture
Uses Adapter pattern for AI integration:

**GeminiImageAdapter** (`app/models/GeminiImageAdapter.php`)
- Google Vertex AI for image generation
- Requires GCP service account JSON file
- Primary image generation service

**GeminiTextAdapter** (`app/models/GeminiTextAdapter.php`)
- Google Gemini for text generation and prompt optimization
- Fallback text processing

**PerplexityAdapter** (`app/models/PerplexityAdapter.php`)
- Perplexity AI for quote generation and prompt optimization
- Primary text generation service

### Configuration Management
Environment variables loaded from `.env` file via custom parser in `config.php`:
- Database credentials (DB_HOST, DB_NAME, DB_USER, DB_PASS)
- AI service keys (PERPLEXITY_API_KEY, GEMINI_API_KEY)
- GCP configuration (GCP_PROJECT_ID, GCP_REGION)
- Model configurations (GEMINI_TEXT_MODEL, etc.)

### Authentication Flow
1. Session validation in `public/index.php` checks user existence in database
2. Invalid sessions are cleared and redirected to login
3. Controllers verify session in constructor before processing requests

### Image Generation Pipeline
1. User writes diary entry
2. **GeminiTextAdapter** optimizes prompt based on content and style
3. **GeminiImageAdapter** generates image via Vertex AI
4. Fallback: None (StabilityAI was removed)
5. Images stored in `public/images/` directory

### Error Handling and Logging
- Custom `logMessage()` function writes to `/logs/` directory
- PHP error logging configured
- Try-catch blocks in controllers with user-friendly error responses
- AI service failures gracefully degrade (no image vs error)

## Important Notes

### AI Service Dependencies
- **Primary**: Google Vertex AI for images, Gemini for text, Perplexity for quotes
- **Authentication**: GCP uses service account JSON file, others use API keys
- **Graceful Degradation**: Failed AI calls return null/empty rather than errors

### Database Considerations
- No ORM or query builder - uses raw PDO prepared statements
- Manual schema management - no migrations
- Session validation queries on every request

### File Structure Patterns
- Models contain both data access and business logic
- Controllers handle HTTP requests and coordinate between models
- Views are simple PHP templates with minimal logic
- Custom autoloader uses namespace-to-directory mapping

### Development Workflow
1. Modify PHP files directly
2. Refresh browser to see changes
3. Check `/logs/` for errors
4. Use browser dev tools for frontend debugging
5. Database changes require manual SQL execution