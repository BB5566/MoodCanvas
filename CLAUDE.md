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
- **AI Integration**: Direct API calls from `DiaryController` (Replicate for images, DeepSeek for text)
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
- `?action=generate_card_content` → DiaryController::generateCardContent() (async image + quote generation)

### Database Schema
Core tables managed manually:
- `users` - User authentication and profiles
- `diaries` - Journal entries with mood scores and metadata
- No migration system - schema changes require manual SQL

### AI Service Architecture
AI calls are made directly from `DiaryController` via cURL (no adapter classes):

**Image generation — Replicate**
- Calls `https://api.replicate.com/v1/predictions` (see `DiaryController::callAIImageGeneration()`)
- Model is pinned by a specific version hash in the request payload
- Auth via `REPLICATE_API_KEY` env var
- Flow: create prediction → poll until `succeeded` → download the resulting image to `public/storage/generated_images/`

**Text generation — Pioneer API (DeepSeek model)**
- Calls `https://api.pioneer.ai/v1/chat/completions` (OpenAI-compatible), model `deepseek-v4-pro`
- Used by `DiaryController::callAIQuoteGeneration()` (card mood phrase) and `callAIInsightGeneration()` (dashboard AI insight)
- Auth via `PIONEER_API_KEY` env var

### Configuration Management
Environment variables loaded from `.env` file via custom parser in `config.php`:
- Database credentials (DB_HOST, DB_NAME, DB_USER, DB_PASS)
- AI service keys (REPLICATE_API_KEY for images, PIONEER_API_KEY for text via DeepSeek model)
- Admin tooling (ADMIN_PASSWORD — must be set to use `public/image-resize.php`)

### Authentication Flow
1. Session validation in `public/index.php` checks user existence in database
2. Invalid sessions are cleared and redirected to login
3. Controllers verify session in constructor before processing requests

### Image Generation Pipeline
1. User writes diary entry
2. `DiaryController::buildImagePrompt()` builds an English prompt from content, mood, and chosen style
3. **Replicate** generates the image (model pinned by version hash), polled until complete
4. **Pioneer (DeepSeek model)** generates a short mood phrase for the card back
5. Generated images downloaded and stored in `public/storage/generated_images/`

### Error Handling and Logging
- Custom `logMessage()` function writes to `/logs/` directory
- PHP error logging configured
- Try-catch blocks in controllers with user-friendly error responses
- AI service failures gracefully degrade (no image vs error)

## Important Notes

### AI Service Dependencies
- **Images**: Replicate (model pinned by version hash) via `REPLICATE_API_KEY`
- **Text/Quotes/Insight**: Pioneer API (`api.pioneer.ai`, DeepSeek `deepseek-v4-pro` model) via `PIONEER_API_KEY`
- **Authentication**: Bearer API keys for both services
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