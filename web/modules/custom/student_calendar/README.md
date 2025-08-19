# Student Calendar Module

## Description

The Student Calendar module provides a calendar view for students showing menu days based on their school and room assignments. This module allows users to:

- View a list of students (child nodes) they have authored
- Select a student to view their menu calendar
- See menu days filtered by the student's school and room
- View detailed information about each menu day including date, meal, school, and room

## Features

- **Student Selection**: Dropdown list of students authored by the current user
- **URL Auto-Selection**: Automatically select student using `?id=33` URL parameter
- **AJAX Filtering**: Dynamic loading of menu days when a student is selected
- **Calendar View**: Displays menu days in a proper calendar layout based on field_date
- **Month Navigation**: Navigate between different months with previous/next buttons
- **Today Button**: Quick navigation to current month and date
- **Always Show Calendar**: Calendar is displayed even when no menu days are found
- **Clickable Menu Days**: Click on any menu day to navigate to its node page
- **Admin Plus Icons**: Administrators can add new menu days directly from calendar
- **Auto-populated Dates**: Date field is automatically filled when creating from calendar
- **Smart Redirects**: Returns to calendar after creating/editing menu days
- **Today Highlighting**: Current date is visually highlighted
- **Responsive Design**: Mobile-friendly calendar that adapts to different screen sizes
- **Visual Indicators**: Days with menu items are highlighted
- **Detailed Information**: Hover over menu items to see meal details
- **Access Control**: Only shows students authored by the current user

## Requirements

- Drupal 11
- Node module (core)
- User module (core)
- Content types: `child`, `menu_day`, `school`, `room`
- Fields:
  - `field_school_ref` (Entity reference to school nodes)
  - `field_ref_room` (Entity reference to room nodes)
  - `field_date` (Date field on menu_day)
  - `field_meal` (Entity reference to commerce product on menu_day)

## Installation

1. Place the module in `web/modules/custom/student_calendar/`
2. Enable the module via Drupal admin interface or drush: `drush en student_calendar`
3. Clear cache: `drush cr`

## Usage

### Basic Calendar View
1. Navigate to `/calendar` or use the "Calendar" link in the main menu
2. Auto-select a student using URL: `/calendar?id=33` (replace 33 with student node ID)
3. Select a student from the dropdown if not auto-selected
4. Use the month navigation arrows to browse different months
5. Click the "Today" button to jump to the current month
6. Click on any menu day title to navigate to the full menu day node page
7. Days with menu items are highlighted in green
8. Today's date is highlighted with a yellow border
9. Hover over menu items to see additional details

### Admin Features
1. **Admin Access**: Administrators can access `/admin/content/calendar` for full calendar management
2. **All Students View**: Admins can select "All Students" to see all menu days across all students
3. **No Student Restriction**: Admins can use the calendar even without assigned students
4. **Adding Menu Days**: Administrators see green plus (+) icons on calendar days
5. **Quick Add**: Click the plus icon to create a new menu day for that specific date
6. **Auto-populated Date**: The date field is automatically filled with the selected date
7. **Smart Redirect**: After saving, you're redirected back to the calendar
8. **Permission-based**: Plus icons only appear for users with menu day creation permissions
9. **Admin Styling**: Admin calendar has purple header to distinguish from regular view

### Admin Routes
- **Regular Calendar**: `/calendar` - Standard user view
- **Admin Calendar**: `/admin/content/calendar` - Full administrative access
- **With Student**: `/calendar?id=33` - View specific student's calendar
- **All Students**: `/calendar` (select "All Students" from dropdown)

### FullCalendar View
1. Navigate to `/calendar/fullcalendar` or use the "Calendar (FullCalendar)" link
2. Auto-select a student using URL: `/calendar/fullcalendar?id=33`
3. Enjoy enhanced calendar features including:
   - Month, week, and day views
   - Event tooltips
   - Better mobile responsiveness
   - Professional calendar interface

## Access Restrictions

The module implements "next week only" access control for menu purchasing:

- **Next Week Access**: Users can only access menu days starting from next Monday
- **Current Week Restriction**: Menu days for the current week (Monday-Sunday) are not accessible
- **Visual Calendar**: Users can see the full calendar with all menu days
- **Restricted Items**: Current week items are shown but not clickable (with lock icon)
- **Purchase Window**: Menu items become available for purchase from next Monday onwards

### How It Works:
- **Monday**: Can access menu days from next Monday (7 days later)
- **Tuesday-Sunday**: Can access menu days from the coming Monday
- **Example**: If today is Wednesday, users can access menu days starting from the upcoming Monday

Visual indicators:
- **Blue background**: Available menu days (clickable)
- **Gray background with lock**: Restricted menu days (not clickable)
- **Yellow border**: Today's date
- **Light gray**: Other month days

## Content Type Structure

### Child (Student)
- `field_school_ref`: Reference to school node
- `field_ref_room`: Reference to room node
- `field_allergies`: Text field for allergies
- `field_division`: Text field for division

### Menu Day
- `field_school_ref`: Reference to school node
- `field_ref_room`: Reference to room node
- `field_date`: Date field
- `field_meal`: Reference to commerce product (meal)

### School
- `field_address`: Address field

### Room
- Basic content type

## Permissions

The module uses the 'access content' permission. Users need to be able to view content and must be the author of child nodes to see them in the dropdown.

## Customization

The module includes CSS and JavaScript files that can be customized:
- `css/student-calendar.css`: Styling for the calendar interface
- `js/student-calendar.js`: JavaScript behaviors for interactions

## Support

This is a custom module created for the Canuel Caterers project.
