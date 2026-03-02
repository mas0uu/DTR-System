# DTR System - Database & API Reference

## Database Schema

### Users Table (Extended)

```sql
CREATE TABLE users (
    id BIGINT UNSIGNED PRIMARY KEY,
    name VARCHAR(255),
    email VARCHAR(255) UNIQUE,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255),
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    -- DTR Fields
    student_name VARCHAR(255) NULL,
    student_no VARCHAR(255) UNIQUE NULL,
    school VARCHAR(255) NULL,
    required_hours INT DEFAULT 0,
    company VARCHAR(255) NULL,
    department VARCHAR(255) NULL,
    supervisor_name VARCHAR(255) NULL,
    supervisor_position VARCHAR(255) NULL
);

-- Indexes
CREATE UNIQUE INDEX users_email_unique ON users(email);
CREATE UNIQUE INDEX users_student_no_unique ON users(student_no);
```

### DTR Months Table

```sql
CREATE TABLE dtr_months (
    id BIGINT UNSIGNED PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    month TINYINT UNSIGNED NOT NULL,
    year SMALLINT UNSIGNED NOT NULL,
    is_fulfilled BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_user_month_year (user_id, month, year)
);

-- Indexes
CREATE INDEX dtr_months_user_id_index ON dtr_months(user_id);
```

### DTR Rows Table

```sql
CREATE TABLE dtr_rows (
    id BIGINT UNSIGNED PRIMARY KEY,
    dtr_month_id BIGINT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    day VARCHAR(255) NULL,
    time_in TIME NULL,
    time_out TIME NULL,
    remarks VARCHAR(255) NULL,
    total_minutes INT DEFAULT 0,
    status ENUM('draft', 'finished') DEFAULT 'draft',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (dtr_month_id) REFERENCES dtr_months(id) ON DELETE CASCADE,
    UNIQUE KEY unique_month_date (dtr_month_id, date)
);

-- Indexes
CREATE INDEX dtr_rows_dtr_month_id_index ON dtr_rows(dtr_month_id);
CREATE INDEX dtr_rows_date_index ON dtr_rows(date);
```

## Relationships

```
User (1) ----< (Many) DtrMonth
  |
  └─> dtrMonths()

DtrMonth (1) ----< (Many) DtrRow
  |
  ├─> user()
  └─> rows()

DtrRow (Many) ----> (1) DtrMonth
  └─> dtrMonth()
```

## Model Attributes & Casts

### User Model
```php
$fillable = [
    'name', 'email', 'password',
    'student_name', 'student_no', 'school', 'required_hours',
    'company', 'department', 'supervisor_name', 'supervisor_position'
];

$casts = [
    'email_verified_at' => 'datetime',
    'password' => 'hashed',
];
```

### DtrMonth Model
```php
$fillable = [
    'user_id', 'month', 'year', 'is_fulfilled'
];

$casts = [
    'is_fulfilled' => 'boolean',
];

// Relationships
public function user() { ... }
public function rows() { ... }

// Attributes
public function getTotalHoursAttribute() { ... }
public function getFinishedRowsCountAttribute() { ... }
```

### DtrRow Model
```php
$fillable = [
    'dtr_month_id', 'date', 'day', 'time_in', 'time_out',
    'total_minutes', 'status', 'remarks'
];

$casts = [
    'date' => 'date',
];

// Relationships
public function dtrMonth() { ... }

// Methods
public function calculateTotalHours() { ... }
```

## API Endpoints

### Authentication
```
POST   /register                 - Register new user
POST   /login                    - Login user
POST   /logout                   - Logout user
```

### DTR Months
```
GET    /dtr                      - Dashboard with month list (returns JSON)
GET    /dtr/months               - List all months with user info
POST   /dtr/months               - Create new month
GET    /dtr/months/{month}       - Get month details with rows
```

Request/Response Examples:

**GET /dtr/months**
```json
{
  "months": [
    {
      "id": 1,
      "month": 2,
      "year": 2026,
      "monthName": "February 2026",
      "is_fulfilled": false,
      "total_hours": 120.5,
      "finished_rows": 15
    }
  ],
  "current_month_id": 1,
  "user": {
    "student_name": "John Doe",
    "student_no": "INTERN001",
    "school": "University of the Philippines",
    "required_hours": 480,
    "company": "Tech Innovations Inc.",
    "department": "Software Development",
    "supervisor_name": "Maria Santos",
    "supervisor_position": "Senior Developer"
  }
}
```

**GET /dtr/months/{month}**
```json
{
  "month": {
    "id": 1,
    "month": 2,
    "year": 2026,
    "monthName": "February 2026",
    "is_fulfilled": false
  },
  "rows": [
    {
      "id": 1,
      "date": "2026-02-17",
      "day": "Tuesday",
      "time_in": "08:30:00",
      "time_out": "17:30:00",
      "total_hours": 9,
      "total_minutes": 540,
      "status": "finished",
      "remarks": "Regular work day"
    }
  ],
  "total_hours": 120.5,
  "required_hours": 480,
  "remaining_hours": 359.5,
  "user": { ... }
}
```

### DTR Rows
```
POST   /dtr/rows                 - Add attendance record
PATCH  /dtr/rows/{row}           - Update record
DELETE /dtr/rows/{row}           - Delete record
```

