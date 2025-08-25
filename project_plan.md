# Project Plan for Real Estate Social Network

## Objective
Build a social network focused on real estate with multiple user roles and a superuser for managing registrations.

## Steps

### 1. Frontend Development
- **Setup HTML Structure**:
  - Create `index.php` as the main router.
  - Develop HTML pages in the `views/` directory:
    - `login.html`
    - `register.html`
    - `feed.html`
    - `post_form.html`
    - `admin_dashboard.html`
  
- **Styling**:
  - Use Bootstrap for responsive design.
  - Link CSS in `public/css/styles.css`.

- **JavaScript**:
  - Implement `app.js` for handling user interactions and API calls.

### 2. Backend Development
- **Setup PHP Environment**:
  - Create API endpoints in the `api/` directory:
    - `auth.php` for authentication.
    - `users.php` for user management.
    - `posts.php` for post management.
    - `uploads.php` for image uploads.
    - `messages.php` for messaging functionality.

- **Database Setup**:
  - Create a MySQL database schema in `sql/schema.sql`.

### 3. Role Management
- **Define Roles**:
  - Implement role-based access control in `guard.php`.
  - Create functions for user state management.

### 4. Image Uploads
- **Implement Image Upload Functionality**:
  - Create an upload handler in `uploads.php`.

### 5. Testing
- **Testing**:
  - Write tests for each API endpoint using PHPUnit.
  - Verify functionality and security.

## Conclusion
This plan outlines the necessary steps to build a real estate social network with a focus on user roles and image management.
