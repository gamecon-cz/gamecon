# Symfony Migration Guide

This document describes the hybrid Symfony + Legacy PHP setup for gradually migrating the GameCon admin system to Symfony.

## What's Been Implemented

### ✅ Completed Setup
- **Symfony Framework**: Core Symfony components installed and configured
- **Hybrid Routing**: Admin routes first check Symfony, then fall back to legacy system
- **Directory Structure**: Clean separation in `/symfony/` directory
- **Admin Integration**: Legacy authentication and session handling preserved
- **Namespace Setup**: `App\` namespace for Symfony controllers and services

### 📁 Directory Structure
```
gamecon/
├── symfony/                    # New Symfony application
│   ├── config/                 # Symfony configuration
│   │   ├── packages/framework.yaml
│   │   ├── routes.yaml         # Route definitions
│   │   └── services.yaml       # Service configuration
│   ├── src/                    # Symfony source code
│   │   ├── Controller/         # Symfony controllers
│   │   ├── Service/            # Symfony services
│   │   └── Kernel.php          # Symfony kernel
│   └── var/                    # Cache and logs
├── admin/                      # Legacy admin system
│   ├── index.php              # Modified with Symfony integration
│   └── symfony.php            # Symfony front controller
└── .env.local                 # Environment configuration
```

## How It Works

### Routing Flow
1. Request comes to `/admin/some-route`
2. `admin/index.php` parses the route using existing `parseRoute()` function
3. **NEW**: Check if route is in Symfony route list (`dashboard`, `users`, `activities`, `symfony-test`)
4. If Symfony route exists → handle with Symfony
5. If not → fall back to legacy routing system
6. If neither exists → 404

### Testing the System

#### Symfony Routes (NEW)
- `/admin/symfony-test` - Symfony test page
- `/admin/dashboard` - Symfony admin dashboard
- `/admin/users` - User management (Symfony)
- `/admin/activities` - Activity management (Symfony)
- `/admin/api/test` - Symfony API test endpoint

#### Legacy Routes (Still Working)
- `/admin/` - Default admin (redirects based on user permissions)
- `/admin/uzivatel` - Legacy user management
- `/admin/aktivity` - Legacy activity management
- All existing admin modules continue to work unchanged

## Adding New Symfony Routes

### 1. Define Route in `symfony/config/routes.yaml`
```yaml
admin_new_feature:
    path: /admin/new-feature
    controller: App\Controller\AdminController::newFeature
```

### 2. Add Route to Symfony Check in `admin/index.php`
```php
if (in_array($stranka, ['dashboard', 'users', 'activities', 'symfony-test', 'new-feature']) ||
    ($stranka === 'api' && $podstranka === 'test')) {
```

### 3. Create Controller Method
```php
// In symfony/src/Controller/AdminController.php
public function newFeature(): Response
{
    if (!$this->legacySession->hasAdminAccess()) {
        return new RedirectResponse('/admin/login.php');
    }

    return new Response('Your new feature content');
}
```

## Migration Strategy

### Phase 1: Basic Routes (COMPLETED)
- ✅ Symfony infrastructure setup
- ✅ Test routes working
- ✅ Legacy fallback mechanism
- ✅ Admin authentication integration

### Phase 2: Simple Modules (NEXT)
Migrate simple admin modules that don't have complex dependencies:
- Reports and statistics
- Simple CRUD operations
- API endpoints

### Phase 3: Complex Modules
Gradually migrate complex modules:
- Activity management
- User management
- Financial operations

### Phase 4: Full Migration
- Remove legacy routing
- Clean up old code
- Optimize Symfony setup

## Development Guidelines

### Authentication
- Use `LegacySessionService` to check user permissions
- Always redirect unauthorized users to `/admin/login.php`
- Preserve existing session handling during transition

### Legacy Integration
- Don't modify legacy code unnecessarily
- Use bridge services to access legacy functionality
- Maintain backward compatibility

### Code Organization
- Controllers: `symfony/src/Controller/`
- Services: `symfony/src/Service/`
- Configuration: `symfony/config/`
- Follow Symfony best practices

## Environment Configuration

The system uses `.env.local` for configuration:
```bash
APP_SECRET=your_secret_key_here
```

For production, set proper environment variables and change the secret key.

## Benefits of This Approach

1. **Zero Downtime**: Legacy system continues working
2. **Gradual Migration**: Migrate modules one by one
3. **Risk Mitigation**: Easy rollback if needed
4. **Team Collaboration**: Different features can be migrated by different developers
5. **Testing**: Each migrated module can be thoroughly tested before going live

## Next Steps

1. **Migrate First Module**: Choose a simple admin module to migrate completely
2. **Database Integration**: Add Doctrine ORM for new Symfony components
3. **Template System**: Integrate Twig templates with existing design
4. **API Modernization**: Migrate API endpoints to REST/JSON
5. **Authentication Upgrade**: Gradually move to Symfony Security component