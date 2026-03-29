# Admin User Management Interface Guide

## Page Structure

### 1. Users List Page (`admin/users.php`)

```
┌─────────────────────────────────────────────────────────────┐
│ Site Users                              [Badge: X Total Users]│
├─────────────────────────────────────────────────────────────┤
│                                                               │
│ ┌─────────────┐  ┌─────────────┐  ┌─────────────┐          │
│ │ Total Users │  │Active Users │  │Inactive Users│          │
│ │     XX      │  │     XX      │  │     XX       │          │
│ └─────────────┘  └─────────────┘  └─────────────┘          │
│                                                               │
├─────────────────────────────────────────────────────────────┤
│ Filters                                                       │
│ ┌──────────────────────────────────────────────────────────┐│
│ │ Search: [________________]  Status: [All Users ▼]        ││
│ │ [Filter] [Clear]                                          ││
│ └──────────────────────────────────────────────────────────┘│
│                                                               │
├─────────────────────────────────────────────────────────────┤
│ User List                                                     │
│ ┌──────────────────────────────────────────────────────────┐│
│ │ID│Name    │Email      │Mobile    │Orders│Spent │Status  ││
│ ├──┼────────┼───────────┼──────────┼──────┼──────┼────────┤│
│ │1 │John Doe│john@...   │9876543210│  5   │₹5,000│[Active]││
│ │  │        │           │          │      │      │[View]  ││
│ │  │        │           │          │      │      │[Disable││
│ ├──┼────────┼───────────┼──────────┼──────┼──────┼────────┤│
│ │2 │Jane S. │jane@...   │9876543211│  3   │₹3,500│[Active]││
│ │  │        │           │          │      │      │[View]  ││
│ │  │        │           │          │      │      │[Disable││
│ └──────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────┘
```

### 2. User Details Page (`admin/user-details.php`)

```
┌─────────────────────────────────────────────────────────────┐
│ ← Back to Users                                               │
│ User Details                              [Status Badge]      │
├─────────────────────────────────────────────────────────────┤
│ User Information                                              │
│ ┌──────────────────────────────────────────────────────────┐│
│ │ User ID: 1              Full Name: John Doe              ││
│ │ Email: john@example.com Mobile: 9876543210               ││
│ │ Registered: 15 Jan 2024 Status: [Active]                 ││
│ └──────────────────────────────────────────────────────────┘│
│                                                               │
├─────────────────────────────────────────────────────────────┤
│ ┌─────────────┐  ┌─────────────┐  ┌─────────────┐          │
│ │Total Orders │  │ Total Spent │  │Avg Order Val│          │
│ │      5      │  │  ₹5,000.00  │  │  ₹1,000.00  │          │
│ └─────────────┘  └─────────────┘  └─────────────┘          │
│                                                               │
├─────────────────────────────────────────────────────────────┤
│ Order History                                                 │
│ ┌──────────────────────────────────────────────────────────┐│
│ │Order#   │Date    │Customer│Contact  │Amount │Status     ││
│ ├─────────┼────────┼────────┼─────────┼───────┼───────────┤│
│ │ORD-001  │15 Jan  │John Doe│john@... │₹1,200 │[Delivered]││
│ │         │        │        │98765... │       │[View]     ││
│ ├─────────┼────────┼────────┼─────────┼───────┼───────────┤│
│ │ORD-002  │16 Jan  │John Doe│john@... │₹850   │[Shipped]  ││
│ │         │        │        │98765... │       │[View]     ││
│ └──────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────┘
```

## Feature Breakdown

### Users List Page Features

#### Statistics Cards
- **Total Users**: Count of all registered users
- **Active Users**: Count of users with is_active = 1
- **Inactive Users**: Count of users with is_active = 0

#### Search & Filter
- **Search Box**: Search by name, email, or mobile number
- **Status Filter**: Dropdown to filter by Active/Inactive/All
- **Filter Button**: Apply filters
- **Clear Button**: Reset all filters

