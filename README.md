# Football Academy ERP Backend

A Laravel 11 REST API backend for managing football academies. The system helps academies organize their work with features for team management, player development tracking, match management, and more.

## Requirements

- PHP 8.2 or higher
- MySQL 8.0 or higher
- Composer 2.0 or higher
- Node.js 18.0 or higher (for asset compilation)

## API Authentication

The API uses JWT (JSON Web Token) authentication. All API requests except login/register require a valid JWT token in the Authorization header:

```
Authorization: Bearer <your_jwt_token>
```

### Authentication Flow

1. **Login** - Get JWT token:
```http
POST /api/v1/login
Content-Type: application/json

{
    "email": "user@example.com",
    "password": "password"
}
```

2. **Phone Login** - Alternative login with phone:
```http
POST /api/v1/login
Content-Type: application/json

{
    "phone": "+1234567890"
}
```

3. **OTP Verification** - Required for phone login:
```http
POST /api/v1/verify-otp
Content-Type: application/json

{
    "phone": "+1234567890",
    "otp": "123456"
}
```

4. **Register New User**:
```http
POST /api/v1/register
Content-Type: application/json

{
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "password": "password",
    "password_confirmation": "password"
}
```

## API Endpoints

### Organizations

```http
# List organizations
GET /api/v1/organizations

# Create organization
POST /api/v1/organizations
{
    "name": "Academy Name",
    "description": "Academy description",
    "settings": {
        "timezone": "UTC",
        "currency": "USD"
    }
}

# Get organization details
GET /api/v1/organizations/{id}

# Update organization
PUT /api/v1/organizations/{id}

# Get organization statistics
GET /api/v1/organizations/{id}/stats

# List organization players
GET /api/v1/organizations/{id}/players

# List organization coaches
GET /api/v1/organizations/{id}/coaches

# Invite user to organization
POST /api/v1/organizations/{id}/invite
{
    "email": "user@example.com",
    "role": "coach"
}
```

### Teams

```http
# List teams
GET /api/v1/teams

# Create team
POST /api/v1/teams
{
    "name": "Team Name",
    "description": "Team description",
    "organization_id": 1,
    "tier_id": 1
}

# Get team details
GET /api/v1/teams/{id}

# Update team
PUT /api/v1/teams/{id}

# Add player to team
POST /api/v1/teams/{id}/players/{playerId}

# Remove player from team
DELETE /api/v1/teams/{id}/players/{playerId}

# Get team statistics
GET /api/v1/teams/{id}/stats

# Get team schedule
GET /api/v1/teams/{id}/schedule
```

### Players

```http
# Get player details
GET /api/v1/players/{id}

# Get player statistics
GET /api/v1/players/{id}/stats

# Get player skills
GET /api/v1/players/{id}/skills

# Update skill target
PUT /api/v1/players/{id}/skills/{skillId}
{
    "target_value": 85
}

# Get attendance history
GET /api/v1/players/{id}/attendance
```

### Matches

```http
# List matches
GET /api/v1/matches

# Create match
POST /api/v1/matches
{
    "home_team_id": 1,
    "away_team_id": 2,
    "scheduled_at": "2024-03-20T15:00:00Z",
    "venue": "Stadium Name"
}

# Get match details
GET /api/v1/matches/{id}

# Update match
PUT /api/v1/matches/{id}

# Record match event
POST /api/v1/matches/{id}/events
{
    "type": "goal",
    "player_id": 1,
    "minute": 35,
    "details": {}
}

# Get match lineup
GET /api/v1/matches/{id}/lineup

# Update match lineup
PUT /api/v1/matches/{id}/lineup
```

### Evaluations

```http
# List evaluations
GET /api/v1/evaluations

# Create evaluation
POST /api/v1/evaluations
{
    "player_id": 1,
    "evaluator_id": 2,
    "skills": {
        "technical": 85,
        "tactical": 75,
        "physical": 80
    },
    "notes": "Player shows good progress"
}

# Get evaluation details
GET /api/v1/evaluations/{id}

# Update evaluation
PUT /api/v1/evaluations/{id}

# Delete evaluation
DELETE /api/v1/evaluations/{id}
```

### Team Schedule

```http
# List team schedules
GET /api/v1/team-schedules

# Create schedule
POST /api/v1/team-schedules
{
    "team_id": 1,
    "type": "training",
    "starts_at": "2024-03-20T15:00:00Z",
    "ends_at": "2024-03-20T16:30:00Z",
    "location": "Training Ground"
}

# Mark attendance
POST /api/v1/team-schedules/{id}/attendance
{
    "player_id": 1,
    "status": "present"
}
```

### Notifications

```http
# List notifications
GET /api/v1/notifications

# Mark notifications as read
POST /api/v1/notifications/mark-read
{
    "notification_ids": [1, 2, 3]
}

# Mark all as read
POST /api/v1/notifications/mark-all-read

# Clear notifications
DELETE /api/v1/notifications/clear
```

### Subscriptions

```http
# List subscription plans
GET /api/v1/subscriptions/plans

# Subscribe to plan
POST /api/v1/subscriptions/subscribe
{
    "plan_id": 1,
    "payment_method": "card"
}

# Cancel subscription
POST /api/v1/subscriptions/cancel

# Get subscription status
GET /api/v1/subscriptions/status
```

## Response Format

All API responses follow a standard format:

```json
{
    "success": true,
    "data": {
        // Response data here
    },
    "message": "Optional message",
    "errors": {
        // Validation errors if any
    }
}
```

### Error Responses

```json
{
    "success": false,
    "message": "Error message",
    "errors": {
        "field": ["Error description"]
    }
}
```

Common HTTP status codes:
- 200: Success
- 201: Created
- 400: Bad Request
- 401: Unauthorized
- 403: Forbidden
- 404: Not Found
- 422: Validation Error
- 500: Server Error

## User Roles & Permissions

The system implements role-based access control with the following roles:

- **Superadmin**: Full system access
- **Manager**: Organization-level administration
- **Coach**: Team management and player evaluation
- **Player**: Limited access to personal data
- **Guardian**: Access to linked player's data

Each role has specific permissions that determine what actions they can perform. The API will return a 403 Forbidden status for unauthorized actions.

## Pagination

List endpoints support pagination with the following query parameters:

```
/api/v1/teams?page=1&per_page=20
```

Response includes pagination metadata:

```json
{
    "data": [...],
    "meta": {
        "current_page": 1,
        "per_page": 20,
        "total": 100,
        "total_pages": 5
    }
}
```

## Filtering & Sorting

Many list endpoints support filtering and sorting:

```
/api/v1/players?sort=name&order=asc&filter[team_id]=1
```

## Real-time Updates

The system uses WebSocket connections for real-time updates. Connect to:

```
ws://your-domain/ws
```

Events you can listen for:
- match.update
- notification.new
- schedule.change
- evaluation.created

## Rate Limiting

API requests are rate-limited to:
- 60 requests per minute for authenticated users
- 30 requests per minute for unauthenticated requests

## Development Notes

- All dates are in UTC
- API versioning is in the URL (v1)
- Use appropriate HTTP methods (GET, POST, PUT, DELETE)
- Include Accept: application/json header
- Test environment available at: https://api-test.cleansheet.com

## Support

For API support or questions, contact:
- Email: api-support@cleansheet.com
- Documentation: https://docs.cleansheet.com
