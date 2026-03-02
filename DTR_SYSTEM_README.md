# Daily Time Record (DTR) Web Application

A comprehensive web-based attendance management system designed specifically for interns, built with Laravel 11, Inertia.js, React, and Ant Design.

## Features

### User Registration & Authentication
- **DTR-Specific Registration Fields:**
  - Student Name
  - Student Number (Unique Identifier)
  - School/University
  - Required Internship Hours
  - Company
  - Department
  - Supervisor Name
  - Supervisor Position

- **Login Options:**
  - Login using Student Number
  - Login using Email
  - Password-based authentication
  - Remember me functionality

### DTR Management
- **Monthly Records:** Automatically create and manage DTR records by month
- **Attendance Tracking:**
  - Record Time In / Time Out
  - Automatic day-of-week calculation
  - Automatic total hours calculation
  - Status tracking (Draft / Finished)
  - Add remarks/notes

- **Progress Tracking:**
  - View total hours logged vs required hours
  - Track remaining hours needed
  - Visual progress indicators
  - Month fulfillment status

- **DTR Operations:**
  - Add attendance records
  - Edit existing records
  - Delete records
  - Print DTR forms for submission

## Database Schema

### Users Table
Extended with DTR-specific fields:
- `student_name` - Intern's full name
- `student_no` - Unique student identifier
- `school` - School/University name
- `required_hours` - Total internship hours required
- `company` - Internship company
- `department` - Department/Division
- `supervisor_name` - Direct supervisor name
- `supervisor_position` - Supervisor's position

### DTR Months Table
- `id` - Primary key
- `user_id` - Foreign key to users
- `month` - Month number (1-12)
- `year` - Year
- `is_fulfilled` - Boolean flag for completion
- Unique constraint on (user_id, month, year)

### DTR Rows Table
- `id` - Primary key
- `dtr_month_id` - Foreign key to dtr_months
- `date` - Attendance date
- `day` - Day of week (auto-generated)
- `time_in` - Time logged in (HH:mm format)
- `time_out` - Time logged out (HH:mm format)
- `total_minutes` - Calculated total minutes worked
- `status` - Record status (draft/finished)
- `remarks` - Optional notes/remarks
- Unique constraint on (dtr_month_id, date)

## Installation & Setup

### 1. Run Migrations
```bash
php artisan migrate
```

This will:
- Add DTR fields to users table
- Create dtr_months table
- Create dtr_rows table (with new columns: day, total_minutes, status)

### 2. Seed Database (Optional)
```bash
php artisan db:seed --class=DtrSeeder
```

This creates sample intern users with attendance data:
- **INTERN001** (John Doe)
  - School: University of the Philippines
  - Company: Tech Innovations Inc.
  - Department: Software Development
  - Supervisor: Maria Santos

- **INTERN002** (Jane Smith)
  - School: De La Salle University
  - Company: Digital Solutions Ltd.
  - Department: UI/UX Design
  - Supervisor: Alex Johnson

### 3. Build Frontend Assets
```bash
npm run dev  # Development mode with hot reload
npm run build  # Production build
```

### 4. Start Laravel Server
```bash
php artisan serve
```

## Accessing the Application

### URLs
- **Welcome Page:** `http://localhost:8000`
- **Register:** `http://localhost:8000/register`
- **Login:** `http://localhost:8000/login`
- **DTR Dashboard:** `http://localhost:8000/dtr` (Authenticated)
- **DTR Month View:** `http://localhost:8000/dtr/months/{monthId}` (Authenticated)

### Test Credentials
Available after running `php artisan db:seed`:

**Intern 1:**
- Student Number: `INTERN001`
- Password: `password`

**Intern 2:**
- Student Number: `INTERN002`
- Password: `password`

## User Workflow

### 1. Registration
1. Navigate to `/register`
2. Fill in all required fields:
   - Academic Information
   - Internship Information
   - Security (Password)
3. Click "Register"
4. Automatically logged in and redirected to API response page