#### User Table Columns
1. **ID**: User's unique identifier
2. **Full Name**: User's full name (or "N/A" if not set)
3. **Email**: User's email address
4. **Mobile**: User's mobile number (or "N/A" if not set)
5. **Orders**: Count of orders placed by user
6. **Total Spent**: Sum of all order amounts
7. **Status**: Active/Inactive badge
8. **Registered**: Registration date
9. **Actions**: View, Enable/Disable, Delete buttons

#### Action Buttons
- **View**: Navigate to user details page
- **Enable/Disable**: Toggle user account status
- **Delete**: Remove user (only shown if order count = 0)

### User Details Page Features

#### User Information Section
Displays:
- User ID
- Full Name
- Email Address
- Mobile Number
- Registration Date & Time
- Account Status Badge

#### Order Statistics Cards
- **Total Orders**: Count of all orders
- **Total Spent**: Sum of all order amounts
- **Average Order Value**: Average amount per order

#### Order History Table
Shows all orders placed by the user with:
- Order Number
- Order Date
- Customer Name
- Contact Information (Email & Phone)
- Order Amount (or "Pending" for unpriced items)
- Payment Mode
- Order Status (with color-coded badges)
- View button (links to full order details)

#### Status Badge Colors
- **Pending**: Orange (#f39c12)
- **Confirmed**: Blue (#3498db)
- **Processing**: Purple (#9b59b6)
- **Shipped**: Teal (#1abc9c)
- **Delivered**: Green (#27ae60)
- **Cancelled**: Red (#e74c3c)

## Navigation Flow

```
Admin Dashboard
    │
    ├─→ Site Users (users.php)
    │       │
    │       ├─→ Search/Filter Users
    │       │
    │       ├─→ View User Details (user-details.php)
    │       │       │
    │       │       ├─→ View User Info
    │       │       │
    │       │       ├─→ View Order Statistics
    │       │       │
    │       │       └─→ View Order (orders.php?id=X)
    │       │
    │       ├─→ Enable/Disable User
    │       │
    │       └─→ Delete User (if no orders)
    │
    └─→ Back to Dashboard
```

## Responsive Design

### Desktop (> 768px)
- Full table layout with all columns visible
- Statistics cards in 3-column grid
- Filter form in horizontal layout

### Tablet (768px - 480px)
- Table remains scrollable horizontally
- Statistics cards in 2-column grid
- Filter form stacks vertically

### Mobile (< 480px)
- Table scrolls horizontally
- Statistics cards in 1-column stack
- Filter form fully stacked
- Buttons stack vertically

## Color Scheme

### Status Indicators
- **Active**: Green background (#d4edda), Dark green text (#155724)
- **Inactive**: Red background (#f8d7da), Dark red text (#721c24)

### Buttons
- **Primary (View)**: Blue (#3498db)
- **Warning (Disable)**: Orange (#f39c12)
- **Success (Enable)**: Green (#27ae60)
- **Danger (Delete)**: Red (#e74c3c)

### Cards & Sections
- **Background**: White (#ffffff)
- **Border**: Light gray (#e0e0e0)
- **Shadow**: Subtle (0 2px 4px rgba(0,0,0,0.05))

## User Experience Features

1. **Instant Feedback**: Status changes reflect immediately
2. **Confirmation Dialogs**: Delete action requires confirmation
3. **Breadcrumb Navigation**: Easy back navigation
4. **Hover Effects**: Interactive elements highlight on hover
5. **Loading States**: Forms disable during submission
6. **Error Handling**: Clear error messages displayed
7. **Success Messages**: Confirmation of successful actions
8. **Empty States**: Friendly messages when no data exists

## Accessibility

- Semantic HTML structure
- Proper form labels
- Keyboard navigation support
- Color contrast compliance
- Screen reader friendly
- Focus indicators on interactive elements