Request/Response Examples:

**POST /dtr/rows**
```json
{
  "dtr_month_id": 1,
  "date": "2026-02-18"
}
```

Response:
```json
{
  "id": 2,
  "date": "2026-02-18",
  "day": "Wednesday",
  "time_in": null,
  "time_out": null,
  "total_hours": 0,
  "total_minutes": 0,
  "status": "draft",
  "remarks": null
}
```

**PATCH /dtr/rows/{row}**
```json
{
  "time_in": "08:30",
  "time_out": "17:30",
  "status": "finished",
  "remarks": "Regular work"
}
```

Response:
```json
{
  "id": 2,
  "date": "2026-02-18",
  "day": "Wednesday",
  "time_in": "08:30:00",
  "time_out": "17:30:00",
  "total_hours": 9,
  "total_minutes": 540,
  "status": "finished",
  "remarks": "Regular work"
}
```

## Business Rules

### Registration
- `student_no` must be unique
- `required_hours` must be > 0
- All fields except `remarks` are required
- Email auto-generated from `student_no@intern.local`

### Month Management
- One month per (user, month, year) combination
- Auto-created on first access
- Current month created if doesn't exist

### Attendance Recording
- Only one draft record per month at a time
- Cannot add new record if draft exists
- Date must be unique within month
- Date with existing record disabled in picker

### Time Entry
- Both `time_in` and `time_out` required to finish
- `time_out` must be after `time_in`
- Total minutes auto-calculated
- Total hours = total_minutes / 60

### Hours Tracking
- Total hours shown in decimal (e.g., 9.5)
- Progress % = (total_hours / required_hours) × 100
- Remaining hours = required_hours - total_hours
- Month fulfilled when total_hours ≥ required_hours

## Validation Rules

### User Registration
```php
'student_name' => 'required|string|max:255',
'student_no' => 'required|string|unique:users',
'school' => 'required|string|max:255',
'required_hours' => 'required|integer|min:1',
'company' => 'required|string|max:255',
'department' => 'required|string|max:255',
'supervisor_name' => 'required|string|max:255',
'supervisor_position' => 'required|string|max:255',
'password' => 'required|confirmed|min:8',
```

### Login
```php
'credential' => 'required|string',
'password' => 'required|string',
```

### Attendance Record
```php
'dtr_month_id' => 'required|exists:dtr_months,id',
'date' => 'required|date',
'time_in' => 'nullable|date_format:H:i',
'time_out' => 'nullable|date_format:H:i',
'status' => 'required|in:draft,finished',
'remarks' => 'nullable|string|max:255',
```

## Error Responses

### 422 Unprocessable Entity
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "student_no": ["The student no has already been taken."]
  }
}
```

### 403 Forbidden
```json
{
  "message": "This action is unauthorized."
}
```

### Custom DTR Errors
```json
{
  "error": "You have an unfinished row. Please finish it first."
}
```

## Authorization Policies

### DtrMonthPolicy
```php
public function view(User $user, DtrMonth $month): bool
{
    return $user->id === $month->user_id;
}

public function create(User $user): bool
{
    return true; // All authenticated users
}
```

### DtrRowPolicy
```php
public function create(User $user, DtrMonth $month): bool
{
    return $user->id === $month->user_id;
}

public function update(User $user, DtrRow $row): bool
{
    return $user->id === $row->dtrMonth->user_id;
}

public function delete(User $user, DtrRow $row): bool
{
    return $user->id === $row->dtrMonth->user_id;
}
```

## Query Examples

### Get user with all DTR data
```php
$user = User::with('dtrMonths.rows')->find($userId);
```

### Get current month for user
```php
$month = DtrMonth::where('user_id', $userId)
    ->where('month', now()->month)
    ->where('year', now()->year)
    ->with('rows')
    ->firstOrFail();
```

### Get total hours for a month
```php
$totalMinutes = DtrRow::whereHas('dtrMonth', fn($q) => $q->where('id', $monthId))
    ->where('status', 'finished')
    ->sum('total_minutes');

$totalHours = $totalMinutes / 60;
```

### Check if month is fulfilled
```php
$month = DtrMonth::find($monthId);
$totalHours = $month->rows()
    ->where('status', 'finished')
    ->sum('total_minutes') / 60;

$isFulfilled = $totalHours >= $month->user->required_hours;
```

## Performance Tips

1. Use `with()` for eager loading relationships
2. Index queries on `user_id`, `month`, `year`
3. Cache month statistics
4. Use pagination for large month lists
5. Add database indexes for frequently searched columns

## Troubleshooting Queries

### Find users without any DTR data
```php
$users = User::doesntHave('dtrMonths')->get();
```

### Find incomplete months
```php
$incomplete = DtrMonth::where('is_fulfilled', false)->get();
```

### Find records with missing time_out
```php
$missing = DtrRow::whereNull('time_out')->get();
```

### Get summary statistics
```php
$stats = DtrRow::selectRaw('COUNT(*) as total, SUM(total_minutes) as total_minutes')
    ->where('status', 'finished')
    ->groupBy('dtr_month_id')
    ->get();
```