### 2. Dashboard (DTR Index)
1. Access `/dtr` after logging in
2. View two tabs:
   - **User Information:** Display of all registered information
   - **Monthly Records:** List of all DTR months with statistics

### 3. Monthly DTR Record
1. Click "View" on a month in the list
2. See month details:
   - Total Hours Logged
   - Required Hours
   - Remaining Hours
   - Progress bar

### 4. Record Attendance
1. Click "Add Attendance Record"
2. Select date (dates with existing records disabled)
3. Enter Time In and Time Out
4. Add optional remarks
5. Click "Add Record"

### 5. Edit/Delete Records
- Click edit icon to modify a record
- Click delete icon to remove a record
- Cannot add new row while editing (one draft at a time)

### 6. Print DTR
- Click "Print DTR" button
- Use browser's print functionality
- Save as PDF for submission

## API Endpoints

### DTR Month Endpoints
```
GET    /dtr/months                 - List all months
POST   /dtr/months                 - Create new month
GET    /dtr/months/{month}         - View specific month
```

### DTR Row Endpoints
```
POST   /dtr/rows                   - Add attendance record
PATCH  /dtr/rows/{row}             - Update record
DELETE /dtr/rows/{row}             - Delete record
```

## Role-Based Access Control

Currently, the DTR system:
- Uses single-role architecture (Intern/User)
- Doesn't require specific roles
- Restricts access based on user ownership (policies)

### Policies
- `DtrMonthPolicy` - Users can only view their own months
- `DtrRowPolicy` - Users can only edit/delete their own records

## Frontend Components

### Pages
- **`Pages/Auth/Register.tsx`** - DTR-specific registration form
- **`Pages/Auth/Login.tsx`** - Login with Student Number or Email
- **`Pages/Dtr/Index.tsx`** - DTR dashboard and month list
- **`Pages/Dtr/Show.tsx`** - Specific month view with attendance table

### Features
- Responsive design with Tailwind CSS
- Ant Design components for UI
- Real-time form validation
- Loading states and error handling
- Print-friendly DTR forms

## Calculations

### Total Hours
```
Total Hours = Sum of finished rows' total_minutes / 60
```

### Progress
```
Progress % = (Total Hours / Required Hours) × 100
```

### Remaining Hours
```
Remaining Hours = max(0, Required Hours - Total Hours)
```

## Key Business Logic

### One Draft Per Month
- Only one unfinished (draft) record allowed per month
- Must finish current record before adding new ones
- "Finish" button disabled if Time In or Time Out missing

### Auto Date Calculation
- Day of week automatically calculated from date selection
- Dates with existing records disabled in date picker

### Auto Hour Calculation
- Total minutes calculated only when both times provided
- Time validation: Time Out must be after Time In
- Hours displayed in decimal format (e.g., 9.5 hours)

### Month Fulfillment
- Month marked as fulfilled when total hours ≥ required hours
- Alert displayed when month is complete
- Visual indicator in month list

## Styling

### Tailwind CSS
- Responsive grid system
- Custom utility classes
- Dark mode compatible

### Ant Design Components
- Card, Table, Button, Modal
- Form, Input, DatePicker, TimePicker
- Statistic, Progress, Alert
- Spin, Empty, Message
- Consistent theming

## Future Enhancements

- Export DTR to PDF/Excel
- Email notifications for month completion
- Attendance analytics and reports
- Multiple supervisor support
- Document upload (company approval forms)
- Bulk import of attendance records
- Mobile app version

## Troubleshooting

### Database Issues
- Ensure migrations ran: `php artisan migrate:fresh`
- Check database connection in `.env`

### Frontend Issues
- Clear Node modules and reinstall: `npm install`
- Rebuild assets: `npm run dev`

### Authentication Issues
- Clear browser cache and cookies
- Ensure passwords don't contain special characters that need escaping

## Support

For issues or questions:
1. Check the DTR documentation
2. Review migration files in `database/migrations/`
3. Check controller logic in `app/Http/Controllers/`
4. Review React component implementations in `resources/js/Pages/Dtr/`
